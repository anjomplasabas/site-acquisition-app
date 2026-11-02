<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * CommercialDocumentType duplicated what StageDocumentRequirement (module=pipeline,
     * stage=Commercials) already does, but with no Settings UI to manage it — it silently
     * overrode the Settings "Required" toggle for MOA/NTP no matter what was configured there.
     * Its intent (MOA/NTP required by default) now lives in PipelineStageDocumentRequirementSeeder
     * instead, matching how ProjectStageDocumentRequirementSeeder already seeds Planning's MOA/NTP
     * — deliberately a seeder, not baked into this migration, so it doesn't change what a fresh
     * test database defaults to.
     */
    public function up(): void
    {
        Schema::dropIfExists('commercial_document_types');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('commercial_document_types', function ($table) {
            $table->id();
            $table->string('name');
            $table->boolean('is_required')->default(false);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }
};
