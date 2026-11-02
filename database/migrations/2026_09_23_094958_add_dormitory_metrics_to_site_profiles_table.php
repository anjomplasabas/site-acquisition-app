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
        Schema::table('site_profiles', function (Blueprint $table) {
            $table->decimal('turnover_rate', 5, 2)->nullable()->after('total_units_per_floor');
            $table->decimal('occupancy_rate', 5, 2)->nullable()->after('turnover_rate');
            $table->string('whz_location')->nullable()->after('occupancy_rate');
            $table->unsignedInteger('total_whz_allocation')->nullable()->after('whz_location');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('site_profiles', function (Blueprint $table) {
            $table->dropColumn(['turnover_rate', 'occupancy_rate', 'whz_location', 'total_whz_allocation']);
        });
    }
};
