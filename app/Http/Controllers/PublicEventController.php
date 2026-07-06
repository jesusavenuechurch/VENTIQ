<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Organization;
use Illuminate\Http\Request;

class PublicEventController extends Controller
{
    public function show($orgSlug, $eventSlug)
    {
        $organization = Organization::where('slug', $orgSlug)->firstOrFail();

        $event = Event::where('slug', $eventSlug)
            ->where('organization_id', $organization->id)
            ->where('is_public', true)
            ->with(['tiers' => function ($query) {
                $query->where('is_active', true)->orderBy('price', 'asc');
            }])
            ->firstOrFail();

        $canRegister = $event->registration_deadline === null || now()->lt($event->registration_deadline);

        $tierAvailability = [];
        foreach ($event->tiers as $tier) {
            $sold = $tier->tickets()->whereIn('status', ['active', 'checked_in'])->count();
            $available = $tier->capacity ? ($tier->capacity - $sold) : null;
            $tierAvailability[$tier->id] = [
                'sold' => $sold,
                'available' => $available,
                'is_sold_out' => $tier->capacity && $available <= 0,
            ];
        }

        return view('public.event', compact('organization', 'event', 'canRegister', 'tierAvailability'));
    }

    public function listEvents($orgSlug)
    {
        $organization = Organization::where('slug', $orgSlug)->firstOrFail();

        $events = Event::where('organization_id', $organization->id)
            ->where('is_public', true)
            ->where(function ($query) {
                $query->where('event_date', '>=', now())->orWhereNull('event_date');
            })
            ->with('tiers')
            ->orderBy('event_date', 'asc')
            ->get();

        return view('public.events', compact('organization', 'events'));
    }

    /**
     * Full paginated catalogue at /events. This is NOT the homepage —
     * see index() below for that. District and category are optional
     * query-string filters (?district=Maseru&category=music); both are
     * validated against config('constants.districts')/categories so an
     * unrecognized value is silently ignored rather than zeroing out
     * the result set.
     */
    public function browseAll(Request $request)
    {
        $validDistrict = $request->filled('district')
            && in_array($request->district, config('constants.districts'));
    
        $validCategory = $request->filled('category')
            && array_key_exists($request->category, config('constants.categories'));
    
        $when = $request->get('when'); // 'weekend' | 'tonight' | null
        $closingSoon = $request->boolean('closing_soon');
        $free = $request->boolean('free');
    
        $events = Event::where('is_public', true)
            ->where(function ($query) {
                $query->where('event_date', '>=', now())->orWhereNull('event_date');
            })
            ->when($validDistrict, fn ($q) => $q->where('city', $request->district))
            ->when($validCategory, fn ($q) => $q->where('category', $request->category))
            ->when($when === 'weekend', fn ($q) => $this->scopeWeekend($q))
            ->when($when === 'tonight', fn ($q) => $this->scopeTonight($q))
            ->when($closingSoon, fn ($q) => $q->whereNotNull('registration_deadline')
                ->whereBetween('registration_deadline', [now(), now()->addHours(48)]))
            ->when($free, fn ($q) => $q->where('payment_mode', 'free'))
            ->with(['organization', 'tiers' => function ($query) {
                $query->where('is_active', true);
            }])
            ->orderBy('event_date', 'asc')
            ->paginate(12)
            ->withQueryString();
    
        return view('events.browse', compact('events'));
    }

