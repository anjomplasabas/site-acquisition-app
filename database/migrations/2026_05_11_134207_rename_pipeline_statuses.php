<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $renames = [
            'Site Visit Approval' => 'Initiated',
            'Ongoing Deployment' => 'Acquired',
        ];

        foreach ($renames as $old => $new) {
            DB::table('pipelines')->where('status', $old)->update(['status' => $new]);
            DB::table('pipeline_status_logs')->where('status', $old)->update(['status' => $new]);
            DB::table('pipeline_remark_logs')->where('status', $old)->update(['status' => $new]);
            DB::table('role_permissions')->where('pipeline_status', $old)->update(['pipeline_status' => $new]);
        }
    }

    public function down(): void
    {
        $renames = [
            'Initiated' => 'Site Visit Approval',
            'Acquired' => 'Ongoing Deployment',
        ];

        foreach ($renames as $old => $new) {
            DB::table('pipelines')->where('status', $old)->update(['status' => $new]);
            DB::table('pipeline_status_logs')->where('status', $old)->update(['status' => $new]);
            DB::table('pipeline_remark_logs')->where('status', $old)->update(['status' => $new]);
            DB::table('role_permissions')->where('pipeline_status', $old)->update(['pipeline_status' => $new]);
        }
    }
};
