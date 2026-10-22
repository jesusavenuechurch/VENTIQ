<?php

namespace Database\Factories;

use App\Models\AgentEarning;
use Illuminate\Database\Eloquent\Factories\Factory;

class AgentEarningFactory extends Factory
{
    protected $model = AgentEarning::class;

    public function definition(): array
    {
        return [
            'type'         => 'commission',
            'amount'       => 140.00,
            'package_price' => 700.00,
            'package_type' => 'standard',
            'status'       => 'pending',
        ];
    }

    // ── Types ──

    public function commission(float $packagePrice = 700.00): static
    {
        return $this->state([
            'type'          => 'commission',
            // ✅ Uses actual static method from model
            'amount'        => AgentEarning::calculateCommission($packagePrice),
            'package_price' => $packagePrice,
        ]);
    }

    public function milestoneBonus(int $tier = 1): static
    {
        return $this->state([
            'type'                => 'milestone_bonus',
            // ✅ Uses actual static method from model
            'amount'              => AgentEarning::calculateMilestoneBonus($tier),
            'package_price'       => null,
            'package_type'        => null,
            'milestone_tier'      => $tier,
            'milestone_org_count' => $tier * 5,
        ]);
    }

    // ── Statuses ──

    public function pending(): static
    {
        return $this->state(['status' => 'pending']);
    }

    public function approved(): static
    {
        return $this->state([
            'status'      => 'approved',
            'approved_at' => now(),
        ]);
    }

    public function paid(): static
    {
        return $this->state([
            'status'            => 'paid',
            'paid_at'           => now(),
            'payment_method'    => 'mpesa',
            'payment_reference' => 'MP' . fake()->numerify('########'),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state([
            'status' => 'cancelled',
            'notes'  => 'Cancelled: Test cancellation',
        ]);
    }
}