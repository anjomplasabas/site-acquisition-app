<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pipelines', function (Blueprint $table) {
            $table->dropColumn('commercial_price');
            $table->text('commercial_remarks')->nullable()->after('priority_level');
        });
    }

    public function down(): void
    {
        Schema::table('pipelines', function (Blueprint $table) {
            $table->dropColumn('commercial_remarks');
            $table->decimal('commercial_price', 15, 2)->nullable()->after('priority_level');
        });
    }
};
