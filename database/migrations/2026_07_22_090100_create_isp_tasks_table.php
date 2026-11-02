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
        Schema::create('isp_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_stage_isp_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('status')->default('Pending');
            $table->timestamp('status_changed_at')->nullable();
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('isp_tasks');
    }
};
