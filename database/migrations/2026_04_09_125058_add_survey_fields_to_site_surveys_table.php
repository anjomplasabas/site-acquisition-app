<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_surveys', function (Blueprint $table) {
            $table->unsignedInteger('floors_surveyed')->nullable()->after('survey_date');
            $table->unsignedInteger('actual_unit_count')->nullable()->after('floors_surveyed');
            $table->boolean('has_roof_access')->nullable()->after('actual_unit_count');
            $table->text('equipment_notes')->nullable()->after('has_roof_access');
            $table->text('remarks')->nullable()->after('equipment_notes');
        });
    }

    public function down(): void
    {
        Schema::table('site_surveys', function (Blueprint $table) {
            $table->dropColumn(['floors_surveyed', 'actual_unit_count', 'has_roof_access', 'equipment_notes', 'remarks']);
        });
    }
};
