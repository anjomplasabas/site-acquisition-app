<?php

use App\Enums\PipelineStatus;
use App\Enums\SiteProfileType;
use App\Models\Address;
use App\Models\Pipeline;
use App\Models\SiteProfile;
use App\Models\SiteSurvey;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function dashboardPipeline(PipelineStatus $status, array $attributes = []): Pipeline
{
    $owner = $attributes['owner'] ?? User::factory()->saqSpecialist()->create();
    $address = Address::create([
        'city' => $attributes['city'] ?? 'Manila',
        'province' => $attributes['province'] ?? 'Metro Manila',
    ]);
    $siteProfile = SiteProfile::create([
        'name' => $attributes['profile_name'] ?? fake()->company(),
        'type' => SiteProfileType::Condo,
        'address_id' => $address->id,
    ]);

    $pipeline = Pipeline::create([
        'name' => $attributes['name'] ?? fake()->words(3, true),
        'pipeline_id' => nextPipelineId(),
        'project_manager_id' => $owner->id,
        'assigned_saq_id' => $owner->id,
        'site_profile_id' => $siteProfile->id,
        'status' => $status,
    ]);

    if (isset($attributes['status_changed_at'])) {
        $pipeline->update(['status_changed_at' => $attributes['status_changed_at']]);
    }

    return $pipeline;
}

it('scopes the in_acquisition stat to pipelines visible to the acting user', function () {
    $user = User::factory()->saqSpecialist()->create();

    dashboardPipeline(PipelineStatus::Prospecting, ['owner' => $user, 'name' => 'Mine']);
    dashboardPipeline(PipelineStatus::Prospecting, ['owner' => User::factory()->saqSpecialist()->create(), 'name' => 'Someone Else']);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('stats.in_acquisition', 1)
        );
});

it('shows dynamic pipeline dashboard data', function () {
    $user = User::factory()->saqSpecialist()->create();

    $prospect = dashboardPipeline(PipelineStatus::Prospect, [
        'owner' => $user,
        'name' => 'Prospect Lead',
        'status_changed_at' => now()->subMinutes(30),
    ]);
    $approval = dashboardPipeline(PipelineStatus::Approval, [
        'owner' => $user,
        'name' => 'Approval Lead',
        'status_changed_at' => now()->subMinutes(20),
    ]);
    $rfs = dashboardPipeline(PipelineStatus::ReadyForService, [
        'owner' => $user,
        'name' => 'Service Ready Lead',
        'status_changed_at' => now()->subMinutes(10),
    ]);

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('stats.in_acquisition', 1)
            ->where('stats.pending_approval', 1)
            ->where('stats.ready_for_service', 1)
            ->has('activeProjects', 0)
            ->has('recentPipelines', 3)
            ->where('recentPipelines.0.id', $rfs->id)
            ->has('statusDistribution', 7)
            ->where('statusDistribution.0.status', PipelineStatus::Prospect->value)
            ->where('statusDistribution.0.count', 1)
            ->where('statusDistribution.6.status', PipelineStatus::Completed->value)
            ->has('attentionItems', 0)
        );

    expect($prospect)->toBeInstanceOf(Pipeline::class)
        ->and($approval)->toBeInstanceOf(Pipeline::class);
});

it('includes stale non-terminal leads as attention items', function () {
    $user = User::factory()->saqSpecialist()->create();

    $stale = dashboardPipeline(PipelineStatus::SiteSurvey, [
        'owner' => $user,
        'name' => 'Stale Visit Lead',
        'status_changed_at' => now()->subDays(10),
    ]);

    dashboardPipeline(PipelineStatus::Completed, [
        'owner' => $user,
        'name' => 'Completed Old Lead',
        'status_changed_at' => now()->subDays(30),
    ]);

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->has('attentionItems', 1)
            ->where('attentionItems.0.id', $stale->id)
            ->where('attentionItems.0.status', PipelineStatus::SiteSurvey->value)
        );
});

it('shows lead age status age and current owner in the dashboard aging overview widget', function () {
    $user = User::factory()->leadSaqSpecialist()->create();
    $surveyor = User::factory()->surveyor()->create(['full_name' => 'Current Surveyor']);
    $pipeline = dashboardPipeline(PipelineStatus::SiteSurvey, [
        'owner' => $user,
        'name' => 'Aging Widget Lead',
    ]);
    $siteSurvey = SiteSurvey::create([
        'site_profile_id' => $pipeline->site_profile_id,
        'surveyor_id' => $surveyor->id,
        'survey_date' => now()->toDateString(),
    ]);
    $pipeline->forceFill([
        'site_survey_id' => $siteSurvey->id,
        'status_changed_at' => now()->subDays(5),
        'last_activity_at' => now()->subDays(3),
    ])->saveQuietly();

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->has('agingOverview', 1)
            ->where('agingOverview.0.id', $pipeline->id)
            ->where('agingOverview.0.lead_age.label', '3d')
            ->where('agingOverview.0.stage_context.stage', PipelineStatus::SiteSurvey->value)
            ->where('agingOverview.0.stage_context.stage_age_days', 5)
            ->where('agingOverview.0.stage_context.owner_label', 'Current Surveyor')
            ->where('agingOverview.0.current_owner', 'Current Surveyor')
        );
});
