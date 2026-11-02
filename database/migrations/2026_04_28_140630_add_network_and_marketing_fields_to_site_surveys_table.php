<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_surveys', function (Blueprint $table) {
            $table->string('building_network_system')->nullable()->after('equipment_notes');
            $table->string('indoor_outlet_capacity')->nullable()->after('building_network_system');
            $table->unsignedInteger('isp_count_per_unit')->nullable()->after('indoor_outlet_capacity');
            $table->string('telco_room_location')->nullable()->after('isp_count_per_unit');
            $table->string('electrical_room_location')->nullable()->after('telco_room_location');
            $table->boolean('lobby_booth_allowed')->nullable()->after('electrical_room_location');
            $table->boolean('lobby_standee_allowed')->nullable()->after('lobby_booth_allowed');
            $table->boolean('lobby_flyers_stand_allowed')->nullable()->after('lobby_standee_allowed');
            $table->boolean('roaming_sales_rep_allowed')->nullable()->after('lobby_flyers_stand_allowed');
            $table->string('telco_room_fee')->nullable()->after('roaming_sales_rep_allowed');
            $table->text('marketing_paraphernalia')->nullable()->after('telco_room_fee');
            $table->text('brochure_posting_location')->nullable()->after('marketing_paraphernalia');
        });
    }

    public function down(): void
    {
        Schema::table('site_surveys', function (Blueprint $table) {
            $table->dropColumn([
                'building_network_system',
                'indoor_outlet_capacity',
                'isp_count_per_unit',
                'telco_room_location',
                'electrical_room_location',
                'lobby_booth_allowed',
                'lobby_standee_allowed',
                'lobby_flyers_stand_allowed',
                'roaming_sales_rep_allowed',
                'telco_room_fee',
                'marketing_paraphernalia',
                'brochure_posting_location',
            ]);
        });
    }
};
