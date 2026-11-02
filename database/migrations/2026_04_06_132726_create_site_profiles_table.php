<?php

use App\Enums\SiteProfileType;
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
        Schema::create('site_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('developer')->nullable();
            $table->enum('type', array_column(SiteProfileType::cases(), 'value'));
            $table->foreignId('address_id');
            $table->foreignId('contact_id')->nullable();
            $table->integer('total_unit_count')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('site_profiles');
    }
};
