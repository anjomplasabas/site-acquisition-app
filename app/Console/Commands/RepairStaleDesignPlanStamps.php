<?php

namespace App\Console\Commands;

use App\Enums\PipelineStatus;
use App\Models\SitePlanningAssignment;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * One-off, idempotent repair for pipelines that re-entered Site Survey (approval rejection or
 * Re-Open Survey) before SiteSurveyProgression::enterSiteSurvey() became tower-aware:
 * on towered sites the reset used to resolve Pipeline::sitePlanningAssignment() — the untowered
 * default, null once towers exist — so the Design Plan completion/approval stamps were never
 * cleared, and completeDesignPlan()/approveDesignPlan() refuse to re-run while they're set,
 * leaving the site stuck. Clears those stamps for every assignment whose pipeline currently sits
 * in Site Survey; touches nothing else (tasks, documents, surveys, assignees all retained).
 * Safe to re-run: already-null rows are skipped by the where clauses.
 */
#[Signature('app:repair-stale-design-plan-stamps')]
#[Description('Clear stale Design Plan completion/approval stamps on pipelines stuck in Site Survey')]
class RepairStaleDesignPlanStamps extends Command
{
    public function handle(): int
    {
        $repaired = SitePlanningAssignment::query()
            ->whereHas('pipeline', fn ($q) => $q->where('status', PipelineStatus::SiteSurvey->value))
            ->where(fn ($q) => $q
                ->whereNotNull('design_plan_completed_at')
                ->orWhereNotNull('design_plan_approved_at'))
            ->update([
                'design_plan_completed_at' => null,
                'design_plan_completed_by_id' => null,
                'design_plan_approved_at' => null,
                'design_plan_approved_by_id' => null,
            ]);

        $this->info("Cleared stale Design Plan stamps on {$repaired} assignment(s).");

        return self::SUCCESS;
    }
}
