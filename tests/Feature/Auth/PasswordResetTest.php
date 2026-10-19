<?php

use App\Models\User;
use Illuminate\Support\Facades\{Hash, Password};

it('only sets a password with a real, unused reset token for that email', function () {
    $user = User::factory()->create(['email' => 'lerato@example.com', 'password' => Hash::make('Old-Pass-123')]);

    // A made-up token changes nothing: this used to set the password anyway.
    $this->post(route('password.update'), ['token' => 'made-up', 'email' => 'lerato@example.com', 'password' => 'Taken-Over-1', 'password_confirmation' => 'Taken-Over-1'])
        ->assertSessionHasErrors('email');
    expect(Hash::check('Old-Pass-123', $user->fresh()->password))->toBeTrue();
    $this->assertGuest();

    // Another account's token doesn't work for this email either.
    $other = User::factory()->create();
    $otherToken = Password::createToken($other);
    $this->post(route('password.update'), ['token' => $otherToken, 'email' => 'lerato@example.com', 'password' => 'Taken-Over-1', 'password_confirmation' => 'Taken-Over-1'])
        ->assertSessionHasErrors('email');

    $token = Password::createToken($user);
    $this->post(route('password.update'), ['token' => $token, 'email' => 'lerato@example.com', 'password' => 'New-Pass-123', 'password_confirmation' => 'New-Pass-123'])
        ->assertRedirect();
    expect(Hash::check('New-Pass-123', $user->fresh()->password))->toBeTrue();
    $this->assertAuthenticatedAs($user->fresh());

    // And it works once.
    auth()->logout();
    $this->post(route('password.update'), ['token' => $token, 'email' => 'lerato@example.com', 'password' => 'Again-123', 'password_confirmation' => 'Again-123'])
        ->assertSessionHasErrors('email');
});
