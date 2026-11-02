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
        Schema::table('project_stage_isps', function (Blueprint $table) {
            $table->foreignId('source_project_stage_isp_id')->nullable()->after('project_stage_id')
                ->constrained('project_stage_isps')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_stage_isps', function (Blueprint $table) {
            $table->dropConstrainedForeignId('source_project_stage_isp_id');
        });
    }
};
