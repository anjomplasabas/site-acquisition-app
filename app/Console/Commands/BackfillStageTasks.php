<?php

namespace App\Console\Commands;

use App\Models\ProjectStage;
use App\Support\StageTaskSeeder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * One-off, idempotent backfill for ProjectStage rows created before a stage_task_list field
 * existed on their stage (e.g. Planning's "Plans/Drawing" checklist, added after some projects
 * already had a Planning stage row) — InitializeProject only seeds stage_task_list fields once,
 * at project-creation time, so pre-existing rows never picked up a newly-added field on their own.
 * Safe to re-run: StageTaskSeeder::seedMissingFor() skips any field_key that already has tasks.
 */
#[Signature('app:backfill-stage-tasks')]
#[Description('Seed missing stage_task_list checklist tasks (e.g. Plans/Drawing) for existing project stages')]
class BackfillStageTasks extends Command
{
    public function handle(): int
    {
        $seeded = 0;

        ProjectStage::query()->chunkById(100, function ($stages) use (&$seeded): void {
            foreach ($stages as $stage) {
                $before = $stage->stageTasks()->count();
                StageTaskSeeder::seedMissingFor($stage);
                $seeded += $stage->stageTasks()->count() - $before;
            }
        });

        $this->info("Backfilled {$seeded} stage task(s).");

        return self::SUCCESS;
    }
}
