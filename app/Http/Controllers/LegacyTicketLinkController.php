<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Notifications\Payments\AttendeeTicketNotice;
use Illuminate\Support\Facades\{Cache, Notification};
use Illuminate\Support\Str;

/**
 * Old links built from a ticket's number (/register/…/payment/12). The
 * number isn't a secret, so these pages show nothing about the ticket: they
 * offer to send its private link to the email it was registered with.
 */
class LegacyTicketLinkController extends Controller
{
    public function show(string $orgSlug, string $eventSlug, int $ticketId)
    {
        $ticket = Ticket::with(['client', 'event.organization'])->find($ticketId);
        $matches = $ticket && $ticket->event?->slug === $eventSlug && $ticket->event->organization?->slug === $orgSlug;

        return view('tickets.link-moved', [
            'ticket'      => $matches ? $ticket : null,
            'maskedEmail' => $matches && $ticket->client?->email ? self::mask($ticket->client->email) : null,
            'organizer'   => $matches ? $ticket->event->organization->name : null,
        ]);
    }

    public function send(Ticket $ticket)
    {
        $ticket->loadMissing(['client', 'event.organization']);
        $email = $ticket->client?->email;

        // Once an hour per ticket, whoever asks: it only ever goes to the owner.
        if ($email && Cache::add("ticket-link-sent:{$ticket->id}", true, now()->addHour())) {
            Notification::route('mail', $email)->notify(new AttendeeTicketNotice(
                subject: "Your ticket link: {$ticket->event->name}",
                greeting: "Hi {$ticket->client->full_name},",
                lines: ["Here's the private link to your {$ticket->event->name} ticket. Use it to pay, send proof of payment, or show your ticket at the door. Keep it to yourself: anyone with it can see your ticket."],
                actionText: 'Open my ticket',
                actionUrl: route('ticket.download', $ticket->qr_code),
            ));
        }

        return back()->with('status', $email
            ? 'If that ticket has an email address, its link is on its way. Check your inbox (and spam).'
            : 'That ticket has no email address. Please ask the organizer to send you your ticket.');
    }

    private static function mask(string $email): string
    {
        [$name, $domain] = explode('@', $email, 2) + [1 => ''];

        return Str::substr($name, 0, 1) . str_repeat('•', max(2, Str::length($name) - 1)) . '@' . $domain;
    }
}
