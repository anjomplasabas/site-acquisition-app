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
        Schema::create('approval_config_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('step1_config_id');
            $table->foreignId('step2_config_id')->nullable();
            $table->foreignId('step3_config_id')->nullable();
            $table->timestamp('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('approval_config_snapshots');
    }
};
