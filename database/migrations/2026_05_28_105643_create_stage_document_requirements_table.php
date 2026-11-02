<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('stage_document_requirements', function (Blueprint $table) {
            $table->id();
            $table->string('stage');
            $table->string('name');
            $table->boolean('is_required')->default(true);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['stage', 'name']);
            $table->index(['stage', 'is_active', 'is_required']);
        });

        if (Schema::hasTable('commercial_document_types')) {
            DB::table('commercial_document_types')
                ->orderBy('id')
                ->cursor()
                ->each(function (object $type): void {
                    $keys = [
                        'stage' => 'Commercials',
                        'name' => $type->name,
                    ];

                    $values = [
                        'is_required' => (bool) $type->is_required,
                        'is_active' => (bool) $type->is_active,
                        'sort_order' => (int) ($type->sort_order ?? 0),
                        'updated_at' => now(),
                    ];

                    if (! DB::table('stage_document_requirements')->where($keys)->exists()) {
                        $values['created_at'] = now();
                    }

                    DB::table('stage_document_requirements')->updateOrInsert($keys, $values);
                });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stage_document_requirements');
    }
};
