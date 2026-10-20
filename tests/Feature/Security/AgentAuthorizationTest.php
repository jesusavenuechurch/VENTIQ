<?php

use App\Models\{AgentEarning, Organization, User};
use Inertia\Testing\AssertableInertia as Assert;

describe('Agent authorization: the walls', function () {

    beforeEach(fn () => $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class));

    it('keeps agents and organization admins out of the VENTIQ admin', function () {
        [, $agent] = createApprovedAgent();
        [, $orgAdmin] = createOrgWithAdmin();

        foreach ([$agent, $orgAdmin] as $user) {
            $this->actingAs($user)->get(route('ventiq.organizations.index'))->assertForbidden();
            $this->get(route('ventiq.agents.index'))->assertForbidden();
            $this->get(route('ventiq.money.index'))->assertForbidden();
        }
    });

    it("shows an agent only their own earnings and organizations", function () {
        [$agent1, $user1] = createApprovedAgent();
        [$agent2] = createApprovedAgent();
        AgentEarning::factory()->create(['agent_id' => $agent2->id]);
        Organization::factory()->referredBy($agent2)->create();

        $this->actingAs($user1)->get(route('agent.home'))->assertInertia(fn (Assert $page) => $page
            ->component('Agent/Home')
            ->has('earnings', 0)
            ->has('organizations', 0));
    });

    it("keeps one organization's admin out of another's events", function () {
        [$org1, $user1] = createOrgWithAdmin();
        [$org2] = createOrgWithAdmin();
        $event = \App\Models\Event::create(['organization_id' => $org2->id, 'name' => 'Not yours', 'slug' => 'not-yours', 'event_date' => now()->addWeek()]);

        $this->actingAs($user1)->get(route('organizer.events.edit', $event))->assertNotFound();
    });

    it('sends a guest from the old admin address to sign in', function () {
        $this->get('/admin')->assertRedirect(route('login'));
        $this->get('/admin/events/12/edit')->assertRedirect(route('login'));
        $this->get(route('ventiq.organizations.index'))->assertRedirect(route('login'));
    });
});
