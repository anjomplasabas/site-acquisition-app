<?php

use App\Enums\ApprovalStatus;
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
        Schema::create('pipeline_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pipeline_id');
            $table->foreignId('config_snapshot_id');
            $table->integer('step');
            $table->foreignId('approver_id');
            $table->enum('status', array_column(ApprovalStatus::cases(), 'value'))
                ->default(ApprovalStatus::Pending->value);
            $table->timestamp('decided_at')->nullable();
            $table->text('remarks')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pipeline_approvals');
    }
};
