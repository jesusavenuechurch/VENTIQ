<?php

namespace App\Jobs;

use App\Models\Session;
use App\Services\Memory\MemoryIndexer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/** A session's passages for Ask Ventiq, made again after it changes (once, however many changes are waiting). */
class IndexSessionMemory implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public function __construct(public int $sessionId) {}

    public function uniqueId(): string
    {
        return (string) $this->sessionId;
    }

    public function handle(MemoryIndexer $indexer): void
    {
        $session = Session::withTrashed()->find($this->sessionId);
        if ($session) {
            $indexer->indexSession($session);
        }
    }
}
