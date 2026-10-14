<?php

namespace App\Notifications\Accounts;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;

/** A week's notice before an account that was never used is removed. */
class UnusedAccountWarning extends Notification
{
    use Queueable;

    public function __construct(public Carbon $removeOn) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function keepUrl($notifiable): string
    {
        return URL::temporarySignedRoute('account.keep', $this->removeOn->copy()->addDays(7), ['user' => $notifiable->id]);
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your VENTIQ account will be removed on ' . $this->removeOn->format('j F'))
            ->greeting('Hi ' . (\Illuminate\Support\Str::before((string) $notifiable->name, ' ') ?: 'there'))
            ->line('You opened a VENTIQ account but haven\'t created anything on it, and it hasn\'t been used for about two months.')
            ->line('So we can keep VENTIQ tidy, we\'ll remove the account on ' . $this->removeOn->format('j F Y') . '.')
            ->action('Keep my account', $this->keepUrl($notifiable))
            ->line('Signing in also keeps it. If you no longer need it, there\'s nothing to do.');
    }
}
