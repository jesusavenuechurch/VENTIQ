<?php

namespace App\Filament\Resources\EventResource\Pages;

use App\Filament\Resources\EventResource;
use App\Models\OrganizationPaymentMethod;
use App\Services\Reports\AttendanceReportService;
use App\Services\Reports\RegistrationSummaryService;
use App\Services\Reports\RevenueReportService;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;

class EditEvent extends EditRecord
{
    protected static string $resource = EventResource::class;

    protected function getHeaderActions(): array
    {
        $record = $this->record;

        return [
            // ── Publish ───────────────────────────────────────────────────────
            Action::make('publish')
                ->label('Publish Event')
                ->icon('heroicon-o-rocket-launch')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Publish this event?')
                ->modalDescription('This will make the event visible to the public immediately.')
                ->visible(fn () => $this->record->status === 'draft')
                ->action(function () {
                    $this->record->update(['status' => 'published']);
                    Notification::make()
                        ->title('Event Published 🚀')
                        ->body("'{$this->record->name}' is now live.")
                        ->success()
                        ->send();
                }),

            // ── Reports ───────────────────────────────────────────────────────

            Action::make('attendance_pdf')
                ->label('Attendance Register')
                ->icon('heroicon-o-document-arrow-down')
                ->color('info')
                ->button()
                ->url(fn () => route('reports.attendance', $record), shouldOpenInNewTab: true),

            Action::make('attendance_excel')
                ->label('Attendance Excel')
                ->icon('heroicon-o-table-cells')
                ->color('success')
                ->button()
                ->action(fn () => (new AttendanceReportService($record))->downloadExcel()),

            Action::make('registration_summary')
                ->label('Registration Summary')
                ->icon('heroicon-o-chart-bar')
                ->color('warning')
                ->button()
                ->url(fn () => route('reports.registration-summary', $record), shouldOpenInNewTab: true),

            Action::make('revenue_report')
                ->label('Revenue Report')
                ->icon('heroicon-o-banknotes')
                ->color('gray')
                ->button()
                ->visible(fn () => $record->tickets()->where('is_complimentary', false)->exists())
                ->url(fn () => route('reports.revenue', $record), shouldOpenInNewTab: true),

            // ── Delete ────────────────────────────────────────────────────────
            Actions\DeleteAction::make(),
        ];
    }

    // ── Existing form data handling — unchanged ───────────────────────────────

    protected function mutateFormDataBeforeFill(array $data): array
    {
        if (!empty($data['event_date'])) {
            try {
                $datetime = \Carbon\Carbon::parse($data['event_date']);
                $data['event_date_only'] = $datetime->format('Y-m-d');
                $data['event_time_only'] = $datetime->format('H:i');
            } catch (\Exception $e) {}
        }

        if (!empty($data['registration_deadline'])) {
            try {
                $datetime = \Carbon\Carbon::parse($data['registration_deadline']);
                $data['registration_deadline_date'] = $datetime->format('Y-m-d');
                $data['registration_deadline_time'] = $datetime->format('H:i');
            } catch (\Exception $e) {}
        }

        // enabled_payment_method_ids stores online + manual method IDs
        // together (see mutateFormDataBeforeSave below) — split the online
        // one back out into its own checkbox for the form, and leave only
        // the manual ones in the checkbox list.
        $enabledIds = $data['enabled_payment_method_ids'] ?? [];
        $onlineMethodId = OrganizationPaymentMethod::where('organization_id', $data['organization_id'] ?? null)
            ->where('payment_method', 'online')
            ->value('id');

        $data['enable_online_payments'] = $onlineMethodId && in_array($onlineMethodId, $enabledIds ?? []);
        $data['enabled_payment_method_ids'] = array_values(array_diff($enabledIds ?? [], [$onlineMethodId]));

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (isset($data['event_date_only'], $data['event_time_only'])) {
            $data['event_date'] = \Carbon\Carbon::parse($data['event_date_only'])
                ->setTimeFromTimeString($data['event_time_only'])
                ->format('Y-m-d H:i:s');
        }

        if (isset($data['registration_deadline_date'], $data['registration_deadline_time'])) {
            $data['registration_deadline'] = $data['registration_deadline_date'] . ' ' . $data['registration_deadline_time'];
        } else {
            $data['registration_deadline'] = null;
        }

        // Merge the online method's ID back into enabled_payment_method_ids
        // (mirrors CreateEvent's mutateFormDataBeforeCreate) so
        // RegistrationController::payment() keeps working off one combined
        // allowlist.
        $enabledIds = $data['enabled_payment_method_ids'] ?? [];
        if (!empty($data['enable_online_payments'])) {
            $onlineMethod = OrganizationPaymentMethod::firstOrCreate(
                ['organization_id' => $data['organization_id'], 'payment_method' => 'online'],
                ['is_active' => true, 'display_order' => 0]
            );
            $enabledIds = array_merge([$onlineMethod->id], (array) $enabledIds);
        }
        $data['enabled_payment_method_ids'] = !empty($enabledIds) ? array_values(array_unique($enabledIds)) : null;

        unset(
            $data['event_date_only'],
            $data['event_time_only'],
            $data['registration_deadline_date'],
            $data['registration_deadline_time'],
            $data['enable_online_payments'],
        );

        return $data;
    }

    protected function getSavedNotification(): ?Notification
    {
        return Notification::make()
            ->title('Event Updated')
            ->success();
    }
}