<?php

namespace App\Http\Controllers\Organizer;

use App\Http\Controllers\Controller;
use App\Http\Requests\OrganizerEventRequest;
use App\Models\{Event, OrganizationPaymentMethod};
use App\Services\Events\EventService;
use App\Services\Payments\{PaymentAccountService, PaymentGatewayFactory};
use Illuminate\Http\Request;

/** Creating and editing events in the organizer area. */
class EventsController extends Controller
{
    public function __construct(private EventService $events) {}

    public function create(Request $request)
    {
        $organization = $request->attributes->get('organization');

        return view('organizer.events.form', $this->formData($request, new Event([
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

        return view('organizer.events.form', $data);
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

    private function formData(Request $request, Event $event, array $selectedAccounts, bool $online): array
    {
        $organization = $request->attributes->get('organization');

        return [
            'event'            => $event,
            'accounts'         => OrganizationPaymentMethod::where('organization_id', $organization->id)
                ->where('is_active', true)
                ->where('payment_method', '!=', 'online')
                ->orderBy('display_order')->orderBy('id')
                ->get(),
            'selectedAccounts' => old('account_ids', $selectedAccounts),
            'online'           => (bool) old('online', $online),
            'onlineDrivers'    => PaymentGatewayFactory::enabledMethods(),
            'modeLocked'       => $event->exists && $event->tickets()->exists(),
            'tiers'            => old('tiers', $event->exists
                ? $event->tiers()->orderBy('price')->get()->map(fn ($t) => [
                    'id'                    => $t->id,
                    'tier_name'             => $t->tier_name,
                    'price'                 => (float) $t->price,
                    'quantity_available'    => $t->quantity_available,
                    'description'           => $t->description,
                    'quantity_per_purchase' => $t->quantity_per_purchase ?? 1,
                    'color'                 => $t->color,
                    'is_active'             => (bool) $t->is_active,
                    'sold'                  => $t->tickets()->count(),
                ])->all()
                : [['tier_name' => 'General Admission', 'price' => null, 'quantity_per_purchase' => 1, 'is_active' => true]]),
        ];
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

    private function authorizeEvent(Request $request, Event $event): void
    {
        abort_unless($event->organization_id === $request->attributes->get('organization')->id, 404);
    }
}
