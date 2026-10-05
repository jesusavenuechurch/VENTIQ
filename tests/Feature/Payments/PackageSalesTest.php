<?php

use App\Models\{Organization, OrganizationPackage, PaymentSession, User};

it('does not sell packages unless switched on', function () {
    $user = User::factory()->create(['organization_id' => Organization::factory()->create()->id]);

    $this->actingAs($user)->get(route('online-payment.package.initiate', ['package_id' => 1]))->assertNotFound();
});

it('has retired the MoPay ticket checkout that added an unrecorded surcharge', function () {
    $this->get('/payment/ticket/initiate?ticket_id=1')->assertStatus(410);
});

it('does not approve a package again when its return page is reloaded', function () {
    $org = Organization::factory()->create();
    $package = OrganizationPackage::factory()->create(['organization_id' => $org->id]);
    $session = PaymentSession::create([
        'payable_type' => 'package', 'payable_id' => $package->id, 'gateway' => 'mopay', 'client_reference' => 'PKG-1',
        'mopay_session_id' => 'MS-1', 'amount' => 600, 'status' => 'completed', 'organization_id' => $org->id,
    ]);

    $mopay = Mockery::mock(\App\Services\MopayService::class);
    $mopay->shouldNotReceive('verifySession');
    app()->instance(\App\Services\MopayService::class, $mopay);

    $this->get('/payment/package/callback?sessionId=MS-1')->assertRedirect()->assertSessionHas('success');
});
