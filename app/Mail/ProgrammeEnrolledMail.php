<?php

namespace App\Mail;

use App\Models\ProgrammeEnrolment;
use App\Models\Session;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** "You're enrolled": the programme's days, and what it takes to get the certificate. */
class ProgrammeEnrolledMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public ProgrammeEnrolment $enrolment) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "You're enrolled: {$this->enrolment->programme->name}");
    }

    public function content(): Content
    {
        $programme = $this->enrolment->programme;
        $days = Session::where('event_id', $programme->id)->orderBy('date')->orderBy('start_time')->get();
        $required = app(\App\Services\Sessions\ProgrammeProgress::class)->required($programme, $days);

        return new Content(markdown: 'emails.programme-enrolled', with: [
            'name'         => $this->enrolment->client->full_name,
            'programme'    => $programme->name,
            'organization' => $programme->organization?->name,
            'venue'        => $programme->venue,
            'days'         => $days->map(fn ($d) => $d->date->format('l j F') . ($d->start_time ? ', ' . substr($d->start_time, 0, 5) : ''))->all(),
            'rule'         => $programme->certificates_enabled && $required
                ? ($required >= $days->count() ? 'every day' : "at least {$required} of the {$days->count()} days")
                : null,
        ]);
    }
}
