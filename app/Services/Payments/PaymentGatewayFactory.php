<?php 
// app/Services/Payments/PaymentGatewayFactory.php
namespace App\Services\Payments;

use App\Services\Payments\Contracts\PaymentDriver;
use App\Services\Payments\Drivers\{PayLesothoEcocashDriver, PayLesothoMpesaDriver};
use InvalidArgumentException;

class PaymentGatewayFactory
{
    public function make(string $method): PaymentDriver
    {
        return match ($method) {
            'ecocash' => app(PayLesothoEcocashDriver::class),
            'mpesa'   => app(PayLesothoMpesaDriver::class),
            default   => throw new InvalidArgumentException("No PayLesotho driver registered for [{$method}]"),
        };
    }
}