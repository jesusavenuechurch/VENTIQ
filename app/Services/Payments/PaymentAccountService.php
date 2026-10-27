<?php
// app/Services/Payments/PaymentAccountService.php
namespace App\Services\Payments;

use App\Models\{Event, Organization, OrganizationPaymentMethod, TicketPayment};
use Illuminate\Support\Collection;

/**
 * Which payment options an event offers, kept in one place so the
 * attendee payment page, the event form and (later) the organizer app
 * agree.
 *
 * Two kinds of option:
 *  - Pay online: VENTIQ collects through PayLesotho. Not an organizer
 *    account; stored as the legacy 'online' row only so existing events
 *    keep their on/off choice.
 *  - Pay directly to organizer: the organization's own accounts
 *    (EcoCash numbers, bank accounts, cash), several per method allowed.
 *    Offered only on events VENTIQ sponsors: everywhere else attendees pay
 *    VENTIQ (online, or its merchant number by hand) and VENTIQ pays the
 *    organizer out, so its fees never have to be chased.
 */
class PaymentAccountService
{
    /** Whether attendees may pay into the organizer's own accounts. */
    public function directPaymentAllowed(Event $event): bool
    {
        return (bool) $event->fees_sponsored;
    }

    /** The organizer's accounts attendees are offered (none unless VENTIQ sponsors the event). */
    public function offeredDirectAccounts(Event $event): Collection
    {
        return $this->directPaymentAllowed($event) ? $this->directAccountsForEvent($event) : collect();
    }

    /**
     * VENTIQ's EcoCash merchant number, for paying by hand: when the PIN
     * prompt doesn't work, or when the event offers nothing else.
     *
     * @return array{code: string, name: string}|null
     */
    public function merchantForEvent(Event $event): ?array
    {
        $code = config('gateways.paylesotho.ecocash.merchant_code');
        if (!$code) {
            return null;
        }

        $offered = in_array('ecocash', $this->onlineMethodsForEvent($event), true)
            || $this->offeredDirectAccounts($event)->isEmpty();

        return $offered ? ['code' => (string) $code, 'name' => config('gateways.paylesotho.ecocash.merchant_name') ?: 'VENTIQ'] : null;
    }

    /**
     * The organizer's accounts chosen for this event (whether or not
     * they're offered: see offeredDirectAccounts). An event that never
     * chose (enabled_payment_method_ids = null) offers every active
     * account, which is how all existing events behave.
     */
    public function directAccountsForEvent(Event $event): Collection
    {
        $query = OrganizationPaymentMethod::where('organization_id', $event->organization_id)
            ->where('is_active', true)
            ->where('payment_method', '!=', 'online')
            ->orderBy('display_order')
            ->orderBy('id');

        if ($event->enabled_payment_method_ids !== null) {
            $query->whereIn('id', $event->enabled_payment_method_ids);
        }

        return $query->get();
    }

    /** The online drivers this event offers; empty when online is off. */
    public function onlineMethodsForEvent(Event $event): array
    {
        $online = OrganizationPaymentMethod::where('organization_id', $event->organization_id)
            ->where('payment_method', 'online')
            ->where('is_active', true)
            ->first();

        if (!$online) {
            return [];
        }

        if ($event->enabled_payment_method_ids !== null
            && !in_array($online->id, $event->enabled_payment_method_ids)) {
            return [];
        }

        $live = PaymentGatewayFactory::enabledMethods();

        // An event that chose methods offers those that are live; older
        // events offer every live method.
        return $event->online_methods === null
            ? $live
            : array_values(array_intersect($live, $event->online_methods));
    }

    /**
     * Ways to pay VENTIQ by hand this event offers (M-Pesa to VENTIQ's
     * number, a bank transfer), gated like online payment: the event offers
     * VENTIQ's online account, and chose the method (older events: all).
     *
     * @return string[]
     */
    public function manualMethodsForEvent(Event $event): array
    {
        $online = OrganizationPaymentMethod::where('organization_id', $event->organization_id)
            ->where('payment_method', 'online')->where('is_active', true)->first();
        if (!$online || ($event->enabled_payment_method_ids !== null && !in_array($online->id, $event->enabled_payment_method_ids))) {
            return [];
        }
        $manual = PaymentGatewayFactory::manualMethods();

        return $event->online_methods === null ? $manual : array_values(array_intersect($manual, $event->online_methods));
    }

    /**
     * Accounts a new event starts with: the default account for each
     * method, or every active account when the organization hasn't marked
     * any default (so nothing disappears for organizations that never
     * used defaults).
     *
     * @return int[]
     */
    public function defaultAccountIds(Organization|int $organization): array
    {
        $orgId = $organization instanceof Organization ? $organization->id : $organization;

        $active = OrganizationPaymentMethod::where('organization_id', $orgId)
            ->where('is_active', true)
            ->where('payment_method', '!=', 'online')
            ->get();

        $defaults = $active->where('is_default', true);

        return ($defaults->isNotEmpty() ? $defaults : $active)->pluck('id')->values()->all();
    }

    /**
     * Inactive tickets whose attendee hasn't submitted a payment yet: the
     * people who'll see changed payment options next time they pay.
     */
    public function unpaidTicketCount(Event $event): int
    {
        return $event->tickets()
            ->where('status', 'pending')
            ->whereDoesntHave('payments', fn ($q) => $q->where('status', 'pending')->whereNotNull('submitted_at'))
            ->count();
    }

    /** True once any attendee payment points at this account. */
    public function hasPayments(OrganizationPaymentMethod $account): bool
    {
        return $account->exists
            && TicketPayment::where('organization_payment_method_id', $account->id)->exists();
    }
}
