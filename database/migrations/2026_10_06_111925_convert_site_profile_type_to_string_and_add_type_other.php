<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('site_profiles', function (Blueprint $table) {
            $table->string('type')->change();
            $table->string('type_other')->nullable()->after('type');
        });

        DB::table('site_profiles')->where('type', 'Condo')->update(['type' => 'Condominium']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('site_profiles')->where('type', 'Condominium')->update(['type' => 'Condo']);

        Schema::table('site_profiles', function (Blueprint $table) {
            $table->dropColumn('type_other');
        });
    }
};
