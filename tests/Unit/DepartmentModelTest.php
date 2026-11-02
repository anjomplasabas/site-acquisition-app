<?php

use App\Models\Department;
use App\Models\User;

it('has many users', function () {
    $department = Department::factory()->create();
    User::factory()->count(3)->create(['department_id' => $department->id]);

    expect($department->users)->toHaveCount(3)
        ->each->toBeInstanceOf(User::class);
});

it('defaults status to ACTIVE', function () {
    $department = Department::factory()->create();

    expect($department->status)->toBe('ACTIVE');
});

it('can be set to INACTIVE', function () {
    $department = Department::factory()->inactive()->create();

    expect($department->status)->toBe('INACTIVE');
});

it('user count decreases when a user is removed', function () {
    $department = Department::factory()->create();
    $user = User::factory()->create(['department_id' => $department->id]);

    expect($department->users()->count())->toBe(1);

    $user->delete();

    expect($department->fresh()->users()->count())->toBe(0);
});
