<?php

namespace App\Filament\Resources\TicketResource\Pages;

use App\Filament\Resources\TicketResource;
use App\Models\Event;
use App\Models\OrganizationPackage;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;

class CreateTicket extends CreateRecord
{
    protected static string $resource = TicketResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['status']         = 'active';
        $data['payment_status'] = 'completed';
        $data['created_by']     = auth()->id();

        // Stamp the package on the ticket for reporting
        if (!empty($data['event_id'])) {
            $event = Event::find($data['event_id']);
            if ($event?->organization_package_id) {
                $data['organization_package_id'] = $event->organization_package_id;
            }
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        $ticket = $this->record->fresh();

        // Increment ticket counter on the event's bound package
        if ($ticket->event?->organization_package_id) {
            $package = OrganizationPackage::find($ticket->event->organization_package_id);
            $package?->incrementTicketsUsed();
        }

        $ticket->generateQrCode();
        dispatch(fn () => $ticket->autoDeliverTicket())->afterResponse();
    }
}