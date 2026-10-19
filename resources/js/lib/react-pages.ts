/**
 * Routes whose page is React. While the move from Blade is under way, a
 * link to one of these moves within the app (Inertia); a link to any other
 * route is a normal page load, since Inertia can't show a Blade page.
 * Add a route here when its page moves; this file goes once all have.
 */
export const REACT_ROUTES = new Set<string>([
    'ticket.find', 'installment.search', 'ticket.download', 'ticket.pay', 'ticket.pay.another', 'ticket.registered',
    'public.event', 'event.short', 'registration.form',
    'organizer.home', 'organizer.payments.index', 'organizer.accounts.index',
    'organizer.organization.edit', 'organizer.payout.edit',
]);
