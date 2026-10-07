<?php

it('serves the privacy policy publicly, as Meta needs for WhatsApp', function () {
    $this->get('/privacy')->assertOk()
        ->assertSee('Privacy policy')
        ->assertSee('support@ventiq.co.ls')
        ->assertSee('WhatsApp Business Platform')
        ->assertSee('id="delete"', false);

    $this->get('/terms')->assertSee(route('privacy'));
});
