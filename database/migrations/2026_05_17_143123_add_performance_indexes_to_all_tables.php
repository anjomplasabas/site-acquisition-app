<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // pipelines — status filter, visibility filters, dashboard stale query, soft-delete, pagination sort
        Schema::table('pipelines', function (Blueprint $table) {
            $table->index('status');
            $table->index('project_manager_id');
            $table->index('assigned_saq_id');
            $table->index('status_changed_at');
            $table->index('deleted_at');
            $table->index(['status', 'created_at']);
        });

        // users — status filter, FK lookups, full_name sort
        Schema::table('users', function (Blueprint $table) {
            $table->index('status');
            $table->index('role_id');
            $table->index('department_id');
            $table->index('full_name');
        });

        // pipeline_approvals — loaded per pipeline; pending approval decision query
        Schema::table('pipeline_approvals', function (Blueprint $table) {
            $table->index('pipeline_id');
            $table->index(['pipeline_id', 'status']);
        });

        // pipeline_documents — existence check and eager load per pipeline
        Schema::table('pipeline_documents', function (Blueprint $table) {
            $table->index('pipeline_id');
            $table->index('document_id');
        });

        // pipeline_remark_logs / pipeline_status_logs — eager loaded per pipeline
        Schema::table('pipeline_remark_logs', function (Blueprint $table) {
            $table->index('pipeline_id');
        });

        Schema::table('pipeline_status_logs', function (Blueprint $table) {
            $table->index('pipeline_id');
        });

        // approval_configs — WHERE is_active = true ORDER BY step (common in settings + commercials)
        Schema::table('approval_configs', function (Blueprint $table) {
            $table->index(['is_active', 'step']);
        });

        // role_permissions — used in whereHas role visibility checks
        Schema::table('role_permissions', function (Blueprint $table) {
            $table->index('role_id');
            $table->index(['role_id', 'pipeline_status']);
        });

        // site_profiles — ORDER BY name for dropdowns; soft-delete
        Schema::table('site_profiles', function (Blueprint $table) {
            $table->index('name');
            $table->index('deleted_at');
        });

        // site_contacts — soft-delete; name search
        Schema::table('site_contacts', function (Blueprint $table) {
            $table->index('deleted_at');
            $table->index('name');
        });

        // site_surveys — FK lookups
        Schema::table('site_surveys', function (Blueprint $table) {
            $table->index('site_profile_id');
            $table->index('surveyor_id');
        });
    }

    public function down(): void
    {
        Schema::table('pipelines', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['project_manager_id']);
            $table->dropIndex(['assigned_saq_id']);
            $table->dropIndex(['status_changed_at']);
            $table->dropIndex(['deleted_at']);
            $table->dropIndex(['status', 'created_at']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['role_id']);
            $table->dropIndex(['department_id']);
            $table->dropIndex(['full_name']);
        });

        Schema::table('pipeline_approvals', function (Blueprint $table) {
            $table->dropIndex(['pipeline_id']);
            $table->dropIndex(['pipeline_id', 'status']);
        });

        Schema::table('pipeline_documents', function (Blueprint $table) {
            $table->dropIndex(['pipeline_id']);
            $table->dropIndex(['document_id']);
        });

        Schema::table('pipeline_remark_logs', function (Blueprint $table) {
            $table->dropIndex(['pipeline_id']);
        });

        Schema::table('pipeline_status_logs', function (Blueprint $table) {
            $table->dropIndex(['pipeline_id']);
        });

        Schema::table('approval_configs', function (Blueprint $table) {
            $table->dropIndex(['is_active', 'step']);
        });

        Schema::table('role_permissions', function (Blueprint $table) {
            $table->dropIndex(['role_id']);
            $table->dropIndex(['role_id', 'pipeline_status']);
        });

        Schema::table('site_profiles', function (Blueprint $table) {
            $table->dropIndex(['name']);
            $table->dropIndex(['deleted_at']);
        });

        Schema::table('site_contacts', function (Blueprint $table) {
            $table->dropIndex(['deleted_at']);
            $table->dropIndex(['name']);
        });

        Schema::table('site_surveys', function (Blueprint $table) {
            $table->dropIndex(['site_profile_id']);
            $table->dropIndex(['surveyor_id']);
        });
    }
};
