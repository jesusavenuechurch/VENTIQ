<?php

namespace App\Http\Controllers;

use App\Models\{Agent, AgentCertification, AgentEarning, Organization};
use App\Support\AgentCertificationQuiz;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * A sales agent's own pages: the organizations they brought in and how
 * each is doing, what they've earned, their referral link, and the
 * certification that comes first.
 */
class AgentAreaController extends Controller
{
    public function home(Request $request)
    {
        $agent = $this->agent($request);
        $orgs = Organization::where('agent_id', $agent->id)->withCount('events')->latest()->get();
        $cert = $agent->certification;

        $rows = $orgs->map(function (Organization $org) use ($agent) {
            $phone = preg_replace('/\D/', '', (string) $org->phone);
            $live = $org->events_count > 0;

            return [
                'id'       => $org->id,
                'name'     => $org->name,
                'joined'   => $org->created_at?->format('j M Y'),
                'events'   => $org->events_count,
                'state'    => $live ? 'active' : 'registered',
                'whatsapp' => $phone ? "https://wa.me/{$phone}?text=" . rawurlencode("Hi, this is {$agent->name} from VENTIQ. Just checking in: can I help you set up your first event?") : null,
            ];
        });
        $quiet = $rows->firstWhere('state', 'registered');

        return Inertia::render('Agent/Home', [
            'agent'     => ['name' => $agent->name, 'referralUrl' => $agent->registration_url, 'status' => $agent->status],
            'certified' => (bool) $cert?->passed,
            'cert'      => $cert ? ['score' => $cert->score, 'total' => $cert->total_questions, 'percent' => $cert->score_percentage, 'attempts' => $cert->attempts] : null,
            'stats'     => [
                'organizations' => $rows->count(),
                'active'        => $rows->where('state', 'active')->count(),
                'paid'          => (float) AgentEarning::where('agent_id', $agent->id)->where('status', 'paid')->sum('amount'),
                'owed'          => (float) AgentEarning::where('agent_id', $agent->id)->where('status', 'approved')->sum('amount'),
            ],
            // The one thing worth doing next.
            'next'      => match (true) {
                !$cert?->passed      => 'Get certified first: it takes about ten minutes, and your referral link counts once you pass.',
                $rows->isEmpty()     => "Share your referral link to bring in your first organization. Once they register, they'll show here.",
                (bool) $quiet        => "{$quiet['name']} registered but hasn't made an event yet. A quick WhatsApp can make the difference.",
                default              => 'All your organizations are running events. Keep looking for new ones to bring in.',
            },
            'organizations' => $rows->values(),
            'earnings'  => AgentEarning::where('agent_id', $agent->id)->with('organization')->latest()->limit(50)->get()->map(fn (AgentEarning $e) => [
                'id'           => $e->id,
                'type'         => $e->type === 'milestone_bonus' ? 'Bonus' : 'Commission',
                'organization' => $e->organization?->name,
                'amount'       => (float) $e->amount,
                'status'       => $e->status,
                'date'         => ($e->paid_at ?? $e->created_at)?->format('j M Y'),
            ]),
            'meta'      => ['title' => 'Agent | VENTIQ'],
        ]);
    }

    public function certification(Request $request)
    {
        $agent = $this->agent($request);
        $cert = $agent->certification;

        return Inertia::render('Agent/Certification', [
            'modules'         => AgentCertificationQuiz::modules(),
            'acknowledgments' => AgentCertificationQuiz::acknowledgments(),
            'passPercent'     => AgentCertificationQuiz::PASS_PERCENT,
            'cert'            => $cert ? ['passed' => (bool) $cert->passed, 'score' => $cert->score, 'total' => $cert->total_questions, 'percent' => $cert->score_percentage, 'attempts' => $cert->attempts, 'completed' => $cert->completed_at?->format('j M Y')] : null,
            'referralUrl'     => $agent->registration_url,
            'meta'            => ['title' => 'Certification | VENTIQ'],
        ]);
    }

    public function submitCertification(Request $request)
    {
        $agent = $this->agent($request);
        $acks = array_keys(AgentCertificationQuiz::acknowledgments());
        $request->validate(
            collect($acks)->mapWithKeys(fn ($a) => [$a => ['accepted']])->all() + ['answers' => ['required', 'array']],
            collect($acks)->mapWithKeys(fn ($a) => ["{$a}.accepted" => 'Confirm every statement at the end before submitting.'])->all(),
        );

        $result = AgentCertificationQuiz::grade((array) $request->input('answers'));
        $cert = AgentCertification::firstOrNew(['agent_id' => $agent->id]);
        $alreadyPassed = (bool) $cert->passed;

        $cert->fill([
            'score'             => $alreadyPassed ? max($cert->score, $result['score']) : $result['score'],
            'total_questions'   => $result['total'],
            // A pass stays a pass.
            'passed'            => $alreadyPassed || $result['passed'],
            'last_attempted_at' => now(),
            'completed_at'      => $cert->completed_at ?? ($result['passed'] ? now() : null),
        ]);
        $cert->attempts = ($cert->attempts ?? 0) + 1;
        $cert->save();

        return back()->with($result['passed'] ? 'status' : 'error', $result['passed']
            ? "Certified! You scored {$result['score']} of {$result['total']} ({$result['percent']}%). Your referral link is ready to share."
            : "You scored {$result['score']} of {$result['total']} ({$result['percent']}%). " . AgentCertificationQuiz::PASS_PERCENT . '% passes: read the modules again and have another go.');
    }

    private function agent(Request $request): Agent
    {
        $user = $request->user();
        abort_unless($user->isSalesAgent(), 403);

        return Agent::where('email', $user->email)->firstOr(fn () => abort(403, 'No agent profile for this account.'));
    }
}
