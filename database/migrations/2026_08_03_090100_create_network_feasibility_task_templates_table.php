<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The Network module's JO (Job Order) checklist template — one continuous flat list, no
 * design_plan_type variance (unlike task_templates). Data transcribed verbatim from the
 * previously-hardcoded App\Support\NetworkFeasibilityTaskDefinitions, which now reads from this
 * table instead.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('network_feasibility_task_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('stage'); // App\Enums\ProjectStage value
            $table->boolean('requires_document')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $now = now();

        $tasks = [
            ['name' => 'JO Approval', 'stage' => 'Planning', 'requires_document' => true],
            ['name' => 'Send JO to ISP', 'stage' => 'Planning', 'requires_document' => false],
            ['name' => 'ISP Schedules Actual Site Survey', 'stage' => 'Planning', 'requires_document' => false],
            ['name' => 'Actual Site Survey & Design Plan Prep', 'stage' => 'Planning', 'requires_document' => false],
            ['name' => 'Design Plan Endorsement', 'stage' => 'Planning', 'requires_document' => false],
            ['name' => 'Design Plan Approval', 'stage' => 'Planning', 'requires_document' => false],
            ['name' => 'Project Implementation', 'stage' => 'Execution', 'requires_document' => false],
            ['name' => 'Installation Completion', 'stage' => 'Monitoring', 'requires_document' => false],
            ['name' => 'Final Testing / Punch list / Handover', 'stage' => 'Monitoring', 'requires_document' => false],
        ];

        DB::table('network_feasibility_task_templates')->insert(array_values(array_map(
            fn (array $task, int $index): array => [
                'name' => $task['name'],
                'stage' => $task['stage'],
                'requires_document' => $task['requires_document'],
                'sort_order' => $index,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            $tasks,
            array_keys($tasks)
        )));
    }

    public function down(): void
    {
        Schema::dropIfExists('network_feasibility_task_templates');
    }
};
