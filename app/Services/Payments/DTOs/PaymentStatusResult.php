<?php 
// app/Services/Payments/DTOs/PaymentStatusResult.php
namespace App\Services\Payments\DTOs;

final class PaymentStatusResult
{
    public function __construct(
        public readonly string $status, // 'completed' | 'failed' | 'pending'
        public readonly ?string $gatewayTransactionId = null,
        public readonly array $raw = [],
    ) {}
}