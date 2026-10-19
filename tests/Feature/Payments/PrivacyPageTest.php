<?php

use Inertia\Testing\AssertableInertia as Assert;

it('serves the privacy policy publicly, as Meta needs for WhatsApp', function () {
    $this->get('/privacy')->assertOk()
        ->assertSee('<title inertia>Privacy | VENTIQ</title>', false)
        ->assertInertia(fn (Assert $page) => $page->component('Legal/Privacy'));

    $this->get('/terms')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Legal/Terms'));
});
