<?php

use App\Models\AuditLog;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('redirects unauthenticated users from audit logs', function () {
    $this->get('/audit-logs')->assertRedirect('/');
});

it('blocks non-super-admin users from audit logs', function () {
    $this->actingAs(User::factory()->create())
        ->get('/audit-logs')
        ->assertForbidden();
});

it('allows super admin to view audit logs', function () {
    $user = User::factory()->superAdmin()->create();

    $this->actingAs($user)
        ->get('/audit-logs')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('AuditLogs/Index'));
});

it('returns paginated logs ordered by newest first', function () {
    $user = User::factory()->superAdmin()->create();

    AuditLog::factory()->count(30)->create();

    $this->actingAs($user)
        ->get('/audit-logs')
        ->assertInertia(fn (Assert $page) => $page
            ->component('AuditLogs/Index')
            ->has('logs.data', 25)
            ->where('logs.total', 30)
        );
});

it('filters logs by search term', function () {
    $user = User::factory()->superAdmin()->create();

    AuditLog::factory()->create(['description' => 'Created pipeline Vista Mall']);
    AuditLog::factory()->create(['description' => 'Logged out']);

    $this->actingAs($user)
        ->get('/audit-logs?search=Vista+Mall')
        ->assertInertia(fn (Assert $page) => $page
            ->has('logs.data', 1)
            ->where('logs.data.0.description', 'Created pipeline Vista Mall')
        );
});

it('filters logs by user', function () {
    $admin = User::factory()->superAdmin()->create();
    $other = User::factory()->create();

    AuditLog::factory()->create(['user_id' => $other->id]);
    AuditLog::factory()->count(3)->create(['user_id' => $admin->id]);

    $this->actingAs($admin)
        ->get("/audit-logs?user_id={$other->id}")
        ->assertInertia(fn (Assert $page) => $page->has('logs.data', 1));
});
