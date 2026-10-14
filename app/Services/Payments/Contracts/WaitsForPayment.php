<?php
// app/Services/Payments/Contracts/WaitsForPayment.php
namespace App\Services\Payments\Contracts;

use App\Services\Payments\DTOs\{PaymentInitiationData, PaymentStatusResult};

/**
 * A driver whose request stays open until the payer approves or declines
 * on their phone, and answers with how it ended. No callback needed.
 */
interface WaitsForPayment
{
    /** True when this driver should be used this way right now (config). */
    public function waitsForPayment(): bool;

    /**
     * Send the push and wait for the outcome. 'pending' means no clear
     * answer came back (timeout, an unknown reply): the money may or may
     * not have moved, so it's left for a person to check.
     */
    public function charge(PaymentInitiationData $data): PaymentStatusResult;
}
