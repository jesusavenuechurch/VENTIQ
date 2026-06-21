<?php

namespace App\Jobs;

use App\Models\AiGenerationResult;
use App\Models\OrganizationalRecord;
use App\Models\RecordExtraction;
use App\Models\RecordActionItem;
use App\Models\RecordOpenIssue;
use App\Services\AI\AIService;
use App\Services\AI\Prompts\OrganizationalRecordPrompt;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GenerateOrganizationalRecord implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 180;
    public int $tries   = 2;
    public int $backoff = 10;

    public function __construct(
        public readonly string $jobId,
        public readonly int    $userId,
        public readonly int    $recordId,
        public readonly array  $promptData,
    ) {}

    public function handle(AIService $ai): void
    {
        // Mark AI result as processing
        AiGenerationResult::where('job_id', $this->jobId)
            ->update(['status' => 'processing']);

        try {
            $prompt = (new OrganizationalRecordPrompt())->with($this->promptData);
            $result = $ai->generate($prompt);

            if (!$result->success) {
                $this->markFailed($result->error);
                return;
            }

            // parseSections() already handles the HEADING: content pattern
            $sections = $ai->parseSections($result->content);

            // ── Parse each section into clean arrays ──────────────────────────
            $agenda           = $this->parseLines($sections['agenda']           ?? '');
            $discussionPoints = $this->parseLines($sections['discussion_points'] ?? '');
            $decisions        = $this->parseLines($sections['decisions']         ?? '');
            $actionItems      = $this->parseLines($sections['action_items']      ?? '');
            $openIssues       = $this->parseLines($sections['open_issues']       ?? '');
            $suggestedTitle   = trim($sections['suggested_title']                ?? '');

            DB::beginTransaction();

            // ── Save extractions to child tables ──────────────────────────────
            $record = OrganizationalRecord::findOrFail($this->recordId);

            // Clear any previous extractions if this is a re-run
            $record->extractions()->delete();
            $record->actionItems()->delete();
            $record->openIssues()->delete();

            // Agenda
            foreach ($agenda as $order => $item) {
                RecordExtraction::create([
                    'record_id'      => $record->id,
                    'category'       => 'agenda',
                    'content'        => $item,
                    'is_ai_generated'=> true,
                    'display_order'  => $order,
                ]);
            }

            // Discussion points
            foreach ($discussionPoints as $order => $item) {
                RecordExtraction::create([
                    'record_id'      => $record->id,
                    'category'       => 'discussion_point',
                    'content'        => $item,
                    'is_ai_generated'=> true,
                    'display_order'  => $order,
                ]);
            }

            // Decisions
            foreach ($decisions as $order => $item) {
                RecordExtraction::create([
                    'record_id'      => $record->id,
                    'category'       => 'decision',
                    'content'        => $item,
                    'is_ai_generated'=> true,
                    'display_order'  => $order,
                ]);
            }

            // Action items — go into both record_extractions AND record_action_items
            foreach ($actionItems as $order => $item) {
                $extraction = RecordExtraction::create([
                    'record_id'      => $record->id,
                    'category'       => 'action_item',
                    'content'        => $item,
                    'is_ai_generated'=> true,
                    'display_order'  => $order,
                ]);

                // Parse "• Action — Person — Deadline" format
                [$description, $assignee, $deadline] = $this->parseActionItem($item);

                RecordActionItem::create([
                    'record_id'        => $record->id,
                    'extraction_id'    => $extraction->id,
                    'description'      => $description,
                    'assigned_to_name' => $assignee,
                    'due_date'         => $deadline,
                    'status'           => 'pending',
                ]);
            }

            // Open issues — go into both record_extractions AND record_open_issues
            foreach ($openIssues as $order => $item) {
                $extraction = RecordExtraction::create([
                    'record_id'      => $record->id,
                    'category'       => 'open_issue',
                    'content'        => $item,
                    'is_ai_generated'=> true,
                    'display_order'  => $order,
                ]);

                RecordOpenIssue::create([
                    'record_id'     => $record->id,
                    'extraction_id' => $extraction->id,
                    'description'   => $item,
                    'status'        => 'open',
                ]);
            }

            // Update the master record
            $record->update([
                'status'       => 'extracted',
                'ai_extracted' => [
                    'agenda'            => $agenda,
                    'discussion_points' => $discussionPoints,
                    'decisions'         => $decisions,
                    'action_items'      => $actionItems,
                    'open_issues'       => $openIssues,
                    'suggested_title'   => $suggestedTitle,
                ],
                // If no title yet, use AI suggestion
                'title' => $record->title ?: ($suggestedTitle ?: null),
                'extraction_job_id'  => null,
            ]);

            DB::commit();

            // Mark AI result as completed
            AiGenerationResult::where('job_id', $this->jobId)
                ->update([
                    'status'   => 'completed',
                    'result'   => $result->content,
                    'sections' => $sections,
                    'duration' => $result->duration,
                ]);

            Log::info('OrganizationalRecord extraction complete', [
                'record_id' => $record->id,
                'agenda'    => count($agenda),
                'actions'   => count($actionItems),
                'issues'    => count($openIssues),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('GenerateOrganizationalRecord failed', [
                'job_id'    => $this->jobId,
                'record_id' => $this->recordId,
                'error'     => $e->getMessage(),
            ]);
            $this->markFailed($e->getMessage());
        }
    }

    public function failed(\Throwable $e): void
    {
        $this->markFailed($e->getMessage());
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Split a section text into clean individual items.
     * Strips bullet characters, empty lines, and "None identified."
     */
    protected function parseLines(string $text): array
    {
        return collect(explode("\n", $text))
            ->map(fn ($line) => trim($line, "•-– \t"))
            ->filter(fn ($line) => $line && $line !== 'None identified.')
            ->values()
            ->toArray();
    }

    /**
     * Parse "• Action — Person — Deadline" into three parts.
     * Gracefully handles missing parts.
     */
    protected function parseActionItem(string $raw): array
    {
        $parts = array_map('trim', explode('—', $raw));

        $description = $parts[0] ?? $raw;
        $assignee    = isset($parts[1]) && $parts[1] ? $parts[1] : null;
        $deadline    = isset($parts[2]) && $parts[2] ? $this->parseDate($parts[2]) : null;

        return [$description, $assignee, $deadline];
    }

    /**
     * Attempt to parse a date string — returns null if unparseable.
     */
    protected function parseDate(string $raw): ?string
    {
        try {
            return \Carbon\Carbon::parse($raw)->format('Y-m-d');
        } catch (\Exception) {
            return null;
        }
    }

    protected function markFailed(string $error): void
    {
        AiGenerationResult::where('job_id', $this->jobId)
            ->update(['status' => 'failed', 'error' => $error]);

        OrganizationalRecord::where('id', $this->recordId)
            ->update(['extraction_job_id' => null]);
    }
}