<?php

namespace App\Console\Commands;

use App\Jobs\EmbedMemoryChunks;
use App\Models\MemoryChunk;
use App\Models\Session;
use App\Services\Memory\MemoryIndexer;
use Illuminate\Console\Command;

/**
 * Ask Ventiq's memory: --all makes every held session's passages again
 * (once, after deploying); on its own (hourly) it embeds the passages
 * still waiting for the Mac mini.
 */
class IndexMemoryCommand extends Command
{
    protected $signature = 'sessions:index-memory {--all : Make every held session\'s passages again} {--organization= : Only this organization}';

    protected $description = "Keep Ask Ventiq's memory of each organization's sessions up to date";

    public function handle(MemoryIndexer $indexer): int
    {
        $org = $this->option('organization');

        if ($this->option('all')) {
            $count = 0;
            Session::where('status', 'reported')->when($org, fn ($q) => $q->where('organization_id', $org))
                ->chunkById(100, function ($sessions) use ($indexer, &$count) {
                    $sessions->each(function ($s) use ($indexer, &$count) {
                        $indexer->indexSession($s);
                        $count++;
                    });
                });
            $this->info("Indexed {$count} session(s).");
        }

        $waiting = MemoryChunk::whereNull('embedding')->when($org, fn ($q) => $q->where('organization_id', $org))->distinct()->pluck('organization_id');
        $waiting->each(fn ($id) => EmbedMemoryChunks::dispatch($id));
        $this->info("Embedding for {$waiting->count()} organization(s).");

        return self::SUCCESS;
    }
}
