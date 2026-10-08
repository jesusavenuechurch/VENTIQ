<?php
// app/Services/Payments/DTOs/PaymentInitiationData.php
namespace App\Services\Payments\DTOs;

final class PaymentInitiationData
{
    public function __construct(
        public readonly float $amount,
        public readonly string $mobileNumber,
        public readonly string $clientReference,
    ) {}
}