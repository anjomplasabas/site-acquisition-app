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
        Schema::table('project_phases', function (Blueprint $table) {
            $table->dropIndex(['group']);
            $table->dropColumn('group');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // project_phases was dropped for good by the later WBS squash
        // (2026_07_19_060000_rebuild_project_stages_without_tasks) — nothing to reverse
        // if a rollback has already gone that far back.
        if (! Schema::hasTable('project_phases')) {
            return;
        }

        Schema::table('project_phases', function (Blueprint $table) {
            $table->string('group')->nullable()->after('name');
        });

        Schema::table('project_phases', function (Blueprint $table) {
            $table->index('group');
        });
    }
};
