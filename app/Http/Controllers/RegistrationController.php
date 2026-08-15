<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventTier;
use App\Models\Organization;
use App\Models\OrganizationPaymentMethod;
use App\Models\Client;
use App\Models\Ticket;
use App\Models\TicketPayment;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class RegistrationController extends Controller
{
    public function showForm($orgSlug, $eventSlug, Request $request)
    {
        $organization = Organization::where('slug', $orgSlug)->firstOrFail();

        $event = Event::where('slug', $eventSlug)
            ->where('organization_id', $organization->id)
            ->where('is_public', true)
            ->with(['tiers' => function ($query) {
                $query->where('is_active', true)->orderBy('price', 'asc');
            }])
            ->firstOrFail();

        if ($event->registration_deadline && now()->gt($event->registration_deadline)) {
            return view('public.registration-closed', compact('event', 'organization'));
        }

        $selectedTierId = $request->query('tier');
        $selectedTier   = $selectedTierId ? $event->tiers->firstWhere('id', $selectedTierId) : null;

        return view('public.register', compact('organization', 'event', 'selectedTier'));
    }

    public function register(Request $request, $orgSlug, $eventSlug)
    {
        $request->merge(['phone' => $this->normalizePhone($request->phone)]);

        $organization = Organization::where('slug', $orgSlug)->firstOrFail();
        $event        = Event::where('slug', $eventSlug)
            ->where('organization_id', $organization->id)
            ->where('is_public', true)
            ->firstOrFail();

        $tier                = EventTier::findOrFail($request->tier_id);
        $isFree              = $tier->price == 0;
        $quantityPerPurchase = $tier->quantity_per_purchase ?? 1;

        // ── Validation ────────────────────────────────────────────────
        $rules = [
            'tier_id'            => 'required|exists:event_tiers,id',
            'full_name'          => 'required|string|max:255',
            'email'              => 'nullable|email|max:255',
            'phone'              => 'required|string|regex:/^\+266[0-9]{8}$/',
            'terms'              => 'accepted',
            'has_whatsapp'       => 'nullable|boolean',
            'preferred_delivery' => 'nullable|in:email,whatsapp,both',
        ];

        if ($quantityPerPurchase > 1) {
            for ($i = 2; $i <= $quantityPerPurchase; $i++) {
                $rules["companion_{$i}_name"]  = 'required|string|max:255';
                $rules["companion_{$i}_phone"] = 'nullable|string';
                $rules["companion_{$i}_email"] = 'nullable|email';
            }
        }

        $validated = $request->validate($rules);

        // ── Build payment context ─────────────────────────────────────
        // Payment method/amount are no longer decided here — they're chosen on the
        // payment screen (Screen 2). Every paid ticket starts pending at the full price.
        $paymentMethodName = $isFree ? 'free' : null;
        $pricePerTicket    = $tier->price / $quantityPerPurchase;

        $ticketStatus  = $isFree ? 'active' : 'pending';
        $paymentStatus = $isFree ? 'completed' : 'pending';

        $hasWhatsApp       = $request->has('has_whatsapp') && $request->has_whatsapp;
        $preferredDelivery = $this->determinePreferredDelivery($request, $validated);

        // ── Create tickets ────────────────────────────────────────────
        DB::beginTransaction();
        try {
            $primaryClient = Client::firstOrCreate(
                ['phone' => $validated['phone'], 'organization_id' => $organization->id],
                [
                    'full_name' => $validated['full_name'],
                    'email'     => $validated['email'] ?? null,
                    'status'    => 'active',
                    'notes'     => 'Self-registered via public event page',
                    'created_by'=> null,
                ]
            );

            if (!$primaryClient->wasRecentlyCreated) {
                $primaryClient->update([
                    'full_name' => $validated['full_name'],
                    'email'     => $validated['email'] ?? null,
                ]);
            }

            $createdTickets = [];

            for ($i = 1; $i <= $quantityPerPurchase; $i++) {
                if ($i === 1) {
                    $client = $primaryClient;
                } else {
                    $companionPhone = $request->input("companion_{$i}_phone")
                        ? $this->normalizePhone($request->input("companion_{$i}_phone"))
                        : $validated['phone'];

                    $client = Client::firstOrCreate(
                        ['phone' => $companionPhone, 'organization_id' => $organization->id],
                        [
                            'full_name'  => $request->input("companion_{$i}_name"),
                            'email'      => $request->input("companion_{$i}_email"),
                            'status'     => 'active',
                            'notes'      => "Companion ticket purchased by {$validated['full_name']}",
                            'created_by' => null,
                        ]
                    );
                }

                $ticket = Ticket::create([
                    'event_id'          => $event->id,
                    'client_id'         => $client->id,
                    'event_tier_id'     => $tier->id,
                    'qr_code'           => 'QR-' . Str::uuid(),
                    'status'            => $ticketStatus,
                    'payment_method'    => $paymentMethodName,
                    'amount'            => $pricePerTicket,
                    'amount_paid'       => 0,
                    'payment_status'    => $paymentStatus,
                    'payment_reference' => null,
                    'delivery_method'   => !empty($validated['email']) ? 'email' : 'whatsapp',
                    'delivered_at'      => $isFree ? now() : null,
                    'created_by'        => null,
                    'has_whatsapp'      => $hasWhatsApp,
                    'preferred_delivery'=> $preferredDelivery,
                    'delivery_status'   => 'pending',
                ]);

                if (!$isFree) {
                    TicketPayment::create([
                        'ticket_id'         => $ticket->id,
                        'amount'            => $pricePerTicket,
                        'payment_method'    => null,
                        'payment_reference' => null,
                        'status'            => 'pending',
                        'payment_date'      => now(),
                        'payment_type'      => 'full',
                    ]);
                }

                if ($event->event_type === 'workshop') {
                    $ticket->workshopDetail()->updateOrCreate([], [
                        'position'         => $request->position,
                        'institution'      => $request->institution,
                        'district'         => $request->district,
                        'signature_status' => 'pending',
                    ]);
                }

                $createdTickets[] = $ticket;
            }

            DB::commit();

            // ── Emails ────────────────────────────────────────────────
            foreach ($createdTickets as $ticket) {
                $ticket->load(['client', 'event', 'tier', 'event.organization']);

                if (!$ticket->client->email) continue;

                if ($isFree) {
                    \Mail::to($ticket->client->email)->send(new \App\Mail\TicketApprovedMail($ticket));
                } else {
                    // Approval email is sent once a gateway callback or admin approves payment.
                    \Mail::to($ticket->client->email)->send(new \App\Mail\TicketPendingMail($ticket));
                }
            }

            // ── Route: paid tickets go to the payment screen, free tickets go straight to confirmation ──
            if (!$isFree) {
                return redirect()->route('registration.payment', [
                    'orgSlug'   => $orgSlug,
                    'eventSlug' => $eventSlug,
                    'ticketId'  => $createdTickets[0]->id,
                ]);
            }

            return redirect()->route('registration.confirmation', [
                'orgSlug'  => $orgSlug,
                'eventSlug'=> $eventSlug,
                'ticketId' => $createdTickets[0]->id,
            ])->with('all_tickets', $createdTickets);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Registration failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return back()->withInput()->with('error', 'Registration failed. Please try again. Error: ' . $e->getMessage());
        }
    }

    public function confirmation($orgSlug, $eventSlug, $ticketId)
    {
        $organization = Organization::where('slug', $orgSlug)->firstOrFail();

        $event = Event::where('slug', $eventSlug)
            ->where('organization_id', $organization->id)
            ->firstOrFail();

        $ticket = Ticket::with(['client', 'tier', 'payments'])
            ->where('event_id', $event->id)
            ->findOrFail($ticketId);

        $allTickets = collect(session('all_tickets', [$ticket]));

        $paymentMethodDetails = null;
        if ($ticket->payment_status !== 'completed' && $ticket->payment_method !== 'free') {
            $paymentMethodDetails = OrganizationPaymentMethod::where('organization_id', $organization->id)
                ->where('payment_method', $ticket->payment_method)
                ->where('is_active', true)
                ->first();
        }

        return view('public.confirmation', compact('organization', 'event', 'ticket', 'paymentMethodDetails', 'allTickets'));
    }

    /**
     * Screen 2 — pick a payment method. Online (PayLesotho) is the default and
     * most prominent option; manual methods (cash/bank/manual mobile money) sit
     * behind a "pay another way" toggle. Driven entirely by ticket ID + current
     * payment_status, so it's safe to reload at any point.
     */
    public function payment($orgSlug, $eventSlug, $ticketId)
    {
        $organization = Organization::where('slug', $orgSlug)->firstOrFail();

        $event = Event::where('slug', $eventSlug)
            ->where('organization_id', $organization->id)
            ->firstOrFail();

        $ticket = Ticket::with(['client', 'tier', 'event'])
            ->where('event_id', $event->id)
            ->findOrFail($ticketId);

        if ($ticket->payment_status === 'completed') {
            return redirect()->route('ticket.download', ['qr_code' => $ticket->qr_code]);
        }

        $enabledIds = $event->enabled_payment_method_ids; // null = all org methods

        $paymentMethods = $organization->paymentMethods()
            ->where('is_active', true)
            ->where('payment_method', '!=', 'online')
            ->when($enabledIds, fn ($q) => $q->whereIn('id', $enabledIds))
            ->orderBy('display_order')
            ->get();

        return view('public.payment', compact('organization', 'event', 'ticket', 'paymentMethods'));
    }

    /**
     * Manual payment sub-form on Screen 2 (cash/bank/manually-entered mobile money).
     * Updates the ticket's existing pending TicketPayment in place — the pending row
     * was already created at registration time, this just records the chosen method,
     * reference, and (if the event allows installments) the deposit amount.
     */
    public function submitManualPayment(Request $request, $orgSlug, $eventSlug, $ticketId)
    {
        $organization = Organization::where('slug', $orgSlug)->firstOrFail();

        $event = Event::where('slug', $eventSlug)
            ->where('organization_id', $organization->id)
            ->firstOrFail();

        $ticket = Ticket::with(['tier', 'payments'])
            ->where('event_id', $event->id)
            ->findOrFail($ticketId);

        if ($ticket->payment_status === 'completed') {
            return redirect()->route('ticket.download', ['qr_code' => $ticket->qr_code]);
        }

        $rules = [
            'payment_method_id' => 'required|exists:organization_payment_methods,id',
            'payment_reference' => 'nullable|string|max:255',
        ];

        if ($event->allow_installments) {
            $rules['payment_type']   = 'required|in:full,deposit';
            $rules['deposit_amount'] = 'nullable|numeric|min:0';
        }

        $validated = $request->validate($rules);

        $paymentMethodRecord = OrganizationPaymentMethod::where('organization_id', $organization->id)
            ->where('payment_method', '!=', 'online')
            ->findOrFail($validated['payment_method_id']);

        $tier                = $ticket->tier;
        $quantityPerPurchase = $tier->quantity_per_purchase ?? 1;
        $paymentAmount       = $tier->price;
        $paymentType         = 'full';

        if ($event->allow_installments && ($validated['payment_type'] ?? 'full') === 'deposit') {
            $minimumDeposit = ($tier->price * ($event->minimum_deposit_percentage ?? 30)) / 100;
            $depositAmount  = $validated['deposit_amount'] ?? $minimumDeposit;
            $paymentAmount  = max($minimumDeposit, min($depositAmount, $tier->price));
            $paymentType    = 'deposit';
        }

        $paymentPerTicket = $paymentAmount / $quantityPerPurchase;

        $pendingPayment = $ticket->payments()->where('status', 'pending')->latest()->first();
        $pendingPayment?->update([
            'amount'            => $paymentPerTicket,
            'payment_method'    => $paymentMethodRecord->payment_method,
            'payment_reference' => $validated['payment_reference'] ?? null,
            'payment_type'      => $paymentType,
        ]);

        $ticket->update([
            'payment_method'    => $paymentMethodRecord->payment_method,
            'payment_reference' => $validated['payment_reference'] ?? null,
            'payment_status'    => $paymentType === 'deposit' ? 'partial' : 'pending',
        ]);

        return redirect()->route('registration.confirmation', [
            'orgSlug'   => $orgSlug,
            'eventSlug' => $eventSlug,
            'ticketId'  => $ticket->id,
        ])->with('all_tickets', [$ticket]);
    }

    private function determinePreferredDelivery(Request $request, array $validated): string
    {
        if ($request->has('preferred_delivery')) {
            return $validated['preferred_delivery'];
        }

        $hasEmail    = !empty($validated['email']);
        $hasWhatsApp = $request->has('has_whatsapp') && $request->has_whatsapp;

        if ($hasEmail && $hasWhatsApp) return 'both';
        if ($hasWhatsApp) return 'whatsapp';
        if ($hasEmail) return 'email';

        return 'whatsapp';
    }

    private function normalizePhone($phone): string
    {
        $phone = preg_replace('/\D/', '', $phone);
        if (!str_starts_with($phone, '266')) {
            $phone = '266' . $phone;
        }
        return '+' . $phone;
    }
}