<?php 
// app/Services/Payments/PaymentGatewayFactory.php
namespace App\Services\Payments;

use App\Services\Payments\Contracts\PaymentDriver;
use App\Services\Payments\Drivers\{PayLesothoEcocashDriver, PayLesothoMpesaDriver};
use InvalidArgumentException;

class PaymentGatewayFactory
{
    private const DRIVERS = ['ecocash', 'mpesa'];

    /**
     * Drivers attendees may be offered: PayLesotho switched on overall,
     * and each driver switched on in its own right.
     *
     * @return string[]
     */
    public static function enabledMethods(): array
    {
        if (!config('gateways.paylesotho.enabled')) {
            return [];
        }

        return array_values(array_filter(
            self::DRIVERS,
            fn ($method) => (bool) config("gateways.paylesotho.{$method}.enabled", false),
        ));
    }

    public function make(string $method): PaymentDriver
    {
        return match ($method) {
            'ecocash' => app(PayLesothoEcocashDriver::class),
            'mpesa'   => app(PayLesothoMpesaDriver::class),
            default   => throw new InvalidArgumentException("No PayLesotho driver registered for [{$method}]"),
        };
    }
}