<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $role = Role::firstOrCreate(['name' => 'SUPER_ADMIN']);

        User::where('email', 'admin@admin.com')->update(['role_id' => $role->id]);
    }
}
