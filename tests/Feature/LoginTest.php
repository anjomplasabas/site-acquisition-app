<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;

test('login page loads', function () {
    $response = $this->get('/');

    $response->assertSuccessful();
});

test('empty password shows a required error', function () {
    $user = User::factory()->create();

    $response = $this->post('/', [
        'email' => $user->email,
        'password' => '',
    ]);

    $response->assertSessionHasErrors('password');
    $this->assertGuest();
});

test('inactive user cannot log in with valid credentials', function () {
    $user = User::factory()->create(['status' => 'INACTIVE']);

    $response = $this->post('/', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertSessionHasErrors('email');
    $this->assertGuest();
});

test('active user can log in with valid credentials', function () {
    $user = User::factory()->create(['status' => 'ACTIVE']);

    $response = $this->post('/', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertRedirect('/dashboard');
    $this->assertAuthenticatedAs($user);
});

test('remember me cookie expires after the configured number of days, not the framework default', function () {
    config(['auth.remember_me_lifetime_days' => 2]);
    $user = User::factory()->create(['status' => 'ACTIVE']);

    $response = $this->post('/', [
        'email' => $user->email,
        'password' => 'password',
        'remember' => true,
    ]);

    $response->assertRedirect('/dashboard');

    $recallerName = Auth::guard()->getRecallerName();
    $cookie = collect($response->headers->getCookies())->first(fn ($cookie) => $cookie->getName() === $recallerName);

    expect($cookie)->not->toBeNull()
        ->and($cookie->getExpiresTime())->toBeGreaterThan(now()->addDay()->timestamp)
        ->and($cookie->getExpiresTime())->toBeLessThan(now()->addDays(3)->timestamp);
});

test('remember me is off by default and issues no recaller cookie', function () {
    $user = User::factory()->create(['status' => 'ACTIVE']);

    $response = $this->post('/', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertRedirect('/dashboard');

    $recallerName = Auth::guard()->getRecallerName();
    $cookie = collect($response->headers->getCookies())->first(fn ($cookie) => $cookie->getName() === $recallerName);

    expect($cookie)->toBeNull();
});
