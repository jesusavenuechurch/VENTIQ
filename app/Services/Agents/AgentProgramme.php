<?php

namespace App\Services\Agents;

use App\Mail\{AgentAccountInitialized, AgentPayoutReceipt};
use App\Models\{Agent, AgentEarning, User};
use Illuminate\Support\Facades\{DB, Hash, Log, Mail, Password};
use Illuminate\Support\Str;
use InvalidArgumentException;

/** VENTIQ's sales agents: letting one in, what they've earned, and paying them. */
class AgentProgramme
{
    public const PAYOUT_METHODS = ['ecocash' => 'EcoCash', 'mpesa' => 'M-Pesa', 'bank_transfer' => 'Bank transfer', 'cash' => 'Cash'];

    /**
     * Approve an application: an account with the sales agent role, and an
     * email to set their password. An agent who already has an account
     * keeps it.
     */
    public function approve(Agent $agent, User $by): User
    {
        if ($agent->status === 'approved') {
            throw new InvalidArgumentException("{$agent->name} is already approved.");
        }

        $user = DB::transaction(function () use ($agent, $by) {
            $user = User::firstOrCreate(
                ['email' => $agent->email],
                ['name' => $agent->name, 'password' => Hash::make(Str::random(40)), 'email_verified_at' => now()],
            );
            $user->assignRole('sales_agent');
            $agent->update(['status' => 'approved', 'is_active' => true, 'approved_at' => now(), 'approved_by' => $by->id]);

            return $user;
        });

        $url = route('password.reset', ['token' => Password::createToken($user), 'email' => $user->email]);
        Mail::to($user->email)->send(new AgentAccountInitialized($user, $url));
        Log::info("Agent {$agent->id} approved by user {$by->id}");

        return $user;
    }

    /** Turn an application down. */
    public function decline(Agent $agent, User $by): void
    {
        if ($agent->status === 'approved') {
            throw new InvalidArgumentException("{$agent->name} is already approved: switch them off instead.");
        }

        $agent->update(['status' => 'rejected', 'is_active' => false]);
        Log::info("Agent {$agent->id} declined by user {$by->id}");
    }

    /** Something VENTIQ owes the agent, agreed outside the app, ready to pay. */
    public function recordEarning(Agent $agent, float $amount, string $type, ?int $organizationId, ?string $notes, User $by): AgentEarning
    {
        $earning = AgentEarning::create([
            'agent_id' => $agent->id, 'organization_id' => $organizationId, 'type' => $type, 'amount' => round($amount, 2),
            'status' => 'approved', 'approved_by' => $by->id, 'approved_at' => now(), 'notes' => $notes,
        ]);
        $agent->updateEarnings();

        return $earning;
    }

    /** Pay everything approved and not yet paid, and email the agent a receipt. Returns the total paid. */
    public function payout(Agent $agent, string $method, string $reference, User $by): float
    {
        $earnings = $agent->earnings()->where('status', 'approved')->get();
        if ($earnings->isEmpty()) {
            throw new InvalidArgumentException("Nothing is owed to {$agent->name} right now.");
        }

        $total = (float) $earnings->sum('amount');
        DB::transaction(fn () => $earnings->each(fn (AgentEarning $e) => $e->markAsPaid($method, $reference)));
        $agent->updateEarnings();
        Log::info("Agent {$agent->id} paid M{$total} by user {$by->id}", ['reference' => $reference]);

        Mail::to($agent->email)->send(new AgentPayoutReceipt($agent, $total, $method, $reference, $earnings));

        return $total;
    }
}
