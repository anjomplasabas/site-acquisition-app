<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

it('shows the forgot password page', function () {
    $this->get('/forgot-password')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page->component('Auth/ForgotPassword'));
});

it('redirects authenticated users away from forgot password page', function () {
    $this->actingAs(User::factory()->create())
        ->get('/forgot-password')
        ->assertRedirect();
});

it('sends a password reset link for a registered email', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post('/forgot-password', ['email' => $user->email])
        ->assertRedirect();

    Notification::assertSentTo($user, ResetPassword::class);
});

it('does not reveal whether an email is registered', function () {
    Notification::fake();

    $this->post('/forgot-password', ['email' => 'notregistered@example.com'])
        ->assertRedirect()
        ->assertSessionHas('status');

    Notification::assertNothingSent();
});

it('fails validation with an invalid email format', function () {
    $this->post('/forgot-password', ['email' => 'not-an-email'])
        ->assertSessionHasErrors('email');
});

it('fails validation with a missing email', function () {
    $this->post('/forgot-password', ['email' => ''])
        ->assertSessionHasErrors('email');
});
