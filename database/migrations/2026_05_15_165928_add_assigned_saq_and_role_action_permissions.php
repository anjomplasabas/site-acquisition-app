<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pipelines', function (Blueprint $table) {
            $table->foreignId('assigned_saq_id')->nullable()->after('project_manager_id');
        });

        Schema::create('role_action_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->string('permission');
            $table->unique(['role_id', 'permission']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_action_permissions');

        Schema::table('pipelines', function (Blueprint $table) {
            $table->dropColumn('assigned_saq_id');
        });
    }
};
