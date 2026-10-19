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
- **Emails and PDFs stay Blade.**

## Checking your work

`npx tsc -p tsconfig.json` (types), `npm run build`, and the PHP tests.

## Progress

| Phase | Pages | State |
|---|---|---|
| 1. Foundation | Find my ticket | Done |
| 2. Attendee pages | Event page, registration (and its closed and error pages), payment (PIN prompt, EcoCash by hand, organizer accounts), confirmation, the ticket pass, inactive ticket, moved link | Done |
| 3. Organizer area | Site and organizer layouts, events home, payments to confirm and the review page, payment accounts, settings (organization, payouts, team), setup, the event form with Ventiq Assist (`/assist/event-content`), attendees and money, issue a ticket, guest-list import, event day (live with `usePoll`), Browse events | Done |
| 4. VENTIQ admin | Money page, then each Filament resource | Next |
| 5. The rest | Sessions, auth, home, about, pricing, terms, privacy, an organization's own events page | |
| 6. Removal | Filament, Livewire, Alpine | |

Stays Blade: emails, and the ticket PDF (`tickets/avatar.blade.php`).

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
