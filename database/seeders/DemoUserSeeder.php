<?php

namespace Database\Seeders;

use App\Models\ApprovalConfig;
use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoUserSeeder extends Seeder
{
    private const DEMO_PASSWORD = 'DemoOnly123!';

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        $users = [
            ['department' => 'IT', 'role' => 'SUPER_ADMIN', 'name' => 'Demo Administrator', 'email' => 'admin@example.test'],
            ['department' => 'Site Acquisition Team', 'role' => 'Lead SAQ Specialist', 'name' => 'Alex Reyes', 'email' => 'lead.saq@example.test'],
            ['department' => 'Site Acquisition Team', 'role' => 'SAQ Specialist', 'name' => 'Jordan Santos', 'email' => 'saq@example.test'],
            ['department' => 'Infrastructure Team', 'role' => 'Infrastructure Manager', 'name' => 'Morgan Cruz', 'email' => 'infrastructure.manager@example.test'],
            ['department' => 'Infrastructure Team', 'role' => 'Infrastructure Specialist', 'name' => 'Taylor Garcia', 'email' => 'infrastructure.specialist@example.test'],
            ['department' => 'Infrastructure Team', 'role' => 'Infra Designer', 'name' => 'Casey Flores', 'email' => 'designer@example.test'],
            ['department' => 'Infrastructure Team', 'role' => 'Surveyor', 'name' => 'Riley Mendoza', 'email' => 'surveyor@example.test'],
            ['department' => 'Network Team', 'role' => 'Network Surveyor', 'name' => 'Avery Navarro', 'email' => 'network.surveyor@example.test'],
            ['department' => 'Network Team', 'role' => 'Network Manager', 'name' => 'Cameron Torres', 'email' => 'network.manager@example.test'],
            ['department' => 'Network Team', 'role' => 'Network Specialist', 'name' => 'Quinn Ramos', 'email' => 'network.specialist@example.test'],
            ['department' => 'Executive', 'role' => 'COMMERCIAL_MANAGER', 'name' => 'Parker Lim', 'email' => 'commercial.manager@example.test'],
            ['department' => 'Executive', 'role' => 'COO', 'name' => 'Jamie Rivera', 'email' => 'coo@example.test'],
        ];

        $departments = [];

        foreach ($users as $data) {
            $departments[$data['department']] ??= Department::firstOrCreate(['name' => $data['department']]);

            $role = Role::firstOrCreate(['name' => $data['role']]);

            User::updateOrCreate(
                ['email' => $data['email']],
                [
                    'full_name' => $data['name'],
                    'password' => Hash::make(self::DEMO_PASSWORD),
                    'role_id' => $role->id,
                    'department_id' => $departments[$data['department']]->id,
                    'status' => 'ACTIVE',
                    'force_password_change' => false,
                ]
            );
        }

        $coo = User::where('email', 'coo@example.test')->firstOrFail();
        $cooRole = Role::where('name', 'COO')->firstOrFail();

        ApprovalConfig::updateOrCreate(
            ['step' => 1],
            [
                'role_id' => $cooRole->id,
                'is_active' => true,
                'created_by' => $coo->id,
                'created_at' => now(),
            ],
        );

        $this->command?->warn('Demo accounts use DemoOnly123! for local review only. Never use this password in a deployed environment.');
    }
}
