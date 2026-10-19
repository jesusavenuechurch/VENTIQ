<?php

namespace App\Services\MobileMoney;

use App\Models\{MobileMoneyMessage, PaymentSession, Ticket};
use App\Services\Payments\PaymentCompletion;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\{DB, Log};
use InvalidArgumentException;

/**
 * The SMS VENTIQ's merchant SIM receives, forwarded by the phone holding
 * it, turned into confirmed payments.
 *
 * Every payment into the merchant wallet sends an SMS, whichever way it was
 * paid, so each one is matched in this order:
 *   1. an attendee who said "I've paid" by hand;
 *   2. an online payment (a PIN prompt VENTIQ sent): the SMS confirms it,
 *      and completes it if PayLesotho never answered;
 *   3. the ticket whose entry code the payer typed as the reference;
 *   4. the one waiting ticket registered to the paying number, for that amount.
 * The ticket is then activated as an online sale: VENTIQ holds the money and
 * pays the organizer out, fees off. Anything else waits for a person.
 *
 * A payment is acted on only when its new balance follows from the last
 * one (and any money out since): a made-up SMS can't know the wallet's
 * balance. One that doesn't is held until a person says it's real.
 */
class MobileMoneyInbox
{
    // How long around the SMS a PIN prompt counts as the same payment.
    private const PUSH_BEFORE_MINUTES = 30;
    private const PUSH_AFTER_MINUTES  = 5;

    public function __construct(private PaymentCompletion $completion) {}

    /** The provider a sender is, or null when it isn't one VENTIQ reads. */
    public static function providerFor(string $sender): ?string
    {
        $sender = strtoupper(trim($sender));
        foreach ((array) config('services.mobile_money.senders', []) as $provider => $senders) {
            if (in_array($sender, array_map('strtoupper', (array) $senders), true)) {
                return $provider;
            }
        }

        return null;
    }

    /**
     * Record a forwarded SMS and act on it. A message seen before (the
     * forwarder retrying) is returned as it is.
     */
    public function receive(string $provider, string $sender, string $body, Carbon $receivedAt, ?string $device = null, array $raw = []): MobileMoneyMessage
    {
        $parsed = EcoCashSms::parse($body);

        if ($parsed['transaction_id']) {
            $existing = MobileMoneyMessage::where('provider', $provider)->where('transaction_id', $parsed['transaction_id'])->first();
            if ($existing) {
                return $existing;
            }
        }

        try {
            $message = $this->store($parsed, $provider, $sender, $body, $receivedAt, $device, $raw);
        } catch (\Illuminate\Database\UniqueConstraintViolationException) {
            // The same SMS forwarded twice at once.
            return MobileMoneyMessage::where('provider', $provider)->where('transaction_id', $parsed['transaction_id'])->firstOrFail();
        }

        if ($message->kind === MobileMoneyMessage::KIND_IN) {
            $this->process($message);
        }

        if (in_array($message->kind, [MobileMoneyMessage::KIND_IN, MobileMoneyMessage::KIND_OUT], true)) {
            // A message that arrived late can put later ones back in the chain.
            $this->recheckHeld($message);
        }

        Log::info('Mobile money SMS', ['id' => $message->id, 'kind' => $message->kind, 'status' => $message->status, 'txn' => $message->transaction_id]);

        return $message->fresh();
    }

    private function store(array $parsed, string $provider, string $sender, string $body, Carbon $receivedAt, ?string $device, array $raw): MobileMoneyMessage
    {
        return MobileMoneyMessage::create($parsed + [
            'provider'    => $provider,
            'sender'      => $sender,
            'body'        => $body,
            'received_at' => $receivedAt,
            'device'      => $device,
            'raw'         => $raw,
            'status'      => match ($parsed['kind']) {
                MobileMoneyMessage::KIND_IN  => MobileMoneyMessage::UNMATCHED,
                MobileMoneyMessage::KIND_OUT => MobileMoneyMessage::OUT,
                default                      => MobileMoneyMessage::IGNORED,
            },
        ]);
    }

