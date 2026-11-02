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
        Schema::table('site_surveys', function (Blueprint $table) {
            // Nullable: historic rows and single/no-tower sites have no owning pipeline recorded
            // here (the pointer runs the other way, via pipelines.site_survey_id) — this column
            // exists so a pipeline that fans out into multiple towers can look up every
            // SiteSurvey row it created (one per tower), not just its single primary survey.
            $table->foreignId('pipeline_id')->nullable()->after('site_profile_id')->constrained()->nullOnDelete();
            $table->foreignId('tower_id')->nullable()->after('pipeline_id')->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('site_surveys', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tower_id');
            $table->dropConstrainedForeignId('pipeline_id');
        });
    }
};
