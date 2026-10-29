import { useCallback, useEffect, useRef, useState } from 'react';
import { json } from '@/lib/http';

/**
 * Records a live session in 30-second pieces. Each piece is a whole file
 * on its own (the recorder is restarted for each), kept in the phone's
 * storage until the server has it, so a dropped connection, or the page
 * being closed, only delays it. One phone records a session at a time.
 */

const PIECE_MS = 30_000;
const DB = 'ventiq-recordings';
const STORE = 'pieces';

export interface RecordingState {
    available: boolean;
    recorder: string | null;
    paused: boolean;
    seconds: number;
    max: number;
    nextSeq: number;
}

interface StoredPiece {
    session: number;
    seq: number;
    segmentId: number | null;
    durationMs: number;
    recordedAt: string;
    mime: string;
    blob: Blob;
}

export type RecorderStatus = 'idle' | 'starting' | 'recording' | 'paused' | 'sending';

function openDb(): Promise<IDBDatabase> {
    return new Promise((resolve, reject) => {
        const req = indexedDB.open(DB, 1);
        req.onupgradeneeded = () => req.result.createObjectStore(STORE, { keyPath: ['session', 'seq'] });
        req.onsuccess = () => resolve(req.result);
        req.onerror = () => reject(req.error);
    });
}

async function store<T>(mode: IDBTransactionMode, run: (s: IDBObjectStore) => IDBRequest<T>): Promise<T> {
    const db = await openDb();
    return new Promise((resolve, reject) => {
        const req = run(db.transaction(STORE, mode).objectStore(STORE));
        req.onsuccess = () => resolve(req.result);
        req.onerror = () => reject(req.error);
    });
}

const savePiece = (p: StoredPiece) => store('readwrite', (s) => s.put(p));
const dropPiece = (session: number, seq: number) => store('readwrite', (s) => s.delete([session, seq]));
const waiting = (session: number) =>
    store<StoredPiece[]>('readonly', (s) => s.getAll(IDBKeyRange.bound([session, 0], [session, Number.MAX_SAFE_INTEGER]))).catch(() => [] as StoredPiece[]);

function pickMime(): string {
    const options = ['audio/webm;codecs=opus', 'audio/webm', 'audio/mp4', 'audio/ogg;codecs=opus'];
    return options.find((m) => typeof MediaRecorder !== 'undefined' && MediaRecorder.isTypeSupported?.(m)) ?? '';
}

/** This phone's name for itself in this session, kept across reloads so it stays the one recording. */
function recorderId(session: number): string {
    const key = `ventiq_recorder_${session}`;
    try {
        const known = localStorage.getItem(key);
        if (known) return known;
        const made = crypto.randomUUID?.() ?? `${Date.now()}-${Math.random().toString(36).slice(2)}`;
        localStorage.setItem(key, made);
        return made;
    } catch {
        return `${Date.now()}-${Math.random().toString(36).slice(2)}`;
    }
}

