<?php
namespace App\Http\Controllers;

use App\Models\{PaymentSession, Ticket};
use App\Services\Payments\{PaymentSessionService, TicketApprovalService};
use App\Services\SessionPackageService;
use App\Support\SessionPackageDefinition;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class PayLesothoController extends Controller
{
    public function __construct(
        private PaymentSessionService $payments,
        private TicketApprovalService $approval,
        private SessionPackageService $sessionPackages,
    ) {}

    public function initiateTicketPayment(Request $request)
    {
        $data = $request->validate([
            'ticket_id'     => 'required|exists:tickets,id',
            'method'        => 'required|in:mpesa,ecocash',
            'mobile_number' => 'required|string',
        ]);

        $ticket = Ticket::with('event')->findOrFail($data['ticket_id']);

        $session = $this->payments->initiate(
            payableType: 'ticket',
            payableId: $ticket->id,
            method: $data['method'],
            amount: (float) $ticket->amount,
            mobileNumber: $data['mobile_number'],
            organizationId: $ticket->event->organization_id,
        );

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
            'method'        => 'required|in:mpesa,ecocash',
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
        Log::info("PayLesotho callback [{$method}]", $request->all());

        $reference = $request->input('transaction_reference') ?? $request->input('client_reference');

        $session = PaymentSession::where('gateway', 'paylesotho')
            ->where(fn ($q) => $q->where('transaction_id', $reference)->orWhere('client_reference', $reference))
            ->first();

        if (!$session) {
            Log::error('PayLesotho callback: session not found', ['reference' => $reference]);
            return response()->json(['received' => true], 200);
        }

        $session = $this->payments->handleCallback($request, $method, $session);

        if ($session->isCompleted() && $session->payable_type === 'ticket') {
            $ticket = Ticket::with(['client', 'event', 'tier'])->find($session->payable_id);
            if ($ticket) {
                $this->approval->approve($ticket, 'paylesotho', $method, $session->transaction_id, $session);
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