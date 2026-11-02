<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Backfills site_surveys.pipeline_id for every survey row that predates that column (added in
     * add_pipeline_and_tower_id_to_site_surveys_table), matched via the pipeline's own
     * site_survey_id pointer. Without this, SiteSurveyProgression::advanceFromSiteSurveyWhenReady's
     * per-tower completion check (SiteSurvey::where('pipeline_id', ...)) would silently treat any
     * pre-existing pipeline's survey as "no incomplete towers" — vacuously true, since the query
     * would match zero rows — regardless of whether survey_completed_at is actually set.
     */
    public function up(): void
    {
        DB::statement(
            'update site_surveys
             set pipeline_id = (select id from pipelines where pipelines.site_survey_id = site_surveys.id limit 1)
             where pipeline_id is null
               and exists (select 1 from pipelines where pipelines.site_survey_id = site_surveys.id)'
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Not reversible — there's no way to distinguish a pipeline_id backfilled here from one
        // set by normal application writes after this migration ran.
    }
};
