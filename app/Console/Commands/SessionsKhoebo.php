<?php

namespace App\Console\Commands;

use App\Models\PaymentSession;
use App\Services\Khoebo\KhoeboException;
use App\Services\Sessions\SessionsBooks;
use Illuminate\Console\Command;

/** Sessions payments not yet in the Sessions books (Khoebo was down, say): booked now. Hourly. */
class SessionsKhoebo extends Command
{
    protected $signature = 'sessions:khoebo {--payment= : One payment session id}';

    protected $description = 'Put paid Sessions plans and sessions into the Sessions books in Khoebo';

    public function handle(SessionsBooks $books): int
    {
        if (!$books->configured()) {
            $this->warn('The Sessions books are not set up: SESSIONS_KHOEBO_URL and SESSIONS_KHOEBO_TOKEN.');

            return self::SUCCESS;
        }

        $payments = PaymentSession::where('payable_type', 'session_package')->where('status', 'completed')->whereNull('khoebo_payment_id')
            ->when($this->option('payment'), fn ($q, $id) => $q->whereKey($id), fn ($q) => $q->where('updated_at', '>=', config('services.khoebo.sessions.from')))
            ->orderBy('id')->get();

        $done = 0;
        foreach ($payments as $payment) {
            try {
                $books->recordSale($payment);
                $this->line("#{$payment->id}: " . ($payment->fresh()->khoebo_reference ?? 'booked'));
                $done++;
            } catch (KhoeboException $e) {
                $this->error("#{$payment->id}: {$e->getMessage()}");
            }
        }
        $this->info("{$done} of {$payments->count()} booked.");

        return self::SUCCESS;
    }
}
