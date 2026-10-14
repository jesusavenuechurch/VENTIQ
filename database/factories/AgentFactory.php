<?php

namespace Database\Factories;

use App\Models\Agent;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class AgentFactory extends Factory
{
    protected $model = Agent::class;

    public function definition(): array
    {
        return [
            'name'           => fake()->name(),
            'email'          => fake()->unique()->safeEmail(),
            'phone'          => '+266 5' . fake()->numerify('### ####'),
            'city_district'  => fake()->randomElement(['Maseru', 'Leribe', 'Berea', 'Mafeteng']),
            'access_types'   => ['churches', 'schools'],
            'motivation'     => fake()->sentences(3, true),
            'status'         => 'pending',
            'is_active'      => false,
            'referral_token' => 'AG-' . strtoupper(Str::random(6)),
            // Earnings start at zero
            'total_paid_organizations'  => 0,
            'last_milestone_tier'       => 0,
            'total_commissions_earned'  => 0,
            'total_bonuses_earned'      => 0,
            'total_earnings'            => 0,
        ];
    }

    // ── States ──

    public function approved(): static
    {
        return $this->state([
            'status'    => 'approved',
            'is_active' => true,
        ]);
    }

    public function pending(): static
    {
        return $this->state([
            'status'    => 'pending',
            'is_active' => false,
        ]);
    }

    public function withPaidOrgs(int $count): static
    {
        return $this->state([
            'total_paid_organizations' => $count,
        ]);
    }
}