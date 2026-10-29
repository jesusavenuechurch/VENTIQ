<?php

namespace App\Services\Sessions;

use App\Models\Participant;
use App\Models\Session;
use App\Models\SessionRecordingPiece;
use App\Models\SessionSegment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Recorded sessions: the phone sends 30-second pieces, Whisper on the Mac
 * mini writes each down, and the text goes under the agenda item that was
 * running. English for now (docs/transcription-plan.md).
 */
class Recording
{
    /** Whole-piece "text" Whisper makes up from silence or noise. */
    private const NOISE = ['thank you', 'thank you very much', 'thanks', 'thanks for watching', 'you', 'bye', 'okay', 'so'];

    public static function available(): bool
    {
        return (bool) config('services.whisper.url');
    }

    /** What the console needs to know about recording this session. */
    public static function state(Session $session): array
    {
        $meta = $session->meta['recording'] ?? [];

        return [
            'available' => static::available(),
            'recorder'  => $meta['recorder'] ?? null,
            'paused'    => (bool) ($meta['paused'] ?? false),
            'seconds'   => (int) round(SessionRecordingPiece::where('session_id', $session->id)->sum('duration_ms') / 1000),
            'max'       => (int) config('services.whisper.max_seconds'),
            'nextSeq'   => (int) (SessionRecordingPiece::where('session_id', $session->id)->max('seq') ?? -1) + 1,
        ];
    }

    /** Someone else recording right now, on another phone: they sent a piece in the last 2 minutes. */
    public static function heldByOther(Session $session, string $recorder): bool
    {
        $meta = $session->meta['recording'] ?? [];
        if (empty($meta['recorder']) || $meta['recorder'] === $recorder) {
            return false;
        }
        $last = isset($meta['seen_at']) ? \Illuminate\Support\Carbon::parse($meta['seen_at']) : null;

        return $last && $last->gt(now()->subMinutes(2));
    }

    public static function remember(Session $session, array $changes): void
    {
        $meta = $session->meta ?? [];
        $meta['recording'] = array_merge($meta['recording'] ?? [], $changes);
        $session->update(['meta' => $meta]);
    }

    /**
     * Words Whisper should expect in this session: the organisation, the
     * title, the agenda, who leads it and who came. It gets names right
     * that it would otherwise spell by ear.
     */
    public function hints(Session $session): string
    {
        $session->loadMissing(['segments', 'organization']);
        $words = collect([$session->organization?->name, $session->resolved_title, 'VENTIQ', 'Ventiq Assist', $session->meta['chair'] ?? null, $session->meta['secretary'] ?? null])
            ->merge($session->segments->flatMap(fn (SessionSegment $s) => [$s->title, $s->presenter_name]))
            ->merge(Participant::where('session_id', $session->id)->with('client')->limit(60)->get()->map(fn ($p) => $p->client?->full_name));

        $hint = $words->filter()->map(fn ($w) => trim(preg_replace('/\s+/', ' ', (string) $w)))->filter()->unique()->implode(', ');

        return Str::limit($hint, 600, '');
    }

    /** Sends the piece to Whisper. Returns the cleaned text ('' when nothing was said); throws when Whisper can't be reached. */
    public function transcribe(SessionRecordingPiece $piece): string
    {
        $audio = Storage::disk('local')->get($piece->path);
        // Unreachable (the Mac mini off, Tailscale down): fail in seconds, and the piece is tried again later.
        $response = Http::connectTimeout(5)->timeout((int) config('services.whisper.timeout'))
            ->attach('file', $audio, basename($piece->path))
            ->post(rtrim(config('services.whisper.url'), '/') . '/inference', [
                'response_format' => 'json',
                'language'        => 'en',
                'temperature'     => '0.0',
                'prompt'          => $this->hints($piece->session),
            ]);

        if (!$response->successful()) {
            throw new \RuntimeException('Whisper answered ' . $response->status());
        }

        return static::clean((string) $response->json('text', ''));
    }

    /**
     * Tidies what Whisper wrote: drops its [BLANK_AUDIO]-style tags and a
     * sentence repeated straight after itself (it gets stuck when unsure),
     * and treats a piece that's only "Thank you." as silence.
     */
    public static function clean(string $text): string
    {
        $text = preg_replace('/\[[^\]]*\]|\((?:music|applause|laugh\w*|silence|inaudible|noise)[^)]*\)/i', ' ', $text);
        $text = trim(preg_replace('/\s+/', ' ', $text));
        if ($text === '') {
            return '';
        }

        $kept = [];
        $seen = [];
        foreach (preg_split('/(?<=[.!?])\s+/', $text) as $sentence) {
            $key = static::key($sentence);
            if ($key === '') {
                continue;
            }
            $seen[$key] = ($seen[$key] ?? 0) + 1;
            if ($seen[$key] > 2 || ($kept && static::key(end($kept)) === $key)) {
                continue;
            }
            $kept[] = $sentence;
        }
        $clean = implode(' ', $kept);
        // Whisper spells the name by ear: Vintiq, Ventik, Vintig, Ventex…
        $clean = preg_replace('/\b(?:v[ei]nt[ie][qkcg]s?|ventex|vintage)(?=\s+(?:assist|sessions?)\b)|\b(?:vintiq|ventik|vintik|vintig|ventig|ventic|vintic|ventex)\b/i', 'Ventiq', $clean);

        return in_array(static::key($clean), self::NOISE, true) ? '' : $clean;
    }

    private static function key(string $sentence): string
    {
        return trim(preg_replace('/[^\p{L}\p{N}]+/u', ' ', mb_strtolower($sentence)));
    }

    /** The agenda item's transcript, from its pieces in order, unless someone has edited it. */
    public function rebuild(?SessionSegment $segment): void
    {
        if (!$segment || $segment->transcript_edited_at) {
            return;
        }
        $text = SessionRecordingPiece::where('segment_id', $segment->id)->where('status', 'done')->orderBy('seq')->pluck('text')->implode(' ');
        $segment->update(['transcript' => trim($text) !== '' ? trim($text) : null]);
    }

    /** Pieces still being written down. */
    public static function pending(Session $session): int
    {
        return SessionRecordingPiece::where('session_id', $session->id)->where('status', 'pending')->count();
    }

    /**
     * A finished session's report waits for its last pieces; once none
     * are left (written down, or given up on), it's written.
     */
    public static function reportIfReady(Session $session): void
    {
        $session->refresh();
        if ($session->status !== 'completed' || !($session->meta['recording']['report_waiting'] ?? false) || static::pending($session) > 0) {
            return;
        }
        static::remember($session, ['report_waiting' => false]);
        $session->queueReportGeneration();
    }

    /** Once the report is reviewed, when the organisation keeps no audio: delete it, keep the text. */
    public static function deleteAudio(Session $session): void
    {
        SessionRecordingPiece::where('session_id', $session->id)->whereNotNull('path')->get()->each(function (SessionRecordingPiece $piece) {
            Storage::disk('local')->delete($piece->path);
            $piece->update(['path' => null]);
        });
    }
}