    /**
     * Shared weekend-window logic — also used by the pulse-strip
     * signals() method. Handles "today is already Sat/Sun" correctly
     * rather than always jumping to next week's weekend.
     */
    private function scopeWeekend($query)
    {
        $today = now()->startOfDay();
        $saturday = $today->isSunday()
            ? $today->copy()->subDay()
            : ($today->isSaturday() ? $today->copy() : $today->copy()->next(\Carbon\Carbon::SATURDAY));
        $weekendEnd = $saturday->copy()->addDay()->endOfDay();
    
        return $query->whereBetween('event_date', [$saturday, $weekendEnd]);
    }
    private function scopeTonight($query)
    {
        return $query->whereBetween('event_date', [
            now()->startOfDay()->addHours(17), // 5pm today
            now()->endOfDay(),
        ]);
    }
    /**
     * Powers the homepage discovery carousel. Every card here is a
     * saved query into the same events table the search modal and
     * browse page use — no separate "collections" table or feature.
     *
     * CRITICAL RULE: a collection with a zero count is not included
     * in the response at all. The frontend has no code path that
     * renders an empty card, by design — this endpoint is the only
     * place that decides what's real enough to show.
     */
    public function discover(Request $request)
    {
        $district = $request->get('district');
        $validDistrict = $district && in_array($district, config('constants.districts'));
    
        $collections = [];
    
        $collections[] = $this->buildCollection(
            key: 'weekend',
            label: "This\nWeekend",
            color: '#F07F22',
            query: fn () => Event::where('is_public', true)->tap(fn ($q) => $this->scopeWeekend($q)),
            href: ['when' => 'weekend'],
        );
    
        $collections[] = $this->buildCollection(
            key: 'tonight',
            label: 'Tonight',
            color: '#1D4069',
            query: fn () => Event::where('is_public', true)->tap(fn ($q) => $this->scopeTonight($q)),
            href: ['when' => 'tonight'],
        );
    
        if ($validDistrict) {
            $collections[] = $this->buildCollection(
                key: 'near_you',
                label: "Near\nYou",
                color: '#639922',
                query: fn () => Event::where('is_public', true)
                    ->where('city', $district)
                    ->where('event_date', '>=', now()),
                href: ['district' => $district],
            );
        }
    
        // "New in {city}" — checks each district in turn, uses the
        // first one with real recent activity rather than hardcoding
        // Maseru, so this stays honest as other districts grow.
        foreach (config('constants.districts') as $city) {
            $collection = $this->buildCollection(
                key: 'new_' . \Illuminate\Support\Str::slug($city),
                label: "New in\n{$city}",
                color: '#7F77DD',
                query: fn () => Event::where('is_public', true)
                    ->where('city', $city)
                    ->where('created_at', '>=', now()->subDays(14)),
                href: ['district' => $city],
            );
            if ($collection) {
                $collections[] = $collection;
                break; // one "new in X" card is enough on the homepage
            }
        }
    
        $collections[] = $this->buildCollection(
            key: 'free',
            label: 'Free Events',
            color: '#0F6E56',
            query: fn () => Event::where('is_public', true)
                ->where('payment_mode', 'free')
                ->where('event_date', '>=', now()),
            href: ['free' => 1],
        );
    
        $collections[] = $this->buildCollection(
            key: 'closing_soon',
            label: "Closing\nSoon",
            color: '#BA7517',
            query: fn () => Event::where('is_public', true)
                ->whereNotNull('registration_deadline')
                ->whereBetween('registration_deadline', [now(), now()->addHours(48)]),
            href: ['closing_soon' => 1],
        );
    
        return response()->json(array_values(array_filter($collections)));
    }
    
    /**
     * Runs the query, and returns null (not a zero-count card) if
     * there's nothing to show. $query is a closure so the count and
     * the "pick a cover image" query can share the same conditions
     * without duplicating the where-clauses twice by hand.
     */
    private function buildCollection(string $key, string $label, string $color, \Closure $query, array $href): ?array
    {
        $count = $query()->count();
        if ($count === 0) {
            return null;
        }
    
        $coverEvent = $query()->whereNotNull('banner_image')->latest('created_at')->first();
    
        // Real event names for the active-card list — capped at 3 so the
        // card never has to guess how much text it can fit. `remaining`
        // tells the frontend whether "+N more" is honest to show.
        $sampleNames = $query()->orderBy('event_date', 'asc')->limit(3)->pluck('name')->toArray();
        $remaining = max(0, $count - count($sampleNames));
    
        return [
            'key' => $key,
            'label' => $label,
            'color' => $color,
            'count' => $count,
            'events' => $sampleNames,
            'remaining' => $remaining,
            'image' => $coverEvent ? asset('storage/' . $coverEvent->banner_image) : null,
            'href' => route('events.browse', $href),
        ];
    }
    /**
     * Live search used by the nav search modal (app.blade.php). Ranks
     * results from the given district first but does NOT exclude other
     * districts — a partial match elsewhere in the country still shows,
     * just further down the list. `is_local` is passed back per-result
     * so the view can label out-of-district matches if it wants to.
     */
    public function search(Request $request)
    {
        $q = trim($request->get('q', ''));
        if (strlen($q) < 2) return response()->json([]);

        $district = $request->get('district');
        $validDistrict = $district && in_array($district, config('constants.districts'));

        $query = Event::where('is_public', true)
            ->where(function ($query) use ($q) {
                $query->where('name', 'like', "%{$q}%")
                    ->orWhere('venue', 'like', "%{$q}%")
                    ->orWhere('city', 'like', "%{$q}%");
            });

        if ($validDistrict) {
            $query->orderByRaw('CASE WHEN city = ? THEN 0 ELSE 1 END', [$district]);
        }

        $events = $query->orderBy('event_date', 'asc')
            ->limit(10)
            ->get(['name', 'slug', 'organization_id', 'venue', 'city', 'event_date', 'banner_image']);

        return response()->json($events->map(fn ($e) => [
            'name' => $e->name,
            'subtitle' => trim(($e->venue ?? '') . ($e->city ? ' · ' . $e->city : '')),
            'date' => $e->event_date?->format('M d, Y'),
            'url' => $e->public_url,
            'image' => $e->banner_image ? asset('storage/' . $e->banner_image) : null,
            'is_local' => $validDistrict ? ($e->city === $district) : null,
        ]));
    }

