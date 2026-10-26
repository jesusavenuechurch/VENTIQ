<?php

namespace App\Http\Controllers\Organizer;

use App\Http\Controllers\Controller;
use App\Http\Requests\OrganizerEventRequest;
use App\Models\{Event, OrganizationPaymentMethod};
use App\Services\Events\EventService;
use App\Services\Payments\{PaymentAccountService, PaymentGatewayFactory};
use Illuminate\Http\Request;
use Inertia\Inertia;

/** Creating and editing events in the organizer area. */
class EventsController extends Controller
{
    public function __construct(private EventService $events) {}

    public function create(Request $request)
    {
        $organization = $request->attributes->get('organization');

        return Inertia::render('Organizer/EventForm', $this->formData($request, new Event([
            'is_public'    => true,
            'city'         => 'Maseru',
            'payment_mode' => 'free',
            'status'       => 'draft',
        ]), app(PaymentAccountService::class)->defaultAccountIds($organization), true));
    }

    public function store(OrganizerEventRequest $request)
    {
        $event = $this->events->create(
            $request->attributes->get('organization'),
            $request->validated(),
            $request->file('banner'),
        );

        return redirect()->route('organizer.home')->with('status',
            $event->status === 'published'
                ? "{$event->name} is live. Share its link to start taking registrations."
                : "{$event->name} is saved as a draft. Publish it when you're ready.");
    }

    public function edit(Request $request, Event $event)
    {
        $this->authorizeEvent($request, $event);

        $selected = $event->enabled_payment_method_ids;
        $online = OrganizationPaymentMethod::where('organization_id', $event->organization_id)
            ->where('payment_method', 'online')->value('id');

        $data = $this->formData(
            $request,
            $event,
            // An event that never chose offers every active account; show
            // that as everything ticked.
            $selected === null
                ? app(PaymentAccountService::class)->directAccountsForEvent($event)->pluck('id')->all()
                : array_values(array_diff($selected, [$online])),
            $selected === null ? (bool) $online : in_array($online, $selected ?? []),
        );

        return Inertia::render('Organizer/EventForm', $data);
    }

    public function update(OrganizerEventRequest $request, Event $event)
    {
        $this->authorizeEvent($request, $event);

        $event = $this->events->update($event, $request->validated(), $request->file('banner'));

        $messages = ["{$event->name} is saved."];

        if ($this->events->deactivatedTiers) {
            $messages[] = 'Tickets were already sold on ' . implode(', ', $this->events->deactivatedTiers)
                . ', so it was switched off instead of removed.';
        }

        if ($this->events->paymentOptionsChanged($event)) {
            $unpaid = app(PaymentAccountService::class)->unpaidTicketCount($event);
            if ($unpaid > 0) {
                $messages[] = "{$unpaid} attendee(s) haven't paid yet; their payment page now shows the new options. Payments already submitted keep the account they were made to.";
            }
        }

        return redirect()->route('organizer.events.edit', $event)->with('status', implode(' ', $messages));
    }

