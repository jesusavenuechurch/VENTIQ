<?php

namespace App\Http\Controllers;

use App\Filament\Resources\PackagePurchaseResource;
use App\Models\AgentEarning;
use App\Models\Organization;
use App\Models\OrganizationPackage;
use App\Models\PaymentSession;
use App\Models\Ticket;
use App\Models\TicketPayment;
use App\Services\MopayService;
use App\Support\PackageDefinition;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\SettlementItem;

class MopayController extends Controller
{
    public function __construct(private MopayService $mopay) {}

    // =========================================================================
    // PACKAGE PAYMENT
    // =========================================================================

    public function initiatePackagePayment(Request $request)
    {
        $request->validate([
            'package_id' => 'required|exists:organization_packages,id',
        ]);

        $user    = auth()->user();
        $package = OrganizationPackage::findOrFail($request->package_id);
        $def     = PackageDefinition::get($package->package_type);

        if (!$def) {
            return back()->with('error', 'Invalid package.');
        }

        try {
            $internalRef = 'PKG' . $package->id . 'T' . time();

            $session = $this->mopay->createSession([
                'amount'        => $def['price'],
                'reference'     => $internalRef,
                'redirectUrl'   => route('online-payment.package.callback'),
                'description'   => strtoupper($package->package_type) . ' Package — Ventiq',
                'customerEmail' => $user->email,
                'customerName'  => $user->name,
            ]);

            PaymentSession::create([
                'payable_type'      => 'package',
                'payable_id'        => $package->id,
                'mopay_reference'   => $internalRef,
                'mopay_session_id'  => $session['sessionId'],
                'mopay_payment_url' => $session['paymentUrl'],
                'amount'            => $def['price'],
                'status'            => 'pending',
                'organization_id'   => $package->organization_id,
                'initiated_by'      => $user->id,
            ]);

            $package->update(['payment_reference' => $internalRef]);

            return redirect()->away($session['paymentUrl']);

        } catch (\Exception $e) {
            Log::error('MoPay package initiation failed', ['error' => $e->getMessage()]);
            return back()->with('error', 'Could not initiate payment: ' . $e->getMessage());
        }
    }

    /**
     * MoPay redirects here after package payment.
     *
     * NOTE: every redirect below uses PackagePurchaseResource::getUrl('index')
     * instead of a hardcoded route name string. This resource now lives
     * inside OrganizationCluster, which changed its actual route name —
     * getUrl() always resolves correctly regardless of which cluster (or
     * no cluster) the resource currently lives in, so this can't break
     * again the next time navigation gets reorganized.
     */
    public function packageCallback(Request $request)
    {
        $sessionId = $request->query('sessionId');
        $status    = $request->query('status');

        Log::info('MoPay package callback', $request->query());

        $packageIndexUrl = PackagePurchaseResource::getUrl('index');

        if (!$sessionId) {
            return redirect($packageIndexUrl)
                ->with('error', 'Invalid payment callback.');
        }

        $paymentSession = PaymentSession::where('mopay_session_id', $sessionId)
            ->where('payable_type', 'package')
            ->first();

        if (!$paymentSession) {
            Log::error('MoPay: package session not found', ['sessionId' => $sessionId]);
            return redirect($packageIndexUrl)
                ->with('error', 'Payment session not found.');
        }

        try {
            $verified = $this->mopay->verifySession($sessionId);
        } catch (\Exception $e) {
            Log::error('MoPay package verification failed', ['error' => $e->getMessage()]);
            return redirect($packageIndexUrl)
                ->with('error', 'Could not verify payment. Please contact support.');
        }

        $paymentSession->update([
            'callback_payload' => array_merge($request->query(), ['verified' => $verified]),
            'payment_method'   => $verified['selectedPaymentMethod'] ?? $request->query('paymentMethod'),
            'transaction_id'   => $verified['transactionId'] ?? null,
        ]);

        $package = OrganizationPackage::find($paymentSession->payable_id);

        if (!$package) {
            return redirect($packageIndexUrl)
                ->with('error', 'Package not found.');
        }

        if (($verified['status'] ?? '') === 'COMPLETED') {
            $paymentSession->update(['status' => 'completed']);
            $this->approvePackage($package, $verified);

            return redirect($packageIndexUrl)
                ->with('success', '✅ Payment successful! Your ' . strtoupper($package->package_type) . ' package is now active.');
        }

        if (in_array($verified['status'] ?? '', ['FAILED', 'CANCELLED', 'EXPIRED'])) {
            $paymentSession->update(['status' => strtolower($verified['status'])]);
            $package->delete();

            $message = match($verified['status']) {
                'CANCELLED' => 'Payment was cancelled. You can try again.',
                'FAILED'    => 'Payment failed. Please try again or use a different method.',
                default     => 'Payment expired. Please try again.',
            };

            return redirect($packageIndexUrl)
                ->with('error', $message);
        }

        return redirect($packageIndexUrl)
            ->with('warning', 'Payment is being processed. Your package will activate shortly.');
    }

