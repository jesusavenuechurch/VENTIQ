<?php

namespace App\Filament\Resources\EventResource\Pages;

use App\Filament\Resources\EventResource;
use App\Models\OrganizationPackage;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Notifications\Notification;

class ListEvents extends ListRecords
{
    protected static string $resource = EventResource::class;

    protected function getHeaderActions(): array
    {
        $user = auth()->user();

        if ($user->isSuperAdmin()) {
            return [
                CreateAction::make()
                    ->label('Create Event')
                    ->icon('heroicon-o-plus'),
            ];
        }

        $hasAnyPackage = OrganizationPackage::where('organization_id', $user->organization_id)->exists();

        if (!$hasAnyPackage) {
            return [
                Action::make('start_free_trial')
                    ->label('Start Free Trial')
                    ->icon('heroicon-o-gift')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Start Your Free Trial')
                    ->modalDescription('Get started with a FREE Standard package — 150 tickets, 1 event. No payment required!')
                    ->modalSubmitActionLabel('Activate Free Trial')
                    ->action(function () use ($user) {
                        OrganizationPackage::createFreeTrialPackage($user->organization_id);
                        Notification::make()
                            ->title('Free Trial Activated! 🎉')
                            ->body('You can now create your first event.')
                            ->success()
                            ->send();
                        redirect()->to(EventResource::getUrl('index'));
                    }),
            ];
        }

        if (static::getResource()::canCreate()) {
            return [
                CreateAction::make()
                    ->label('Create Event')
                    ->icon('heroicon-o-plus'),
            ];
        }

        // All packages exhausted/expired — trigger upgrade modal
        return [
            Action::make('upgrade')
                ->label('Upgrade Package')
                ->icon('heroicon-o-arrow-up-circle')
                ->color('primary')
                ->extraAttributes([
                    'onclick' => "window.dispatchEvent(new CustomEvent('open-upgrade-modal'))",
                    'type'    => 'button',
                ])
                ->action(fn () => null) // no-op, JS handles it
                ->tooltip('All packages exhausted or expired. Upgrade to create more events.'),
        ];
    }
}