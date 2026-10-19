<?php

namespace App\Http\Controllers\Ventiq;

use App\Http\Controllers\Controller;
use App\Models\{Event, Organization, TierTemplate};
use App\Support\Admin\AdminTable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Saved sets of ticket types (e.g. "Early bird, General, VIP") to add
 * to an event in one go. A template belongs to an organization, or to
 * nobody, for any organization.
 */
class TierTemplatesController extends Controller
{
    public function index(Request $request)
    {
        $table = AdminTable::for(TierTemplate::query()->with('organization'), $request)
            ->search(fn ($q, $term) => $q->where('name', 'like', AdminTable::like($term))->orWhere('description', 'like', AdminTable::like($term)))
            ->sorts(['name' => 'name', 'created' => 'created_at'], 'created')
            ->filters(['default' => fn ($q, $v) => $q->where('is_default', $v === 'yes')]);

        return Inertia::render('Ventiq/TierTemplates/Index', [
            'rows'  => $table->paginate()->through(fn (TierTemplate $t) => [
                'id'           => $t->id,
                'name'         => $t->name,
                'description'  => $t->description,
                'organization' => $t->organization?->name,
                'organizationId' => $t->organization_id,
                'tiers'        => collect($t->tiers_config)->pluck('tier_name')->filter()->values(),
                'default'      => (bool) $t->is_default,
                'created'      => $t->created_at?->format('j M Y'),
            ]),
            'state' => $table->state(),
            'meta'  => ['title' => 'Tier templates | VENTIQ'],
        ]);
    }

    public function create()
    {
        return $this->form(null);
    }

    public function store(Request $request)
    {
        $template = TierTemplate::create($this->validated($request));
        $this->onlyDefault($template);

        return redirect()->route('ventiq.tier-templates.edit', $template)->with('status', "{$template->name} is saved.");
    }

    public function edit(TierTemplate $tierTemplate)
    {
        return $this->form($tierTemplate);
    }

    public function update(Request $request, TierTemplate $tierTemplate)
    {
        $tierTemplate->update($this->validated($request));
        $this->onlyDefault($tierTemplate);

        return back()->with('status', "{$tierTemplate->name} is saved.");
    }

    public function destroy(TierTemplate $tierTemplate)
    {
        $tierTemplate->delete();

        return redirect()->route('ventiq.tier-templates.index')->with('status', "{$tierTemplate->name} has been deleted.");
    }

    /** Add the template's ticket types to an event (its organization's, or any for a template for everyone). */
    public function apply(Request $request, TierTemplate $tierTemplate)
    {
        $data = $request->validate(['event_id' => ['required', 'integer', Rule::exists('events', 'id')]]);
        $event = Event::findOrFail($data['event_id']);
        if ($tierTemplate->organization_id && $event->organization_id !== $tierTemplate->organization_id) {
            return back()->withErrors(['event_id' => "That event isn't {$tierTemplate->organization->name}'s."]);
        }

        $count = $tierTemplate->applyToEvent($event);

        return back()->with('status', "{$count} ticket " . ($count === 1 ? 'type' : 'types') . " added to {$event->name}.");
    }

    public function duplicate(TierTemplate $tierTemplate)
    {
        $copy = $tierTemplate->replicate();
        $copy->name = "{$tierTemplate->name} (copy)";
        $copy->is_default = false;
        $copy->save();

        return redirect()->route('ventiq.tier-templates.edit', $copy)->with('status', 'Copied. Rename it and change what you need.');
    }

    private function form(?TierTemplate $t)
    {
        return Inertia::render('Ventiq/TierTemplates/Form', [
            'template'      => $t ? [
                'id' => $t->id, 'name' => $t->name, 'description' => (string) $t->description, 'organization_id' => $t->organization_id,
                'is_default' => (bool) $t->is_default,
                'tiers' => collect($t->tiers_config)->map(fn ($tier) => [
                    'tier_name' => (string) ($tier['tier_name'] ?? ''), 'description' => (string) ($tier['description'] ?? ''),
                    'price' => $tier['price'] ?? '', 'quantity_available' => $tier['quantity_available'] ?? '', 'color' => strtoupper((string) ($tier['color'] ?? '')),
                ])->values(),
            ] : null,
            // Events to apply it to: the template organization's upcoming ones, or anyone's.
            'events'        => $t ? Event::query()->when($t->organization_id, fn ($q) => $q->where('organization_id', $t->organization_id))
                ->where(fn ($q) => $q->whereNull('event_date')->orWhere('event_date', '>=', now()->startOfDay()))
                ->with('organization')->orderBy('event_date')->limit(200)->get()
                ->map(fn ($e) => ['value' => $e->id, 'label' => "{$e->name} · {$e->organization?->name}" . ($e->event_date ? ' · ' . $e->event_date->format('j M Y') : '')]) : [],
            'organizations' => Organization::orderBy('name')->get(['id', 'name'])->map(fn ($o) => ['value' => $o->id, 'label' => $o->name]),
            'colours'       => config('constants.qr_colours'),
            'meta'          => ['title' => ($t ? $t->name : 'New tier template') . ' | VENTIQ'],
        ]);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name'                       => ['required', 'string', 'max:255'],
            'description'                => ['nullable', 'string', 'max:1000'],
            'organization_id'            => ['nullable', 'integer', Rule::exists('organizations', 'id')],
            'is_default'                 => ['boolean'],
            'tiers'                      => ['required', 'array', 'min:1', 'max:20'],
            'tiers.*.tier_name'          => ['required', 'string', 'max:255', 'distinct:ignore_case'],
            'tiers.*.description'        => ['nullable', 'string', 'max:1000'],
            'tiers.*.price'              => ['required', 'numeric', 'min:0'],
            'tiers.*.quantity_available' => ['nullable', 'integer', 'min:1'],
            'tiers.*.color'              => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ], [
            'tiers.*.tier_name.required' => 'Every ticket type needs a name.',
            'tiers.*.tier_name.distinct' => 'Two ticket types have the same name.',
            'tiers.*.price.required'     => 'Every ticket type needs a price.',
        ]);

        return [
            'name' => $data['name'], 'description' => $data['description'] ?? null, 'organization_id' => $data['organization_id'] ?? null,
            'is_default' => (bool) ($data['is_default'] ?? false),
            'tiers_config' => collect($data['tiers'])->map(fn ($t) => [
                'tier_name' => $t['tier_name'], 'description' => $t['description'] ?? null, 'price' => (float) $t['price'],
                'quantity_available' => isset($t['quantity_available']) ? (int) $t['quantity_available'] : null, 'color' => $t['color'] ?? null,
            ])->values()->all(),
        ];
    }

    /** One default per organization (or among templates for everyone). */
    private function onlyDefault(TierTemplate $template): void
    {
        if ($template->is_default) {
            TierTemplate::where('organization_id', $template->organization_id)->whereKeyNot($template->id)->update(['is_default' => false]);
        }
    }
}
