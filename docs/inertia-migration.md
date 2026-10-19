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
- **Emails and PDFs stay Blade.**

## Checking your work

`npx tsc -p tsconfig.json` (types), `npm run build`, and the PHP tests.
