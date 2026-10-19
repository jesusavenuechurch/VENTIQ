/**
 * Routes whose page is React. While the move from Blade is under way, a
 * link to one of these moves within the app (Inertia); a link to any other
 * route is a normal page load, since Inertia can't show a Blade page.
 * Add a route here when its page moves; this file goes once all have.
 */
export const REACT_ROUTES = new Set<string>([
    'ticket.find', 'installment.search', 'ticket.download', 'ticket.pay', 'ticket.pay.another', 'ticket.registered',
    'public.event', 'event.short', 'registration.form', 'events.browse',
    'organization.invite.show', 'agent.apply', 'account.edit', 'certificates.lookup', 'certificates.verify',
    'login', 'login.link.show', 'org.register.direct', 'agent.registration.form', 'password.reset', 'verification.notice',
    'ventiq.money.index', 'ventiq.organizations.index', 'ventiq.organizations.create', 'ventiq.organizations.edit',
    'ventiq.users.index', 'ventiq.users.create', 'ventiq.users.edit', 'ventiq.events.index', 'ventiq.tickets.index',
    'ventiq.tiers.index', 'ventiq.clients.index', 'ventiq.tier-templates.index', 'ventiq.tier-templates.create', 'ventiq.tier-templates.edit',
    'ventiq.payouts.index', 'ventiq.session-plans.index', 'ventiq.inbox.index', 'ventiq.app-releases.index', 'ventiq.agents.index',
    'agent.home', 'agent.certification',
    'organizer.home', 'organizer.payments.index', 'organizer.accounts.index',
    'organizer.organization.edit', 'organizer.payout.edit', 'organizer.team.index', 'organizer.setup',
    'organizer.events.create', 'organizer.events.edit', 'organizer.events.attendees', 'organizer.events.comp.create', 'organizer.events.guests.create', 'organizer.events.day',
]);
