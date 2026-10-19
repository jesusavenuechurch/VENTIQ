<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateEventContent;
use App\Models\AiGenerationResult;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Ventiq Assist on the event form: write an event's name, tagline and
 * description from a few details. The writing happens in a job; the
 * page polls show() until it's done.
 */
class EventContentController extends Controller
{
    private const TYPE = 'event_description';

    // A job no worker picked up would otherwise leave the page waiting forever.
    private const STALE_AFTER_SECONDS = 90;

    /** The last one this person asked for in the past two hours, to pick up where they left off. */
    public function latest(Request $request)
    {
        $recent = AiGenerationResult::where('user_id', $request->user()->id)
            ->where('type', self::TYPE)
            ->where('created_at', '>=', now()->subHours(2))
            ->latest()
            ->first();

        return response()->json(['result' => $recent ? $this->present($recent) : null]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'     => ['required', 'string', 'min:3', 'max:255'],
            'category' => ['required', 'string', 'max:100'],
            'date'     => ['nullable', 'string', 'max:100'],
            'venue'    => ['nullable', 'string', 'max:255'],
            'audience' => ['nullable', 'string', 'max:255'],
            'notes'    => ['nullable', 'string', 'max:2000'],
            'tone'     => ['required', 'string', 'max:100'],
        ], [
            'name.required'     => 'Please enter the event name.',
            'category.required' => 'Please choose a category.',
        ]);
        $data = array_map(fn ($v) => (string) $v, [...array_fill_keys(['date', 'venue', 'audience', 'notes'], ''), ...$data]);

        $result = AiGenerationResult::create([
            'job_id'  => (string) Str::uuid(),
            'user_id' => $request->user()->id,
            'type'    => self::TYPE,
            'status'  => 'pending',
            'payload' => json_encode($data),
        ]);

        GenerateEventContent::dispatch(
            jobId: $result->job_id,
            userId: $request->user()->id,
            promptData: [
                ...$data,
                'date'     => $data['date'] ?: 'Date to be confirmed',
                'audience' => $data['audience'] ?: 'General public',
            ],
        );

        return response()->json(['result' => $this->present($result)]);
    }

    public function show(Request $request, string $jobId)
    {
        $result = AiGenerationResult::where('job_id', $jobId)
            ->where('user_id', $request->user()->id)
            ->where('type', self::TYPE)
            ->firstOrFail();

        if ($result->isPending() && $result->created_at->diffInSeconds(now()) >= self::STALE_AFTER_SECONDS) {
            $result->update(['status' => 'failed', 'error' => 'That took longer than expected. Please try again.']);
        }

        return response()->json(['result' => $this->present($result)]);
    }

    /** @return array<string, mixed> */
    private function present(AiGenerationResult $result): array
    {
        $sections = $result->sections ?? [];

        return [
            'jobId'   => $result->job_id,
            'status'  => $result->isPending() ? 'pending' : $result->status,
            'error'   => $result->isFailed() ? ($result->error ?: 'Writing failed. Please try again.') : null,
            'input'   => json_decode((string) $result->payload, true) ?: [],
            'content' => $result->isCompleted() ? [
                'description' => (string) ($sections['description'] ?? ''),
                'tagline'     => (string) ($sections['tagline'] ?? ''),
                'titles'      => array_values(array_filter((array) ($sections['titles'] ?? []), 'is_string')),
            ] : null,
        ];
    }
}
