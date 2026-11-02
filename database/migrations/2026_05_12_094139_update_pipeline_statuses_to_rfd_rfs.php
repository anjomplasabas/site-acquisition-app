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
        $renames = [
            'Acquired' => 'RFD',
            'Active' => 'RFS',
        ];

        foreach ($renames as $old => $new) {
            DB::table('pipelines')->where('status', $old)->update(['status' => $new]);
            DB::table('pipeline_status_logs')->where('status', $old)->update(['status' => $new]);
            DB::table('pipeline_remark_logs')->where('status', $old)->update(['status' => $new]);
            DB::table('role_permissions')->where('pipeline_status', $old)->update(['pipeline_status' => $new]);
        }

        Schema::table('pipelines', function (Blueprint $table) {
            $table->string('status')->default('Prospect')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $renames = [
            'RFD' => 'Acquired',
            'RFS' => 'Active',
        ];

        foreach ($renames as $old => $new) {
            DB::table('pipelines')->where('status', $old)->update(['status' => $new]);
            DB::table('pipeline_status_logs')->where('status', $old)->update(['status' => $new]);
            DB::table('pipeline_remark_logs')->where('status', $old)->update(['status' => $new]);
            DB::table('role_permissions')->where('pipeline_status', $old)->update(['pipeline_status' => $new]);
        }

        Schema::table('pipelines', function (Blueprint $table) {
            $table->string('status')->default('Prospect')->change();
        });
    }
};
