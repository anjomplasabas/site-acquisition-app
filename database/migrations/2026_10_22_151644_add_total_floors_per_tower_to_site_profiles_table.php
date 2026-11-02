<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Total Floor per Tower" — the per-tower planning companion to total_unit_count (relabeled
 * "Total Unit per Tower") for multi-building sites.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_profiles', function (Blueprint $table): void {
            $table->unsignedInteger('total_floors_per_tower')->nullable()->after('total_floors');
        });
    }

    public function down(): void
    {
        Schema::table('site_profiles', function (Blueprint $table): void {
            $table->dropColumn('total_floors_per_tower');
        });
    }
};
