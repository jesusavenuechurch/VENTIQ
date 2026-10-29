<?php

namespace App\Http\Controllers;

use App\Jobs\TranscribeRecordingPiece;
use App\Models\Session;
use App\Models\SessionRecordingPiece;
use App\Services\Sessions\Recording;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * Recording a live session from the console: one phone at a time sends
 * 30-second pieces. Sending a piece twice (a retry after a dropped
 * connection) keeps the first.
 */
class SessionRecordingController extends Controller
{
    public function start(Request $request, Session $session)
    {
        $this->authorizeSession($session);
        $recorder = $request->validate(['recorder' => 'required|string|max:64'])['recorder'];
        abort_unless(Recording::available(), 409, 'Recording isn\'t set up yet.');
        abort_unless($session->status === 'active', 409, 'Start the session first.');
        if (Recording::heldByOther($session, $recorder)) {
            return response()->json(['message' => 'Someone is already recording this session on another device.'], 409);
        }
        if (Recording::state($session)['seconds'] >= config('services.whisper.max_seconds')) {
            return response()->json(['message' => 'This session has reached its recording limit.'], 422);
        }

        Recording::remember($session, ['recorder' => $recorder, 'paused' => false, 'seen_at' => now()->toIso8601String(), 'started_at' => $session->meta['recording']['started_at'] ?? now()->toIso8601String()]);

        return response()->json(['status' => 'ok'] + Recording::state($session->fresh()));
    }

    public function piece(Request $request, Session $session)
    {
        $this->authorizeSession($session);
        $data = $request->validate([
            'recorder'    => 'required|string|max:64',
            'seq'         => 'required|integer|min:0|max:100000',
            'segment_id'  => 'nullable|integer',
            'duration_ms' => 'required|integer|min:0|max:120000',
            'recorded_at' => 'nullable|date',
            'audio'       => 'required|file|max:8192',
        ]);
        abort_unless(Recording::available(), 409, 'Recording isn\'t set up yet.');

        // Already here (a retry): nothing more to do.
        if (SessionRecordingPiece::where('session_id', $session->id)->where('seq', $data['seq'])->exists()) {
            return response()->json(['status' => 'ok', 'duplicate' => true]);
        }
        if (Recording::heldByOther($session, $data['recorder'])) {
            return response()->json(['message' => 'Someone is already recording this session on another device.'], 409);
        }
        $mime = (string) $request->file('audio')->getMimeType();
        if (!preg_match('#^(audio|video)/#', $mime)) {
            return response()->json(['message' => 'That isn\'t audio.'], 422);
        }
        $recorded = SessionRecordingPiece::where('session_id', $session->id)->sum('duration_ms');
        if ($recorded >= config('services.whisper.max_seconds') * 1000) {
            return response()->json(['message' => 'This session has reached its recording limit.', 'limit' => true], 422);
        }

        $segmentId = $data['segment_id'] && $session->segments()->whereKey($data['segment_id'])->exists() ? $data['segment_id'] : null;
        $extension = str_contains($mime, 'mp4') || str_contains($mime, 'aac') || str_contains($mime, 'm4a') ? 'm4a' : (str_contains($mime, 'ogg') ? 'ogg' : 'webm');
        $path = $request->file('audio')->storeAs("recordings/session-{$session->id}", sprintf('%05d.%s', $data['seq'], $extension), 'local');

        $piece = SessionRecordingPiece::create([
            'session_id'  => $session->id,
            'segment_id'  => $segmentId,
            'seq'         => $data['seq'],
            'recorder'    => $data['recorder'],
            'recorded_at' => $data['recorded_at'] ?? now(),
            'duration_ms' => $data['duration_ms'],
            'path'        => $path,
            'mime'        => mb_substr($mime, 0, 60),
        ]);
        Recording::remember($session, ['recorder' => $data['recorder'], 'seen_at' => now()->toIso8601String()]);

        config('queue.default') === 'sync'
            ? TranscribeRecordingPiece::dispatchAfterResponse($piece->id)
            : TranscribeRecordingPiece::dispatch($piece->id);

        return response()->json(['status' => 'ok', 'seconds' => (int) round(($recorded + $piece->duration_ms) / 1000)]);
    }

    /** Off the record: nothing is recorded until it's resumed, and the transcript says so. */
    public function pause(Request $request, Session $session)
    {
        $this->authorizeSession($session);
        $pauses = $session->meta['recording']['pauses'] ?? [];
        if (!($session->meta['recording']['paused'] ?? false)) {
            $pauses[] = ['from' => now()->toIso8601String(), 'to' => null];
        }
        Recording::remember($session, ['paused' => true, 'pauses' => $pauses, 'seen_at' => now()->toIso8601String()]);

        return response()->json(['status' => 'ok']);
    }

    public function resume(Request $request, Session $session)
    {
        $this->authorizeSession($session);
        $pauses = $session->meta['recording']['pauses'] ?? [];
        if ($pauses && end($pauses)['to'] === null) {
            $pauses[array_key_last($pauses)]['to'] = now()->toIso8601String();
        }
        Recording::remember($session, ['paused' => false, 'pauses' => $pauses, 'seen_at' => now()->toIso8601String()]);

        return response()->json(['status' => 'ok']);
    }

    /** Recording over, everything sent: the phone lets go of it. */
    public function stop(Request $request, Session $session)
    {
        $this->authorizeSession($session);
        $pauses = $session->meta['recording']['pauses'] ?? [];
        if ($pauses && end($pauses)['to'] === null) {
            $pauses[array_key_last($pauses)]['to'] = now()->toIso8601String();
        }
        Recording::remember($session, ['recorder' => null, 'paused' => false, 'pauses' => $pauses, 'stopped_at' => now()->toIso8601String()]);
        Recording::reportIfReady($session);

        return response()->json(['status' => 'ok']);
    }

    private function authorizeSession(Session $session): void
    {
        abort_unless($session->organization_id === Auth::user()->organization_id, 404);
    }
}
