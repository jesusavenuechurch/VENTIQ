<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Someone's action points that are due soon, due today or overdue, in one email. */
class ActionPointsReminderMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param array<int, array{task: string, when: string, overdue: bool, from: string}> $points
     */
    public function __construct(public string $name, public string $organization, public array $points, public ?string $link) {}

    public function envelope(): Envelope
    {
        $overdue = collect($this->points)->where('overdue', true)->count();

        return new Envelope(subject: $overdue
            ? "{$overdue} of your action points " . ($overdue === 1 ? 'is' : 'are') . " overdue · {$this->organization}"
            : (count($this->points) === 1 ? 'An action point is due soon' : count($this->points) . ' action points are due soon') . " · {$this->organization}");
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.action-points-reminder');
    }
}
