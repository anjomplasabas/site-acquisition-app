<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $saqRoleId = DB::table('roles')->where('name', 'SAQ Specialist')->value('id');

        if ($saqRoleId === null) {
            $saqRoleId = DB::table('roles')->insertGetId(['name' => 'SAQ Specialist']);
        }

        $projectManagerRoleId = DB::table('roles')->where('name', 'PROJECT_MANAGER')->value('id');

        if ($projectManagerRoleId === null) {
            return;
        }

        DB::table('users')
            ->where('role_id', $projectManagerRoleId)
            ->update(['role_id' => $saqRoleId]);

        DB::table('role_permissions')
            ->where('role_id', $projectManagerRoleId)
            ->update(['role_id' => $saqRoleId]);

        DB::table('roles')->where('id', $projectManagerRoleId)->delete();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('roles')->updateOrInsert(['name' => 'PROJECT_MANAGER']);
    }
};
