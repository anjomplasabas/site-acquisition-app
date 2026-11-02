<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** @var array<string, string> */
    private const RENAMES = [
        'pipelines.view_site_planning' => 'site_planning.list',
        'pipelines.schedule_site_survey' => 'site_planning.manage',
        'pipelines.fill_site_survey' => 'site_planning.fill_survey',
        'pipelines.manage_plans_drawing' => 'site_planning.design_plan',
        'pipelines.approve_design_plan' => 'site_planning.approve_design_plan',
        'pipelines.assign_design_plan' => 'site_planning.assign_design_plan',
    ];

    /**
     * Site Planning's action permissions moved out of PipelineActionPermission into their own
     * SitePlanningActionPermission enum (its own Role Management card) — renaming the stored
     * permission strings keeps existing role assignments intact.
     */
    public function up(): void
    {
        foreach (self::RENAMES as $old => $new) {
            DB::table('role_action_permissions')->where('permission', $old)->update(['permission' => $new]);
        }
    }

    public function down(): void
    {
        foreach (self::RENAMES as $old => $new) {
            DB::table('role_action_permissions')->where('permission', $new)->update(['permission' => $old]);
        }
    }
};
