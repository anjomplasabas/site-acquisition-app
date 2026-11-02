<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Inertia\Testing\AssertableInertia as Assert;

it('shows the reset password page with a valid token', function () {
    $user = User::factory()->create();
    $token = Password::createToken($user);

    $this->get("/reset-password/{$token}?email={$user->email}")
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Auth/ResetPassword')
            ->where('token', $token)
            ->where('email', $user->email)
        );
});

it('resets the password with a valid token', function () {
    $user = User::factory()->create();
    $token = Password::createToken($user);

    $this->post('/reset-password', [
        'token' => $token,
        'email' => $user->email,
        'password' => 'newpassword123',
        'password_confirmation' => 'newpassword123',
    ])->assertRedirect('/');

    expect(Hash::check('newpassword123', $user->fresh()->password))->toBeTrue();
});

it('clears force_password_change after a successful reset', function () {
    $user = User::factory()->create(['force_password_change' => true]);
    $token = Password::createToken($user);

    $this->post('/reset-password', [
        'token' => $token,
        'email' => $user->email,
        'password' => 'newpassword123',
        'password_confirmation' => 'newpassword123',
    ]);

    expect($user->fresh()->force_password_change)->toBeFalse();
});

it('fails with an invalid token', function () {
    $user = User::factory()->create();

    $this->post('/reset-password', [
        'token' => 'invalid-token',
        'email' => $user->email,
        'password' => 'newpassword123',
        'password_confirmation' => 'newpassword123',
    ])->assertSessionHasErrors('email');
});

it('fails when passwords do not match', function () {
    $user = User::factory()->create();
    $token = Password::createToken($user);

    $this->post('/reset-password', [
        'token' => $token,
        'email' => $user->email,
        'password' => 'newpassword123',
        'password_confirmation' => 'different_password',
    ])->assertSessionHasErrors('password');
});

it('fails with a password shorter than 8 characters', function () {
    $user = User::factory()->create();
    $token = Password::createToken($user);

    $this->post('/reset-password', [
        'token' => $token,
        'email' => $user->email,
        'password' => 'short',
        'password_confirmation' => 'short',
    ])->assertSessionHasErrors('password');
});

it('redirects authenticated users away from the reset password page', function () {
    $user = User::factory()->create();
    $token = Password::createToken($user);

    $this->actingAs($user)
        ->get("/reset-password/{$token}")
        ->assertRedirect();
});
