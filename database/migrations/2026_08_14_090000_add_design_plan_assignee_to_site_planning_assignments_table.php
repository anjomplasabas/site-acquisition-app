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
            $table->foreignId('design_plan_assignee_id')->nullable()->after('design_plan_type')->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('site_planning_assignments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('design_plan_assignee_id');
        });
    }
};
