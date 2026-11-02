<?php

use App\Mail\UserCredentialsMail;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;

// --- Authentication ---

it('redirects unauthenticated users from users index', function () {
    $this->get('/users')->assertRedirect('/');
});

it('redirects unauthenticated users from create page', function () {
    $this->get('/users/create')->assertRedirect('/');
});

it('redirects unauthenticated users from edit page', function () {
    $user = User::factory()->superAdmin()->create();

    $this->get("/users/{$user->id}/edit")->assertRedirect('/');
});

it('blocks non-super-admin users from user management', function () {
    $this->actingAs(User::factory()->create())
        ->get('/users')
        ->assertForbidden();
});

// --- Index ---

it('shows the users list', function () {
    $this->actingAs(User::factory()->superAdmin()->create())
        ->get('/users')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Users/Index')
            ->has('users.data')
            ->has('roles')
            ->has('filters')
        );
});

it('filters users by search', function () {
    $auth = User::factory()->superAdmin()->create();
    User::factory()->create(['full_name' => 'Findable Person']);
    User::factory()->create(['full_name' => 'Other Person']);

    $this->actingAs($auth)
        ->get('/users?search=Findable')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Users/Index')
            ->has('users.data', 1)
            ->where('users.data.0.full_name', 'Findable Person')
        );
});

it('filters users by status', function () {
    User::factory()->create(['status' => 'ACTIVE']);
    User::factory()->inactive()->create();

    $this->actingAs(User::factory()->superAdmin()->create())
        ->get('/users?status=INACTIVE')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Users/Index')
            ->has('users.data', 1)
            ->where('users.data.0.status', 'INACTIVE')
        );
});

it('keeps the status filter applied when combined with a search term', function () {
    User::factory()->create(['full_name' => 'Network Active', 'status' => 'ACTIVE']);
    User::factory()->inactive()->create(['full_name' => 'Network Inactive']);

    $this->actingAs(User::factory()->superAdmin()->create())
        ->get('/users?search=Network&status=INACTIVE')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->has('users.data', 1)
            ->where('users.data.0.full_name', 'Network Inactive')
        );
});

it('filters users by role', function () {
    $targetRole = Role::factory()->create();
    $otherUser = User::factory()->create();
    $targetUser = User::factory()->create(['role_id' => $targetRole->id]);

    $this->actingAs(User::factory()->superAdmin()->create())
        ->get("/users?role={$targetRole->id}")
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Users/Index')
            ->has('users.data', 1)
            ->where('users.data.0.id', $targetUser->id)
        );

    expect($otherUser->role_id)->not->toBe($targetRole->id);
});

// --- Create ---

it('shows the create user page', function () {
    $this->actingAs(User::factory()->superAdmin()->create())
        ->get('/users/create')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Users/Create')
            ->has('roles')
            ->has('departments')
        );
});

// --- Store ---

it('creates a user with valid data', function () {
    $role = Role::factory()->create();
    $department = Department::factory()->create();

    $this->actingAs(User::factory()->superAdmin()->create())
        ->post('/users', [
            'full_name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'role_id' => $role->id,
            'department_id' => $department->id,
            'status' => 'ACTIVE',
        ])
        ->assertRedirect('/users');

    expect(User::where('email', 'jane@example.com')->exists())->toBeTrue();
});

it('auto-generates and hashes the password on create', function () {
    $role = Role::factory()->create();
    $department = Department::factory()->create();

    $this->actingAs(User::factory()->superAdmin()->create())
        ->post('/users', [
            'full_name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'role_id' => $role->id,
            'department_id' => $department->id,
            'status' => 'ACTIVE',
        ]);

    $created = User::where('email', 'jane@example.com')->first();

    expect($created)->not->toBeNull()
        ->and($created->password)->not->toBeEmpty()
        ->and($created->force_password_change)->toBeTrue();
});

it('fails to create a user with invalid data', function (array $override) {
    $role = Role::factory()->create();
    $department = Department::factory()->create();

    $base = [
        'full_name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'role_id' => $role->id,
        'department_id' => $department->id,
        'status' => 'ACTIVE',
    ];

    $this->actingAs(User::factory()->superAdmin()->create())
        ->post('/users', array_merge($base, $override))
        ->assertSessionHasErrors();
})->with([
    'missing full_name' => [['full_name' => '']],
    'missing email' => [['email' => '']],
    'invalid email' => [['email' => 'not-an-email']],
    'invalid status' => [['status' => 'PENDING']],
    'invalid role' => [['role_id' => 9999]],
    'invalid department' => [['department_id' => 9999]],
]);

