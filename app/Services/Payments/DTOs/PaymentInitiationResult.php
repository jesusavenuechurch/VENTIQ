<?php 
// app/Services/Payments/DTOs/PaymentInitiationResult.php
namespace App\Services\Payments\DTOs;

final class PaymentInitiationResult
{
    public function __construct(
        public readonly bool $success,
        public readonly ?string $gatewayReference = null, // e.g. "REF697799"
        public readonly ?string $message = null,
        public readonly array $raw = [],
    ) {}
}