<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // project_tasks — dashboard critical query filters by target_date
        Schema::table('project_tasks', function (Blueprint $table) {
            $table->index('target_date');
        });

        // project_phases — group-based display filtering
        Schema::table('project_phases', function (Blueprint $table) {
            $table->index('group');
        });

        // projects — SoftDeletes WHERE deleted_at IS NULL on every query
        Schema::table('projects', function (Blueprint $table) {
            $table->index('deleted_at');
        });

        // pipeline_approvals — role and approver lookups in eager loads
        Schema::table('pipeline_approvals', function (Blueprint $table) {
            $table->index('role_id');
            $table->index('decided_by_id');
        });

        // approval_configs — role lookup for config matching
        Schema::table('approval_configs', function (Blueprint $table) {
            $table->index('role_id');
        });

        // site_survey_documents — FK joins; no indexes exist
        Schema::table('site_survey_documents', function (Blueprint $table) {
            $table->index('site_survey_id');
            $table->index('document_id');
        });

        // survey_providers — FK join to site_surveys
        Schema::table('survey_providers', function (Blueprint $table) {
            $table->index('site_survey_id');
        });

        // site_profiles — FK lookups for address and contact
        Schema::table('site_profiles', function (Blueprint $table) {
            $table->index('address_id');
            $table->index('contact_id');
        });

        // sessions — lookup by user for session management
        Schema::table('sessions', function (Blueprint $table) {
            $table->index('user_id');
            $table->index('last_activity');
        });

        // documents — FK lookup for uploader
        Schema::table('documents', function (Blueprint $table) {
            $table->index('uploaded_by');
        });
    }

    public function down(): void
    {
        // project_tasks and project_phases were dropped for good by the later WBS squash
        // (2026_07_19_060000_rebuild_project_stages_without_tasks) — nothing to reverse on
        // either one if a rollback has already gone that far back.
        if (Schema::hasTable('project_tasks')) {
            Schema::table('project_tasks', function (Blueprint $table) {
                $table->dropIndex(['target_date']);
            });
        }

        if (Schema::hasTable('project_phases')) {
            Schema::table('project_phases', function (Blueprint $table) {
                $table->dropIndex(['group']);
            });
        }

        Schema::table('projects', function (Blueprint $table) {
            $table->dropIndex(['deleted_at']);
        });

        Schema::table('pipeline_approvals', function (Blueprint $table) {
            $table->dropIndex(['role_id']);
            $table->dropIndex(['decided_by_id']);
        });

        Schema::table('approval_configs', function (Blueprint $table) {
            $table->dropIndex(['role_id']);
        });

        Schema::table('site_survey_documents', function (Blueprint $table) {
            $table->dropIndex(['site_survey_id']);
            $table->dropIndex(['document_id']);
        });

        Schema::table('survey_providers', function (Blueprint $table) {
            $table->dropIndex(['site_survey_id']);
        });

        Schema::table('site_profiles', function (Blueprint $table) {
            $table->dropIndex(['address_id']);
            $table->dropIndex(['contact_id']);
        });

        Schema::table('sessions', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
            $table->dropIndex(['last_activity']);
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->dropIndex(['uploaded_by']);
        });
    }
};