    /**
     * Powers the homepage "Upcoming Events" grid via fetch — called on
     * initial mount and again whenever the nav's district changes. If a
     * district has nothing scheduled, falls back to nationwide upcoming
     * events rather than showing an empty homepage.
     */
    public function upcoming(Request $request)
    {
        $district = $request->get('district');
        $validDistrict = $district && in_array($district, config('constants.districts'));

        $base = fn () => Event::where('is_public', true)
            ->where(function ($q) {
                $q->where('event_date', '>=', now())->orWhereNull('event_date');
            })
            ->with('organization');

        $events = ($validDistrict ? $base()->where('city', $district) : $base())
            ->orderBy('event_date', 'asc')
            ->take(8)
            ->get();

        if ($validDistrict && $events->isEmpty()) {
            $events = $base()->orderBy('event_date', 'asc')->take(8)->get();
        }

        if ($events->isEmpty()) {
            return response()->json($this->dummyUpcomingEvents());
        }

        return response()->json($events->map(fn ($e) => [
            'name' => $e->name,
            'venue' => $e->venue,
            'city' => $e->city,
            'category_label' => $e->category_label,
            'category_color' => $e->category_color,
            'organizer' => $e->organization->name ?? null,
            'date' => $e->event_date?->format('M d'),
            'time' => $e->event_date?->format('g:i A'),
            'image' => $e->banner_image ? asset('storage/' . $e->banner_image) : null,
            'url' => $e->public_url,
        ]));
    }

    /**
     * Same fallback content used in index() when the DB has zero public
     * events, reshaped to match the /events/upcoming JSON contract so the
     * homepage's Alpine component can render it identically either way.
     */
    private function dummyUpcomingEvents(): array
    {
        return [
            [
                'name' => 'Maseru street food & craft market',
                'venue' => 'Maseru Mall', 'city' => 'Maseru',
                'category_label' => 'Markets', 'category_color' => '#F07F22',
                'organizer' => 'VENTIQ', 'date' => now()->addDays(2)->format('M d'),
                'time' => '10:00 AM', 'image' => null, 'url' => '#',
            ],
            [
                'name' => 'Live acoustic sessions at the mountain lodge',
                'venue' => 'Mountain Lodge', 'city' => 'Leribe',
                'category_label' => 'Music', 'category_color' => '#D4537E',
                'organizer' => 'VENTIQ', 'date' => now()->addDays(5)->format('M d'),
                'time' => '7:00 PM', 'image' => null, 'url' => '#',
            ],
            [
                'name' => 'High altitude marathon finish line gala',
                'venue' => 'Setsoto Stadium', 'city' => 'Mokhotlong',
                'category_label' => 'Sports', 'category_color' => '#639922',
                'organizer' => 'VENTIQ', 'date' => now()->addDays(12)->format('M d'),
                'time' => '2:00 PM', 'image' => null, 'url' => '#',
            ],
            [
                'name' => 'Community worship gathering',
                'venue' => 'Berea Community Hall', 'city' => 'Berea',
                'category_label' => 'Worship', 'category_color' => '#7F77DD',
                'organizer' => 'VENTIQ', 'date' => now()->addDays(7)->format('M d'),
                'time' => '6:00 PM', 'image' => null, 'url' => '#',
            ],
        ];
    }

    /**
     * Homepage. URL: /
     *
     * NOTE ON DUMMY FALLBACK EVENTS BELOW:
     * These are `new Event([...])` instances that are never saved —
     * they exist purely so the homepage isn't empty while the
     * database has no public events yet. Every key used here MUST
     * match a real column in $fillable on the Event model. Any key
     * not in $fillable is silently dropped during mass assignment.
     */
    public function index()
    {
        $upcomingEvents = Event::where('is_public', true)
            ->where(function ($query) {
                $query->where('event_date', '>=', now())->orWhereNull('event_date');
            })
            ->with('organization')
            ->orderBy('event_date', 'asc')
            ->take(8)
            ->get();

        // Explicitly empty so the conditional section in the view hides for now.
        // Populate this with a real query (e.g. Event::where('is_sponsored', true))
        // once sponsorship is an actual column.
        $sponsoredEvents = collect();

        $metrics = [
            'total_events' => Event::where('is_public', true)->count(),
            'total_cities' => Event::where('is_public', true)->distinct('city')->count('city'),
            'total_categories' => Event::where('is_public', true)->distinct('category')->count('category'),
        ];

        return view('welcome', compact('upcomingEvents', 'sponsoredEvents', 'metrics'));
    }
}