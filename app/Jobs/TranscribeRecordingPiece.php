<?php

namespace App\Jobs;

use App\Models\SessionRecordingPiece;
use App\Services\Sessions\Recording;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/** Has Whisper write down one 30-second piece; tries again later if the Mac mini can't be reached. */
class TranscribeRecordingPiece implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 4;

    public function __construct(public int $pieceId) {}

    public function backoff(): array
    {
        return [30, 120, 600];
    }

    public function handle(Recording $recording): void
    {
        $piece = SessionRecordingPiece::with(['session', 'segment'])->find($this->pieceId);
        if (!$piece || $piece->status !== 'pending' || !$piece->path) {
            return;
        }
        $piece->increment('attempts');

        try {
            $text = $recording->transcribe($piece);
        } catch (\Throwable $e) {
            $piece->update(['error' => mb_substr($e->getMessage(), 0, 250)]);
            // The queue tries again; with no queue worker (sync), it's given up on now.
            if (config('queue.default') !== 'sync' && $this->attempts() < $this->tries) {
                throw $e;
            }
            $this->giveUp($piece, $e);

            return;
        }

        $piece->update(['status' => $text === '' ? 'empty' : 'done', 'text' => $text ?: null, 'error' => null]);
        $recording->rebuild($piece->segment);
        Recording::reportIfReady($piece->session);
    }

    public function failed(\Throwable $e): void
    {
        if ($piece = SessionRecordingPiece::with('session')->find($this->pieceId)) {
            $this->giveUp($piece, $e);
        }
    }

    private function giveUp(SessionRecordingPiece $piece, \Throwable $e): void
    {
        Log::warning("Recording piece {$piece->id} couldn't be written down", ['error' => $e->getMessage()]);
        $piece->update(['status' => 'failed']);
        Recording::reportIfReady($piece->session);
    }
}
