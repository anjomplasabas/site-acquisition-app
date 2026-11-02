<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('roles')->where('name', 'SURVEYOR')->update(['name' => 'SAQ Specialist']);
    }

    public function down(): void
    {
        DB::table('roles')->where('name', 'SAQ Specialist')->update(['name' => 'SURVEYOR']);
    }
};
