<?php

use App\Models\{Organization, User};

it('no longer sells event packages', function () {
    $user = User::factory()->create(['organization_id' => Organization::factory()->create()->id]);

    $this->actingAs($user)->get('/payment/package/initiate?package_id=1')->assertNotFound();
    $this->get('/payment/package/callback?sessionId=MS-1')->assertNotFound();
});

it('has retired the MoPay ticket checkout that added an unrecorded surcharge', function () {
    $this->get('/payment/ticket/initiate?ticket_id=1')->assertStatus(410);
});
