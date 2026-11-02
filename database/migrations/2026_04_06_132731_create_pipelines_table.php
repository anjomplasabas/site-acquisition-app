<?php

use App\Enums\PriorityLevel;
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
        Schema::create('pipelines', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('pipeline_id');
            $table->foreignId('project_manager_id');
            $table->foreignId('site_profile_id');
            $table->timestamp('endorsement_date')->nullable();
            $table->foreignId('site_survey_id')->nullable();
            $table->enum('priority_level', array_column(PriorityLevel::cases(), 'value'))->nullable();
            $table->string('status')->default('Site Visit Approval');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pipelines');
    }
};
