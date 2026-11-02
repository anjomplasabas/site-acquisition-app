<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_profiles', function (Blueprint $table) {
            $table->date('building_operation_date')->nullable()->after('developer');
            $table->unsignedInteger('total_building_count')->nullable()->after('building_operation_date');
            $table->unsignedInteger('total_parking_floors')->nullable()->after('total_unit_count');
            $table->unsignedInteger('residential_floor_starts_at')->nullable()->after('total_parking_floors');
            $table->text('amenities')->nullable()->after('residential_floor_starts_at');
            $table->unsignedInteger('total_commercial_unit_count')->nullable()->after('amenities');
            $table->decimal('residential_occupancy_percentage', 5, 2)->nullable()->after('total_commercial_unit_count');
            $table->unsignedInteger('total_units_per_floor')->nullable()->after('residential_occupancy_percentage');
            $table->text('room_types')->nullable()->after('total_units_per_floor');
        });
    }

    public function down(): void
    {
        Schema::table('site_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'building_operation_date',
                'total_building_count',
                'total_parking_floors',
                'residential_floor_starts_at',
                'amenities',
                'total_commercial_unit_count',
                'residential_occupancy_percentage',
                'total_units_per_floor',
                'room_types',
            ]);
        });
    }
};
