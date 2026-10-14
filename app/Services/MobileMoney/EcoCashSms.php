<?php

namespace App\Services\MobileMoney;

use App\Models\MobileMoneyMessage;

/**
 * Reads the SMS EcoCash (sender 199) sends VENTIQ's merchant SIM.
 *
 * Money in:
 *   You have received M4000.0 from NAME SURNAME-63000000 for Merchant
 *   Payment. Approval Code: MP261008.1608.A38818. New wallet balance: M4003.0.
 * ("for ..." is the reference the payer typed: "0" when they typed nothing.)
 *
 * Money out (a withdrawal, which carries no balance):
 *   Stock Reimbursement transaction of M4000 is successful to IND01 with
 *   Txn ID: OW261009.1107.A49445
 *
 * Anything else is kept as 'other' and left alone.
 */
class EcoCashSms
{
    private const MONEY = '([0-9][0-9,]*(?:\.[0-9]+)?)';

    /**
     * @return array{kind: string, amount: ?float, payer_name: ?string, payer_phone: ?string,
     *               reference: ?string, transaction_id: ?string, balance_after: ?float}
     */
    public static function parse(string $body): array
    {
        $text = preg_replace('/\s+/', ' ', trim($body));

        if (preg_match('/You have received M' . self::MONEY . ' from (.+?)-\+?(\d{8,11}) for (.*?)\. ?Approval Code: ?([A-Z0-9.]+?)\.? New wallet balance: ?M' . self::MONEY . '/i', $text, $m)) {
            return [
                'kind'           => MobileMoneyMessage::KIND_IN,
                'amount'         => self::money($m[1]),
                'payer_name'     => trim($m[2]),
                'payer_phone'    => substr($m[3], -8),
                'reference'      => trim($m[4]),
                'transaction_id' => strtoupper($m[5]),
                'balance_after'  => self::money($m[6]),
            ];
        }

        if (preg_match('/transaction of M' . self::MONEY . ' is successful to (.+?) with Txn ID: ?([A-Z0-9.]+)/i', $text, $m)) {
            return [
                'kind'           => MobileMoneyMessage::KIND_OUT,
                'amount'         => self::money($m[1]),
                'payer_name'     => null,
                'payer_phone'    => null,
                'reference'      => trim($m[2]),
                'transaction_id' => strtoupper(rtrim($m[3], '.')),
                'balance_after'  => null,
            ];
        }

        return [
            'kind' => MobileMoneyMessage::KIND_OTHER, 'amount' => null, 'payer_name' => null, 'payer_phone' => null,
            'reference' => null, 'transaction_id' => null, 'balance_after' => null,
        ];
    }

    private static function money(string $value): float
    {
        return round((float) str_replace(',', '', rtrim($value, '.')), 2);
    }
}