    /**
     * An attendee has just said they paid by hand: their SMS may already
     * be here, waiting. True when it was, and the ticket is now active.
     */
    public function claimed(PaymentSession $claim): bool
    {
        $byHand = $claim->callback_payload['by_hand'] ?? [];
        $reference = strtoupper(trim((string) ($byHand['reference'] ?? '')));
        $phone = self::localPhone($byHand['paid_from'] ?? null);

        if ($reference === '' && !$phone) {
            return false;
        }

        // The amount must be what's owed, however the payment was found.
        $message = MobileMoneyMessage::where('kind', MobileMoneyMessage::KIND_IN)
            ->where('provider', $claim->payment_method)
            ->where('status', MobileMoneyMessage::UNMATCHED)
            ->where('received_at', '>', now()->subDays(7))
            ->where('amount', $claim->amount)
            ->where(fn ($q) => $q
                ->when($reference !== '', fn ($q) => $q->orWhere('transaction_id', $reference))
                ->when($phone, fn ($q) => $q->orWhere('payer_phone', $phone)))
            ->oldest('received_at')
            ->first();

        if (!$message) {
            return false;
        }

        return $this->apply($message, $claim, MobileMoneyMessage::MATCHED, 'Matched to what the attendee said they paid.');
    }

    /** A person matches a payment to a ticket by its entry code. */
    public function matchToTicket(MobileMoneyMessage $message, string $entryCode, ?int $by = null): Ticket
    {
        $this->assertToCheck($message);

        // "X82L" is enough: the VQ- is the same on every code.
        $ticket = Ticket::byVoucherCode(self::entryCode($entryCode) ?? $entryCode)->first()
            ?? throw new InvalidArgumentException("No ticket has the entry code {$entryCode}.");
        if (!$this->payable($ticket)) {
            throw new InvalidArgumentException("{$ticket->voucher_code} is already paid, or can no longer be paid for.");
        }

        $message->update(['balance_ok' => true, 'decided_by' => $by, 'decided_at' => now()]);
        $this->apply($message, $this->byHandSession($ticket, $message), MobileMoneyMessage::MATCHED, 'Matched by hand.');

        return $ticket->fresh();
    }

    /** A person says a held payment is real: it's matched as if it had followed. */
    public function accept(MobileMoneyMessage $message, ?int $by = null): MobileMoneyMessage
    {
        $this->assertToCheck($message);

        $message->update(['balance_ok' => true, 'decided_by' => $by, 'decided_at' => now(), 'status' => MobileMoneyMessage::UNMATCHED, 'note' => null]);
        $this->process($message->fresh(), checkBalance: false);
        $this->recheckHeld($message);

        return $message->fresh();
    }

    /** A person says this money isn't for a ticket (or isn't real). */
    public function dismiss(MobileMoneyMessage $message, ?int $by = null, ?string $note = null): void
    {
        $this->assertToCheck($message);

        $message->update([
            'status' => MobileMoneyMessage::DISMISSED, 'note' => $note ?: 'Not for a ticket.',
            'decided_by' => $by, 'decided_at' => now(),
        ]);
    }

    /** When the phone holding the SIM last forwarded anything. */
    public static function lastHeard(): ?Carbon
    {
        $at = MobileMoneyMessage::max('created_at');

        return $at ? Carbon::parse($at) : null;
    }

    private function process(MobileMoneyMessage $message, bool $checkBalance = true): void
    {
        if ($checkBalance) {
            $follows = $this->balanceFollows($message);
            $message->update(['balance_ok' => $follows]);

            if (!$follows) {
                [, $ticket, $session] = $this->find($message) + [null, null, null];
                $message->update([
                    'status'             => MobileMoneyMessage::HELD,
                    'note'               => "The new balance doesn't follow from the last message. Check it on the EcoCash statement.",
                    'ticket_id'          => $ticket?->id ?? ($session?->payable_type === 'ticket' ? $session->payable_id : null),
                    'payment_session_id' => $session?->id,
                ]);

                return;
            }
        }

        [$how, $ticket, $session, $note] = $this->find($message) + [null, null, null, null];

        if ($how === 'session') {
            $this->apply($message, $session, $session->purchase_meta ? MobileMoneyMessage::MATCHED : MobileMoneyMessage::ONLINE, $note);
        } elseif ($how === 'ticket') {
            $this->apply($message, $this->byHandSession($ticket, $message), MobileMoneyMessage::MATCHED, $note);
        } else {
            $message->update(['status' => $how === 'held' ? MobileMoneyMessage::HELD : MobileMoneyMessage::UNMATCHED, 'note' => $note, 'ticket_id' => $ticket?->id]);
        }
    }

