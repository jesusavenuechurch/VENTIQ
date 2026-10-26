<?php

namespace App\Services\Sessions;

use App\Mail\SessionInvoiceMail;
use App\Models\Organization;
use App\Models\Session;
use App\Models\SessionAgreement;
use App\Models\SessionInvoice;
use App\Models\User;
use App\Services\Khoebo\KhoeboPayments;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Corporate rates for Sessions. A super admin agrees a price per session
 * with an organization; it is never stopped by a quota. Each session is
 * priced when it starts (the month's first free ones at 0, the rest at the
 * rate), and on the 1st the month's sessions go on one invoice: raised in
 * the Sessions books in Khoebo (and emailed from there), or emailed by
 * VENTIQ with its own PDF when Khoebo can't be reached.
 */
class CorporateBilling
{
    public function __construct(private SessionsBooks $books) {}

    public function agreement(Organization $organization): ?SessionAgreement
    {
        return SessionAgreement::where('organization_id', $organization->id)
            ->whereNull('ended_at')->whereDate('starts_on', '<=', today())
            // A pilot past its last day has ended, whether or not the daily run has got to it.
            ->where(fn ($q) => $q->whereNull('ends_on')->orWhereDate('ends_on', '>=', today()))->first();
    }

    /** A session being held: its price under the organization's agreement, kept on it. */
    public function charge(Session $session, SessionAgreement $agreement): float
    {
        return DB::transaction(function () use ($session, $agreement) {
            // Locked, so two sessions starting together don't both take the last free place.
            SessionAgreement::whereKey($agreement->id)->lockForUpdate()->first();
            $month = ($session->counted_at ?? now())->copy();
            $already = Session::where('organization_id', $session->organization_id)
                ->whereKeyNot($session->id)->whereNotNull('charge')
                ->whereBetween('counted_at', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])
                ->count();
            $charge = $already < $agreement->free_per_month ? 0.0 : (float) $agreement->rate;
            $session->forceFill(['charge' => $charge])->save();

            return $charge;
        });
    }

    /** This month so far: sessions held, how many free, and what's owed. */
    public function monthToDate(Organization $organization): array
    {
        $sessions = $this->heldIn($organization, now());

        return [
            'held'  => $sessions->count(),
            'free'  => $sessions->where('charge', 0)->count(),
            'owed'  => round((float) $sessions->sum('charge'), 2),
        ];
    }

    /** @return Collection<int, Session> priced sessions held in that month, not yet invoiced */
    private function heldIn(Organization $organization, CarbonInterface $month): Collection
    {
        return Session::where('organization_id', $organization->id)->whereNotNull('charge')->whereNull('session_invoice_id')
            ->whereBetween('counted_at', [Carbon::instance($month)->startOfMonth(), Carbon::instance($month)->endOfMonth()])
            ->orderBy('counted_at')->get();
    }

    /** Organizations with priced sessions in that month still to invoice. */
    public function due(CarbonInterface $month): Collection
    {
        return Organization::whereIn('id', Session::where('charge', '>', 0)->whereNull('session_invoice_id')
            ->whereBetween('counted_at', [Carbon::instance($month)->startOfMonth(), Carbon::instance($month)->endOfMonth()])
            ->select('organization_id'))->get();
    }

    /**
     * The month's invoice: made in VENTIQ, raised in Khoebo, emailed. Null
     * when there's nothing to pay (every session was a free one; those are
     * then marked as settled). Safe to run again: a month is invoiced once.
     */
    public function invoice(Organization $organization, CarbonInterface $month): ?SessionInvoice
    {
        $start = Carbon::instance($month)->startOfMonth();
        $invoice = DB::transaction(function () use ($organization, $start) {
            if (SessionInvoice::where('organization_id', $organization->id)->whereDate('period_start', $start)->lockForUpdate()->exists()) {
                return null;
            }
            $sessions = $this->heldIn($organization, $start);
            $total = round((float) $sessions->sum('charge'), 2);
            if ($sessions->isEmpty() || $total <= 0) {
                return null;
            }
            $agreement = SessionAgreement::where('organization_id', $organization->id)->first();

            $invoice = SessionInvoice::create([
                'organization_id' => $organization->id,
                'number'          => $this->nextNumber(),
                'period_start'    => $start->toDateString(),
                'period_end'      => $start->copy()->endOfMonth()->toDateString(),
                'rate'            => $agreement?->rate ?? $sessions->max('charge'),
                'sessions_count'  => $sessions->count(),
                'free_count'      => $sessions->where('charge', 0)->count(),
                'total'           => $total,
                'due_date'        => today()->addDays((int) config('ventiq.billing.payment_days', 14))->toDateString(),
                'status'          => 'unpaid',
            ]);
            Session::whereIn('id', $sessions->pluck('id'))->update(['session_invoice_id' => $invoice->id]);

            return $invoice;
        });

        if (!$invoice) {
            return null;
        }

        // Khoebo emails it from its template; VENTIQ's own email when it couldn't be raised there.
        $this->raiseInKhoebo($invoice)
            ? $this->books->send((int) $invoice->fresh()->khoebo_invoice_id, $this->recipients($invoice->organization), "{$invoice->number}-send")
            : $this->email($invoice);
        if ($invoice->fresh()->khoebo_invoice_id) {
            $invoice->forceFill(['emailed_at' => now()])->save();
        }

        return $invoice->fresh();
    }

    /** The invoice in the Sessions books, one line per session billed, posted. A failure is kept on the invoice to try again. */
    public function raiseInKhoebo(SessionInvoice $invoice): bool
    {
        if ($invoice->khoebo_invoice_id || !$this->books->configured()) {
            return (bool) $invoice->khoebo_invoice_id;
        }

        try {
            $productId = $this->books->productId('session');
            $made = $this->books->invoice($invoice->organization, $invoice->sessions()->where('charge', '>', 0)->get()->map(fn (Session $s) => [
                'product_id'  => $productId,
                'quantity'    => '1',
                'unit_price'  => number_format((float) $s->charge, 2, '.', ''),
                'description' => "{$s->resolved_title}, " . $s->counted_at->format('j M Y'),
            ])->values()->all(), $invoice->number, $invoice->created_at->toDateString());
            $invoice->update(['khoebo_invoice_id' => $made['id'], 'khoebo_reference' => $made['reference'] ?? "KHOEBO-{$made['id']}", 'khoebo_error' => null]);

            return true;
        } catch (\Throwable $e) {
            Log::warning('Sessions invoice not raised in Khoebo', ['invoice' => $invoice->number, 'error' => $e->getMessage()]);
            $invoice->update(['khoebo_error' => $e->getMessage()]);

            return false;
        }
    }

    /** To the billing email and the organization's admins, with the PDF. */
    public function email(SessionInvoice $invoice): int
    {
        $to = $this->recipients($invoice->organization);
        foreach ($to as $address) {
            Mail::to($address)->queue(new SessionInvoiceMail($invoice));
        }
        if ($to !== []) {
            $invoice->update(['emailed_at' => now()]);
        }

        return count($to);
    }

    /** @return string[] */
    public function recipients(Organization $organization): array
    {
        return self::recipientsOf($organization);
    }

    /** @return string[] the billing email, then the organization's Admins */
    public static function recipientsOf(Organization $organization): array
    {
        $billing = SessionAgreement::where('organization_id', $organization->id)->value('billing_email');
        $admins = $organization->members()->role('org_admin')->pluck('email');

        return collect([$billing])->merge($admins)->filter()->map(fn ($e) => strtolower(trim($e)))->unique()->values()->all();
    }

    /** A payment against the invoice, recorded in Khoebo too when the invoice is there. */
    public function recordPayment(SessionInvoice $invoice, float $amount, string $date, string $method, ?string $reference, User $by): SessionInvoice
    {
        $owed = $invoice->outstanding();
        if ($amount <= 0 || $amount > $owed + 0.005) {
            throw new \InvalidArgumentException('Enter an amount up to the M' . number_format($owed, 2) . " still owed on {$invoice->number}.");
        }

        if ($invoice->khoebo_invoice_id) {
            if (!$this->books->khoebo->journal($method)) {
                throw new \InvalidArgumentException('Set SESSIONS_KHOEBO_JOURNAL_BANK, _ECOCASH and _MPESA: the Sessions journals payments are recorded in.');
            }
            $money = number_format($amount, 2, '.', '');
            $key = "ventiq-payment-sessions-{$invoice->id}-" . substr(sha1("{$money}|{$date}|{$method}|{$reference}"), 0, 12);
            $this->books->pay((int) $invoice->khoebo_invoice_id, $method, $amount, $date, $reference ?: $key, $key);
        }

        $paid = round((float) $invoice->paid_amount + $amount, 2);
        $invoice->update([
            'paid_amount'       => $paid,
            'payment_method'    => $method,
            'payment_reference' => $reference,
            'paid_by'           => $by->id,
            'status'            => $paid >= (float) $invoice->total - 0.005 ? 'paid' : 'unpaid',
            'paid_at'           => $paid >= (float) $invoice->total - 0.005 ? Carbon::parse($date) : null,
        ]);
        Log::info('Sessions invoice payment recorded', ['invoice' => $invoice->number, 'amount' => $amount, 'by' => $by->id]);

        return $invoice->fresh();
    }

    public static function methods(): array
    {
        return KhoeboPayments::METHODS;
    }

    /** VQS-2026-0001, counting up through the year. */
    private function nextNumber(): string
    {
        $prefix = 'VQS-' . now()->year . '-';
        $last = SessionInvoice::where('number', 'like', "{$prefix}%")->lockForUpdate()->orderByDesc('number')->value('number');

        return $prefix . str_pad((string) (($last ? (int) substr($last, strlen($prefix)) : 0) + 1), 4, '0', STR_PAD_LEFT);
    }
}
