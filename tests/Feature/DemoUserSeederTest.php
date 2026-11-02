<?php

use App\Models\ApprovalConfig;
use App\Models\User;
use Database\Seeders\DemoUserSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Hash;

const DEMO_ACCOUNTS = [
    'admin@example.test' => 'SUPER_ADMIN',
    'lead.saq@example.test' => 'Lead SAQ Specialist',
    'saq@example.test' => 'SAQ Specialist',
    'infrastructure.manager@example.test' => 'Infrastructure Manager',
    'infrastructure.specialist@example.test' => 'Infrastructure Specialist',
    'designer@example.test' => 'Infra Designer',
    'surveyor@example.test' => 'Surveyor',
    'network.surveyor@example.test' => 'Network Surveyor',
    'network.manager@example.test' => 'Network Manager',
    'network.specialist@example.test' => 'Network Specialist',
    'commercial.manager@example.test' => 'COMMERCIAL_MANAGER',
    'coo@example.test' => 'COO',
];

it('seeds only fictional demo accounts with the expected roles and password', function () {
    $this->seed(RoleSeeder::class);
    $this->seed(DemoUserSeeder::class);

    expect(User::count())->toBe(count(DEMO_ACCOUNTS));

    foreach (DEMO_ACCOUNTS as $email => $roleName) {
        $user = User::with('role', 'department')->where('email', $email)->firstOrFail();

        expect($user->role->name)->toBe($roleName)
            ->and($user->department)->not->toBeNull()
            ->and($user->status)->toBe('ACTIVE')
            ->and($user->force_password_change)->toBeFalse()
            ->and(Hash::check('DemoOnly123!', $user->password))->toBeTrue();
    }
});

it('creates the default COO approval step from the fictional COO account', function () {
    $this->seed(RoleSeeder::class);
    $this->seed(DemoUserSeeder::class);

    $coo = User::where('email', 'coo@example.test')->firstOrFail();
    $approvalStep = ApprovalConfig::with('role')->where('step', 1)->firstOrFail();

    expect($approvalStep->role->name)->toBe('COO')
        ->and($approvalStep->created_by)->toBe($coo->id)
        ->and($approvalStep->is_active)->toBeTrue();
});

it('is idempotent', function () {
    $this->seed(RoleSeeder::class);
    $this->seed(DemoUserSeeder::class);
    $this->seed(DemoUserSeeder::class);

    expect(User::count())->toBe(count(DEMO_ACCOUNTS))
        ->and(ApprovalConfig::where('step', 1)->count())->toBe(1);
});

it('does not seed demo accounts outside local and testing environments', function () {
    $originalEnvironment = app()->environment();
    app()['env'] = 'production';

    try {
        app(DemoUserSeeder::class)->run();
    } finally {
        app()['env'] = $originalEnvironment;
    }

    expect(User::count())->toBe(0)
        ->and(ApprovalConfig::count())->toBe(0);
});
