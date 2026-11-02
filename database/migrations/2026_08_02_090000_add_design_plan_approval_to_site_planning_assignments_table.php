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
        Schema::table('site_planning_assignments', function (Blueprint $table) {
            $table->timestamp('design_plan_completed_at')->nullable()->after('design_plan_type');
            $table->foreignId('design_plan_completed_by_id')->nullable()->after('design_plan_completed_at')->constrained('users')->nullOnDelete();
            $table->timestamp('design_plan_approved_at')->nullable()->after('design_plan_completed_by_id');
            $table->foreignId('design_plan_approved_by_id')->nullable()->after('design_plan_approved_at')->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('site_planning_assignments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('design_plan_completed_by_id');
            $table->dropConstrainedForeignId('design_plan_approved_by_id');
            $table->dropColumn(['design_plan_completed_at', 'design_plan_approved_at']);
        });
    }
};
