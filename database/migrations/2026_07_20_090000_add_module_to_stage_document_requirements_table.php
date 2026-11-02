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
        Schema::table('stage_document_requirements', function (Blueprint $table) {
            $table->string('module')->default('pipeline')->after('id');
        });

        Schema::table('stage_document_requirements', function (Blueprint $table) {
            $table->dropUnique(['stage', 'name']);
            $table->unique(['module', 'stage', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stage_document_requirements', function (Blueprint $table) {
            $table->dropUnique(['module', 'stage', 'name']);
            $table->unique(['stage', 'name']);
            $table->dropColumn('module');
        });
    }
};
