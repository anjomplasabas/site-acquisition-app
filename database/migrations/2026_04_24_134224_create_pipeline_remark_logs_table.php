<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pipeline_remark_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pipeline_id');
            $table->string('status');
            $table->text('content');
            $table->foreignId('added_by');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pipeline_remark_logs');
    }
};
