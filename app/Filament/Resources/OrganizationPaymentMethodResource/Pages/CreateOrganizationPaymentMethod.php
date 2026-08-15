<?php

namespace App\Filament\Resources\OrganizationPaymentMethodResource\Pages;

use App\Filament\Resources\EventResource;
use App\Filament\Resources\OrganizationPaymentMethodResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;

class CreateOrganizationPaymentMethod extends CreateRecord
{
    protected static string $resource = OrganizationPaymentMethodResource::class;

    /**
     * After creating a payment method, suggest creating an event
     */
    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Payment Method Added')
            ->body('Your payment method has been configured successfully.')
            ->actions([
                \Filament\Notifications\Actions\Action::make('create_event')
                    ->label('Create Event Now')
                    ->button()
                    ->url(EventResource::getUrl('create'))
                    ->color('primary'),
            ]);
    }
}