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
        Schema::table('site_surveys', function (Blueprint $table) {
            $table->timestamp('survey_completed_at')->nullable()->after('remarks');
            $table->foreignId('survey_completed_by_id')->nullable()->after('survey_completed_at')->constrained('users')->nullOnDelete();
            $table->timestamp('network_feasibility_completed_at')->nullable()->after('survey_completed_by_id');
            $table->foreignId('network_feasibility_completed_by_id')->nullable()->after('network_feasibility_completed_at')->constrained('users')->nullOnDelete();
        });

        DB::table('pipelines')
            ->whereNotIn('status', [
                'Prospect',
                'Prospecting',
                'Site Survey Pending',
                'Site Survey',
                'Inactive',
            ])
            ->whereNotNull('site_survey_id')
            ->select(['site_survey_id', 'updated_at'])
            ->orderBy('id')
            ->cursor()
            ->each(function (object $pipeline): void {
                DB::table('site_surveys')
                    ->where('id', $pipeline->site_survey_id)
                    ->whereNull('survey_completed_at')
                    ->update([
                        'survey_completed_at' => $pipeline->updated_at ?? now(),
                        'network_feasibility_completed_at' => $pipeline->updated_at ?? now(),
                    ]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('site_surveys', function (Blueprint $table) {
            $table->dropConstrainedForeignId('network_feasibility_completed_by_id');
            $table->dropColumn('network_feasibility_completed_at');
            $table->dropConstrainedForeignId('survey_completed_by_id');
            $table->dropColumn('survey_completed_at');
        });
    }
};
