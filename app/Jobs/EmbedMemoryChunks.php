<?php

namespace App\Jobs;

use App\Models\MemoryChunk;
use App\Services\Memory\Embedder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Embeddings for an organization's passages that don't have one (or have
 * one from another model), on the Mac mini, a batch at a time. When it
 * can't be reached it stops; the hourly sessions:embed-memory tries again.
 */
class EmbedMemoryChunks implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $timeout = 600;
    public int $tries = 1;

    private const BATCH = 16;

    public function __construct(public int $organizationId) {}

    public function uniqueId(): string
    {
        return (string) $this->organizationId;
    }

    public function handle(Embedder $embedder): void
    {
        if (!$embedder->enabled()) {
            return;
        }
        $model = $embedder->model();

        while (true) {
            $chunks = MemoryChunk::where('organization_id', $this->organizationId)
                ->where(fn ($q) => $q->whereNull('embedding')->orWhere('embedding_model', '!=', $model)->orWhereNull('embedding_model'))
                ->orderBy('id')->limit(self::BATCH)->get();
            if ($chunks->isEmpty()) {
                return;
            }

            // nomic-embed-text wants to know which side of the search a text is on.
            $vectors = $embedder->embed($chunks->map(fn ($c) => "search_document: {$c->title}\n{$c->body}")->all());
            if ($vectors === null) {
                return;
            }
            foreach ($chunks->values() as $i => $chunk) {
                $chunk->forceFill(['embedding' => MemoryChunk::pack($vectors[$i]), 'embedding_model' => $model])->save();
            }
        }
    }
}
