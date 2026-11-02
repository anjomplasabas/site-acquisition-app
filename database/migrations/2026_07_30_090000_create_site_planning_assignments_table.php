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
        Schema::create('site_planning_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pipeline_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('site_survey_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('assigned_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('site_planning_assignments');
    }
};
