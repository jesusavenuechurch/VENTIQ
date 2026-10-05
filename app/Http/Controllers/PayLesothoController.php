<?php
namespace App\Http\Controllers;

use App\Models\{PaymentSession, Ticket};
use App\Services\Payments\{PaymentCompletion, PaymentSessionService};
use App\Support\SessionPackageDefinition;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class PayLesothoController extends Controller
{
    public function __construct(
        private PaymentSessionService $payments,
        private PaymentCompletion $completion,
    ) {}

    /** Pushes sent for this ticket in the last day (paying by hand isn't one). */
    public static function pushesFor(Ticket $ticket)
    {
        return PaymentSession::where('payable_type', 'ticket')->where('payable_id', $ticket->id)
            ->where('gateway', 'paylesotho')->whereNull('purchase_meta')
            ->where('created_at', '>', now()->subDay());
    }

    public static function attemptsLeft(Ticket $ticket): int
    {
        return max(0, (int) config('gateways.paylesotho.max_attempts', 3) - static::pushesFor($ticket)->count());
    }

    public function initiateTicketPayment(Request $request)
    {
        $data = $request->validate([
            'ticket_id'     => 'required|exists:tickets,id',
            'method'        => ['required', \Illuminate\Validation\Rule::in(\App\Services\Payments\PaymentGatewayFactory::enabledMethods())],
            'mobile_number' => 'required|string',
        ]);

        $ticket = Ticket::with(['event.organization', 'client'])->findOrFail($data['ticket_id']);

        // The event decides whether online payment is offered at all.
        if (!in_array($data['method'], app(\App\Services\Payments\PaymentAccountService::class)->onlineMethodsForEvent($ticket->event), true)) {
            return response()->json(['message' => 'Online payment is not available for this event.'], 422);
        }

        if ($ticket->payment_status === 'completed') {
            return response()->json(['message' => 'This ticket is already paid.'], 422);
        }
        if (!in_array($ticket->status, ['pending', 'active'], true)) {
            return response()->json(['message' => 'This ticket can no longer be paid for. Register again, or contact the organizer.'], 422);
        }

        // A push still on the attendee's phone: follow that one instead of
        // sending a second. Once the page has stopped waiting for it, the
        // attendee may ask for a new one ("I didn't get it").
        $inFlight = static::pushesFor($ticket)->where('status', 'pending')
            ->where('created_at', '>', now()->subSeconds((int) config('gateways.paylesotho.page_wait_seconds', 90)))->latest('id')->first();
        if ($inFlight) {
            return response()->json($this->state($inFlight, $ticket));
        }

        if (static::attemptsLeft($ticket) === 0) {
            return response()->json(['message' => 'No tries left. Please pay another way below.', 'attempts_left' => 0], 429);
        }

        // Only what's still owed: a deposit may already have been paid.
        $owed = round(max(0, (float) $ticket->amount - (float) $ticket->amount_paid), 2);
        if ($owed <= 0) {
            return response()->json(['message' => 'Nothing is owed on this ticket.'], 422);
        }

        $session = $this->payments->initiate(
            payableType: 'ticket',
            payableId: $ticket->id,
            method: $data['method'],
            amount: $owed,
            mobileNumber: $data['mobile_number'],
            organizationId: $ticket->event->organization_id,
        );

        // WhatsApp "ticket_registered" fires here, not at initial details
        // submission — this is the moment the customer has actually
        // submitted their number and a push went out, not just filled in
        // their name. Same reasoning as submitManualPayment().
        if ($ticket->shouldDeliverViaWhatsApp() && static::pushesFor($ticket)->count() === 1) {
            $sent = app(\App\Services\WhatsAppCloudService::class)->sendTicketPending($ticket);

            if (!$sent) {
                $ticket->logDeliveryFailure('whatsapp', 'Failed to send ticket_registered via Meta WhatsApp Cloud API');
            }
        }

        return $this->closing($this->state($session, $ticket));
    }

    /**
     * A reply the browser can finish reading straight away, so the page
     * starts its countdown while the push is waited on after the response
     * (on PHP-FPM that's fastcgi_finish_request; elsewhere the length and
     * Connection: close let the browser stop waiting for the socket).
     */
    private function closing(array $data)
    {
        $response = response()->json($data);

        return $response->withHeaders(['Content-Length' => strlen($response->getContent()), 'Connection' => 'close']);
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

        return $this->closing([
            'session_id' => $session->id,
            'status'     => $session->status,
        ]);
    }

    public function status(PaymentSession $session)
    {
        $ticket = $session->payable_type === 'ticket' ? Ticket::find($session->payable_id) : null;

        return response()->json($ticket ? $this->state($session, $ticket) : ['status' => $session->status]);
    }

    /** What the payment page needs to show for a push. */
    private function state(PaymentSession $session, Ticket $ticket): array
    {
        return [
            'session_id'    => $session->id,
            'status'        => $session->status,
            'message'       => $session->status === 'failed' ? ($session->callback_payload['failure'] ?? "The payment wasn't completed.") : null,
            'attempts_left' => static::attemptsLeft($ticket),
            'wait_seconds'  => (int) config('gateways.paylesotho.page_wait_seconds', 90),
        ];
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
        $this->completion->completed($session);

        return response()->json(['received' => true]);
    }
}