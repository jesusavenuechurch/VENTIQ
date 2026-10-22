<?php

namespace App\Http\Controllers\Ventiq;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Support\Admin\AdminTable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Every organization on VENTIQ, for super admins: find one, open its
 * organizer area, and the settings only VENTIQ changes (its web address,
 * whether it's active, kept while unused, or has workshop mode). Its
 * profile is edited from inside its organizer area.
 */
class OrganizationsController extends Controller
{
    public function index(Request $request)
    {
        $table = AdminTable::for(Organization::query()->withCount(['events', 'members']), $request)
            ->search(fn ($q, $term) => $q->where('name', 'like', AdminTable::like($term))
                ->orWhere('email', 'like', AdminTable::like($term))
                ->orWhere('slug', 'like', AdminTable::like($term))
                ->orWhere('phone', 'like', AdminTable::like(preg_replace('/\D/', '', $term) ?: $term)))
            ->sorts(['name' => 'name', 'created' => 'created_at', 'events' => 'events_count'], 'created')
            ->filters([
                'active' => fn ($q, $v) => $q->where('is_active', $v === 'yes'),
                'payout' => fn ($q, $v) => $v === 'yes' ? $q->whereNotNull('payout_method') : $q->whereNull('payout_method'),
            ]);

        return Inertia::render('Ventiq/Organizations/Index', [
            'rows'  => $table->paginate()->through(fn (Organization $o) => [
                'id'       => $o->id,
                'name'     => $o->name,
                'email'    => $o->email,
                'phone'    => $o->phone,
                'slug'     => $o->slug,
                'active'   => (bool) $o->is_active,
                'events'   => $o->events_count,
                'members'  => $o->members_count,
                'payout'   => $o->hasPayoutDetails(),
                'created'  => $o->created_at?->format('j M Y'),
            ]),
            'state' => $table->state(),
            'meta'  => ['title' => 'Organizations | VENTIQ'],
        ]);
    }

    public function create()
    {
        return Inertia::render('Ventiq/Organizations/Form', ['organization' => null, 'meta' => ['title' => 'New organization | VENTIQ']]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['slug'] = ($data['slug'] ?? null) ?: $this->freeSlug($data['name']);
        $organization = Organization::create($data + ['tagline' => $data['name']]);
        Log::info("Organization {$organization->id} created by super admin {$request->user()->id}");

        return redirect()->route('ventiq.organizations.edit', $organization)
            ->with('status', "{$organization->name} is set up. Open its organizer area to add its details and first event.");
    }

    public function edit(Organization $organization)
    {
        return Inertia::render('Ventiq/Organizations/Form', [
            'organization' => [
                'id'              => $organization->id,
                'name'            => $organization->name,
                'slug'            => $organization->slug,
                'email'           => $organization->email,
                'phone'           => $organization->phone,
                'is_active'       => (bool) $organization->is_active,
                'keep_account'    => (bool) $organization->keep_account,
                'workshop_enabled' => (bool) $organization->workshop_enabled,
                'events'          => $organization->events()->count(),
                'members'         => $organization->members()->with('roles')->orderBy('name')->get()->map(fn ($u) => [
                    'id' => $u->id, 'name' => $u->name, 'email' => $u->email, 'role' => \App\Support\TeamRoles::label($u->roles->first()?->name),
                ]),
                'payout'          => $organization->hasPayoutDetails() ? $organization->payoutSummary() : null,
                'eventsPage'      => route('public.events', $organization->slug),
                'created'         => $organization->created_at?->format('j M Y'),
            ],
            'meta' => ['title' => "{$organization->name} | VENTIQ"],
        ]);
    }

    public function update(Request $request, Organization $organization)
    {
        $data = $this->validated($request, $organization);
        $data['slug'] = ($data['slug'] ?? null) ?: $organization->slug;
        $organization->update($data);

        return back()->with('status', "{$organization->name} is saved.");
    }

    /** Only an organization nobody has made events for: anything more would take money records with it. */
    public function destroy(Request $request, Organization $organization)
    {
        if ($organization->events()->exists()) {
            return back()->with('error', "{$organization->name} has events, so it can't be deleted. Switch it off instead.");
        }

        $name = $organization->name;
        $organization->members()->update(['organization_id' => null]);
        $organization->delete();
        Log::info("Organization {$organization->id} deleted by super admin {$request->user()->id}");

        return redirect()->route('ventiq.organizations.index')->with('status', "{$name} has been deleted.");
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?Organization $organization = null): array
    {
        $digits = preg_replace('/\D/', '', (string) $request->input('phone'));
        $request->merge(['phone' => $digits === '' ? null : '+266' . substr($digits, -8)]);

        return $request->validate([
            'name'             => ['required', 'string', 'max:255', Rule::unique('organizations', 'name')->ignore($organization?->id)],
            'slug'             => ['nullable', 'alpha_dash', 'max:255', Rule::unique('organizations', 'slug')->ignore($organization?->id)],
            'email'            => ['required', 'email', 'max:255'],
            'phone'            => ['nullable', 'regex:/^\+266[0-9]{8}$/'],
            'is_active'        => ['boolean'],
            'keep_account'     => ['boolean'],
            'workshop_enabled' => ['boolean'],
        ], [
            'phone.regex' => 'Enter an 8-digit Lesotho number.',
            'slug.alpha_dash' => 'Use letters, numbers and dashes only.',
        ]);
    }

    private function freeSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'organization';
        $slug = $base;
        for ($i = 2; Organization::where('slug', $slug)->exists(); $i++) {
            $slug = "{$base}-{$i}";
        }

        return $slug;
    }
}
