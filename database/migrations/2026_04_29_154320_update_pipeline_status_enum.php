<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('pipelines')
            ->where('status', 'Lost / Cancelled')
            ->update(['status' => 'Inactive']);

        Schema::table('pipelines', function (Blueprint $table) {
            $table->string('status')->default('Prospect')->change();
        });
    }

    public function down(): void
    {
        DB::table('pipelines')
            ->where('status', 'Inactive')
            ->update(['status' => 'Lost / Cancelled']);

        Schema::table('pipelines', function (Blueprint $table) {
            $table->string('status')->default('Site Visit Approval')->change();
        });
    }
};