    /** The form's props (resources/js/Pages/Organizer/EventForm.tsx). */
    private function formData(Request $request, Event $event, array $selectedAccounts, bool $online): array
    {
        $organization = $request->attributes->get('organization');
        $colours = config('constants.qr_colours');

        return [
            'event'            => [
                'id'                      => $event->id,
                'name'                    => (string) $event->name,
                'tagline'                 => (string) $event->tagline,
                'description'             => (string) $event->description,
                'category'                => (string) $event->category,
                'city'                    => (string) $event->city,
                'isPublic'                => (bool) $event->is_public,
                'byInvitation'            => (bool) $event->by_invitation,
                'date'                    => $event->event_date?->format('Y-m-d') ?? today()->format('Y-m-d'),
                'time'                    => $event->event_date?->format('H:i') ?? '18:00',
                'deadlineDate'            => (string) $event->registration_deadline?->format('Y-m-d'),
                'deadlineTime'            => $event->registration_deadline?->format('H:i') ?? '23:59',
                'venue'                   => (string) $event->venue,
                'location'                => (string) $event->location,
                'paymentMode'             => $event->payment_mode ?? 'free',
                'allowInstallments'       => (bool) $event->allow_installments,
                'depositPercent'          => $event->minimum_deposit_percentage !== null ? (float) $event->minimum_deposit_percentage : 30,
                'installmentInstructions' => (string) $event->installment_instructions,
                'paymentWindowHours'      => $event->payment_window_hours,
                'status'                  => $event->status ?? 'draft',
                'feesSponsored'           => (bool) $event->fees_sponsored,
                'banner'                  => $event->banner_image ? \App\Support\Thumb::url($event->banner_image, 480) : null,
                'hasTickets'              => $event->exists && $event->tickets()->exists(),
            ],
            'accounts'         => OrganizationPaymentMethod::where('organization_id', $organization->id)
                ->where('is_active', true)
                ->where('payment_method', '!=', 'online')
                ->orderBy('display_order')->orderBy('id')
                ->get()
                ->map(fn (OrganizationPaymentMethod $a) => ['id' => $a->id, 'label' => $a->display_label, 'number' => $a->account_number, 'isDefault' => (bool) $a->is_default])
                ->values(),
            'selectedAccounts' => array_map('intval', $selectedAccounts),
            'onlineDrivers'    => PaymentGatewayFactory::enabledMethods(),
            'onlineCatalog'    => PaymentGatewayFactory::CATALOG,
            // Chosen methods: the event's, or for an event that never chose
            // (and new events) every live one when online is on.
            'onlineMethods'    => array_values($event->online_methods ?? ($online ? PaymentGatewayFactory::enabledMethods() : [])),
            'modeLocked'       => $event->exists && $event->tickets()->exists(),
            'tiers'            => $event->exists
                ? $event->tiers()->orderBy('price')->get()->map(fn ($t) => [
                    'id'                    => $t->id,
                    'tier_name'             => $t->tier_name,
                    'price'                 => (float) $t->price,
                    'quantity_available'    => $t->quantity_available,
                    'description'           => (string) $t->description,
                    'quantity_per_purchase' => $t->quantity_per_purchase ?? 1,
                    'color'                 => strtoupper($t->color ?: array_key_first($colours)),
                    'is_active'             => (bool) $t->is_active,
                    'sold'                  => $t->tickets()->count(),
                    'taken'                 => \App\Support\TierCapacity::taken($t),
                ])->all()
                : [['id' => null, 'tier_name' => 'General Admission', 'price' => null, 'quantity_available' => null, 'description' => '',
                    'quantity_per_purchase' => 1, 'color' => array_key_first($colours), 'is_active' => true, 'sold' => 0, 'taken' => 0]],
            // Free events: the Free ticket type's number.
            'expected'         => $event->exists && $event->payment_mode === 'free'
                ? $event->tiers()->orderBy('id')->value('quantity_available') : null,
            'freeTaken'        => $event->exists && $event->payment_mode === 'free' && ($free = $event->tiers()->orderBy('id')->first())
                ? \App\Support\TierCapacity::taken($free) : 0,
            'statuses'         => $event->exists
                ? config('constants.event_statuses')
                : ['draft' => 'Save as draft', 'published' => 'Publish now'],
            'categories'       => collect(config('constants.categories'))->map(fn ($c, $key) => ['value' => $key, 'label' => $c['label']])->values(),
            'colours'          => $colours,
            'fees'             => [
                'percent'   => (float) config('constants.fees.service_percent'),
                'perPerson' => (float) config('constants.fees.operational_per_person'),
            ],
            'windowDefault'    => config('ventiq.payment_window_hours'),
            'canDelete'        => $request->user()->can('delete_event'),
            'meta'             => ['title' => ($event->exists ? "Edit {$event->name}" : 'Create event') . ' | VENTIQ'],
        ];
    }

    /**
     * Delete an event nobody has registered for. Once there are tickets,
     * deleting would take their payments, fees and check-ins with it, so
     * the organizer cancels the event instead.
     */
    public function destroy(Request $request, Event $event)
    {
        $this->authorizeEvent($request, $event);

        if ($event->tickets()->exists()) {
            return back()->with('status', "{$event->name} has registrations, so it can't be deleted. Set its status to Cancelled instead; registration closes and its records are kept.");
        }

        $name = $event->name;
        $event->delete();
        \Illuminate\Support\Facades\Log::info("Event {$event->id} deleted by user {$request->user()->id}");

        return redirect()->route('organizer.home')->with('status', "{$name} has been deleted.");
    }

    /**
     * The event page's QR code as SVG: sharp at any size, so it can go
     * straight onto a poster. The share panel turns it into PNGs in the
     * browser, which keeps the server free of imagick.
     */
    public function qr(Request $request, Event $event)
    {
        $this->authorizeEvent($request, $event);
        abort_unless($url = $event->share_url, 404);

        $svg = \SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')
            ->size(1024)->margin(2)->errorCorrection('Q')
            ->generate($url);

        $headers = ['Content-Type' => 'image/svg+xml', 'Cache-Control' => 'private, max-age=300'];
        if ($request->boolean('download')) {
            $headers['Content-Disposition'] = 'attachment; filename="' . $event->slug . '-qr.svg"';
        }

        return response((string) $svg, 200, $headers);
    }

    /** Put a draft event live in one tap, from the events list or any of its pages. */
    public function publish(Request $request, Event $event)
    {
        $this->authorizeEvent($request, $event);

        if ($event->status !== 'draft') {
            return back()->with('status', "{$event->name} is already live.");
        }

        $event->update(['status' => 'published']);

        return back()->with('status', "{$event->name} is live. Share its link to start selling.");
    }

    private function authorizeEvent(Request $request, Event $event): void
    {
        abort_unless($event->organization_id === $request->attributes->get('organization')->id && $event->kind === \App\Models\Event::KIND_EVENT, 404);
    }
}
