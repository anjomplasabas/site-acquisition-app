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
        Schema::create('network_feasibility_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('network_feasibility_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('project_stage');
            $table->string('status')->default('Pending');
            $table->timestamp('status_changed_at')->nullable();
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->boolean('requires_document')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('network_feasibility_tasks');
    }
};
