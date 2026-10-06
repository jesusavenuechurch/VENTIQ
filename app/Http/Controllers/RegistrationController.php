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

        if ($closedReason = $event->registrationClosedReason()) {
            return view('public.registration-closed', compact('event', 'organization', 'closedReason'));
        }

        $selectedTierId = $request->query('tier');
        $selectedTier   = $selectedTierId ? $event->tiers->firstWhere('id', $selectedTierId) : null;

        // The form is for one ticket type. A link without a (valid) one,
        // e.g. shared from a poster or an old tier, used to crash: with a
        // single type there's nothing to choose, otherwise the event page
        // lists them.
        if (!$selectedTier) {
            if ($event->tiers->count() !== 1) {
                return redirect()->route('event.short', [$organization->slug, $event->slug]);
            }
            $selectedTier = $event->tiers->first();
        }

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

        if ($closedReason = $event->registrationClosedReason()) {
            return view('public.registration-closed', compact('event', 'organization', 'closedReason'));
        }

        // Only this event's ticket types that are on sale.
        $tier                = EventTier::where('event_id', $event->id)->where('is_active', true)->findOrFail($request->tier_id);
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

        if ($event->event_type === 'workshop') {
            $rules['position']    = 'required|string|max:150';
            $rules['institution'] = 'required|string|max:150';
            $rules['district']    = ['required', \Illuminate\Validation\Rule::in(array_keys(config('constants.workshop_districts', [])))];
        }

        $validated = $request->validate($rules);

        // Registering again for the same paid ticket type with the same
        // number picks up the unpaid ticket already held, rather than
        // holding a second place (one unpaid ticket per number per type).
        if (!$isFree) {
            $held = Ticket::where('event_id', $event->id)->where('event_tier_id', $tier->id)
                ->where('status', 'pending')->whereIn('payment_status', ['pending', 'partial'])
                ->whereHas('client', fn ($q) => $q->where('organization_id', $organization->id)->where('phone', $validated['phone']))
                ->latest('id')->first();

            if ($held) {
                $held->update(['attendee_name' => $this->differentName($held->client, $validated['full_name'])]);
                if (!empty($validated['email']) && !$held->client->email) {
                    $held->client->update(['email' => $validated['email']]);
                }
                if ($held->payment_due_at) {
                    $held->update(['payment_due_at' => \App\Support\PaymentWindow::dueAt($event)]);
                }

                return redirect()->route('ticket.pay', $held->qr_code)
                    ->with('status', "Welcome back! Here's the ticket you started, ready to pay.");
            }
        }

        // ── Build payment context ─────────────────────────────────────
        // Payment method/amount are no longer decided here — they're chosen on the
        // payment screen (Screen 2). Every paid ticket starts pending at the full price.
        // One purchase is one ticket: a group tier's price covers the whole
        // group, and the ticket carries one admission per person.
        $paymentMethodName = $isFree ? 'free' : null;
        $ticketPrice       = $tier->price;

        $ticketStatus  = $isFree ? 'active' : 'pending';
        $paymentStatus = $isFree ? 'completed' : 'pending';

        $hasWhatsApp       = $request->has('has_whatsapp') && $request->has_whatsapp;
        $preferredDelivery = $this->determinePreferredDelivery($request, $validated);

        // ── Create tickets ────────────────────────────────────────────
        DB::beginTransaction();
        try {
            // Unpaid tickets hold their place, so the tier is full once held
            // and paid tickets reach its quantity. The lock stops two people
            // taking the last place at the same moment.
            $lockedTier = EventTier::whereKey($tier->id)->lockForUpdate()->first();
            if (!\App\Support\TierCapacity::hasRoom($lockedTier)) {
                DB::rollBack();
                return back()->withInput()->with('error', "Sorry, {$tier->tier_name} is sold out.");
            }

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

            // A phone shared by several people (a parent registering
            // children, friends) keeps its contact's name: this ticket
            // carries its own instead of renaming the earlier ones. An
            // email is added, never wiped.
            $attendeeName = $primaryClient->wasRecentlyCreated ? null : $this->differentName($primaryClient, $validated['full_name']);
            if (!$primaryClient->wasRecentlyCreated && !empty($validated['email']) && !$primaryClient->email) {
                $primaryClient->update(['email' => $validated['email']]);
            }

            $ticket = Ticket::create([
                'event_id'          => $event->id,
                'client_id'         => $primaryClient->id,
                'attendee_name'     => $attendeeName,
                'event_tier_id'     => $tier->id,
                'qr_code'           => 'QR-' . Str::uuid(),
                'status'            => $ticketStatus,
                'payment_method'    => $paymentMethodName,
                'amount'            => $ticketPrice,
                'amount_paid'       => 0,
                'admissions'        => max(1, (int) $quantityPerPurchase),
                'payment_due_at'    => $isFree ? null : \App\Support\PaymentWindow::dueAt($event),
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
                    'amount'            => $ticketPrice,
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

            $createdTickets = [$ticket];

            DB::commit();

            // ── Emails + WhatsApp ────────────────────────────────────────
            // The ticket is saved: a message that fails to send must not
            // look like a failed registration (people register again and
            // hold two places). Failures are logged on the ticket instead.
            foreach ($createdTickets as $ticket) {
              try {
                $ticket->load(['client', 'event', 'tier', 'event.organization']);

                if ($ticket->client->email) {
                    if ($isFree) {
                        \Mail::to($ticket->client->email)->send(new \App\Mail\TicketApprovedMail($ticket));
                    } else {
                        // Approval email is sent once a gateway callback or admin approves payment.
                        \Mail::to($ticket->client->email)->send(new \App\Mail\TicketPendingMail($ticket));
                    }
                }

                // WhatsApp — free tickets only here. Free tickets are
                // created already payment_status=completed (never go
                // through an update), so Ticket::autoDeliverTicket()'s
                // isDirty('payment_status') hook never fires for them —
                // they need their own explicit send, same as the email
                // branch above.
                //
                // Paid/pending tickets deliberately do NOT get
                // "ticket_registered" here — at this point the customer has
                // only filled in their details and hasn't even reached the
                // payment screen yet, let alone chosen how they're paying.
                // That send happens once they've actually acted on the
                // payment screen instead: submitManualPayment() for the
                // manual path, PayLesothoController::initiateTicketPayment()
                // for the online push.
                if ($isFree && $ticket->shouldDeliverViaWhatsApp()) {
                    $sent = app(\App\Services\WhatsAppCloudService::class)->sendTicketApproved($ticket);

                    if (!$sent) {
                        $ticket->logDeliveryFailure('whatsapp', 'Failed to send registration WhatsApp message via Meta Cloud API');
                    }
                }
              } catch (\Throwable $e) {
                \Log::error('Registration message failed', ['ticket' => $ticket->id, 'error' => $e->getMessage()]);
                $ticket->logDeliveryFailure('registration', $e->getMessage());
              }
            }

            // ── Route: paid tickets go to the payment screen, free tickets go straight to confirmation ──
            if (!$isFree) {
                return redirect()->route('ticket.pay', $createdTickets[0]->qr_code);
            }

            return redirect()->route('ticket.registered', $createdTickets[0]->qr_code)->with('all_tickets', $createdTickets);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Registration failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return back()->withInput()->with('error', "Something went wrong and you weren't registered. Please try again.");
        }
    }

    public function confirmation(string $code)
    {
        [$ticket, $event, $organization] = $this->ticketByCode($code, ['client', 'tier', 'payments']);

        $allTickets = collect(session('all_tickets', [$ticket]));

        // This "protocol status" page exists to explain a still-pending
        // ticket (payment instructions, waiting on approval, etc). Once a
        // ticket is actually completed there's nothing left to explain —
        // send them straight to the ticket itself instead of making them
        // click through a "Verified" holding screen first. Group purchases
        // (companions listed in $allTickets) are the one exception: that's
        // the only place all of a party's tickets are shown together, so
        // keep those on this page even once completed.
        if ($ticket->payment_status === 'completed' && $allTickets->count() <= 1) {
            return redirect()->route('ticket.download', $ticket->qr_code);
        }

        // The account the attendee actually paid into. Looking it up by
        // method name picked an arbitrary one once an organization had two
        // EcoCash accounts; older payments without a stored account fall
        // back to that lookup.
        $paymentMethodDetails = null;
        if ($ticket->payment_status !== 'completed' && $ticket->payment_method !== 'free') {
            $paymentMethodDetails = $ticket->payments
                ->where('status', 'pending')
                ->sortByDesc('id')
                ->first()?->paymentAccount
                ?? OrganizationPaymentMethod::where('organization_id', $organization->id)
                    ->where('payment_method', $ticket->payment_method)
                    ->where('is_active', true)
                    ->first();
        }

        // Paid VENTIQ's merchant by hand: VENTIQ checks it, not the organizer.
        $byHand = $ticket->payment_status !== 'completed' && \App\Models\PaymentSession::where('payable_type', 'ticket')
            ->where('payable_id', $ticket->id)->where('status', 'pending')->whereNotNull('purchase_meta')->exists();
        if ($byHand) {
            $paymentMethodDetails = null;
        }

        return view('public.confirmation', compact('organization', 'event', 'ticket', 'paymentMethodDetails', 'allTickets', 'byHand'));
    }

    /**
     * Screen 2 — pick a payment method. Online (PayLesotho) is the default and
     * most prominent option; manual methods (cash/bank/manual mobile money) sit
     * behind a "pay another way" toggle. Driven entirely by ticket ID + current
     * payment_status, so it's safe to reload at any point.
     */
    public function payment(string $code)
    {
        [$ticket, $event, $organization] = $this->ticketByCode($code, ['client', 'tier']);

        if ($ticket->payment_status === 'completed') {
            return redirect()->route('ticket.download', ['qr_code' => $ticket->qr_code]);
        }

        // The event's own choice of accounts and whether it offers online
        // payment; an event that never chose offers every active account
        // (see PaymentAccountService).
        $accounts       = app(\App\Services\Payments\PaymentAccountService::class);
        $paymentMethods = $accounts->directAccountsForEvent($event);
        $onlineMethods  = $accounts->onlineMethodsForEvent($event);
        $onlineEnabled  = !empty($onlineMethods);

        // For the online part: tries left, a push still waiting (the page
        // was reloaded), and VENTIQ's merchant for paying by hand once the
        // tries are used up.
        $attemptsLeft = \App\Http\Controllers\PayLesothoController::attemptsLeft($ticket);
        $inFlight = $onlineEnabled ? \App\Http\Controllers\PayLesothoController::pushesFor($ticket)->where('status', 'pending')
            ->where('created_at', '>', now()->subSeconds((int) config('gateways.paylesotho.page_wait_seconds', 90)))->latest('id')->first() : null;
        $byHand = \App\Models\PaymentSession::where('payable_type', 'ticket')->where('payable_id', $ticket->id)
            ->where('status', 'pending')->whereNotNull('purchase_meta')->latest('id')->first();
        $merchant = in_array('ecocash', $onlineMethods, true) && config('gateways.paylesotho.ecocash.merchant_code') ? [
            'code' => config('gateways.paylesotho.ecocash.merchant_code'),
            'name' => config('gateways.paylesotho.ecocash.merchant_name') ?: 'VENTIQ',
        ] : null;
        $owed = round(max(0, (float) $ticket->amount - (float) $ticket->amount_paid), 2);

        return view('public.payment', compact('organization', 'event', 'ticket', 'paymentMethods', 'onlineEnabled', 'onlineMethods', 'attemptsLeft', 'inFlight', 'byHand', 'merchant', 'owed'));
    }

    /**
     * The pushes didn't work, so the attendee paid VENTIQ's EcoCash
     * merchant themselves. Recorded as an online payment waiting for
     * VENTIQ to find it on the merchant statement (money page, "Online
     * payments to check"); confirming it activates the ticket like any
     * online sale.
     */
    public function submitMerchantPayment(Request $request, string $code)
    {
        [$ticket, $event, $organization] = $this->ticketByCode($code, ['client']);

        if ($ticket->payment_status === 'completed') {
            return redirect()->route('ticket.download', ['qr_code' => $ticket->qr_code]);
        }
        abort_unless(in_array($ticket->status, ['pending', 'active'], true), 410);
        abort_unless(in_array('ecocash', app(\App\Services\Payments\PaymentAccountService::class)->onlineMethodsForEvent($event), true)
            && config('gateways.paylesotho.ecocash.merchant_code'), 404);

        $validated = $request->validate([
            'merchant_reference' => ['nullable', 'string', 'max:100'],
            'merchant_phone'     => ['required', 'string', 'max:20'],
            'proof'              => \App\Support\PaymentProof::RULES,
        ]);
        if (blank($validated['merchant_reference'] ?? null) && !$request->hasFile('proof')) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'merchant_reference' => 'Enter the reference from your EcoCash message, or add a screenshot of it.',
            ]);
        }

        $owed = round(max(0, (float) $ticket->amount - (float) $ticket->amount_paid), 2);
        abort_if($owed <= 0, 422);

        // One claim at a time: a second submission replaces the first.
        $session = \App\Models\PaymentSession::where('payable_type', 'ticket')->where('payable_id', $ticket->id)
            ->where('status', 'pending')->whereNotNull('purchase_meta')->latest('id')->first()
            ?? new \App\Models\PaymentSession();
        $session->fill([
            'payable_type'     => 'ticket',
            'payable_id'       => $ticket->id,
            'gateway'          => 'paylesotho',
            'status'           => 'pending',
            'payment_method'   => 'ecocash',
            'amount'           => $owed,
            'organization_id'  => $organization->id,
            'client_reference' => $session->client_reference ?? 'HAND' . $ticket->id . 'T' . random_int(1_000_000_000, 9_999_999_999),
            'transaction_id'   => $validated['merchant_reference'] ?? null,
            'purchase_meta'    => ['by_hand' => true],
            'callback_payload' => ['by_hand' => [
                'reference'  => $validated['merchant_reference'] ?? null,
                'paid_from'  => $validated['merchant_phone'],
                'proof_path' => \App\Support\PaymentProof::store($request->file('proof')) ?? ($session->callback_payload['by_hand']['proof_path'] ?? null),
                'at'         => now()->toIso8601String(),
            ]],
        ])->save();

        // Waiting on VENTIQ now, not on the attendee: don't let it expire.
        $ticket->update(['payment_due_at' => null]);

        return redirect()->route('ticket.registered', $ticket->qr_code)->with('all_tickets', [$ticket]);
    }

    /**
     * Manual payment sub-form on Screen 2 (cash/bank/manually-entered mobile money).
     * Updates the ticket's existing pending TicketPayment in place — the pending row
     * was already created at registration time, this just records the chosen method,
     * reference, and (if the event allows installments) the deposit amount.
     */
    public function submitManualPayment(Request $request, string $code)
    {
        [$ticket, $event, $organization] = $this->ticketByCode($code, ['tier', 'payments']);

        if ($ticket->payment_status === 'completed') {
            return redirect()->route('ticket.download', ['qr_code' => $ticket->qr_code]);
        }

        $rules = [
            'payment_method_id' => 'required|exists:organization_payment_methods,id',
            'payment_reference' => 'nullable|string|max:255',
            'proof'             => \App\Support\PaymentProof::RULES,
        ];

        if ($event->allow_installments) {
            $rules['payment_type']   = 'required|in:full,deposit';
            $rules['deposit_amount'] = 'nullable|numeric|min:0';
        }

        $validated = $request->validate($rules);

        // Any active account of the organization is accepted, not only the
        // event's current ones, so an attendee paying from instructions sent
        // before the organizer changed accounts is still recorded correctly.
        // Archived (inactive) accounts take no new payments.
        $paymentMethodRecord = OrganizationPaymentMethod::where('organization_id', $organization->id)
            ->where('payment_method', '!=', 'online')
            ->where('is_active', true)
            ->findOrFail($validated['payment_method_id']);

        // A reference or a screenshot is all the organizer can check a
        // mobile money or bank payment against, so one is needed for
        // everything except cash.
        if ($paymentMethodRecord->payment_method !== 'cash' && blank($validated['payment_reference'] ?? null) && !$request->hasFile('proof')) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'payment_reference' => 'Please enter the transaction reference from your payment confirmation, or add a screenshot of it.',
            ]);
        }

        // A group ticket (admissions = N) pays the whole tier price. Older
        // group purchases were split into one ticket per person, each
        // paying its share. What's already been paid (a deposit) comes off.
        $tier        = $ticket->tier;
        $fullPrice   = $tier->price * max(1, (int) $ticket->admissions) / max(1, $tier->quantity_per_purchase ?? 1);
        $owed        = round(max(0, $fullPrice - (float) $ticket->amount_paid), 2);
        $paymentPerTicket = $owed;
        $paymentType = (float) $ticket->amount_paid > 0 ? 'installment' : 'full';

        if ($event->allow_installments && ($validated['payment_type'] ?? 'full') === 'deposit' && (float) $ticket->amount_paid <= 0) {
            $minimumDeposit   = $fullPrice * ($event->minimum_deposit_percentage ?? 30) / 100;
            $depositAmount    = $validated['deposit_amount'] ?? $minimumDeposit;
            $paymentPerTicket = round(max($minimumDeposit, min($depositAmount, $owed)), 2);
            $paymentType      = 'deposit';
        }

        // Registration leaves one pending row to fill in. After a
        // rejection there isn't one, so resubmitting starts a new row
        // instead of silently doing nothing.
        $pendingPayment = $ticket->payments()->where('status', 'pending')->latest()->first()
            ?? $ticket->payments()->make(['status' => 'pending']);

        $pendingPayment->fill([
            'amount'                         => $paymentPerTicket,
            'payment_method'                 => $paymentMethodRecord->payment_method,
            'organization_payment_method_id' => $paymentMethodRecord->id,
            'source'                         => \App\Services\Payments\TicketActivationService::SOURCE_ORGANIZER_DIRECT,
            'payment_reference'              => $validated['payment_reference'] ?? null,
            'proof_path'                     => \App\Support\PaymentProof::store($request->file('proof')) ?? $pendingPayment->proof_path,
            'payment_type'                   => $paymentType,
            'payment_date'                   => now(),
            'submitted_at'                   => now(),
        ])->save();

        // Stays 'pending' even for a deposit: this is only the attendee's
        // claim. 'partial' is for money the organizer has confirmed.
        // Clearing payment_due_at stops the ticket expiring while it waits
        // for the organizer to check the payment.
        $ticket->update([
            'payment_method'    => $paymentMethodRecord->payment_method,
            'payment_reference' => $validated['payment_reference'] ?? null,
            'payment_status'    => 'pending',
            'payment_due_at'    => null,
        ]);

        // Now there's something for the organizer to do: check the money.
        app(\App\Services\Notifications\OrganizerNotifier::class)->paymentSubmitted($pendingPayment);

        // WhatsApp "ticket_registered" fires here, not at initial details
        // submission — this is the moment the customer has actually acted
        // on the payment screen (picked a manual method, submitted a
        // reference), not just filled in their name.
        $ticket->loadMissing(['client', 'event.organization']);
        if ($ticket->shouldDeliverViaWhatsApp()) {
            $sent = app(\App\Services\WhatsAppCloudService::class)->sendTicketPending($ticket);

            if (!$sent) {
                $ticket->logDeliveryFailure('whatsapp', 'Failed to send ticket_registered via Meta WhatsApp Cloud API');
            }
        }

        return redirect()->route('ticket.registered', $ticket->qr_code)->with('all_tickets', [$ticket]);
    }

    /**
     * The ticket behind a private link. The code is the credential: the
     * pages it opens show the attendee's details and can start payments,
     * so they are never reachable by the ticket's number.
     *
     * @return array{0: Ticket, 1: Event, 2: Organization}
     */
    private function ticketByCode(string $code, array $with = []): array
    {
        $ticket = Ticket::with(array_merge(['event.organization'], $with))->where('qr_code', $code)->firstOrFail();

        return [$ticket, $ticket->event, $ticket->event->organization];
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

    /** The name given, when it isn't the contact's own; null when it is. */
    private function differentName(Client $client, string $name): ?string
    {
        return mb_strtolower(trim($client->full_name)) === mb_strtolower(trim($name)) ? null : trim($name);
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