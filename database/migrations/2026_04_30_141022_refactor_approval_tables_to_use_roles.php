<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('approval_configs', function (Blueprint $table) {
            $table->dropColumn('approver_id');
            $table->foreignId('role_id')->after('step');
        });

        Schema::table('pipeline_approvals', function (Blueprint $table) {
            $table->dropColumn('approver_id');
            $table->foreignId('role_id')->after('step');
            $table->foreignId('decided_by_id')->nullable()->after('role_id');
        });
    }

    public function down(): void
    {
        Schema::table('pipeline_approvals', function (Blueprint $table) {
            $table->dropColumn(['decided_by_id', 'role_id']);
            $table->foreignId('approver_id');
        });

        Schema::table('approval_configs', function (Blueprint $table) {
            $table->dropColumn('role_id');
            $table->foreignId('approver_id');
        });
    }
};
