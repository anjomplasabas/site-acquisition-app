<?php

use App\Enums\PipelineStatus;
use App\Enums\SiteProfileType;
use App\Models\Address;
use App\Models\Pipeline;
use App\Models\SitePlanningAssignment;
use App\Models\SiteProfile;
use App\Models\SiteSurvey;
use App\Models\Tower;

function stuckToweredPipeline(PipelineStatus $status): array
{
    $address = Address::create(['city' => 'Manila']);
    $siteProfile = SiteProfile::create(['name' => 'Stuck Site', 'type' => SiteProfileType::Condo, 'address_id' => $address->id]);
    $pipeline = Pipeline::create([
        'name' => 'Stuck Pipeline',
        'pipeline_id' => nextPipelineId(),
        'site_profile_id' => $siteProfile->id,
        'status' => $status,
    ]);
    $tower = Tower::create(['site_profile_id' => $siteProfile->id, 'name' => 'Tower A']);
    $survey = SiteSurvey::create(['site_profile_id' => $siteProfile->id, 'pipeline_id' => $pipeline->id, 'tower_id' => $tower->id]);
    $assignment = SitePlanningAssignment::create([
        'pipeline_id' => $pipeline->id,
        'site_survey_id' => $survey->id,
        'tower_id' => $tower->id,
        'assigned_at' => now(),
        'design_plan_completed_at' => now(),
        'design_plan_approved_at' => now(),
    ]);

    return compact('pipeline', 'assignment');
}

it('clears stale Design Plan stamps for pipelines stuck in Site Survey and is idempotent', function () {
    ['assignment' => $stuck] = stuckToweredPipeline(PipelineStatus::SiteSurvey);
    ['assignment' => $untouched] = stuckToweredPipeline(PipelineStatus::Approval);

    $this->artisan('app:repair-stale-design-plan-stamps')
        ->expectsOutputToContain('1 assignment(s)')
        ->assertSuccessful();

    $stuck->refresh();
    expect($stuck->design_plan_completed_at)->toBeNull()
        ->and($stuck->design_plan_approved_at)->toBeNull();

    // A pipeline not in Site Survey keeps its stamps — the repair is tightly scoped.
    $untouched->refresh();
    expect($untouched->design_plan_completed_at)->not->toBeNull()
        ->and($untouched->design_plan_approved_at)->not->toBeNull();

    // Re-running finds nothing left to repair.
    $this->artisan('app:repair-stale-design-plan-stamps')
        ->expectsOutputToContain('0 assignment(s)')
        ->assertSuccessful();
});
