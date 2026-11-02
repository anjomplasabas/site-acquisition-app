<?php

namespace App\Support;

use App\Enums\PipelineStatus;
use App\Models\NetworkSiteAssignment;
use App\Models\Pipeline;
use App\Models\PipelineStatusLog;
use App\Models\SitePlanningAssignment;
use App\Models\SiteSurvey;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Shared Site Survey business rules used by both the Pipelines module (survey findings)
 * and the Network module (Network Feasibility) — a pipeline only leaves Site Survey once
 * both halves are complete.
 */
class SiteSurveyProgression
{
    public static function ensureSiteSurvey(Pipeline $pipeline): SiteSurvey
    {
        if ($pipeline->siteSurvey !== null) {
            return $pipeline->siteSurvey;
        }

        $survey = SiteSurvey::create([
            'site_profile_id' => $pipeline->site_profile_id,
            'pipeline_id' => $pipeline->id,
        ]);

        $pipeline->update(['site_survey_id' => $survey->id]);
        $pipeline->setRelation('siteSurvey', $survey);

        return $survey;
    }

    /**
     * Transition the pipeline into Site Survey, ensure its SiteSurvey row exists, log the
     * status change, and ensure the Network and Site Planning modules' assignment records for
     * this site exist. Shared by Pipelines (re-entering Site Survey on an approval rejection or
     * a reopened survey) and Site Planning (scheduling the survey for the first time).
     */
    public static function enterSiteSurvey(Pipeline $pipeline, ?User $actor = null): SiteSurvey
    {
        $survey = self::ensureSiteSurvey($pipeline);

        // Reset both halves' completion markers so re-entering Site Survey (reopen, or an
        // Approval rejection) can reach Commercials again once both are redone —
        // advanceFromSiteSurveyWhenReady() only fires once per completion timestamp, and
        // completeFeasibility()/approveDesignPlan() both refuse to re-run while their own
        // timestamp is still set. Deliberately does not touch surveyor_id/survey_date, the
        // Site Planning tasks, or the Network Feasibility entries/tasks — those stay intact so
        // a re-completion is just re-running the existing "all tasks Done" checks, not redoing
        // the underlying work.
        //
        // Survey findings fan out per tower: every tower survey must be re-completable,
        // so the reset covers the same pipeline_id-or-primary-id union that
        // advanceFromSiteSurveyWhenReady() checks before advancing. Network Feasibility stays
        // pipeline-level/untowered by design (§FR-NET), so its markers reset on the primary
        // survey only.
        self::surveysFor($pipeline, $survey)->update([
            'survey_completed_at' => null,
            'survey_completed_by_id' => null,
        ]);

        $survey->update([
            'network_feasibility_completed_at' => null,
            'network_feasibility_completed_by_id' => null,
        ]);

        // Every tower's Design Plan returns to re-completable — a rejection/reopen is
        // pipeline-level, and Pipeline::sitePlanningAssignment() alone would miss towered
        // assignments entirely (it only resolves the untowered default, which no longer
        // exists once towers are declared).
        $pipeline->sitePlanningAssignments()->update([
            'design_plan_completed_at' => null,
            'design_plan_completed_by_id' => null,
            'design_plan_approved_at' => null,
            'design_plan_approved_by_id' => null,
        ]);

        $pipeline->update(['status' => PipelineStatus::SiteSurvey]);
        PipelineStatusLog::record($pipeline, PipelineStatus::SiteSurvey);

        $networkAssignment = NetworkSiteAssignment::ensureFor($pipeline, $survey);
        if ($networkAssignment->wasRecentlyCreated && $actor !== null) {
            app(PipelineNotifier::class)->networkAssignmentCreated($networkAssignment, $actor);
        }

        SitePlanningAssignment::ensureFor($pipeline, $survey);

        return $survey;
    }

    /**
     * Fires readyForCommercials() the moment both halves (survey findings + network feasibility)
     * are done — the single choke point reached from both SitePlanningController::approveDesignPlan()
     * and NetworkController::completeFeasibility().
     */
    public static function advanceFromSiteSurveyWhenReady(Pipeline $pipeline, ?User $actor = null): bool
    {
        $pipeline->load('siteSurvey');

        if (
            $pipeline->status !== PipelineStatus::SiteSurvey
            // Network Feasibility stays pipeline-level/untowered by design (§FR-NET), so this
            // half is always checked against the primary survey regardless of tower count.
            || $pipeline->siteSurvey?->network_feasibility_completed_at === null
            // Survey findings, however, fan out per tower — the pipeline can't advance to
            // Commercials until every tower's own survey is completed, not just the primary one
            // (a site with no declared towers has exactly one survey here, same as before). The
            // primary survey's own id is included explicitly (not just pipeline_id) as a
            // defense-in-depth fallback for any historical row the pipeline_id backfill missed.
            || self::surveysFor($pipeline, $pipeline->siteSurvey)
                ->whereNull('survey_completed_at')
                ->exists()
        ) {
            return false;
        }

        $pipeline->update(['status' => PipelineStatus::Commercials]);
        PipelineStatusLog::record($pipeline, PipelineStatus::Commercials);

        if ($actor !== null) {
            app(PipelineNotifier::class)->readyForCommercials($pipeline, $actor);
        }

        return true;
    }

    /**
     * Every survey belonging to this pipeline — all tower surveys via pipeline_id, plus the
     * primary survey by its own id as a defense-in-depth fallback for any historical row the
     * pipeline_id backfill missed. Shared by the enterSiteSurvey() reset and the
     * advanceFromSiteSurveyWhenReady() completeness check so the two can't drift apart.
     *
     * @return Builder<SiteSurvey>
     */
    private static function surveysFor(Pipeline $pipeline, ?SiteSurvey $primarySurvey): Builder
    {
        return SiteSurvey::query()->where(fn ($q) => $q->where('pipeline_id', $pipeline->id)->when(
            $primarySurvey !== null,
            fn ($q) => $q->orWhere('id', $primarySurvey->id)
        ));
    }
}
