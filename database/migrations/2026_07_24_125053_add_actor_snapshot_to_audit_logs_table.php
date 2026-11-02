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
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->string('actor_name')->nullable()->after('user_id');
            $table->string('actor_email')->nullable()->after('actor_name');
        });

        // Backfill via a PHP-side loop (not a SQL join) so this runs on both
        // MySQL (dev/production) and SQLite (tests).
        DB::table('audit_logs')
            ->whereNotNull('user_id')
            ->orderBy('id')
            ->chunkById(500, function ($logs) {
                $users = DB::table('users')
                    ->whereIn('id', $logs->pluck('user_id')->unique())
                    ->get(['id', 'full_name', 'email'])
                    ->keyBy('id');

                foreach ($logs as $log) {
                    $user = $users->get($log->user_id);

                    if ($user) {
                        DB::table('audit_logs')->where('id', $log->id)->update([
                            'actor_name' => $user->full_name,
                            'actor_email' => $user->email,
                        ]);
                    }
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropColumn(['actor_name', 'actor_email']);
        });
    }
};