it('rejects a full name with disallowed special characters', function () {
    $role = Role::factory()->create();
    $department = Department::factory()->create();

    $this->actingAs(User::factory()->superAdmin()->create())
        ->post('/users', [
            'full_name' => 'Jane<script>Doe</script>',
            'email' => 'jane@example.com',
            'role_id' => $role->id,
            'department_id' => $department->id,
            'status' => 'ACTIVE',
        ])
        ->assertSessionHasErrors('full_name');
});

it('accepts a full name with allowed punctuation', function () {
    $role = Role::factory()->create();
    $department = Department::factory()->create();

    $this->actingAs(User::factory()->superAdmin()->create())
        ->post('/users', [
            'full_name' => "Anne-Marie O'Connor Jr.",
            'email' => 'anne@example.com',
            'role_id' => $role->id,
            'department_id' => $department->id,
            'status' => 'ACTIVE',
        ])
        ->assertSessionDoesntHaveErrors('full_name');
});

it('rejects an email exceeding the maximum length', function () {
    $role = Role::factory()->create();
    $department = Department::factory()->create();

    $this->actingAs(User::factory()->superAdmin()->create())
        ->post('/users', [
            'full_name' => 'Jane Doe',
            'email' => str_repeat('a', 250).'@example.com',
            'role_id' => $role->id,
            'department_id' => $department->id,
            'status' => 'ACTIVE',
        ])
        ->assertSessionHasErrors('email');
});

it('flashes a success message when creating a user', function () {
    $role = Role::factory()->create();
    $department = Department::factory()->create();

    $this->actingAs(User::factory()->superAdmin()->create())
        ->post('/users', [
            'full_name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'role_id' => $role->id,
            'department_id' => $department->id,
            'status' => 'ACTIVE',
        ])
        ->assertSessionHas('success');
});

it('still creates the user and audit log when the credentials email fails to send', function () {
    Mail::shouldReceive('to->send')->andThrow(new RuntimeException('SMTP unavailable'));

    $role = Role::factory()->create();
    $department = Department::factory()->create();

    $response = $this->actingAs(User::factory()->superAdmin()->create())
        ->post('/users', [
            'full_name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'role_id' => $role->id,
            'department_id' => $department->id,
            'status' => 'ACTIVE',
        ]);

    $response->assertRedirect('/users')->assertSessionHas('error');
    expect(User::where('email', 'jane@example.com')->exists())->toBeTrue()
        ->and(AuditLog::where('action', 'created')->exists())->toBeTrue();
});

it('fails to create a user with a duplicate email', function () {
    $existing = User::factory()->superAdmin()->create(['email' => 'taken@example.com']);
    $role = Role::factory()->create();
    $department = Department::factory()->create();

    $this->actingAs($existing)
        ->post('/users', [
            'full_name' => 'Someone',
            'email' => 'taken@example.com',
            'role_id' => $role->id,
            'department_id' => $department->id,
            'status' => 'ACTIVE',
        ])
        ->assertSessionHasErrors('email');
});

// --- Edit ---

it('shows the edit user page', function () {
    $user = User::factory()->superAdmin()->create();

    $this->actingAs($user)
        ->get("/users/{$user->id}/edit")
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Users/Edit')
            ->has('user')
            ->has('roles')
            ->has('departments')
            ->where('user.id', $user->id)
        );
});

it('returns 404 for a non-existent user on edit', function () {
    $this->actingAs(User::factory()->superAdmin()->create())
        ->get('/users/9999/edit')
        ->assertNotFound();
});

// --- Update ---

it('updates a user with valid data', function () {
    $user = User::factory()->superAdmin()->create();
    $role = Role::factory()->create();
    $department = Department::factory()->create();

    $this->actingAs($user)
        ->put("/users/{$user->id}", [
            'full_name' => 'Updated Name',
            'email' => $user->email,
            'password' => '',
            'role_id' => $role->id,
            'department_id' => $department->id,
            'status' => 'INACTIVE',
        ])
        ->assertRedirect('/users');

    expect($user->fresh()->full_name)->toBe('Updated Name')
        ->and($user->fresh()->status)->toBe('INACTIVE');
});

it('does not log an audit entry when updating a user with no actual changes', function () {
    $admin = User::factory()->superAdmin()->create();
    $target = User::factory()->create();

    $this->actingAs($admin)
        ->put("/users/{$target->id}", [
            'full_name' => $target->full_name,
            'email' => $target->email,
            'password' => '',
            'role_id' => $target->role_id,
            'department_id' => $target->department_id,
            'status' => $target->status,
        ])
        ->assertRedirect('/users')
        ->assertSessionHas('success', 'No changes were made.');

    expect(AuditLog::where('subject_type', User::class)->where('subject_id', $target->id)->exists())->toBeFalse();
});

