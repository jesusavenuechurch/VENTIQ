<?php
namespace App\Http\Controllers;

use App\Models\{PaymentSession, Ticket};
use App\Services\Payments\{PaymentSessionService, TicketActivationService};
use App\Services\SessionPackageService;
use App\Support\SessionPackageDefinition;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class PayLesothoController extends Controller
{
    public function __construct(
        private PaymentSessionService $payments,
        private TicketActivationService $activation,
        private SessionPackageService $sessionPackages,
    ) {}

    public function initiateTicketPayment(Request $request)
    {
        $data = $request->validate([
            'ticket_id'     => 'required|exists:tickets,id',
            'method'        => ['required', \Illuminate\Validation\Rule::in(\App\Services\Payments\PaymentGatewayFactory::enabledMethods())],
            'mobile_number' => 'required|string',
        ]);

        $ticket = Ticket::with(['event.organization', 'client'])->findOrFail($data['ticket_id']);

        $session = $this->payments->initiate(
            payableType: 'ticket',
            payableId: $ticket->id,
            method: $data['method'],
            amount: (float) $ticket->amount,
            mobileNumber: $data['mobile_number'],
            organizationId: $ticket->event->organization_id,
        );

        // WhatsApp "ticket_registered" fires here, not at initial details
        // submission — this is the moment the customer has actually
        // submitted their number and a push went out, not just filled in
        // their name. Same reasoning as submitManualPayment().
        if ($ticket->shouldDeliverViaWhatsApp()) {
            $sent = app(\App\Services\WhatsAppCloudService::class)->sendTicketPending($ticket);

            if (!$sent) {
                $ticket->logDeliveryFailure('whatsapp', 'Failed to send ticket_registered via Meta WhatsApp Cloud API');
            }
        }

        return response()->json([
            'session_id' => $session->id,
            'status'     => $session->status,
        ]);
    }

    public function initiateSessionPackagePayment(Request $request)
    {
        $data = $request->validate([
            'type'          => 'required|in:plan,payg',
            'tier'          => 'required_if:type,plan|nullable|string',
            'quantity'      => 'required_if:type,payg|nullable|integer|min:1',
            'method'        => ['required', \Illuminate\Validation\Rule::in(\App\Services\Payments\PaymentGatewayFactory::enabledMethods())],
            'mobile_number' => 'required|string',
        ]);

        $organization = Auth::user()->organization;
        abort_unless($organization, 403);

        if ($data['type'] === 'plan') {
            $def = SessionPackageDefinition::get($data['tier']);
            abort_unless($def && $data['tier'] !== 'free', 404);

            $amount = $def['price'];
            $purchaseMeta = [
                'type'              => 'plan',
                'tier'              => $data['tier'],
                'sessions_included' => $def['sessions_included'],
                'whatsapp_included' => $def['whatsapp_included'],
                'sms_included'      => $def['sms_included'],
            ];
        } else {
            $quantity = (int) $data['quantity'];
            $amount = SessionPackageDefinition::paygBundlePrice($quantity);
            $purchaseMeta = [
                'type'     => 'payg',
                'quantity' => $quantity,
            ];
        }

        $session = $this->payments->initiate(
            payableType: 'session_package',
            payableId: $organization->id,
            method: $data['method'],
            amount: (float) $amount,
            mobileNumber: $data['mobile_number'],
            organizationId: $organization->id,
            initiatedBy: Auth::id(),
        );

        $session->update(['purchase_meta' => $purchaseMeta]);

        return response()->json([
            'session_id' => $session->id,
            'status'     => $session->status,
        ]);
    }

    public function status(PaymentSession $session)
    {
        return response()->json(['status' => $session->status]);
    }

    public function callback(Request $request, string $method)
    {
        Log::info("PayLesotho callback [{$method}]", $request->except('token'));

        // Shared secret carried in the callback URL (see
        // AbstractPayLesothoDriver::callbackUrlFor). Only enforced once
        // PAYLESOTHO_CALLBACK_SECRET is set, so callbacks configured on
        // PayLesotho's side without it keep arriving until it's rolled out.
        $secret = config('gateways.paylesotho.callback_secret');
        if ($secret && !hash_equals($secret, (string) $request->query('token'))) {
            Log::warning('PayLesotho callback rejected: bad token', ['method' => $method]);
            return response()->json(['received' => false], 403);
        }

        $reference = $request->input('transaction_reference') ?? $request->input('client_reference');

        $session = PaymentSession::where('gateway', 'paylesotho')
            ->where(fn ($q) => $q->where('transaction_id', $reference)->orWhere('client_reference', $reference))
            ->first();

        if (!$session) {
            Log::error('PayLesotho callback: session not found', ['reference' => $reference]);
            return response()->json(['received' => true], 200);
        }

        // A completed session is final: a late or replayed "failed"
        // callback must not reopen it, and a replayed "completed" one has
        // nothing left to do.
        if ($session->isCompleted()) {
            return response()->json(['received' => true]);
        }

        // Don't trust a "completed" callback for a different amount than
        // was charged. PayLesotho's payload field names are still
        // unconfirmed (see resolveStatus), so this only applies when an
        // amount is actually present.
        $callbackAmount = $request->input('amount');
        if ($callbackAmount !== null && abs((float) $callbackAmount - (float) $session->amount) > 0.009) {
            Log::error('PayLesotho callback: amount mismatch, not activating', [
                'session' => $session->id,
                'expected' => $session->amount,
                'received' => $callbackAmount,
            ]);
            return response()->json(['received' => true]);
        }

        $session = $this->payments->handleCallback($request, $method, $session);

        if ($session->isCompleted() && $session->payable_type === 'ticket') {
            $ticket = Ticket::with(['client', 'event', 'tier'])->find($session->payable_id);
            if ($ticket) {
                $this->activation->activate(
                    ticket: $ticket,
                    source: TicketActivationService::SOURCE_VENTIQ_ONLINE,
                    paymentMethod: $method,
                    paymentReference: $session->transaction_id,
                    paymentSession: $session,
                );
            }
        } elseif ($session->isCompleted() && $session->payable_type === 'session_package') {
            $meta = $session->purchase_meta ?? [];

            if (($meta['type'] ?? null) === 'plan') {
                $this->sessionPackages->changePlan(
                    organizationId: $session->organization_id,
                    tier: $meta['tier'],
                    sessionsIncluded: $meta['sessions_included'],
                    whatsappIncluded: $meta['whatsapp_included'],
                    smsIncluded: $meta['sms_included'],
                    pricePaid: (float) $session->amount,
                );
            } elseif (($meta['type'] ?? null) === 'payg') {
                $this->sessionPackages->addPaygCredits(
                    organizationId: $session->organization_id,
                    quantity: $meta['quantity'],
                    pricePaid: (float) $session->amount,
                );
            }
        }

        return response()->json(['received' => true]);
    }
}