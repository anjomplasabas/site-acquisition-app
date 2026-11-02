<?php

use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('belongs to a role', function () {
    $user = User::factory()->create();

    expect($user->role)->toBeInstanceOf(Role::class)
        ->and($user->role->id)->toBe($user->role_id);
});

it('belongs to a department', function () {
    $user = User::factory()->create();

    expect($user->department)->toBeInstanceOf(Department::class)
        ->and($user->department->id)->toBe($user->department_id);
});

it('defaults status to ACTIVE', function () {
    $user = User::factory()->create();

    expect($user->status)->toBe('ACTIVE');
});

it('can be set to INACTIVE', function () {
    $user = User::factory()->inactive()->create();

    expect($user->status)->toBe('INACTIVE');
});

it('hides password and remember token from serialisation', function () {
    $user = User::factory()->create();
    $array = $user->toArray();

    expect($array)->not->toHaveKey('password')
        ->and($array)->not->toHaveKey('remember_token');
});

it('casts password as hashed', function () {
    $user = User::factory()->create(['password' => 'plain_text']);

    expect(Hash::check('plain_text', $user->password))->toBeTrue();
});
