<?php

namespace App\Notifications\Payments;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A short email to an attendee about their ticket's payment: rejected,
 * reminder, window expired. Built from lines rather than a template per
 * case, since each is two or three sentences and a button.
 */
class AttendeeTicketNotice extends Notification
{
    use Queueable;

    /** @param string[] $lines */
    public function __construct(
        public string $subject,
        public string $greeting,
        public array $lines,
        public string $actionText,
        public string $actionUrl,
    ) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $mail = (new MailMessage)->subject($this->subject)->greeting($this->greeting);

        foreach ($this->lines as $line) {
            $mail->line($line);
        }

        return $mail->action($this->actionText, $this->actionUrl);
    }
}
