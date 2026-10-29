<?php

namespace App\Console\Commands;

use App\Jobs\TranscribeRecordingPiece;
use App\Models\Session;
use App\Models\SessionRecordingPiece;
use App\Services\Sessions\Recording;
use Illuminate\Console\Command;

/**
 * The safety net for recorded sessions: pieces still waiting after 10
 * minutes are sent to Whisper again (the Mac mini was off, a job was
 * lost), and a finished session whose report has waited 45 minutes for
 * its last pieces gets its report anyway, from what's there.
 */
class CatchUpRecordings extends Command
{
    protected $signature = 'sessions:recording-catch-up';

    protected $description = 'Retry recording pieces that are stuck, and write reports that waited too long for them';

    public function handle(): int
    {
        $retried = 0;
        if (Recording::available()) {
            SessionRecordingPiece::where('status', 'pending')->whereNotNull('path')
                ->where('updated_at', '<', now()->subMinutes(10))
                ->where('attempts', '<', 8)
                ->limit(200)
                ->get()
                ->each(function (SessionRecordingPiece $piece) use (&$retried) {
                    $piece->touch();
                    TranscribeRecordingPiece::dispatch($piece->id);
                    $retried++;
                });
        }

        $written = 0;
        Session::where('status', 'completed')->where('meta', 'like', '%"report_waiting":true%')->get()
            ->filter(fn (Session $s) => isset($s->meta['recording']['finished_at']) && \Illuminate\Support\Carbon::parse($s->meta['recording']['finished_at'])->lt(now()->subMinutes(45)))
            ->each(function (Session $session) use (&$written) {
                SessionRecordingPiece::where('session_id', $session->id)->where('status', 'pending')->update(['status' => 'failed', 'error' => 'Gave up waiting']);
                Recording::reportIfReady($session);
                $written++;
            });

        $this->info("{$retried} pieces retried, {$written} reports written.");

        return self::SUCCESS;
    }
}