export function useSessionRecorder(sessionId: number, initial: RecordingState, liveSegmentId: number | null) {
    const base = `/sessions/${sessionId}/recording`;
    const id = useRef(recorderId(sessionId));
    const [status, setStatus] = useState<RecorderStatus>(initial.recorder && initial.recorder === id.current ? (initial.paused ? 'paused' : 'idle') : 'idle');
    const [seconds, setSeconds] = useState(initial.seconds);
    const [queued, setQueued] = useState(0);
    const [problem, setProblem] = useState('');
    const stream = useRef<MediaStream | null>(null);
    const media = useRef<MediaRecorder | null>(null);
    const seq = useRef(initial.nextSeq);
    const segment = useRef(liveSegmentId);
    const wake = useRef<{ release: () => Promise<void> } | null>(null);
    const wanted = useRef<'recording' | 'paused' | 'idle'>('idle');
    const uploading = useRef(false);
    const timer = useRef<number | undefined>(undefined);
    // How loud the room is, for the listening bars: what the microphone actually hears.
    const audio = useRef<{ ctx: AudioContext; analyser: AnalyserNode; data: Uint8Array<ArrayBuffer> } | null>(null);

    segment.current = liveSegmentId;

    const refreshQueued = useCallback(async () => setQueued((await waiting(sessionId)).length), [sessionId]);

    /** Sends what's waiting, oldest first; tries again later when the connection is down. */
    const upload = useCallback(async () => {
        if (uploading.current) return;
        uploading.current = true;
        let delay = 4000;
        try {
            for (;;) {
                const pieces = (await waiting(sessionId)).sort((a, b) => a.seq - b.seq);
                setQueued(pieces.length);
                if (!pieces.length) return;
                const p = pieces[0];
                const form = new FormData();
                form.append('recorder', id.current);
                form.append('seq', String(p.seq));
                if (p.segmentId) form.append('segment_id', String(p.segmentId));
                form.append('duration_ms', String(p.durationMs));
                form.append('recorded_at', p.recordedAt);
                form.append('audio', p.blob, `piece-${p.seq}.${p.mime.includes('mp4') ? 'm4a' : p.mime.includes('ogg') ? 'ogg' : 'webm'}`);
                let res: Response | null = null;
                try {
                    res = await fetch(`${base}/pieces`, {
                        method: 'POST',
                        body: form,
                        headers: { Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name=csrf-token]')?.content ?? '' },
                    });
                } catch {
                    res = null;
                }
                if (res?.ok) {
                    const data = await res.json().catch(() => ({}));
                    if (typeof data.seconds === 'number') setSeconds(data.seconds);
                    await dropPiece(sessionId, p.seq);
                    delay = 4000;
                    continue;
                }
                if (res && (res.status === 409 || res.status === 422)) {
                    const data = await res.json().catch(() => ({}));
                    setProblem(data.message ?? 'Recording stopped.');
                    await dropPiece(sessionId, p.seq);
                    if (res.status === 409 || data.limit) stopDevice();
                    continue;
                }
                // Offline, or the server is busy: wait, then try again.
                await new Promise((r) => window.setTimeout(r, delay));
                delay = Math.min(delay * 2, 60_000);
            }
        } finally {
            uploading.current = false;
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [base, sessionId]);

    /** Starts the next 30-second piece, or nothing if recording has stopped. */
    const startPiece = useCallback(() => {
        if (!stream.current || wanted.current !== 'recording') return;
        const mime = pickMime();
        const rec = new MediaRecorder(stream.current, mime ? { mimeType: mime, audioBitsPerSecond: 32000 } : undefined);
        const parts: Blob[] = [];
        const began = Date.now();
        const startedAt = new Date(began).toISOString();
        const itemAtStart = segment.current;
        rec.ondataavailable = (e) => e.data.size && parts.push(e.data);
        rec.onstop = async () => {
            const blob = new Blob(parts, { type: rec.mimeType || mime || 'audio/webm' });
            if (blob.size > 0) {
                await savePiece({ session: sessionId, seq: seq.current++, segmentId: itemAtStart, durationMs: Date.now() - began, recordedAt: startedAt, mime: blob.type, blob }).catch(() => {});
                void upload();
            }
            startPiece();
        };
        rec.start();
        media.current = rec;
        window.clearTimeout(timer.current);
        timer.current = window.setTimeout(() => rec.state === 'recording' && rec.stop(), PIECE_MS);
    }, [sessionId, upload]);

    /** Ends the current piece now (and starts the next one if still recording). */
    const cut = useCallback(() => {
        window.clearTimeout(timer.current);
        if (media.current?.state === 'recording') media.current.stop();
    }, []);

    /** The meeting moved to another item: what's said from now goes under it. */
    const moveTo = useCallback((segmentId: number | null) => {
        segment.current = segmentId;
        cut();
    }, [cut]);

    const keepAwake = async () => {
        try {
            wake.current = await navigator.wakeLock?.request('screen');
        } catch {
            wake.current = null;
        }
    };

    const start = useCallback(async () => {
        setProblem('');
        setStatus('starting');
        const res = await json<{ message?: string; nextSeq?: number }>(`${base}/start`, { method: 'POST', body: { recorder: id.current } }).catch(() => null);
        if (!res?.ok) {
            setProblem(res?.data?.message ?? 'Recording could not start. Check your connection and try again.');
            setStatus('idle');
            return false;
        }
        // Numbers carry on after the server's last piece and any still waiting here.
        const left = await waiting(sessionId);
        seq.current = Math.max(seq.current, res.data.nextSeq ?? 0, ...left.map((p) => p.seq + 1));
        try {
            stream.current = stream.current ?? (await navigator.mediaDevices.getUserMedia({ audio: { echoCancellation: true, noiseSuppression: true } }));
        } catch {
            setProblem('VENTIQ needs the microphone to record. Allow it in your browser, then try again.');
            setStatus('idle');
            void json(`${base}/stop`, { method: 'POST' });
            return false;
        }
        try {
            if (!audio.current) {
                const ctx = new AudioContext();
                const analyser = ctx.createAnalyser();
                analyser.fftSize = 64;
                analyser.smoothingTimeConstant = 0.7;
                ctx.createMediaStreamSource(stream.current).connect(analyser);
                audio.current = { ctx, analyser, data: new Uint8Array(new ArrayBuffer(analyser.frequencyBinCount)) };
            }
            void audio.current.ctx.resume();
        } catch {
            audio.current = null;
        }
        stream.current.getAudioTracks().forEach((t) => (t.onended = () => wanted.current === 'recording' && setProblem('The phone stopped the recording. Keep this page open and the screen on, then tap Record again.')));
        await keepAwake();
        wanted.current = 'recording';
        setStatus('recording');
        startPiece();
        return true;
    }, [base, sessionId, startPiece]);

    const pause = useCallback(async () => {
        if (wanted.current !== 'recording') return;
        wanted.current = 'paused';
        cut();
        setStatus('paused');
        void json(`${base}/pause`, { method: 'POST' });
    }, [base, cut]);

    const resume = useCallback(async () => {
        if (wanted.current !== 'paused') return;
        if (!stream.current) return void start();
        wanted.current = 'recording';
        setStatus('recording');
        void json(`${base}/resume`, { method: 'POST' });
        startPiece();
    }, [base, start, startPiece]);

    function stopDevice() {
        wanted.current = 'idle';
        window.clearTimeout(timer.current);
        if (media.current?.state === 'recording') media.current.stop();
        stream.current?.getTracks().forEach((t) => t.stop());
        stream.current = null;
        void audio.current?.ctx.close().catch(() => {});
        audio.current = null;
        void wake.current?.release().catch(() => {});
        wake.current = null;
        setStatus('idle');
    }

    /** Stops recording and waits until every piece has reached the server (or gives up after a while). */
    const finish = useCallback(async (maxWaitMs = 120_000) => {
        const wasOn = wanted.current !== 'idle';
        stopDevice();
        if (!wasOn && !(await waiting(sessionId)).length) return true;
        setStatus('sending');
        // The last piece is saved on its recorder's stop: give it a moment.
        await new Promise((r) => window.setTimeout(r, 400));
        void upload();
        const until = Date.now() + maxWaitMs;
        while (Date.now() < until) {
            if (!(await waiting(sessionId)).length) break;
            await new Promise((r) => window.setTimeout(r, 1000));
        }
        const left = (await waiting(sessionId)).length;
        await json(`${base}/stop`, { method: 'POST' }).catch(() => null);
        setStatus('idle');
        return left === 0;
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [base, sessionId, upload]);

    // Pieces left from before (the page was closed, the phone went off): send them now.
    useEffect(() => {
        void refreshQueued().then(() => upload());
        const online = () => void upload();
        window.addEventListener('online', online);
        return () => {
            window.removeEventListener('online', online);
            window.clearTimeout(timer.current);
        };
    }, [refreshQueued, upload]);

    // The screen lock comes back off when the page is shown again.
    useEffect(() => {
        const back = () => document.visibilityState === 'visible' && wanted.current === 'recording' && void keepAwake();
        document.addEventListener('visibilitychange', back);
        return () => document.removeEventListener('visibilitychange', back);
    }, []);

    /** The room's loudness right now, one value 0–255 per band; null when not listening. */
    const levels = useCallback((): Uint8Array | null => {
        if (!audio.current || wanted.current !== 'recording') return null;
        audio.current.analyser.getByteFrequencyData(audio.current.data);
        return audio.current.data;
    }, []);

    const supported = typeof window !== 'undefined' && typeof MediaRecorder !== 'undefined' && !!navigator.mediaDevices?.getUserMedia && typeof indexedDB !== 'undefined';

    return { status, seconds, queued, problem, setProblem, supported, start, pause, resume, cut, moveTo, finish, levels };
}
