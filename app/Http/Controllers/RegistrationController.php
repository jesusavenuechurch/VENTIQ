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
use Illuminate\Support\Facades\Validator;

class RegistrationController extends Controller
{
    /**
     * Show registration form
     */
    public function showForm($orgSlug, $eventSlug, Request $request)
    {
        $organization = Organization::where('slug', $orgSlug)->firstOrFail();

        $event = Event::where('slug', $eventSlug)
            ->where('organization_id', $organization->id)
            ->where('is_public', true)
            ->with(['tiers' => function($query) {
                $query->where('is_active', true)->orderBy('price', 'asc');
            }])
            ->firstOrFail();

        // Check if registration is still open
        if ($event->registration_deadline && now()->gt($event->registration_deadline)) {
            return view('public.registration-closed', compact('event', 'organization'));
        }

        // Get selected tier from URL parameter
        $selectedTierId = $request->query('tier');
        $selectedTier = null;

        if ($selectedTierId) {
            $selectedTier = $event->tiers->firstWhere('id', $selectedTierId);
        }

        // Get active payment methods from organization
        $paymentMethods = $organization->paymentMethods()
            ->where('is_active', true)
            ->orderBy('display_order')
            ->get();

        return view('public.register', compact('organization', 'event', 'selectedTier', 'paymentMethods'));
    }

