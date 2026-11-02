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
            $table->string('normalized_name')->nullable()->after('name')->index();
        });

        Schema::table('pipelines', function (Blueprint $table) {
            $table->string('normalized_name')->nullable()->after('name')->index();
            $table->timestamp('last_activity_at')->nullable()->after('status_changed_at')->index();
        });

        DB::table('site_profiles')
            ->orderBy('id')
            ->cursor()
            ->each(fn (object $siteProfile) => DB::table('site_profiles')
                ->where('id', $siteProfile->id)
                ->update(['normalized_name' => str($siteProfile->name)->lower()->squish()->toString()]));

        DB::table('pipelines')
            ->orderBy('id')
            ->cursor()
            ->each(fn (object $pipeline) => DB::table('pipelines')
                ->where('id', $pipeline->id)
                ->update([
                    'normalized_name' => str($pipeline->name)->lower()->squish()->toString(),
                    'last_activity_at' => $pipeline->updated_at ?? $pipeline->created_at,
                ]));
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pipelines', function (Blueprint $table) {
            $table->dropIndex(['normalized_name']);
            $table->dropIndex(['last_activity_at']);
            $table->dropColumn(['normalized_name', 'last_activity_at']);
        });

        Schema::table('site_profiles', function (Blueprint $table) {
            $table->dropIndex(['normalized_name']);
            $table->dropColumn('normalized_name');
        });
    }
};
