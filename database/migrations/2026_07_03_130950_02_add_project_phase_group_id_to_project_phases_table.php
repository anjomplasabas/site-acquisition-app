<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('project_phases', function (Blueprint $table) {
            $table->foreignId('project_phase_group_id')->nullable()->after('group')
                ->constrained('project_phase_groups')->nullOnDelete();
        });

        // Backfill: turn each project's existing 'Network'/'Infrastructure' string
        // group into a real project_phase_groups row, preserving the order/icons
        // the UI previously hardcoded.
        $defaults = [
            'Network' => ['icon' => 'wifi', 'order' => 0],
            'Infrastructure' => ['icon' => 'construction', 'order' => 1],
        ];

        foreach ($defaults as $groupName => $meta) {
            $projectIds = DB::table('project_phases')
                ->where('group', $groupName)
                ->distinct()
                ->pluck('project_id');

            foreach ($projectIds as $projectId) {
                $groupId = DB::table('project_phase_groups')->insertGetId([
                    'project_id' => $projectId,
                    'name' => $groupName,
                    'icon' => $meta['icon'],
                    'order' => $meta['order'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('project_phases')
                    ->where('project_id', $projectId)
                    ->where('group', $groupName)
                    ->update(['project_phase_group_id' => $groupId]);
            }
        }
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
            $table->dropConstrainedForeignId('project_phase_group_id');
        });
    }
};
