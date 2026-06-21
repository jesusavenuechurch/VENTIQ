<?php

namespace App\Livewire\VentiqAssist;

use App\Jobs\GenerateEventContent;
use App\Models\AiGenerationResult;
use Livewire\Component;
use Illuminate\Support\Str;

class EventDescriptionAssist extends Component
{
    public string $selectedTitle = '';
    // ── Inputs ────────────────────────────────────────────────────────
    public string $eventName = '';
    public string $category  = '';
    public string $date      = '';
    public string $venue     = '';
    public string $audience  = '';
    public string $notes     = '';
    public string $tone      = 'professional';

    // ── Job tracking ──────────────────────────────────────────────────
    public ?string $jobId     = null;
    public string  $jobStatus = ''; // pending, processing, completed, failed

    // ── State ─────────────────────────────────────────────────────────
    public bool   $generating = false;
    public bool   $generated  = false;
    public string $error      = '';

    // ── Results ───────────────────────────────────────────────────────
    public string $description = '';
    public string $tagline     = '';
    public string $whatsapp    = '';
    public string $facebook    = '';
    public string $hashtags    = '';
    public array  $titles      = [];
    public string $activeTab   = 'description';
    public array  $copied      = [];
    public bool $open = false;

    // ── Polling ───────────────────────────────────────────────────────

    public function getListeners(): array
    {
        return [
                'open-ventiq-assist-modal' => 'openModal',
            ];
    }

    public function openModal(): void
    {
        $this->open = true;

        // Restore last session if within 2 hours and not yet used
        if (empty($this->eventName)) {
            $recent = AiGenerationResult::where('user_id', auth()->id())
                ->where('type', 'event_description')
                ->where('created_at', '>=', now()->subHours(2))
                ->latest()
                ->first();

            if ($recent && $recent->payload) {
                $payload = json_decode($recent->payload, true);
                $this->eventName = $payload['name'] ?? '';
                $this->category  = $payload['category'] ?? '';
                $this->venue     = $payload['venue'] ?? '';
                $this->audience  = $payload['audience'] ?? '';
                $this->tone      = $payload['tone'] ?? 'professional';
                $this->notes     = $payload['notes'] ?? '';

                // If it completed, restore results too
                if ($recent->isCompleted() && !empty($recent->sections)) {
                    $this->description = $recent->sections['description'] ?? '';
                    $this->tagline     = $recent->sections['tagline'] ?? '';
                    $this->whatsapp    = $recent->sections['whatsapp'] ?? '';
                    $this->facebook    = $recent->sections['facebook'] ?? '';
                    $this->hashtags    = $recent->sections['hashtags'] ?? '';
                    $this->titles      = $recent->sections['titles'] ?? [];
                    $this->generated   = true;
                    $this->selectedTitle = $this->eventName;
                }
            }
        }
    }

    public function closeModal(): void
    {
        $this->open = false;
    }

    // Poll every 2 seconds when generating
    public function polling(): void
    {
        if (!$this->generating || !$this->jobId) return;

        $record = AiGenerationResult::where('job_id', $this->jobId)
            ->where('user_id', auth()->id())
            ->first();

        if (!$record) return;

        $this->jobStatus = $record->status;

        if ($record->isCompleted()) {
            $sections = $record->sections ?? [];

            $this->description = $sections['description'] ?? '';
            $this->tagline     = $sections['tagline'] ?? '';
            $this->whatsapp    = $sections['whatsapp'] ?? '';
            $this->facebook    = $sections['facebook'] ?? '';
            $this->hashtags    = $sections['hashtags'] ?? '';
            $this->titles      = $sections['titles'] ?? [];

            $this->selectedTitle = $this->eventName;

            $this->generated  = true;
            $this->generating = false;
            $this->activeTab  = 'description';
            $this->jobId      = null;
        }

        if ($record->isFailed()) {
            $this->error      = $record->error ?? 'Generation failed. Please try again.';
            $this->generating = false;
            $this->jobId      = null;
        }
    }

    public function generate(): void
    {
        // Guard — prevent multiple dispatches
        if ($this->generating) return;

        $this->validate([
            'eventName' => 'required|min:3',
            'category'  => 'required',
        ], [
            'eventName.required' => 'Please enter the event name.',
            'category.required'  => 'Please select a category.',
        ]);

        $this->generating = true;
        $this->generated  = false;
        $this->error      = '';
        $this->jobId      = (string) Str::uuid();

        // Create the pending result record
        AiGenerationResult::create([
            'job_id'  => $this->jobId,
            'user_id' => auth()->id(),
            'type'    => 'event_description',
            'status'  => 'pending',
            'payload' => json_encode([
                'name'     => $this->eventName,
                'category' => $this->category,
                'date'     => $this->date,
                'venue'    => $this->venue,
                'audience' => $this->audience,
                'notes'    => $this->notes,
                'tone'     => $this->tone,
            ]),
        ]);

        // Dispatch the background job
        GenerateEventContent::dispatch(
            jobId:      $this->jobId,
            userId:     auth()->id(),
            promptData: [
                'name'     => $this->eventName,
                'category' => $this->category,
                'date'     => $this->date ?: 'Date to be confirmed',
                'venue'    => $this->venue,
                'audience' => $this->audience ?: 'General public',
                'notes'    => $this->notes,
                'tone'     => $this->tone,
            ],
        );
    }

    public function regenerate(string $newTone): void
    {
        $this->tone      = $newTone;
        $this->generated = false;
        $this->generate();
    }

    public function useDescription(): void
    {
        $this->dispatch('ventiq-assist-use-description',
            description: $this->description,
            tagline:     $this->tagline,
        );
        $this->dispatch('close-ventiq-assist-modal');
    }

    public function useTitle(string $title): void
    {
        $this->dispatch('ventiq-assist-use-title', title: $title);
    }

    public function markCopied(string $key): void
    {
        $this->copied[$key] = true;
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    public function resetAssist(): void
    {
        $this->generated  = false;
        $this->generating = false;
        $this->jobId      = null;
        $this->error      = '';
    }

    public function render()
    {
        return view('livewire.ventiq-assist.event-description-assist');
    }

    public function fillEventForm(): void
    {
        $nameToUse = $this->selectedTitle ?: $this->eventName;

        $this->dispatch('ventiq-assist-fill-form',
            name:        $nameToUse,
            tagline:     $this->tagline,
            description: $this->description,
        );

        // Save social content to the result record for later access
        AiGenerationResult::where('job_id', $this->jobId ?? '')
            ->orWhere(function ($q) {
                $q->where('user_id', auth()->id())
                ->where('status', 'completed')
                ->latest();
            })
            ->first()
            ?->update([
                'sections->whatsapp' => $this->whatsapp,
                'sections->facebook' => $this->facebook,
                'sections->hashtags' => $this->hashtags,
            ]);

        $this->dispatch('close-ventiq-assist-modal');
        $this->closeModal();
    }
}