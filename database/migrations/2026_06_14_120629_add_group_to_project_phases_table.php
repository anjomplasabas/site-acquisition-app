<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_phases', function (Blueprint $table) {
            $table->string('group')->nullable()->after('name');
        });

        // Assign existing infra-related phases to Infrastructure group
        DB::table('project_phases')
            ->whereIn('name', ['Floor Plan', 'Design Package'])
            ->update(['group' => 'Infrastructure']);
    }

    public function down(): void
    {
        // project_phases was dropped for good by the later WBS squash
        // (2026_07_19_060000_rebuild_project_stages_without_tasks) — nothing to reverse
        // if a rollback has already gone that far back.
        if (! Schema::hasTable('project_phases')) {
            return;
        }

        Schema::table('project_phases', function (Blueprint $table) {
            $table->dropColumn('group');
        });
    }
};
