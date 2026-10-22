<?php

namespace App\Services\Sessions;

use App\Mail\BankTransferClaimedMail;
use App\Mail\BankTransferResultMail;
use App\Models\Organization;
use App\Models\PaymentSession;
use App\Models\User;
use App\Services\Payments\PaymentCompletion;
use App\Support\BankDetails;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * A Sessions plan or sessions paid by bank transfer. The organization
 * says it has paid (with the reference), VENTIQ is told, and once a super
 * admin sees the money in the account and confirms it, the plan or
 * sessions are applied, as a mobile money payment would be.
 */
class BankTransfers
{
    public const GATEWAY = 'bank';
    public const METHOD = 'bank_transfer';

    public function __construct(private PaymentCompletion $completion) {}

    /** @param array{amount: float, label: string, meta: array, code: string} $purchase */
    public function claim(Organization $organization, User $by, array $purchase, string $paidOn, ?string $note): PaymentSession
    {
        $reference = BankDetails::reference($organization->id, $purchase['code']);

        // The same purchase said twice: the one claim, brought up to date.
        $session = DB::transaction(function () use ($organization, $by, $purchase, $paidOn, $note, $reference) {
            $session = PaymentSession::where('organization_id', $organization->id)->where('gateway', self::GATEWAY)
                ->where('client_reference', $reference)->where('status', 'pending')->lockForUpdate()->first()
                ?? new PaymentSession(['gateway' => self::GATEWAY, 'client_reference' => $reference, 'status' => 'pending']);

            $session->fill([
                'payable_type'    => 'session_package',
                'payable_id'      => $organization->id,
                'payment_method'  => self::METHOD,
                'amount'          => $purchase['amount'],
                'organization_id' => $organization->id,
                'initiated_by'    => $by->id,
                'purchase_meta'   => $purchase['meta'] + ['label' => $purchase['label']],
                'callback_payload' => ['paid_on' => $paidOn, 'note' => $note, 'claimed_at' => now()->toIso8601String(), 'claimed_by' => $by->name],
            ])->save();

            return $session;
        });

        Mail::to(config('ventiq.billing.email'))->queue(new BankTransferClaimedMail($session));
        Log::info('Sessions bank transfer claimed', ['session' => $session->id, 'reference' => $reference, 'amount' => $purchase['amount']]);

        return $session;
    }

    /** The money is in VENTIQ's account: apply what was bought. */
    public function confirm(PaymentSession $session, User $by, ?string $bankReference): void
    {
        DB::transaction(function () use ($session, $by, $bankReference) {
            $locked = PaymentSession::whereKey($session->id)->lockForUpdate()->first();
            if ($locked->status !== 'pending' || $locked->gateway !== self::GATEWAY) {
                throw new \InvalidArgumentException('This transfer has already been dealt with.');
            }
            $locked->update([
                'status'           => 'completed',
                'transaction_id'   => $bankReference ?: $locked->client_reference,
                'callback_payload' => array_merge($locked->callback_payload ?? [], ['confirmed_at' => now()->toIso8601String(), 'confirmed_by' => $by->name]),
            ]);
            $this->completion->completed($locked);
        });

        $this->tell($session->fresh(), true, null);
    }

    /** Not found in the account: nothing applied, and the organization is told why. */
    public function reject(PaymentSession $session, User $by, string $reason): void
    {
        abort_unless($session->status === 'pending' && $session->gateway === self::GATEWAY, 422, 'This transfer has already been dealt with.');
        $session->update([
            'status'           => 'failed',
            'callback_payload' => array_merge($session->callback_payload ?? [], ['rejected_at' => now()->toIso8601String(), 'rejected_by' => $by->name, 'reason' => $reason]),
        ]);

        $this->tell($session->fresh(), false, $reason);
    }

    private function tell(PaymentSession $session, bool $confirmed, ?string $reason): void
    {
        $organization = Organization::find($session->organization_id);
        $admins = $organization?->members()->role('org_admin')->pluck('email') ?? collect();
        foreach ($admins as $email) {
            Mail::to($email)->queue(new BankTransferResultMail($session, $confirmed, $reason));
        }
    }
}
