<?php

namespace App\Mail;

use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class TicketApprovedMail extends Mailable
{
    use Queueable, SerializesModels;

    public Ticket $ticket;
    public string $downloadLink;
    public string $accentColor;

    public function __construct(Ticket $ticket)
    {
        $this->ticket = $ticket;
        $this->downloadLink = route('ticket.download', $ticket->qr_code);

        // Same VIP/standard accent logic as the web ticket + avatar views
        $this->accentColor = str_contains(strtolower($ticket->tier->tier_name), 'vip')
            ? '#D4AF37'
            : '#10b981';
    }

    public function build()
    {
        // $subject = "🎉 Your Ticket is Ready - {$this->ticket->event->name}";
        $subject = "🎫 {$this->ticket->event->name} - Your Ticket Is Here";

        return $this->view('emails.tickets.approved')
            ->subject($subject)
            ->with([
                'ticket' => $this->ticket,
                'client' => $this->ticket->client,
                'event' => $this->ticket->event,
                'tier' => $this->ticket->tier,
                'organization' => $this->ticket->event->organization,
                'downloadLink' => $this->downloadLink,
                'accentColor' => $this->accentColor,
            ]);
    }

    public function attachments(): array
    {
        if ($this->ticket->avatar_path && Storage::disk('public')->exists($this->ticket->avatar_path)) {
            return [
                Attachment::fromStorageDisk('public', $this->ticket->avatar_path)
                    ->as("Ventiq-{$this->ticket->ticket_number}.pdf")
                    ->withMime('application/pdf'),
            ];
        }

        return [];
    }
}