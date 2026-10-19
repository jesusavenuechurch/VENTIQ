<?php

use App\Mail\{AgentAccountInitialized, AgentPayoutReceipt};
use App\Models\{Agent, AgentCertification, AgentEarning, Organization, User};
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    $this->super = User::factory()->create(['organization_id' => null]);
    $this->super->assignRole('super_admin');
    $this->agent = Agent::factory()->create(['name' => 'Mpho Lebona', 'email' => 'mpho@example.com', 'status' => 'pending']);
});

it('approves an agent: an account with the agent role, and a password email', function () {
    $this->actingAs($this->super)->post(route('ventiq.agents.approve', $this->agent))->assertSessionHas('status');

    $user = User::where('email', 'mpho@example.com')->sole();
    expect($user->hasRole('sales_agent'))->toBeTrue()->and($this->agent->fresh()->status)->toBe('approved');
    Mail::assertSent(AgentAccountInitialized::class);

    $this->post(route('ventiq.agents.approve', $this->agent))->assertSessionHas('error');
    $this->post(route('ventiq.agents.decline', $this->agent))->assertSessionHas('error');
});

it('records an earning and pays it, with a receipt that has the total', function () {
    $org = Organization::factory()->create(['agent_id' => $this->agent->id]);
    $this->actingAs($this->super)->post(route('ventiq.agents.earning', $this->agent), ['amount' => 150, 'type' => 'commission', 'organization_id' => $org->id, 'notes' => 'First event'])
        ->assertSessionHas('status');
    AgentEarning::create(['agent_id' => $this->agent->id, 'type' => 'milestone_bonus', 'amount' => 300, 'status' => 'approved']);

    $this->get(route('ventiq.agents.index', ['filter' => ['owed' => '1'], 'agent' => $this->agent->id]))
        ->assertInertia(fn ($page) => $page->where('rows.data.0.owed', 450)->has('agent.earnings', 2)->where('agent.organizations.0.id', $org->id));

    $this->post(route('ventiq.agents.payout', $this->agent), ['method' => 'mpesa', 'reference' => 'MP-123'])
        ->assertSessionHas('status', fn ($s) => str_contains($s, 'M450.00'));
    expect(AgentEarning::where('status', 'paid')->count())->toBe(2)->and((float) $this->agent->fresh()->total_earnings)->toBe(450.0);
    Mail::assertSent(AgentPayoutReceipt::class, fn ($mail) => (float) $mail->totalAmount === 450.0);

    $this->post(route('ventiq.agents.payout', $this->agent), ['method' => 'mpesa', 'reference' => 'MP-124'])->assertSessionHas('error');
});

it('shows an agent their organizations, earnings and next step', function () {
    $user = User::factory()->create(['email' => 'mpho@example.com', 'organization_id' => null]);
    $user->assignRole('sales_agent');
    $this->agent->update(['status' => 'approved']);
    Organization::factory()->create(['agent_id' => $this->agent->id, 'name' => 'Leribe Choir', 'phone' => '+26658001234']);

    $this->actingAs($user)->get(route('agent.home'))->assertOk()
        ->assertInertia(fn ($page) => $page->component('Agent/Home')->where('certified', false)->where('stats.organizations', 1)
            ->where('next', fn ($n) => str_contains($n, 'certified'))->where('organizations.0.whatsapp', fn ($u) => str_starts_with($u, 'https://wa.me/26658001234')));

    $other = User::factory()->create(['organization_id' => Organization::factory()->create()->id]);
    $this->actingAs($other)->get(route('agent.home'))->assertForbidden();
});

it('grades the certification on the server, needs every statement, and keeps a pass', function () {
    $user = User::factory()->create(['email' => 'mpho@example.com', 'organization_id' => null]);
    $user->assignRole('sales_agent');
    $right = ['q1' => 'B', 'q2' => 'B', 'q3' => 'B', 'q4' => 'A', 'q5' => 'A', 'q6' => 'B', 'q7' => 'B', 'q8' => 'B'];
    $acks = ['ack1' => true, 'ack2' => true, 'ack3' => true, 'ack4' => true];

    // The answers never reach the page.
    $this->actingAs($user)->get(route('agent.certification'))->assertOk()->assertDontSee('"answer"', false)
        ->assertInertia(fn ($page) => $page->has('modules', 4)->has('acknowledgments', 4));

    $this->post(route('agent.certification.submit'), ['answers' => $right] + ['ack1' => true])->assertSessionHasErrors('ack2');

    // 5 of 8 is 62%: not a pass.
    $this->post(route('agent.certification.submit'), ['answers' => array_merge($right, ['q1' => 'A', 'q2' => 'A', 'q3' => 'A'])] + $acks)->assertSessionHas('error');
    expect(AgentCertification::where('agent_id', $this->agent->id)->value('passed'))->toBeFalsy();

    // All 8 shown questions right is a pass (the old page could never reach 75%).
    $this->post(route('agent.certification.submit'), ['answers' => $right] + $acks)->assertSessionHas('status');
    $cert = AgentCertification::where('agent_id', $this->agent->id)->sole();
    expect($cert->passed)->toBeTrue()->and($cert->attempts)->toBe(2)->and($cert->score)->toBe(8);

    $this->post(route('agent.certification.submit'), ['answers' => ['q1' => 'A']] + $acks);
    expect($cert->fresh()->passed)->toBeTrue();
});

it('sends super admins to the admin and agents to their page, not Filament', function () {
    $agentUser = User::factory()->create(['email' => 'mpho@example.com', 'organization_id' => null]);
    $agentUser->assignRole('sales_agent');

    $this->actingAs($this->super);
    expect(\App\Support\IntentRedirect::resolve(null))->toBe(route('ventiq.money.index'));
    $this->get(route('sessions.index'))->assertRedirect(route('ventiq.money.index'));

    $this->actingAs($agentUser);
    expect(\App\Support\IntentRedirect::resolve(null))->toBe(route('agent.home'));
});
