<?php

use App\Models\Department;
use Database\Seeders\DepartmentSeeder;

it('seeds the standard departments', function () {
    $this->seed(DepartmentSeeder::class);

    expect(Department::count())->toBe(3)
        ->and(Department::pluck('name')->all())->toBe([
            'Site Acquisition Team',
            'Infrastructure Team',
            'Network Team',
        ]);
});

it('is idempotent when run more than once', function () {
    $this->seed(DepartmentSeeder::class);
    $this->seed(DepartmentSeeder::class);

    expect(Department::count())->toBe(3);
});
