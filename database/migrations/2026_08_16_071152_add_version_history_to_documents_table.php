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
        Schema::table('documents', function (Blueprint $table) {
            $table->unsignedInteger('version')->default(1)->after('category');
            $table->foreignId('root_document_id')->nullable()->after('version')->constrained('documents')->nullOnDelete();
            $table->timestamp('superseded_at')->nullable()->after('root_document_id');
        });

        DB::statement('UPDATE documents SET root_document_id = id WHERE root_document_id IS NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('root_document_id');
            $table->dropColumn(['version', 'superseded_at']);
        });
    }
};
