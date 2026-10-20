<?php

namespace App\Services\Memory;

use App\Jobs\EmbedMemoryChunks;
use App\Models\ActionPoint;
use App\Models\MemoryChunk;
use App\Models\Session;
use App\Services\Sessions\MinutesBuilder;
use App\Support\SessionType;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Turns a held session into passages for Ask Ventiq: each part's write-up
 * (or, for a meeting, its minutes), the notes as typed, and the action
 * points. Run again whenever the session changes; a passage whose words
 * didn't change keeps its embedding.
 */
class MemoryIndexer
{
    private const MAX = 1200;

    public function __construct(private MinutesBuilder $minutes) {}

    public function indexSession(Session $session): int
    {
        if ($session->status !== 'reported' || $session->trashed()) {
            MemoryChunk::where('session_id', $session->id)->delete();

            return 0;
        }

        $passages = $this->passages($session->loadMissing('segments', 'series'));

        DB::transaction(function () use ($session, $passages) {
            $existing = MemoryChunk::where('session_id', $session->id)->get()->keyBy(fn ($c) => $this->key($c->source_type, $c->source_id, $c->content_hash));
            $keep = [];
            foreach ($passages as $p) {
                $hash = sha1($p['title'] . "\n" . $p['body']);
                $key = $this->key($p['source_type'], $p['source_id'], $hash);
                if (isset($existing[$key])) {
                    $keep[] = $existing[$key]->id;
                    continue;
                }
                $keep[] = MemoryChunk::create($p + [
                    'organization_id' => $session->organization_id,
                    'session_id'      => $session->id,
                    'date'            => $session->date,
                    'content_hash'    => $hash,
                ])->id;
            }
            MemoryChunk::where('session_id', $session->id)->whereNotIn('id', $keep)->delete();
        });

        EmbedMemoryChunks::dispatch($session->organization_id);

        return count($passages);
    }

    /** @return array<int, array{source_type: string, source_id: ?int, title: string, body: string}> */
    private function passages(Session $session): array
    {
        $label = $session->resolved_title . ' · ' . ($session->date ?? $session->created_at)->format('j M Y');
        $out = collect();
        $add = function (string $type, ?int $id, string $title, ?string $body) use (&$out) {
            foreach ($this->split((string) $body) as $i => $part) {
                $out->push(['source_type' => $type, 'source_id' => $id, 'title' => $i ? "{$title} (cont.)" : $title, 'body' => $part]);
            }
        };

        if (SessionType::takesMinutes($session->type)) {
            // A meeting: its minutes, as confirmed or changed by hand.
            $m = $this->minutes->build($session);
            $add('minutes', null, "{$label} · Attendance", $this->lines([
                $m['heading']['chair'] ? "Chair: {$m['heading']['chair']}" : null,
                $m['heading']['secretary'] ? "Secretary: {$m['heading']['secretary']}" : null,
                $m['heading']['venue'] ? "Venue: {$m['heading']['venue']}" : null,
                $m['present'] ? 'Present: ' . implode(', ', $m['present']) : null,
                $m['apologies'] ? 'Apologies: ' . implode(', ', $m['apologies']) : null,
            ]));
            foreach ($m['items'] as $item) {
                $add('segment', $item['id'], "{$label} · {$item['title']}", $this->lines([
                    $item['lead'] ? "Led by {$item['lead']}." : null,
                    $item['discussion'],
                    $item['decisions'] ? 'Decided: ' . implode('; ', array_map(fn ($d) => rtrim($d, '. '), $item['decisions'])) . '.' : null,
                ]));
            }
            if ($m['aob']) {
                $add('minutes', null, "{$label} · Any other business", $m['aob']);
            }
        } else {
            $sections = SessionType::sections($session->type);
            foreach ($session->segments->where('is_presenting', true) as $s) {
                $summary = $s->ai_summary ?? [];
                if (!empty($summary['_ai_failed'])) {
                    continue;
                }
                $add('segment', $s->id, "{$label} · " . ($s->title ? "{$s->presenter_name}: {$s->title}" : $s->presenter_name), $this->lines(
                    collect($sections)->map(fn ($def, $key) => $this->meaningful($summary[$key] ?? null) ? "{$def['label']}: {$summary[$key]}" : null)->all()
                ));
            }
        }

        // The notes as typed: details the write-up leaves out.
        foreach ($session->segments as $s) {
            $notes = collect($s->raw_log ?? [])->pluck('text')->filter()->implode("\n");
            $add('notes', $s->id, "{$label} · Notes: " . ($s->title ?: $s->presenter_name), $notes);
        }

        // Before parts had write-ups of their own, only the whole report.
        if ($out->where('source_type', 'segment')->isEmpty() && $session->session_report) {
            $add('report', null, "{$label} · Report", $session->session_report);
        }

        ActionPoint::where('session_id', $session->id)->whereIn('status', ['open', 'done', 'proposed'])->with(['ownerUser', 'ownerClient'])->get()
            ->each(fn (ActionPoint $a) => $add('action', $a->id, "{$label} · Action point", $this->lines([
                "Action: {$a->task}",
                'Owner: ' . ($a->ownerLabel() ?? 'no one yet'),
                $a->due_date ? 'Due: ' . $a->due_date->format('j M Y') : null,
                'Status: ' . match ($a->status) {
                    'done'     => 'done' . ($a->done_at ? ' on ' . $a->done_at->format('j M Y') : ''),
                    'proposed' => 'suggested, not yet confirmed',
                    default    => $a->isOverdue() ? 'open, overdue' : 'open',
                },
            ])));

        return $out->values()->all();
    }

    /** Text cut at paragraph and sentence boundaries into passages of a useful size. */
    private function split(string $text): array
    {
        $text = trim(preg_replace("/[ \t]+/", ' ', $text));
        if ($text === '') {
            return [];
        }
        $parts = [];
        $current = '';
        foreach (preg_split('/(?<=[.!?])\s+|\n+/', $text) as $piece) {
            if ($current !== '' && mb_strlen($current) + mb_strlen($piece) + 1 > self::MAX) {
                $parts[] = $current;
                $current = '';
            }
            $current = $current === '' ? $piece : "{$current}\n{$piece}";
        }
        if ($current !== '') {
            $parts[] = $current;
        }

        return array_map(fn ($p) => mb_substr($p, 0, self::MAX * 2), $parts);
    }

    private function lines(array $lines): string
    {
        return collect($lines)->filter(fn ($l) => $this->meaningful($l))->implode("\n");
    }

    private function meaningful(?string $text): bool
    {
        $text = trim((string) $text);

        return $text !== '' && !preg_match('/^none( identified)?\.?$/i', $text);
    }

    private function key(string $type, ?int $id, string $hash): string
    {
        return "{$type}:{$id}:{$hash}";
    }
}
