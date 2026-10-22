<?php

namespace App\Support;

/** VENTIQ's bank account, as a customer needs it to pay by transfer (config ventiq.bank). */
class BankDetails
{
    /** @return array<int, array{label: string, value: string}> */
    public static function lines(?string $reference = null): array
    {
        $b = config('ventiq.bank');

        return collect([
            ['Bank', $b['bank'] ?? null],
            ['Account name', $b['account_name'] ?? null],
            ['Account number', $b['account_number'] ?? null],
            ['Account type', $b['account_type'] ?? null],
            ['Branch', trim(($b['branch'] ?? '') . (!empty($b['branch_code']) ? ", code {$b['branch_code']}" : ''), ', ') ?: null],
            ['SWIFT code', $b['swift'] ?? null],
            ['Reference', $reference],
        ])->filter(fn ($l) => filled($l[1]))->map(fn ($l) => ['label' => $l[0], 'value' => (string) $l[1]])->values()->all();
    }

    public static function configured(): bool
    {
        return filled(config('ventiq.bank.account_number'));
    }

    /** The reference a Sessions purchase is paid with: the organization and what it's buying, e.g. VQ0012-TEAM. */
    public static function reference(int $organizationId, string $code): string
    {
        return 'VQ' . str_pad((string) $organizationId, 4, '0', STR_PAD_LEFT) . '-' . $code;
    }
}
