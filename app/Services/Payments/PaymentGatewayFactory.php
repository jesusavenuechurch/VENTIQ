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
    /**
     * Every way to pay VENTIQ that organizers can offer. EcoCash and M-Pesa
     * are online (a PIN prompt) when VENTIQ's merchant account for them is
     * switched on; otherwise, and for a bank transfer, attendees pay VENTIQ
     * by hand with a reference and VENTIQ confirms it (manualMethods).
     */
    public const CATALOG = ['ecocash' => 'EcoCash', 'mpesa' => 'M-Pesa', 'bank' => 'Bank transfer'];

    /**
     * Methods without a PIN prompt that VENTIQ still takes: M-Pesa to
     * VENTIQ's number until its merchant account is live, and a transfer to
     * VENTIQ's bank account. EcoCash by hand has its own merchant code
     * (PaymentAccountService::merchantForEvent).
     *
     * @return string[]
     */
    public static function manualMethods(): array
    {
        $live = self::enabledMethods();

        return array_values(array_filter([
            !in_array('mpesa', $live, true) && filled(config('gateways.paylesotho.mpesa.merchant_number')) ? 'mpesa' : null,
            \App\Support\BankDetails::configured() ? 'bank' : null,
        ]));
    }

    /** What an organizer can choose from: online, or paid to VENTIQ by hand. @return string[] */
    public static function availableMethods(): array
    {
        $on = array_merge(self::enabledMethods(), self::manualMethods());

        return array_values(array_filter(array_keys(self::CATALOG), fn ($m) => in_array($m, $on, true)));
    }

    /** EcoCash numbers start with 6, M-Pesa numbers with 5: null when the number fits the method. */
    public static function numberMismatch(string $method, string $phone): ?string
    {
        $first = substr(preg_replace('/\D/', '', preg_replace('/^\+?266/', '', $phone)), 0, 1);

        return match (true) {
            $method === 'ecocash' && $first !== '6' => 'EcoCash numbers start with 6. For a number starting with 5, choose M-Pesa.',
            $method === 'mpesa' && $first !== '5' => 'M-Pesa numbers start with 5. For a number starting with 6, choose EcoCash.',
            default => null,
        };
    }

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