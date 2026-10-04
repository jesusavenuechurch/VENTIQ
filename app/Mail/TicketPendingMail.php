<?php

namespace App\Mail;

use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TicketPendingMail extends Mailable
{
    use Queueable, SerializesModels;

    public Ticket $ticket;
    public $paymentMethods;

    public function __construct(Ticket $ticket)
    {
        $this->ticket = $ticket;
        // The accounts this event offers, the same list as the payment page.
        $this->paymentMethods = app(\App\Services\Payments\PaymentAccountService::class)
            ->directAccountsForEvent($ticket->event);
    }

    public function build()
    {
        $subject = "Registration Received - {$this->ticket->event->name}";

        return $this->view('emails.tickets.pending')
            ->subject($subject)
            ->with([
                'ticket' => $this->ticket,
                'client' => $this->ticket->client,
                'event' => $this->ticket->event,
                'tier' => $this->ticket->tier,
                'organization' => $this->ticket->event->organization,
                'paymentMethods' => $this->paymentMethods,
                'ticketUrl'      => route('ticket.download', $this->ticket->qr_code),
                'paymentUrl'     => route('registration.payment', [
                    'orgSlug'   => $this->ticket->event->organization->slug,
                    'eventSlug' => $this->ticket->event->slug,
                    'ticketId'  => $this->ticket->id,
                ]),
            ]);
    }
}
