<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stage_tasks', function (Blueprint $table) {
            $table->string('section')->nullable()->after('field_key');
            $table->string('subsection')->nullable()->after('section');
        });
    }

    public function down(): void
    {
        Schema::table('stage_tasks', function (Blueprint $table) {
            $table->dropColumn(['section', 'subsection']);
        });
    }
};
