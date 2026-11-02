<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stage_tasks', function (Blueprint $table) {
            $table->decimal('weight', 5, 2)->unsigned()->default(0)->change();
        });
    }

    public function down(): void
    {
        Schema::table('stage_tasks', function (Blueprint $table) {
            $table->unsignedTinyInteger('weight')->default(0)->change();
        });
    }
};
