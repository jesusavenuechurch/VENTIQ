<?php

namespace App\Filament\Resources\OrganizationalRecordResource\Pages;

use App\Filament\Resources\OrganizationalRecordResource;
use App\Models\AiGenerationResult;
use App\Models\OrganizationalRecord;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;
use Filament\Actions\Action;
use Illuminate\Support\HtmlString;

class EditOrganizationalRecord extends EditRecord
{
    protected static string $resource = OrganizationalRecordResource::class;

    public function getPollingInterval(): ?string
    {
        return $this->record->extraction_job_id ? '3s' : null;
    }
 
    public function poll(): void
    {
        $record = $this->record->fresh();
        $jobId  = $record->extraction_job_id;
        if (!$jobId) return;

        $result = AiGenerationResult::where('job_id', $jobId)->first();
        if (!$result) return;

        if ($result->status === 'completed') {
            Notification::make()
                ->title('Extraction Complete ✨')
                ->body('Ventiq Assist has structured your notes. Review and edit below.')
                ->success()
                ->send();

            // Redirect forces a full page reload — relationships populate correctly
            $this->redirect(
                OrganizationalRecordResource::getUrl('edit', ['record' => $this->record])
            );
            return;
        }

        if ($result->status === 'failed') {
            $result->update(['status' => 'failed']); // already set by job
            Notification::make()
                ->title('Extraction Failed')
                ->body($result->error ?? 'Something went wrong. Please try again.')
                ->danger()
                ->send();

            // Stop polling by clearing job id
            $record->update(['extraction_job_id' => null]);
        }
    }
 
    // ── Header actions ────────────────────────────────────────────────────────
 
    protected function getHeaderActions(): array
    {
        $record = $this->record;
 
        return [
            // Extracting spinner — shown while job is running
            Action::make('extracting')
                ->label('Extracting...')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->disabled()
                ->visible(fn () => (bool) $this->record->fresh()->extraction_job_id),
 
            // Re-extract — shown on draft or extracted records
            Action::make('re_extract')
                ->label('Re-extract with Ventiq Assist')
                ->icon('heroicon-o-sparkles')
                ->color('warning')
                ->visible(fn () =>
                    in_array($record->status, ['draft', 'extracted']) &&
                    !$record->fresh()->extraction_job_id
                )
                ->requiresConfirmation()
                ->modalHeading('Re-extract?')
                ->modalDescription('This will replace the current extracted content with a fresh extraction from your raw notes.')
                ->action(fn () => OrganizationalRecordResource::dispatchExtraction($record)),
 
            // Finalize
            Action::make('finalize')
                ->label('Finalize & Generate Document')
                ->icon('heroicon-o-document-check')
                ->color('success')
                ->visible(fn () => $record->status === 'extracted')
                ->requiresConfirmation()
                ->modalHeading('Finalize this record?')
                ->modalDescription('This generates the official formatted document from your confirmed extractions.')
                ->action(fn () => OrganizationalRecordResource::generateFinalDocument($record)),
 
            // Download PDF
            Action::make('download_pdf')
                ->label('Download PDF')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('info')
                ->visible(fn () => $record->status === 'finalized')
                ->url(fn () => route('organizational-records.pdf', $record))
                ->openUrlInNewTab(),
 
            Actions\DeleteAction::make(),
        ];
    }
 
    // ── Status banner shown at top of edit page ───────────────────────────────
 
    protected function getHeaderWidgets(): array
    {
        return [];
    }
 
    public function getSubheading(): string|HtmlString|null
    {
        $record = $this->record->fresh();
 
        if ($record->extraction_job_id) {
            return new HtmlString('
                <div class="flex items-center gap-2 text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-4 py-2 mt-2">
                    <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 22 6.477 22 12h-4z"></path>
                    </svg>
                    Ventiq Assist is extracting structure from your notes... This page will update automatically.
                </div>
            ');
        }
 
        return match($record->status) {
            'draft'     => new HtmlString('<span class="text-sm text-gray-500">Draft — save your notes and Ventiq Assist will extract the structure.</span>'),
            'extracted' => new HtmlString('<span class="text-sm text-amber-600">✨ Extraction complete — review the sections below, then finalize to generate the document.</span>'),
            'finalized' => new HtmlString('<span class="text-sm text-green-600">✅ Finalized — your document is ready to download.</span>'),
            default     => null,
        };
    }
 
    protected function getSavedNotificationTitle(): ?string
    {
        return 'Record saved';
    }

}