it('logs an audit entry when updating a user with actual changes', function () {
    $admin = User::factory()->superAdmin()->create();
    $target = User::factory()->create();

    $this->actingAs($admin)
        ->put("/users/{$target->id}", [
            'full_name' => 'Updated Name',
            'email' => $target->email,
            'password' => '',
            'role_id' => $target->role_id,
            'department_id' => $target->department_id,
            'status' => $target->status,
        ])
        ->assertRedirect('/users');

    expect(AuditLog::where('subject_type', User::class)->where('subject_id', $target->id)->where('action', 'updated')->exists())->toBeTrue();
});

it('does not change the password when left blank on update', function () {
    $user = User::factory()->superAdmin()->create();
    $originalHash = $user->password;

    $this->actingAs($user)
        ->put("/users/{$user->id}", [
            'full_name' => $user->full_name,
            'email' => $user->email,
            'password' => '',
            'role_id' => $user->role_id,
            'department_id' => $user->department_id,
            'status' => $user->status,
        ]);

    expect($user->fresh()->password)->toBe($originalHash);
});

it('updates the password when provided', function () {
    $user = User::factory()->superAdmin()->create();

    $this->actingAs($user)
        ->put("/users/{$user->id}", [
            'full_name' => $user->full_name,
            'email' => $user->email,
            'password' => 'newpassword123',
            'role_id' => $user->role_id,
            'department_id' => $user->department_id,
            'status' => $user->status,
        ]);

    expect(Hash::check('newpassword123', $user->fresh()->password))->toBeTrue();
});

it('fails to update a user with a duplicate email', function () {
    User::factory()->create(['email' => 'taken@example.com']);
    $user = User::factory()->superAdmin()->create();

    $this->actingAs($user)
        ->put("/users/{$user->id}", [
            'full_name' => $user->full_name,
            'email' => 'taken@example.com',
            'password' => '',
            'role_id' => $user->role_id,
            'department_id' => $user->department_id,
            'status' => $user->status,
        ])
        ->assertSessionHasErrors('email');
});

it('allows updating a user with their own email', function () {
    $user = User::factory()->superAdmin()->create();

    $this->actingAs($user)
        ->put("/users/{$user->id}", [
            'full_name' => 'Updated Name',
            'email' => $user->email,
            'password' => '',
            'role_id' => $user->role_id,
            'department_id' => $user->department_id,
            'status' => $user->status,
        ])
        ->assertRedirect('/users');
});

it('flashes a success message when updating a user', function () {
    $user = User::factory()->superAdmin()->create();

    $this->actingAs($user)
        ->put("/users/{$user->id}", [
            'full_name' => 'Updated Name',
            'email' => $user->email,
            'password' => '',
            'role_id' => $user->role_id,
            'department_id' => $user->department_id,
            'status' => $user->status,
        ])
        ->assertSessionHas('success');
});

it('returns 404 when updating a non-existent user', function () {
    $this->actingAs(User::factory()->superAdmin()->create())
        ->put('/users/9999', [
            'full_name' => 'Name',
            'email' => 'email@example.com',
            'password' => '',
            'role_id' => 1,
            'department_id' => 1,
            'status' => 'ACTIVE',
        ])
        ->assertNotFound();
});

// --- Destroy ---

it('deletes a user', function () {
    $auth = User::factory()->superAdmin()->create();
    $target = User::factory()->create();

    $this->actingAs($auth)
        ->delete("/users/{$target->id}")
        ->assertRedirect();

    expect(User::find($target->id))->toBeNull();
});

it('returns 404 when deleting a non-existent user', function () {
    $this->actingAs(User::factory()->superAdmin()->create())
        ->delete('/users/9999')
        ->assertNotFound();
});

it('flashes a success message when deleting a user', function () {
    $auth = User::factory()->superAdmin()->create();
    $target = User::factory()->create();

    $this->actingAs($auth)
        ->delete("/users/{$target->id}")
        ->assertSessionHas('success');
});

// --- Reset Password ---

it('resets a user password and flashes success', function () {
    Mail::fake();

    $auth = User::factory()->superAdmin()->create();
    $target = User::factory()->create();
    $originalHash = $target->password;

    $this->actingAs($auth)
        ->post("/users/{$target->id}/reset-password")
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($target->fresh()->password)->not->toBe($originalHash);
    Mail::assertSent(UserCredentialsMail::class);
});

it('still resets the password and logs it when the notification email fails to send', function () {
    Mail::shouldReceive('to->send')->andThrow(new RuntimeException('SMTP unavailable'));

    $auth = User::factory()->superAdmin()->create();
    $target = User::factory()->create();
    $originalHash = $target->password;

    $this->actingAs($auth)
        ->post("/users/{$target->id}/reset-password")
        ->assertRedirect()
        ->assertSessionHas('error');

    expect($target->fresh()->password)->not->toBe($originalHash)
        ->and(AuditLog::where('action', 'password_reset')->exists())->toBeTrue();
});
