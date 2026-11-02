<?php

use App\Enums\SiteProfileType;
use App\Models\Address;
use App\Models\Pipeline;
use App\Models\Project;
use App\Models\ProjectStage;
use App\Models\SiteProfile;
use App\Models\User;

function projectForIspTests(): Project
{
    $address = Address::create(['city' => 'Manila']);
    $siteProfile = SiteProfile::create([
        'name' => 'Test Site',
        'type' => SiteProfileType::Condo,
        'address_id' => $address->id,
    ]);

    $pipeline = Pipeline::create([
        'name' => 'Test Pipeline',
        'pipeline_id' => nextPipelineId(),
        'site_profile_id' => $siteProfile->id,
    ]);

    $project = Project::create(['pipeline_id' => $pipeline->id, 'current_stage' => 'Planning']);

    foreach (['Planning' => 1, 'Execution' => 2, 'Monitoring' => 3, 'Closing' => 4] as $stage => $order) {
        ProjectStage::create(['project_id' => $project->id, 'stage' => $stage, 'status' => 'Pending', 'order' => $order]);
    }

    return $project;
}

it('creates an ISP with no JO checklist, since it was not sourced from the Network module', function () {
    $manager = User::factory()->networkManager()->create();
    $project = projectForIspTests();
    $stage = $project->stages()->where('stage', 'Planning')->first();

    $this->actingAs($manager)
        ->post(route('projects.stages.isps.store', [$project, $stage]), [
            'isp_name' => 'PhilCom',
            'notes' => 'Primary ISP',
        ])
        ->assertRedirect();

    $isp = $stage->isps()->first();

    expect($isp->isp_name)->toBe('PhilCom')
        ->and($isp->notes)->toBe('Primary ISP')
        ->and($isp->network_feasibility_id)->toBeNull()
        ->and($isp->allTasksDone())->toBeTrue();
});

it('resolves the linked Planning ISP via sourceProjectStageIsp', function () {
    $project = projectForIspTests();
    $planningStage = $project->stages()->where('stage', 'Planning')->first();
    $executionStage = $project->stages()->where('stage', 'Execution')->first();

    $planningIsp = $planningStage->isps()->create(['isp_name' => 'PhilCom']);
    $executionIsp = $executionStage->isps()->create([
        'isp_name' => 'PhilCom',
        'source_project_stage_isp_id' => $planningIsp->id,
    ]);

    expect($executionIsp->sourceProjectStageIsp->is($planningIsp))->toBeTrue();

    $planningIsp->delete();

    expect($executionIsp->fresh()->source_project_stage_isp_id)->toBeNull();
});

it('renames an ISP', function () {
    $manager = User::factory()->networkManager()->create();
    $project = projectForIspTests();
    $stage = $project->stages()->where('stage', 'Planning')->first();
    $isp = $stage->isps()->create(['isp_name' => 'PhilCom']);

    $this->actingAs($manager)
        ->patch(route('projects.stages.isps.update', [$project, $stage, $isp]), [
            'isp_name' => 'PhilCom Fiber',
            'notes' => 'Renamed',
        ])
        ->assertRedirect();

    expect($isp->fresh()->isp_name)->toBe('PhilCom Fiber')
        ->and($isp->fresh()->notes)->toBe('Renamed');
});

it('deletes an ISP', function () {
    $manager = User::factory()->networkManager()->create();
    $project = projectForIspTests();
    $stage = $project->stages()->where('stage', 'Planning')->first();
    $isp = $stage->isps()->create(['isp_name' => 'PhilCom']);

    $this->actingAs($manager)
        ->delete(route('projects.stages.isps.delete', [$project, $stage, $isp]))
        ->assertRedirect();

    expect($stage->isps()->find($isp->id))->toBeNull();
});

it('denies a role without a stage permission from managing ISPs', function () {
    $networkManager = User::factory()->networkManager()->create();
    $project = projectForIspTests();
    $project->update(['current_stage' => 'Closing']);
    $stage = $project->stages()->where('stage', 'Closing')->first();

    $this->actingAs($networkManager)
        ->post(route('projects.stages.isps.store', [$project, $stage]), ['isp_name' => 'PhilCom'])
        ->assertForbidden();
});

it('blocks managing ISPs on a stage that is not currently active', function () {
    $manager = User::factory()->networkManager()->create();
    $project = projectForIspTests();
    $executionStage = $project->stages()->where('stage', 'Execution')->first();

    $this->actingAs($manager)
        ->post(route('projects.stages.isps.store', [$project, $executionStage]), ['isp_name' => 'PhilCom'])
        ->assertStatus(422);
});

it('keeps the ISP Status section read-only for the Infrastructure Manager', function () {
    $infraManager = User::factory()->infrastructureManager()->create();
    $project = projectForIspTests();
    $stage = $project->stages()->where('stage', 'Planning')->first();
    $isp = $stage->isps()->create(['isp_name' => 'PhilCom']);

    $this->actingAs($infraManager);

    $this->post(route('projects.stages.isps.store', [$project, $stage]), ['isp_name' => 'New ISP'])->assertForbidden();
    $this->patch(route('projects.stages.isps.update', [$project, $stage, $isp]), ['isp_name' => 'Renamed', 'notes' => 'x'])->assertForbidden();
    $this->delete(route('projects.stages.isps.delete', [$project, $stage, $isp]))->assertForbidden();

    expect($stage->isps()->count())->toBe(1)
        ->and($isp->fresh()->isp_name)->toBe('PhilCom');
});