    private function approvePackage(OrganizationPackage $record, array $verified): void
    {
        DB::transaction(function () use ($record, $verified) {
            $record->update([
                'status'           => 'active',
                'payment_method'   => 'mopay',
                'payment_reference'=> $verified['transactionId'] ?? $record->payment_reference,
                'purchased_at'     => now(),
            ]);

            $hasPaidPackage = OrganizationPackage::where('organization_id', $record->organization_id)
                ->where('id', '!=', $record->id)
                ->where('is_free_trial', false)
                ->where('status', 'active')
                ->exists();

            if (!$hasPaidPackage) {
                OrganizationPackage::where('organization_id', $record->organization_id)
                    ->where('is_free_trial', true)
                    ->where('status', 'active')
                    ->update(['status' => 'converted']);
            }

            $org = $record->organization;
            if ($org && $org->agent_id) {
                $commission = AgentEarning::calculateCommission($record->price_paid);

                AgentEarning::create([
                    'agent_id'                => $org->agent_id,
                    'organization_id'         => $org->id,
                    'organization_package_id' => $record->id,
                    'type'                    => 'commission',
                    'amount'                  => $commission,
                    'package_price'           => $record->price_paid,
                    'package_type'            => $record->package_type,
                    'status'                  => 'approved',
                    'approved_by'             => null,
                    'approved_at'             => now(),
                    'notes'                   => "Auto-approved via MoPay — {$org->name}",
                ]);

                $paidCount = Organization::where('agent_id', $org->agent_id)
                    ->whereHas('activePackages', fn ($q) => $q->where('is_free_trial', false)->where('status', 'active'))
                    ->count();

                if ($paidCount > 0 && $paidCount % 5 === 0) {
                    $tier = AgentEarning::getMilestoneTier($paidCount);
                    AgentEarning::firstOrCreate(
                        ['organization_package_id' => $record->id, 'type' => 'milestone_bonus'],
                        [
                            'agent_id'            => $org->agent_id,
                            'type'                => 'milestone_bonus',
                            'amount'              => AgentEarning::calculateMilestoneBonus($tier),
                            'milestone_tier'      => $tier,
                            'milestone_org_count' => $paidCount,
                            'status'              => 'approved',
                            'approved_by'         => null,
                            'approved_at'         => now(),
                        ]
                    );
                }
            }

            Log::info("MoPay: package {$record->id} auto-approved for org {$record->organization_id}");
        });
    }

    // =========================================================================
    // TICKET PAYMENT
    // =========================================================================

    public function initiateTicketPayment(Request $request)
    {
        $request->validate([
            'ticket_id' => 'required|exists:tickets,id',
        ]);

        $ticket = Ticket::with(['client', 'event', 'tier', 'event.organization'])->findOrFail($request->ticket_id);

        DB::beginTransaction();
        try {
            $internalRef = 'TKT' . $ticket->id . 'T' . time();

            $surchargeRate = config('constants.payment.surcharge_rate');
            $grossAmount   = round($ticket->amount * (1 + $surchargeRate), 2);

            $session = $this->mopay->createSession([
                'amount'        => $grossAmount,
                'reference'     => $internalRef,
                'redirectUrl'   => route('online-payment.ticket.callback'),
                'description'   => $ticket->event->name . ' — ' . $ticket->tier->tier_name,
                'customerEmail' =>  $ticket->client->email ?? 'noreply@ventiq.com',
                'customerName'  => $ticket->client->full_name,
            ]);

            PaymentSession::create([
                'payable_type'      => 'ticket',
                'payable_id'        => $ticket->id,
                'mopay_reference'   => $internalRef,
                'mopay_session_id'  => $session['sessionId'],
                'mopay_payment_url' => $session['paymentUrl'],
                'amount'            => $ticket->amount,
                'status'            => 'pending',
                'organization_id'   => $ticket->event->organization_id,
                'initiated_by'      => null,
            ]);

            $ticket->update(['payment_reference' => $internalRef]);
            $ticket->payments()->latest()->first()?->update(['payment_reference' => $internalRef]);

            DB::commit();

            return redirect()->away($session['paymentUrl']);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('MoPay ticket initiation failed', ['ticket_id' => $ticket->id, 'error' => $e->getMessage()]);

            return redirect()->route('registration.confirmation', [
                'orgSlug'   => $ticket->event->organization->slug,
                'eventSlug' => $ticket->event->slug,
                'ticketId'  => $ticket->id,
            ])->with('error', 'Could not initiate payment. Please try another method.');
        }
    }

