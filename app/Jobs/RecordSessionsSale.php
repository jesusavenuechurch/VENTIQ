<?php

namespace App\Jobs;

use App\Models\PaymentSession;
use App\Services\Sessions\SessionsBooks;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * A Sessions plan or sessions just paid: its invoice, posted and paid, in
 * the Sessions books (SessionsBooks::recordSale). When Khoebo can't be
 * reached it tries again later, and the hourly sessions:khoebo catches
 * any left behind.
 */
class RecordSessionsSale implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 5;
    public int $timeout = 120;

    public function __construct(public int $paymentId) {}

    public function uniqueId(): string
    {
        return (string) $this->paymentId;
    }

    /** @return int[] */
    public function backoff(): array
    {
        return [60, 300, 900, 3600];
    }

    public function handle(SessionsBooks $books): void
    {
        $payment = PaymentSession::find($this->paymentId);
        if ($payment && $books->configured()) {
            $books->recordSale($payment);
        }
    }
}
