<?php
// app/Services/Events/EventService.php
namespace App\Services\Events;

use App\Models\{Event, EventTier, Organization, OrganizationPaymentMethod};
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Creating and editing an event with its ticket tiers and payment
 * options, kept out of the screens so the organizer area (and whatever
 * replaces it) applies the same rules.
 *
 * $data is the validated organizer form (see OrganizerEventRequest).
 */
class EventService
{
    /** Tiers that couldn't be removed because tickets were sold on them. */
    public array $deactivatedTiers = [];

    public function create(Organization $organization, array $data, ?UploadedFile $banner = null): Event
    {
        return DB::transaction(function () use ($organization, $data, $banner) {
            $event = new Event([
                'organization_id' => $organization->id,
                // Workshop mode lives in Programmes; events made here are standard.
                'event_type'      => 'standard',
                'payment_mode'    => $data['payment_mode'],
            ]);

            $this->fill($event, $data, $banner);
            $event->status = $data['status'] ?? 'draft';
            $event->save();

            if ($event->payment_mode === 'free') {
                $event->tiers()->create([
                    'tier_name'             => 'Free',
                    'price'                 => 0,
                    'quantity_available'    => null,
                    'is_active'             => true,
                    'quantity_per_purchase' => 1,
                ]);
            } else {
                $this->syncTiers($event, $data['tiers'] ?? []);
            }

            return $event;
        });
    }

    public function update(Event $event, array $data, ?UploadedFile $banner = null): Event
    {
        return DB::transaction(function () use ($event, $data, $banner) {
            // Free/paid can only change before anyone has registered.
            if (isset($data['payment_mode']) && !$event->tickets()->exists()) {
                $event->payment_mode = $data['payment_mode'];
            }

            $this->fill($event, $data, $banner);
            if (isset($data['status'])) {
                $event->status = $data['status'];
            }
            $event->save();

            if ($event->payment_mode === 'paid') {
                $this->syncTiers($event, $data['tiers'] ?? []);
            } elseif (!$event->tiers()->exists()) {
                $event->tiers()->create(['tier_name' => 'Free', 'price' => 0, 'is_active' => true, 'quantity_per_purchase' => 1]);
            }

            return $event;
        });
    }

    /** Whether the last save changed which payment options the event offers. */
    public function paymentOptionsChanged(Event $event): bool
    {
        return $event->wasChanged('enabled_payment_method_ids');
    }

    private function fill(Event $event, array $data, ?UploadedFile $banner): void
    {
        $event->fill([
            'name'                  => $data['name'],
            'tagline'               => $data['tagline'] ?? null,
            'description'           => $data['description'] ?? null,
            'category'              => $data['category'],
            'city'                  => $data['city'],
            'is_public'             => (bool) ($data['is_public'] ?? false),
            'event_date'            => Carbon::parse($data['event_date'] . ' ' . $data['event_time']),
            'registration_deadline' => !empty($data['registration_deadline_date'])
                ? Carbon::parse($data['registration_deadline_date'] . ' ' . ($data['registration_deadline_time'] ?? '23:59'))
                : null,
            'venue'                 => $data['venue'] ?? null,
            'capacity'              => $data['capacity'] ?? null,
            'location'              => $data['location'] ?? null,
        ]);

        $paid = ($event->payment_mode ?? $data['payment_mode'] ?? 'free') === 'paid';

        $event->fill([
            'enabled_payment_method_ids' => $paid ? $this->paymentSelection($event->organization_id, $data) : null,
            'online_methods'             => $paid ? array_values($data['online_methods'] ?? []) : null,
            'allow_installments'         => $paid && !empty($data['allow_installments']),
            'minimum_deposit_percentage' => $paid && !empty($data['allow_installments']) ? ($data['minimum_deposit_percentage'] ?? 30) : null,
            'installment_instructions'   => $paid && !empty($data['allow_installments']) ? ($data['installment_instructions'] ?? null) : null,
            'payment_window_hours'       => $paid ? ($data['payment_window_hours'] ?? null) : null,
        ]);

        if ($banner) {
            $event->banner_image = $banner->store('event-banners', 'public');
            \App\Support\Thumb::makeAll($event->banner_image);
        }
    }

    /**
     * The event's chosen organizer accounts, plus the legacy 'online' row
     * when online payment is on (that row only records the on/off choice;
     * see PaymentAccountService).
     *
     * @return int[]
     */
    private function paymentSelection(int $organizationId, array $data): array
    {
        $ids = OrganizationPaymentMethod::where('organization_id', $organizationId)
            ->where('payment_method', '!=', 'online')
            ->whereIn('id', $data['account_ids'] ?? [])
            ->pluck('id')
            ->all();

        if (!empty($data['online'])) {
            $online = OrganizationPaymentMethod::firstOrCreate(
                ['organization_id' => $organizationId, 'payment_method' => 'online'],
                ['is_active' => true, 'display_order' => 0],
            );
            if (!$online->is_active) {
                $online->update(['is_active' => true]);
            }
            array_unshift($ids, $online->id);
        }

        return array_values(array_unique($ids));
    }

    /**
     * Create, update and remove tiers to match the form. A tier that has
     * tickets is never deleted (those tickets point at it); it's
     * deactivated instead so nobody new can buy it.
     */
    private function syncTiers(Event $event, array $tiers): void
    {
        $this->deactivatedTiers = [];
        $keep = [];

        foreach ($tiers as $row) {
            $people = max(1, (int) ($row['quantity_per_purchase'] ?? 1));
            $attributes = [
                'tier_name'             => $row['tier_name'],
                'price'                 => $row['price'],
                'quantity_available'    => $row['quantity_available'] ?? null,
                'description'           => $row['description'] ?? null,
                'quantity_per_purchase' => $people,
                'is_group_ticket'       => $people > 1,
                'color'                 => $row['color'] ?? null,
                'is_active'             => (bool) ($row['is_active'] ?? false),
            ];

            $tier = !empty($row['id']) ? $event->tiers()->whereKey($row['id'])->first() : null;

            if ($tier) {
                $tier->update($attributes);
            } else {
                $tier = $event->tiers()->create($attributes);
            }

            $keep[] = $tier->id;
        }

        $event->tiers()->whereNotIn('id', $keep)->get()->each(function (EventTier $tier) {
            if ($tier->tickets()->exists()) {
                $tier->update(['is_active' => false]);
                $this->deactivatedTiers[] = $tier->tier_name;
            } else {
                $tier->delete();
            }
        });
    }
}
