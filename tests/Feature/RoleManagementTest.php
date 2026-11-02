<?php

use App\Models\Role;
use App\Models\User;

it('rejects a role name with excessive special characters', function () {
    $this->actingAs(User::factory()->superAdmin()->create())
        ->post(route('roles.store'), [
            'name' => '!!!Role###@@@',
        ])
        ->assertSessionHasErrors('name');
});

it('accepts a role name with underscores matching existing naming convention', function () {
    $this->actingAs(User::factory()->superAdmin()->create())
        ->post(route('roles.store'), [
            'name' => 'REGIONAL_MANAGER',
        ])
        ->assertSessionDoesntHaveErrors('name');
});

it('blocks deleting a role that still has assigned users', function () {
    $role = Role::factory()->create();
    User::factory()->create(['role_id' => $role->id]);

    $this->actingAs(User::factory()->superAdmin()->create())
        ->delete(route('roles.destroy', $role))
        ->assertRedirect(route('roles.index'))
        ->assertSessionHas('error');

    expect(Role::find($role->id))->not->toBeNull();
});

it('allows deleting a role with no assigned users', function () {
    $role = Role::factory()->create();

    $this->actingAs(User::factory()->superAdmin()->create())
        ->delete(route('roles.destroy', $role))
        ->assertRedirect(route('roles.index'))
        ->assertSessionHas('success');

    expect(Role::find($role->id))->toBeNull();
});
