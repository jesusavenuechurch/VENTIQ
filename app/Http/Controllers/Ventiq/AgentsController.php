<?php

namespace App\Http\Controllers\Ventiq;

use App\Http\Controllers\Controller;
use App\Models\{Agent, AgentEarning, Organization};
use App\Services\Agents\AgentProgramme;
use App\Support\Admin\AdminTable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use InvalidArgumentException;

/** VENTIQ's sales agents, for super admins: applications, earnings, and paying them. */
class AgentsController extends Controller
{
    public function __construct(private AgentProgramme $programme) {}

    public function index(Request $request)
    {
        $table = AdminTable::for(Agent::query()->with('certification')->withCount('organizations')
            ->withSum(['earnings as owed' => fn ($q) => $q->where('status', 'approved')], 'amount')
            ->withSum(['earnings as paid' => fn ($q) => $q->where('status', 'paid')], 'amount'), $request)
            ->search(fn ($q, $term) => $q->where('name', 'like', AdminTable::like($term))
                ->orWhere('email', 'like', AdminTable::like($term))
                ->orWhere('phone', 'like', AdminTable::like($term))
                ->orWhere('referral_token', 'like', AdminTable::like($term)))
            ->sorts(['name' => 'name', 'created' => 'created_at', 'owed' => 'owed'], 'created')
            ->filters([
                'status' => fn ($q, $v) => $q->where('status', $v),
                'owed'   => fn ($q) => $q->whereHas('earnings', fn ($e) => $e->where('status', 'approved')),
            ]);

        return Inertia::render('Ventiq/Agents', [
            'rows'          => $table->paginate()->through(fn (Agent $a) => [
                'id'            => $a->id,
                'name'          => $a->name,
                'email'         => $a->email,
                'phone'         => $a->phone,
                'district'      => $a->city_district,
                'motivation'    => $a->motivation,
                'status'        => $a->status,
                'token'         => $a->referral_token,
                'referralUrl'   => $a->registration_url,
                'organizations' => $a->organizations_count,
                'certified'     => match (true) {
                    !$a->certification || !$a->certification->attempts => null,
                    default => ['passed' => (bool) $a->certification->passed, 'percent' => $a->certification->score_percentage, 'attempts' => $a->certification->attempts],
                },
                'owed'          => (float) $a->owed,
                'paid'          => (float) $a->paid,
                'applied'       => $a->created_at?->format('j M Y'),
            ]),
            'state'         => $table->state(),
            'pending'       => Agent::where('status', 'pending')->count(),
            'methods'       => AgentProgramme::PAYOUT_METHODS,
            // An agent's earnings and organizations, when one is opened (?agent=).
            'agent'         => fn () => ($id = $request->integer('agent')) ? $this->detail($id) : null,
            'meta'          => ['title' => 'Agents | VENTIQ'],
        ]);
    }

    public function approve(Request $request, Agent $agent)
    {
        try {
            $this->programme->approve($agent, $request->user());
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', "{$agent->name} is approved. They've been emailed a link to set their password.");
    }

    public function decline(Request $request, Agent $agent)
    {
        try {
            $this->programme->decline($agent, $request->user());
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', "{$agent->name}'s application is declined.");
    }

    public function earning(Request $request, Agent $agent)
    {
        $data = $request->validate([
            'amount'          => ['required', 'numeric', 'min:0.01'],
            'type'            => ['required', Rule::in(['commission', 'milestone_bonus'])],
            'organization_id' => ['nullable', 'integer', Rule::exists('organizations', 'id')],
            'notes'           => ['nullable', 'string', 'max:500'],
        ]);
        $this->programme->recordEarning($agent, (float) $data['amount'], $data['type'], $data['organization_id'] ?? null, $data['notes'] ?? null, $request->user());

        return back()->with('status', 'M' . number_format((float) $data['amount'], 2) . " recorded for {$agent->name}, ready to pay.");
    }

    public function payout(Request $request, Agent $agent)
    {
        $data = $request->validate([
            'method'    => ['required', Rule::in(array_keys(AgentProgramme::PAYOUT_METHODS))],
            'reference' => ['required', 'string', 'max:100'],
        ]);

        try {
            $total = $this->programme->payout($agent, $data['method'], $data['reference'], $request->user());
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', 'Paid ' . $agent->name . ' M' . number_format($total, 2) . '. Their receipt is on its way by email.');
    }

    /** @return array<string, mixed>|null */
    private function detail(int $id): ?array
    {
        $agent = Agent::find($id);

        return $agent ? [
            'id'            => $agent->id,
            'name'          => $agent->name,
            'organizations' => Organization::where('agent_id', $agent->id)->withCount('events')->latest()->get()
                ->map(fn ($o) => ['id' => $o->id, 'name' => $o->name, 'events' => $o->events_count, 'joined' => $o->created_at?->format('j M Y')]),
            'earnings'      => AgentEarning::where('agent_id', $agent->id)->with('organization')->latest()->get()->map(fn (AgentEarning $e) => [
                'id' => $e->id, 'type' => $e->type === 'milestone_bonus' ? 'Bonus' : 'Commission', 'organization' => $e->organization?->name,
                'amount' => (float) $e->amount, 'status' => $e->status, 'notes' => $e->notes,
                'date' => $e->created_at?->format('j M Y'), 'paid' => $e->paid_at ? "{$e->paid_at->format('j M Y')} · {$e->payment_reference}" : null,
            ]),
            'organizationOptions' => Organization::where('agent_id', $agent->id)->orderBy('name')->get(['id', 'name'])->map(fn ($o) => ['value' => $o->id, 'label' => $o->name]),
        ] : null;
    }
}
