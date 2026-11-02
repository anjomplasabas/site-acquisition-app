<?php

use App\Enums\ProviderStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('survey_providers', function (Blueprint $table) {
            $table->string('status')->default(ProviderStatus::Checking->value)->after('provider_name');
        });
    }

    public function down(): void
    {
        Schema::table('survey_providers', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
