<?php

use App\Models\AuditLog;
use App\Models\User;

it('rejects a full name with disallowed special characters', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->put('/profile', [
            'full_name' => 'John<>Doe',
            'email' => $user->email,
        ])
        ->assertSessionHasErrors('full_name');
});

it('rejects an email exceeding the maximum length', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->put('/profile', [
            'full_name' => $user->full_name,
            'email' => str_repeat('a', 250).'@example.com',
        ])
        ->assertSessionHasErrors('email');
});

it('does not update or log an audit entry when nothing changed', function () {
    $user = User::factory()->create(['full_name' => 'Jane Doe']);

    $this->actingAs($user)
        ->put('/profile', [
            'full_name' => 'Jane Doe',
            'email' => $user->email,
        ])
        ->assertSessionHas('success', 'No changes were made.');

    // AuditMiddleware also logs every successful non-GET request on its
    // own, under the route/controller-derived action ("profile_update") rather than the
    // "updated" action the controller emits explicitly — a no-op save must skip both.
    expect(AuditLog::where('user_id', $user->id)->exists())->toBeFalse();
});

it('updates the profile and logs an audit entry when something changed', function () {
    $user = User::factory()->create(['full_name' => 'Jane Doe']);

    $this->actingAs($user)
        ->put('/profile', [
            'full_name' => 'Jane Smith',
            'email' => $user->email,
        ])
        ->assertSessionHas('success', 'Profile updated successfully.');

    expect($user->fresh()->full_name)->toBe('Jane Smith');

    $log = AuditLog::where('user_id', $user->id)->where('action', 'updated')->first();
    expect($log)->not->toBeNull()
        ->and($log->actor_name)->toBe('Jane Smith');
});

it('keeps historical audit log entries unchanged after a later profile update', function () {
    $user = User::factory()->create(['full_name' => 'Jane Doe', 'email' => 'jane@example.com']);

    $this->actingAs($user)->put('/profile', [
        'full_name' => 'Jane Renamed',
        'email' => 'jane@example.com',
    ]);

    $firstLog = AuditLog::where('user_id', $user->id)->where('action', 'updated')->firstOrFail();
    expect($firstLog->actor_name)->toBe('Jane Renamed');

    $this->actingAs($user->fresh())->put('/profile', [
        'full_name' => 'Jane Renamed Again',
        'email' => 'jane@example.com',
    ]);

    // The earlier log entry must still reflect the name at the time it was written.
    expect($firstLog->fresh()->actor_name)->toBe('Jane Renamed');
});
