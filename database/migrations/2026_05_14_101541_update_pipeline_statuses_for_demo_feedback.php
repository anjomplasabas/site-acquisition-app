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
        $statusMap = [
            'Initiated' => 'Prospecting',
            'Site Visit Approval' => 'Prospecting',
            'Pending Site Visit' => 'Site Survey',
            'Ongoing Site Survey' => 'Site Survey',
            'Pending Commercials' => 'Commercials',
            'Ongoing Pipeline Approval' => 'Approval',
        ];

        foreach ($statusMap as $old => $new) {
            DB::table('pipelines')->where('status', $old)->update(['status' => $new]);
            DB::table('role_permissions')->where('pipeline_status', $old)->update(['pipeline_status' => $new]);
            DB::table('pipeline_status_logs')->where('status', $old)->update(['status' => $new]);
            DB::table('pipeline_remark_logs')->where('status', $old)->update(['status' => $new]);
            DB::table('documents')->where('uploaded_status', $old)->update(['uploaded_status' => $new]);
        }

        $duplicates = DB::table('role_permissions')
            ->select('role_id', 'pipeline_status', DB::raw('MIN(id) as keep_id'))
            ->groupBy('role_id', 'pipeline_status')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $duplicate) {
            DB::table('role_permissions')
                ->where('role_id', $duplicate->role_id)
                ->where('pipeline_status', $duplicate->pipeline_status)
                ->where('id', '!=', $duplicate->keep_id)
                ->delete();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $statusMap = [
            'Prospecting' => 'Initiated',
            'Site Survey' => 'Ongoing Site Survey',
            'Commercials' => 'Pending Commercials',
            'Approval' => 'Ongoing Pipeline Approval',
        ];

        foreach ($statusMap as $old => $new) {
            DB::table('pipelines')->where('status', $old)->update(['status' => $new]);
            DB::table('role_permissions')->where('pipeline_status', $old)->update(['pipeline_status' => $new]);
            DB::table('pipeline_status_logs')->where('status', $old)->update(['status' => $new]);
            DB::table('pipeline_remark_logs')->where('status', $old)->update(['status' => $new]);
            DB::table('documents')->where('uploaded_status', $old)->update(['uploaded_status' => $new]);
        }
    }
};
