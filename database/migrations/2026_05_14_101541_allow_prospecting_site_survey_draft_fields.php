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
            $table->foreignId('surveyor_id')->nullable()->change();
            $table->timestamp('survey_date')->nullable()->change();
        });
    }

    /**
     * Intentionally a no-op: surveyor_id/survey_date being nullable is load-bearing application
     * behavior now (a SiteSurvey is created as soon as a pipeline enters Site Survey Pending,
     * before any surveyor is scheduled — see SiteSurveyProgression::ensureSiteSurvey()). Forcing
     * NOT NULL back here would throw "Invalid use of NULL value" (MySQL 1138) on any database
     * with an unscheduled survey, which is normal, expected data — not something to repair or
     * delete. Rolling back this migration is unsupported.
     */
    public function down(): void
    {
        //
    }
};