    /**
     * What this payment is for.
     *
     * @return array{0: ?string, 1: ?Ticket, 2: ?PaymentSession, 3: ?string}
     *         ['session'|'ticket'|'held'|null, ticket, session, note]
     */
    private function find(MobileMoneyMessage $message): array
    {
        $amount = (float) $message->amount;
        // Never empty: '%' alone would match every number.
        $phone = $message->payer_phone ?: 'no-number';
        $linked = MobileMoneyMessage::whereNotNull('payment_session_id')->where('id', '!=', $message->id)->select('payment_session_id');
        // Online payments include Sessions plans paid by PIN prompt, not only tickets.
        $sessions = PaymentSession::where('payment_method', $message->provider)->whereNotIn('id', $linked);

        // 1. A session already holding this transaction id.
        $session = (clone $sessions)->where('transaction_id', $message->transaction_id)->latest('id')->first();
        if ($session) {
            return abs((float) $session->amount - $amount) < 0.01
                ? ['session', null, $session, 'The SMS for this payment.']
                : ['held', null, $session, 'Same transaction as a payment of M' . number_format((float) $session->amount, 2) . ', but a different amount.'];
        }

        // 2. An attendee who said they paid by hand, from this number or quoting this transaction.
        // (Or one a person already marked received from the statement: the SMS only confirms it.)
        $session = (clone $sessions)->where('payable_type', 'ticket')->whereNotNull('purchase_meta')
            ->where(fn ($q) => $q->where('status', 'pending')
                ->orWhere(fn ($q) => $q->where('status', 'completed')->where('updated_at', '>', $message->received_at->copy()->subDays(3))))
            ->where('amount', $amount)
            ->where(fn ($q) => $q->where('transaction_id', $message->transaction_id)
                ->orWhere('callback_payload->by_hand->paid_from', 'like', '%' . $phone))
            ->orderByRaw("FIELD(status, 'pending', 'completed')")
            ->latest('id')->first();
        if ($session) {
            return ['session', null, $session, 'Matched to what the attendee said they paid.'];
        }

        // 3. A PIN prompt to this number, for this amount, around this time.
        $session = (clone $sessions)->whereNull('purchase_meta')
            ->where('amount', $amount)
            ->where('callback_payload->push_to', 'like', '%' . $phone)
            ->whereBetween('created_at', [
                $message->received_at->copy()->subMinutes(self::PUSH_BEFORE_MINUTES),
                $message->received_at->copy()->addMinutes(self::PUSH_AFTER_MINUTES),
            ])
            ->orderByRaw("FIELD(status, 'completed', 'pending', 'failed')")
            ->latest('id')->first();
        if ($session) {
            return ['session', null, $session, 'The SMS for a payment made online.'];
        }

        // 4. The entry code the payer typed as the reference.
        if ($code = self::entryCode($message->reference)) {
            $ticket = Ticket::with(['client', 'event', 'tier'])->where('voucher_code', $code)->first();
            if ($ticket && $this->payable($ticket)) {
                $owed = $this->owed($ticket);

                return abs($owed - $amount) < 0.01
                    ? ['ticket', $ticket, null, "Matched by the reference {$message->reference}."]
                    : ['held', $ticket, null, "The reference is {$code}, which owes M" . number_format($owed, 2) . ', not M' . number_format($amount, 2) . '.'];
            }
        }

        // 5. The one waiting ticket registered to this number, owing this amount.
        if ($message->payer_phone) {
            $candidates = Ticket::with(['client', 'event', 'tier'])
                ->whereIn('status', ['pending', 'active'])->where('payment_status', '!=', 'completed')
                ->where('created_at', '>', now()->subDays(60))
                ->whereHas('client', fn ($q) => $q->whereRaw("RIGHT(REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '+', ''), 8) = ?", [$message->payer_phone]))
                ->get()
                ->filter(fn (Ticket $t) => abs($this->owed($t) - $amount) < 0.01);

            if ($candidates->count() === 1) {
                return ['ticket', $candidates->first(), null, 'Matched by the paying number and amount.'];
            }
            if ($candidates->count() > 1) {
                return ['held', null, null, "{$candidates->count()} waiting tickets from this number owe this amount. Match it to the right one."];
            }
        }

        return [null, null, null, null];
    }

    /** Confirm the payment on the session and activate its ticket. */
    private function apply(MobileMoneyMessage $message, PaymentSession $session, string $status, ?string $note): bool
    {
        return DB::transaction(function () use ($message, $session, $status, $note) {
            $locked = MobileMoneyMessage::whereKey($message->id)->lockForUpdate()->first();
            if (!$locked || !in_array($locked->status, [MobileMoneyMessage::UNMATCHED, MobileMoneyMessage::HELD], true)) {
                return false;
            }

            $session = PaymentSession::whereKey($session->id)->lockForUpdate()->first();
            $payload = array_merge($session->callback_payload ?? [], ['sms' => ['message_id' => $locked->id, 'at' => now()->toIso8601String()]]);

            $wasCompleted = $session->isCompleted();
            $session->update([
                'status'           => 'completed',
                'transaction_id'   => $locked->transaction_id,
                'callback_payload' => $payload,
            ]);

            $locked->update([
                'status'             => $status,
                'note'               => $note,
                'payment_session_id' => $session->id,
                'ticket_id'          => $session->payable_type === 'ticket' ? $session->payable_id : null,
            ]);

            // Already completed by PayLesotho's answer: the SMS only confirms it.
            if (!$wasCompleted) {
                $this->completion->completed($session->fresh());
            }

            return true;
        });
    }

