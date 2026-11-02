<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_tasks', function (Blueprint $table) {
            $table->foreignId('parent_id')->nullable()->after('phase_id')->constrained('project_tasks')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        // project_tasks was dropped for good by the later WBS squash
        // (2026_07_19_060000_rebuild_project_stages_without_tasks) — nothing to reverse
        // if a rollback has already gone that far back.
        if (! Schema::hasTable('project_tasks')) {
            return;
        }

        Schema::table('project_tasks', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropColumn('parent_id');
        });
    }
};
