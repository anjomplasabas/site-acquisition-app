<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('stage_tasks', function (Blueprint $table) {
            // Discriminates which stage_task_list field a row belongs to (a stage can have more
            // than one, e.g. Monitoring's Infrastructure Deployment Status and Testing) — defaults
            // to Infrastructure Deployment Status since every row that already exists is one of
            // its tasks.
            $table->string('field_key')->default('infrastructure_deployment_status')->after('project_stage_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stage_tasks', function (Blueprint $table) {
            $table->dropColumn('field_key');
        });
    }
};
