<?php

namespace App\Support\Organizer;

use App\Models\TicketPayment;

/** One submitted payment for the organizer to decide, as React props. */
class PaymentCard
{
    /** @return array<string, mixed> */
    public static function for(TicketPayment $payment, ?string $proofUrl): array
    {
        $ticket = $payment->ticket;
        $owed = max(0, (float) $ticket->amount - (float) $ticket->amount_paid);

        return [
            'id'          => $payment->id,
            'event'       => $ticket->event->name,
            'holderName'  => $ticket->holder_name,
            'avatarSeed'  => $ticket->client?->phone,
            'tier'        => $ticket->tier?->tier_name,
            'admissions'  => (int) ($ticket->admissions ?? 1),
            'number'      => $ticket->ticket_number,
            'amount'      => (float) $payment->amount,
            'owed'        => round($owed, 2),
            // A deposit: less than what's still owed.
            'partial'     => (float) $payment->amount + 0.009 < $owed,
            'paidTo'      => $payment->paymentAccount?->display_label
                ?? config('constants.payment_methods.' . $payment->payment_method . '.label', ucfirst((string) $payment->payment_method)),
            'paidToNumber' => $payment->paymentAccount?->account_number,
            'accountLabel' => $payment->paymentAccount?->label ?? 'account',
            'reference'   => $payment->payment_reference,
            'proofUrl'    => $proofUrl,
            'submitted'   => $payment->submitted_at?->diffForHumans(),
        ];
    }
}
