<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pipeline_approvals', function (Blueprint $table) {
            $table->dropColumn('config_snapshot_id');
        });

        Schema::dropIfExists('approval_config_snapshots');
    }

    public function down(): void
    {
        Schema::create('approval_config_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('step1_config_id');
            $table->foreignId('step2_config_id')->nullable();
            $table->foreignId('step3_config_id')->nullable();
            $table->timestamp('created_at');
        });

        Schema::table('pipeline_approvals', function (Blueprint $table) {
            $table->foreignId('config_snapshot_id')->after('pipeline_id');
        });
    }
};
