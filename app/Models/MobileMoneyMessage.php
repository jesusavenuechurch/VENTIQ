<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An SMS VENTIQ's merchant SIM received, forwarded by the phone holding it
 * (see App\Services\MobileMoney\MobileMoneyInbox).
 */
class MobileMoneyMessage extends Model
{
    public const KIND_IN    = 'in';      // money into the merchant wallet
    public const KIND_OUT   = 'out';     // money out (a withdrawal or payout)
    public const KIND_OTHER = 'other';   // anything else from the sender

    public const MATCHED   = 'matched';    // activated a ticket
    public const ONLINE    = 'online';     // the SMS for a payment made online
    public const UNMATCHED = 'unmatched';  // no ticket found: for a person
    public const HELD      = 'held';       // found, but not trusted on its own: for a person
    public const DISMISSED = 'dismissed';  // a person said it isn't for a ticket
    public const OUT       = 'out';
    public const IGNORED   = 'ignored';

    protected $fillable = [
        'provider', 'sender', 'body', 'received_at', 'kind', 'transaction_id', 'amount',
        'payer_name', 'payer_phone', 'reference', 'balance_after', 'balance_ok', 'status', 'note',
        'payment_session_id', 'ticket_id', 'decided_by', 'decided_at', 'device', 'raw',
    ];

    protected $casts = [
        'received_at'   => 'datetime',
        'decided_at'    => 'datetime',
        'amount'        => 'decimal:2',
        'balance_after' => 'decimal:2',
        'balance_ok'    => 'boolean',
        'raw'           => 'array',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function paymentSession(): BelongsTo
    {
        return $this->belongsTo(PaymentSession::class);
    }

    /** Payments in that a person still has to look at. */
    public function scopeToCheck($query)
    {
        return $query->where('kind', self::KIND_IN)->whereIn('status', [self::UNMATCHED, self::HELD]);
    }
}
