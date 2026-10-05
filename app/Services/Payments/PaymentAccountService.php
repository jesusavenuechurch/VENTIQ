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
 */
class PaymentAccountService
{
    /**
     * The organizer's accounts this event offers. An event that never
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
