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
        Schema::table('site_planning_assignments', function (Blueprint $table) {
            $table->foreignId('tower_id')->nullable()->after('pipeline_id')->constrained()->nullOnDelete();
            // Nullable tower_id means MySQL won't enforce uniqueness for the untowered case (every
            // NULL is distinct in a unique index) — SitePlanningAssignment::ensureFor() remains the
            // single, idempotent write path relied on for that guarantee, same as other app-enforced
            // relationships already in this schema (see docs/database-erd.md).
            $table->unique(['pipeline_id', 'tower_id']);
            // Must add the composite unique above before dropping this one — pipeline_id still
            // carries a foreign key, and MySQL refuses to drop the only index covering an FK
            // column (error 1553), even mid-migration.
            $table->dropUnique(['pipeline_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('site_planning_assignments', function (Blueprint $table) {
            $table->unique('pipeline_id');
            $table->dropUnique(['pipeline_id', 'tower_id']);
            $table->dropConstrainedForeignId('tower_id');
        });
    }
};
