<?php

namespace App\Support;

use App\Models\{Event, EventTier, SettlementItem, Ticket, TicketFee};
use Illuminate\Database\Eloquent\Model;

/**
 * Whether deleting something would take money records with it. Tickets,
 * payments, VENTIQ's fees and payout lines are deleted along with their
 * event (database cascades), so an event, ticket type or ticket that has
 * any of them can't be deleted; it's cancelled instead.
 */
class MoneyRecords
{
    /** Why it can't be deleted, or null when it can. */
    public static function blockingDelete(Model $model): ?string
    {
        return match (true) {
            $model instanceof Event && $model->tickets()->exists()
                => "{$model->name} has tickets, so it can't be deleted. Set its status to Cancelled instead.",
            $model instanceof EventTier && $model->tickets()->exists()
                => "{$model->tier_name} has tickets, so it can't be deleted. Take it off sale instead.",
            $model instanceof Ticket && static::ticketHasMoney($model)
                => "Ticket {$model->ticket_number} has payments or fees recorded, so it can't be deleted.",
            default => null,
        };
    }

    private static function ticketHasMoney(Ticket $ticket): bool
    {
        return in_array($ticket->status, ['active', 'checked_in'], true)
            || $ticket->payments()->where('status', 'approved')->exists()
            || TicketFee::where('ticket_id', $ticket->id)->exists()
            || SettlementItem::where('ticket_id', $ticket->id)->exists();
    }

    /** For Filament delete actions: say why and stop. */
    public static function guardAction($action, Model ...$records): void
    {
        foreach ($records as $record) {
            if ($reason = static::blockingDelete($record)) {
                \Filament\Notifications\Notification::make()->title($reason)->danger()->send();
                $action->cancel();
            }
        }
    }
}