    /** A by-hand payment for the ticket, as if the attendee had said they paid. */
    private function byHandSession(Ticket $ticket, MobileMoneyMessage $message): PaymentSession
    {
        return PaymentSession::create([
            'payable_type'     => 'ticket',
            'payable_id'       => $ticket->id,
            'gateway'          => 'paylesotho',
            'status'           => 'pending',
            'payment_method'   => $message->provider,
            'amount'           => $message->amount,
            'organization_id'  => $ticket->event->organization_id,
            'client_reference' => 'SMS' . $ticket->id . 'T' . random_int(1_000_000_000, 9_999_999_999),
            'purchase_meta'    => ['by_hand' => true],
            'callback_payload' => ['by_hand' => [
                'reference' => $message->transaction_id,
                'paid_from' => $message->payer_phone,
                'at'        => now()->toIso8601String(),
            ]],
        ]);
    }

    /**
     * Does this payment's new balance follow from the messages before it?
     * The first payment ever seen starts the chain.
     */
    private function balanceFollows(MobileMoneyMessage $message): bool
    {
        if ($message->balance_after === null) {
            return false;
        }

        $earlier = fn ($q) => $q->where('provider', $message->provider)->where('id', '!=', $message->id)
            ->where(fn ($q) => $q->where('received_at', '<', $message->received_at)
                ->orWhere(fn ($q) => $q->where('received_at', $message->received_at)->where('id', '<', $message->id)));

        $last = MobileMoneyMessage::where('kind', MobileMoneyMessage::KIND_IN)->whereNotNull('balance_after')
            ->where(fn ($q) => $q->whereNull('balance_ok')->orWhere('balance_ok', true))
            ->where($earlier)
            ->orderByDesc('received_at')->orderByDesc('id')->first();

        if (!$last) {
            return true;
        }

        $out = (float) MobileMoneyMessage::where('kind', MobileMoneyMessage::KIND_OUT)->where($earlier)
            ->where('received_at', '>=', $last->received_at)->sum('amount');

        return abs((float) $last->balance_after - $out + (float) $message->amount - (float) $message->balance_after) < 0.01;
    }

    /** Held payments after this one whose balance now follows: matched in order. */
    private function recheckHeld(MobileMoneyMessage $after): void
    {
        MobileMoneyMessage::where('provider', $after->provider)->where('kind', MobileMoneyMessage::KIND_IN)
            ->where('status', MobileMoneyMessage::HELD)->where('balance_ok', false)
            ->where('received_at', '>=', $after->received_at)->where('id', '!=', $after->id)
            ->orderBy('received_at')->orderBy('id')->get()
            ->each(function (MobileMoneyMessage $held) {
                if ($this->balanceFollows($held)) {
                    $held->update(['status' => MobileMoneyMessage::UNMATCHED, 'note' => null, 'ticket_id' => null, 'payment_session_id' => null]);
                    $this->process($held->fresh());
                }
            });
    }

    private function assertToCheck(MobileMoneyMessage $message): void
    {
        if ($message->kind !== MobileMoneyMessage::KIND_IN || !in_array($message->status, [MobileMoneyMessage::UNMATCHED, MobileMoneyMessage::HELD], true)) {
            throw new InvalidArgumentException('This payment has already been dealt with.');
        }
    }

    private function payable(Ticket $ticket): bool
    {
        return in_array($ticket->status, ['pending', 'active'], true)
            && $ticket->payment_status !== 'completed'
            && $this->owed($ticket) > 0;
    }

    private function owed(Ticket $ticket): float
    {
        return round(max(0, (float) $ticket->amount - (float) $ticket->amount_paid), 2);
    }

    /** "X82L", "vq-x82l", "VQX82L" → "VQ-X82L"; anything else → null. */
    public static function entryCode(?string $reference): ?string
    {
        $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $reference));
        if (strlen($code) === 6 && str_starts_with($code, 'VQ')) {
            $code = substr($code, 2);
        }

        return preg_match('/^[2-9A-HJ-NP-Z]{4}$/', $code) ? "VQ-{$code}" : null;
    }

    /** The 8 local digits of a Lesotho number, or null. */
    public static function localPhone(?string $number): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $number);

        return strlen($digits) >= 8 ? substr($digits, -8) : null;
    }
}
