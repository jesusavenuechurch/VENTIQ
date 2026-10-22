# Moving VENTIQ to React (Inertia)

Branch `inertia-react`. Blade and React pages run side by side until every
page has moved; then Filament, Livewire and Alpine are removed.

## Where things go

| What | Where |
|---|---|
| Pages | `resources/js/Pages/<Area>/<Name>.tsx`, rendered with `Inertia::render('<Area>/<Name>', [...])` |
| Layouts | `resources/js/Layouts` (`GuestLayout` for attendee pages) |
| shadcn components, styled as VENTIQ | `resources/js/components/ui` |
| Shared pieces (`Field`, ...) | `resources/js/components` |
| Props every page gets | `HandleInertiaRequests::share()`, typed in `resources/js/types/index.d.ts` |
| Styles | `resources/css/inertia.css` (React only; `app.css` stays for Blade) |

## Rules while both exist

- **Leaving React for a Blade page** (a redirect after a form a React page
  sent): return `Inertia::location($url)`, not `redirect()`. Inertia
  follows redirects in the background, and a Blade page can't be shown that way.
- **Link previews**: pass `'meta' => ['title' => ..., 'description' => ..., 'image' => ...]`;
  `inertia.blade.php` writes the tags into the HTML WhatsApp and Facebook read.
- **Links** use Ziggy's `route('name', params)`, the same names as in PHP.
- **Money** shows as `money(value)` from `@/lib/utils` (M1,234.50).
- **Colours keep one meaning each**: brand navy, action orange (waiting
  on the person), mint (good news), lilac (VENTIQ itself).
- **Tests** check the page and its props:
  `->assertInertia(fn ($page) => $page->component('Tickets/Find')->where(...))`.
  A page a controller names must exist (`ensure_pages_exist`).
- **Confirm before something final** with `ConfirmDialog` (`components/confirm-dialog`), not `confirm()`.
- **A form whose save comes back to the same page** (the event form) uses `preserveState: 'errors'`, so after saving it starts again from the server's data.
- **Admin lists** use `AdminTable` (PHP) with `DataTable` (React): search, sorts and filters are named on the server and live in the URL; `rowId` + `bulk` add picking rows.
- **Emails and PDFs stay Blade.**

## Checking your work

`npx tsc -p tsconfig.json` (types), `npm run build`, and the PHP tests.

## Progress

| Phase | Pages | State |
|---|---|---|
| 1. Foundation | Find my ticket | Done |
| 2. Attendee pages | Event page, registration (and its closed and error pages), payment (PIN prompt, EcoCash by hand, organizer accounts), confirmation, the ticket pass, inactive ticket, moved link | Done |
| 3. Organizer area | Site and organizer layouts, events home, payments to confirm and the review page, payment accounts, settings (organization, payouts, team), setup, the event form with Ventiq Assist (`/assist/event-content`), attendees and money, issue a ticket, guest-list import, event day (live with `usePoll`), Browse events | Done |
| 4. VENTIQ admin | `/ventiq/*` (AdminLayout, DataTable + AdminTable): money, payouts, organizations, events, tickets, users, agents, inbox, session plans, ticket types, clients, tier templates, scanner app. Agents' own pages at `/agent` (home, certification). Dropped: packages, organizational records, the Filament dashboard widgets and event dashboard (event day replaces it) | Done |
| 5. The rest | Sign in and sign-up pages, account, team invite, certificates, home, about, pricing, terms, privacy, an organization's own events page. Sessions (SessionsLayout, shared props from `EnsureSessionsAccess`): desk, schedule, live console, report, reports, check-in list and pass, programmes, the plan page and its payment (replacing Filament's Session Plan), and the public join and self check-in pages | Done |
| 6. Removal | Filament (and `/admin`, which now sends people to their own home), Livewire, Alpine, the old Blade layout, the package purchase and approval flows, organizational records | Done |

Stays Blade: emails and PDFs (the ticket pass `tickets/avatar.blade.php`, certificates, and the Sessions report, attendance and check-in pass PDFs).

Terms and privacy are drawn in the browser like every other page (no SSR service runs); their title and description are in the HTML the server sends.

Left on purpose: the package and organizational-record models and tables.
Packages still give each new organization its free trial row and set the
team size limit (`TeamController`), and both tables hold past data. Removing
them is its own cleanup, with a decision on the team limit first.

`lib/react-pages.ts` (`REACT_ROUTES`) can go too: every page is React now, so
`AppLink` could always use Inertia's `Link`, except for downloads (PDFs, QR
images, the scanner app), which need a normal page load.

## Open questions (decide before building)

- **A ticket that reaches nobody.** At registration the attendee can choose
  not to get their ticket by email. If they also left the email out, or their
  number isn't on WhatsApp, the ticket is delivered nowhere: they only have
  the confirmation page. Options to weigh: require one working channel (email
  when they say they have no WhatsApp); show the ticket link and "save it"
  prompt more strongly on the confirmation page; fall back to SMS; and flag
  these tickets to the organizer (the attendee list already shows "not
  delivered" for a failed WhatsApp send). Raised during the migration, not
  decided.

- **Agent commissions.** Commissions used to be made when VENTIQ approved an
  organization's first paid package (20%, plus milestone bonuses). Packages
  are gone, so nothing makes new earnings: for now a super admin records
  each one by hand in Admin › Agents. Decide the new rule (a share of VENTIQ's
  fees on a referred organization's first event, a flat amount per
  organization that runs an event, ...), then build it in. The certification
  quiz (`App\Support\AgentCertificationQuiz`) still teaches the package rules
  in modules 1 and 3 and questions 1 and 5 to 8; rewrite those with it.

## UX bugs to go through after the migration

- **Fixed: tabs jumping.** Each area's shell (site header and tabs) now stays
  mounted between its pages (`shellFor` in `resources/js/app.tsx`: Admin,
  Events, Sessions, Settings), the admin tab you're on is kept in view, and
  the page keeps room for the scrollbar so it doesn't shift sideways.
  Sessions tabs remember the page you left them on, like a phone's tab bar.
- **Fixed: settings felt like Events.** Organization, Payouts and Team are
  their own "Organization settings" area with a way back to Events or
  Sessions, whichever you came from.
- **Done: roles in Sessions** (`App\Support\SessionsAccess`): Admin
  everything; Staff runs sessions, programmes and reports; Door checks people
  in; Viewer reads reports and programmes; only Admins buy plans. Someone
  without a role is a Viewer, unless the organization has no Admin.
- **Done: session events.** Events have a `kind`: `event` (tickets),
  `session` (made to hold a tracked session's attendees) or `programme`. Only
  `event` shows in the organizer's events and the admin's events (the others
  are under Kind there), and organizer event pages refuse the other kinds.
