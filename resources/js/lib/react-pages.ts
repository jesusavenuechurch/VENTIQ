/**
 * Routes whose page is React. While the move from Blade is under way, a
 * link to one of these moves within the app (Inertia); a link to any other
 * route is a normal page load, since Inertia can't show a Blade page.
 * Add a route here when its page moves; this file goes once all have.
 */
export const REACT_ROUTES = new Set<string>([
    'ticket.find', 'installment.search', 'ticket.download', 'ticket.pay', 'ticket.pay.another', 'ticket.registered',
    'public.event', 'event.short', 'registration.form', 'events.browse',
    'ventiq.money.index',
    'organizer.home', 'organizer.payments.index', 'organizer.accounts.index',
    'organizer.organization.edit', 'organizer.payout.edit', 'organizer.team.index', 'organizer.setup',
    'organizer.events.create', 'organizer.events.edit', 'organizer.events.attendees', 'organizer.events.comp.create', 'organizer.events.guests.create', 'organizer.events.day',
]);