    /**
     * Process registration
     */
    public function register(Request $request, $orgSlug, $eventSlug)
    {
        // Normalize phone before validation
        $request->merge([
            'phone' => $this->normalizePhone($request->phone),
        ]);
         dd($request->phone);

        $organization = Organization::where('slug', $orgSlug)->firstOrFail();

        $event = Event::where('slug', $eventSlug)
            ->where('organization_id', $organization->id)
            ->where('is_public', true)
            ->firstOrFail();

        $tier = EventTier::findOrFail($request->tier_id);
        $isFree = $tier->price == 0;

        $quantityPerPurchase = $tier->quantity_per_purchase ?? 1;

        $rules = [
            'tier_id' => 'required|exists:event_tiers,id',
            'full_name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'required|string|regex:/^\+266[0-9]{8}$/',
            'terms' => 'accepted',
            'has_whatsapp' => 'nullable|boolean',
            'preferred_delivery' => 'nullable|in:email,whatsapp,both',
        ];

        if ($quantityPerPurchase > 1) {
            for ($i = 2; $i <= $quantityPerPurchase; $i++) {
                $rules["companion_{$i}_name"] = 'required|string|max:255';
                $rules["companion_{$i}_phone"] = 'nullable|string';
                $rules["companion_{$i}_email"] = 'nullable|email';
            }
        }

        if (!$isFree) {
            $rules['payment_method_id'] = 'required|exists:organization_payment_methods,id';

            if ($event->allow_installments) {
                $rules['payment_type'] = 'required|in:full,deposit';
                $rules['deposit_amount'] = 'nullable|numeric|min:0';
            }
        }

        $validated = $request->validate($rules);

        DB::beginTransaction();
        try {
            $primaryClient = Client::firstOrCreate(
                ['phone' => $validated['phone'], 'organization_id' => $organization->id],
                [
                    'full_name' => $validated['full_name'],
                    'email' => $validated['email'] ?? null,
                    'status' => 'active',
                    'notes' => 'Self-registered via public event page',
                    'created_by' => null,
                ]
            );

            if (!$primaryClient->wasRecentlyCreated) {
                $primaryClient->update([
                    'full_name' => $validated['full_name'],
                    'email' => $validated['email'] ?? null,
                ]);
            }

            $paymentMethod = null;
            $paymentMethodName = 'free';
            $paymentAmount = $tier->price;
            $paymentType = 'full';

            if (!$isFree && $request->payment_method_id) {
                $paymentMethod = OrganizationPaymentMethod::findOrFail($request->payment_method_id);
                $paymentMethodName = $paymentMethod->payment_method;

                if ($event->allow_installments && $request->payment_type === 'deposit') {
                    $minimumDeposit = ($tier->price * ($event->minimum_deposit_percentage ?? 30)) / 100;
                    $depositAmount = $request->deposit_amount ?? $minimumDeposit;
                    $paymentAmount = max($minimumDeposit, min($depositAmount, $tier->price));
                    $paymentType = 'deposit';
                }
            }

            $pricePerTicket = $tier->price / $quantityPerPurchase;
            $paymentPerTicket = $paymentAmount / $quantityPerPurchase;

            $ticketStatus = $isFree ? 'active' : 'pending';
            $paymentStatus = $isFree ? 'completed' : ($paymentType === 'deposit' ? 'partial' : 'pending');

            $hasWhatsApp = $request->has('has_whatsapp') && $request->has_whatsapp;
            $preferredDelivery = $this->determinePreferredDelivery($request, $validated);

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
                            'full_name' => $request->input("companion_{$i}_name"),
                            'email' => $request->input("companion_{$i}_email"),
                            'status' => 'active',
                            'notes' => "Companion ticket purchased by {$validated['full_name']}",
                            'created_by' => null,
                        ]
                    );
                }

                $qrCode = 'QR-' . Str::uuid();

                $ticket = Ticket::create([
                    'event_id' => $event->id,
                    'client_id' => $client->id,
                    'event_tier_id' => $tier->id,
                    'qr_code' => $qrCode,
                    'status' => $ticketStatus,
                    'payment_method' => $paymentMethodName,
                    'amount' => $pricePerTicket,
                    'amount_paid' => 0,
                    'payment_status' => $paymentStatus,
                    'payment_reference' => $request->payment_reference,
                    'delivery_method' => $validated['email'] ?? null ? 'email' : 'whatsapp',
                    'delivered_at' => $isFree ? now() : null,
                    'created_by' => null,
                    'has_whatsapp' => $hasWhatsApp,
                    'preferred_delivery' => $preferredDelivery,
                    'delivery_status' => 'pending',
                ]);

                if (!$isFree) {
                    TicketPayment::create([
                        'ticket_id' => $ticket->id,
                        'amount' => $paymentPerTicket,
                        'payment_method' => $paymentMethodName,
                        'payment_reference' => $request->payment_reference,
                        'status' => 'pending',
                        'payment_date' => now(),
                        'payment_type' => $paymentType,
                    ]);
                }

                $createdTickets[] = $ticket;
            }

            DB::commit();

            foreach ($createdTickets as $ticket) {
                $ticket->load(['client', 'event', 'tier', 'event.organization']);

                if (!$ticket->client->email) {
                    continue;
                }

                if ($isFree) {
                    \Mail::to($ticket->client->email)
                        ->send(new \App\Mail\TicketApprovedMail($ticket));
                } else {
                    \Mail::to($ticket->client->email)
                        ->send(new \App\Mail\TicketPendingMail($ticket));
                }
            }

            return redirect()->route('registration.confirmation', [
                'orgSlug' => $orgSlug,
                'eventSlug' => $eventSlug,
                'ticketId' => $createdTickets[0]->id,
            ])->with('all_tickets', $createdTickets);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Registration failed: ' . $e->getMessage());
            \Log::error($e->getTraceAsString());

            return back()
                ->withInput()
                ->with('error', 'Registration failed. Please try again or contact support. Error: ' . $e->getMessage());
        }
    }
    /**
     * Show confirmation page
     */
    public function confirmation($orgSlug, $eventSlug, $ticketId)
    {
        $organization = Organization::where('slug', $orgSlug)->firstOrFail();

        $event = Event::where('slug', $eventSlug)
            ->where('organization_id', $organization->id)
            ->firstOrFail();

        $ticket = Ticket::with(['client', 'tier', 'payments'])
            ->where('event_id', $event->id)
            ->findOrFail($ticketId);

        // Get all tickets from this registration (from session)
        $allTickets = collect(session('all_tickets', [$ticket]));

        // Get payment method details if pending
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
     * Determine preferred delivery method
     */
    private function determinePreferredDelivery(Request $request, array $validated): string
    {
        // If user explicitly selected delivery preference
        if ($request->has('preferred_delivery')) {
            return $validated['preferred_delivery'];
        }

        // Auto-determine based on what's available
        $hasEmail = !empty($validated['email']);
        $hasWhatsApp = $request->has('has_whatsapp') && $request->has_whatsapp;

        if ($hasEmail && $hasWhatsApp) {
            return 'both';
        } elseif ($hasWhatsApp) {
            return 'whatsapp';
        } elseif ($hasEmail) {
            return 'email';
        }

        // Default to WhatsApp if phone is provided
        return 'whatsapp';
    }

    /**
     * Normalize phone number
     */
    private function normalizePhone($phone)
    {
        $phone = preg_replace('/\D/', '', $phone);
        if (!str_starts_with($phone, '266')) {
            $phone = '266' . $phone;
        }
        return '+' . $phone;
    }
}