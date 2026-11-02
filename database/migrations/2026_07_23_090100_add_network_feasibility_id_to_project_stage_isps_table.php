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
            $table->foreignId('network_feasibility_id')->nullable()->after('source_project_stage_isp_id')
                ->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_stage_isps', function (Blueprint $table) {
            $table->dropConstrainedForeignId('network_feasibility_id');
        });
    }
};
