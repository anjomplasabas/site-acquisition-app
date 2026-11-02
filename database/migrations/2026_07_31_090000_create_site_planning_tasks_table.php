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
        Schema::create('site_planning_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_planning_assignment_id')->constrained()->cascadeOnDelete();
            $table->string('section')->nullable();
            $table->string('subsection')->nullable();
            $table->string('name');
            $table->string('status')->default('Pending');
            $table->timestamp('status_changed_at')->nullable();
            $table->decimal('weight', 5, 2)->unsigned()->default(0);
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('site_planning_tasks');
    }
};