    public function ticketCallback(Request $request)
    {
        $sessionId = $request->query('sessionId');

        Log::info('MoPay ticket callback', $request->query());

        if (!$sessionId) {
            return redirect('/')->with('error', 'Invalid payment callback.');
        }

        $paymentSession = PaymentSession::where('mopay_session_id', $sessionId)
            ->where('payable_type', 'ticket')
            ->first();

        if (!$paymentSession) {
            Log::error('MoPay: ticket session not found', ['sessionId' => $sessionId]);
            return redirect('/')->with('error', 'Payment session not found.');
        }

        $ticket = Ticket::with(['client', 'event', 'tier', 'event.organization', 'payments'])->find($paymentSession->payable_id);

        if (!$ticket) {
            return redirect('/')->with('error', 'Ticket not found.');
        }

        $confirmationRoute = route('registration.confirmation', [
            'orgSlug'   => $ticket->event->organization->slug,
            'eventSlug' => $ticket->event->slug,
            'ticketId'  => $ticket->id,
        ]);

        try {
            $verified = $this->mopay->verifySession($sessionId);
        } catch (\Exception $e) {
            Log::error('MoPay ticket verification failed', ['error' => $e->getMessage()]);
            return redirect($confirmationRoute)->with('error', 'Could not verify payment. Please contact support.');
        }

        $paymentSession->update([
            'callback_payload' => array_merge($request->query(), ['verified' => $verified]),
            'payment_method'   => $verified['selectedPaymentMethod'] ?? $request->query('paymentMethod'),
            'transaction_id'   => $verified['transactionId'] ?? null,
        ]);

        if (($verified['status'] ?? '') === 'COMPLETED') {
            $paymentSession->update(['status' => 'completed']);
            $this->approveTicket($ticket, $verified, $paymentSession);

            return redirect()->route('ticket.download', [
                'qr_code' => $ticket->qr_code,
            ]);
        }

        if (in_array($verified['status'], ['FAILED', 'CANCELLED', 'EXPIRED'])) {
            $paymentSession->update(['status' => strtolower($verified['status'])]);

            $message = match($verified['status']) {
                'CANCELLED' => 'Payment was cancelled. You can try again.',
                'FAILED'    => 'Payment failed. Please try again.',
                default     => 'Payment expired. Please try again.',
            };

            return redirect($confirmationRoute)->with('error', $message);
        }

        return redirect($confirmationRoute)->with('warning', 'Payment is being processed. Your ticket will activate shortly.');
    }

    private function approveTicket(Ticket $ticket, array $verified, PaymentSession $paymentSession): void
    {
        DB::transaction(function () use ($ticket, $verified, $paymentSession) {

            $pendingPayment = $ticket->payments()->where('status', 'pending')->latest()->first();
            if ($pendingPayment) {
                $pendingPayment->update([
                    'status'            => 'approved',
                    'payment_method'    => $verified['selectedPaymentMethod'] ?? 'mopay',
                    'payment_reference' => $verified['transactionId'] ?? null,
                    'approved_by'       => null,
                    'approved_at'       => now(),
                ]);
            }

            $ticket->update([
                'payment_status'    => 'completed',
                'status'            => 'active',
                'payment_method'    => $verified['selectedPaymentMethod'] ?? 'mopay',
                'payment_reference' => $verified['transactionId'] ?? null,
                'payment_date'      => now(),
                'amount_paid'       => $ticket->amount,
            ]);

            if ($ticket->payments()->where('status', 'approved')->count() === 1) {
                $ticket->tier->increment('quantity_sold');
            }

            $surchargeRate = config('constants.payment.surcharge_rate');
            $gatewayRate   = config('constants.payment.gateway_fee_rate');

            $ticketAmount  = $ticket->amount;
            $grossPaid     = round($ticketAmount * (1 + $surchargeRate), 2);
            $gatewayFee    = round($grossPaid * $gatewayRate, 2);
            $amtReceived   = round($grossPaid - $gatewayFee, 2);

            \App\Models\SettlementItem::create([
                'settlement_id'      => null,
                'payment_session_id' => $paymentSession->id,
                'ticket_id'          => $ticket->id,
                'organization_id'    => $ticket->event->organization_id,
                'ticket_amount'      => $ticketAmount,
                'gross_paid'         => $grossPaid,
                'gateway_fee'        => $gatewayFee,
                'amount_received'    => $amtReceived,
                'amount_owed_to_org' => $ticketAmount,
            ]);

            dispatch(function () use ($ticket) {
                $ticket->load(['client', 'event', 'tier', 'event.organization']);
                $ticket->generateQrCode();
                $ticket->autoDeliverTicket();
            })->afterResponse();

            if ($ticket->client->email) {
                dispatch(new \App\Jobs\SendTicketApprovedEmail($ticket->id))->afterResponse();
            }

            Log::info("MoPay: ticket {$ticket->id} approved, settlement_item created");
        });
    }
}