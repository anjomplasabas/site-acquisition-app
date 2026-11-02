<?php

use App\Enums\DocumentType;
use App\Enums\IspTaskStatus;
use App\Enums\ProjectStage as ProjectStageEnum;
use App\Enums\SiteProfileType;
use App\Models\Address;
use App\Models\Document;
use App\Models\NetworkSiteAssignment;
use App\Models\Pipeline;
use App\Models\Project;
use App\Models\ProjectStage;
use App\Models\ProjectStageIsp;
use App\Models\SitePlanningAssignment;
use App\Models\SiteProfile;
use App\Models\SiteSurvey;
use App\Models\StageDocumentRequirement;
use App\Models\User;
use App\Support\NetworkFeasibilityTaskDefinitions;
use App\Support\StageTaskSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

function attachCompletedStageDocument(ProjectStage $stage, string $category, ?string $status): Document
{
    $document = Document::create([
        'name' => "{$category}.pdf",
        'type' => DocumentType::Document,
        'filename' => "{$category}.pdf",
        'file_path' => "project-stage-documents/{$category}.pdf",
        'category' => $category,
    ]);

    $stage->documents()->attach($document->id, ['created_at' => now(), 'status' => $status]);

    return $document;
}

/**
 * Attaches one document to each of Planning's document_upload fields (MOA, NTP, BOQ, PR) — these
 * fields count as filled on presence alone, no status needed (ProjectStageController::
 * fieldIsFilled). Plans/Drawing no longer lives here — it's now a Site Planning concern (see
 * completeSitePlanningAssignment() below) — so this only covers the document fields.
 */
function attachPlanningDocuments(ProjectStage $stage): void
{
    foreach (['MOA', 'NTP', 'BOQ', 'PR'] as $category) {
        attachCompletedStageDocument($stage, $category, null);
    }
}

/**
 * Creates a SitePlanningAssignment on the project's pipeline with $type set and its Plans/Drawing
 * checklist fully Done — this is now the sole source ProjectStageController::complete() reads
 * from when seeding Monitoring's type-specific Infrastructure checklist (it no longer reads
 * Planning's field_values).
 */
function completeSitePlanningAssignment(ProjectStage $stage, string $type): void
{
    $siteSurvey = SiteSurvey::create(['site_profile_id' => $stage->project->pipeline->site_profile_id]);
    $assignment = SitePlanningAssignment::ensureFor($stage->project->pipeline, $siteSurvey);
    $assignment->update(['design_plan_type' => $type]);
}

/**
 * Creates an ISP linked to a fresh Network Site ISP (NetworkFeasibility), with the full 9-task
 * JO checklist seeded (mirroring InitializeProject::carryForwardIsps) — $taskStatus is applied
 * only to the slice of tasks tagged for $stage, since that's the only slice
 * ProjectStageIsp::allTasksDone() checks for a row on that stage; the other stages' tasks stay
 * Pending, matching how the real, single continuous checklist works in production.
 */
/**
 * Seeds a full 9-task JO checklist and stamps it with $taskStatus the way this stage would
 * actually see it: Monitoring now shows/actions the checklist in full (ProjectStageIsp::
 * tasksForCurrentStage), so every task gets $taskStatus there; Planning/Execution still only see
 * their own tagged slice, so only those get $taskStatus (the rest stay Pending).
 */
function createIspWithTasks(ProjectStage $stage, string $ispName, string $taskStatus = 'Pending'): ProjectStageIsp
{
    $siteSurvey = SiteSurvey::create(['site_profile_id' => $stage->project->pipeline->site_profile_id]);
    $feasibility = $siteSurvey->networkFeasibilities()->create(['isp_name' => $ispName, 'status' => 'Checking']);

    foreach (NetworkFeasibilityTaskDefinitions::all() as $index => $task) {
        $feasibility->tasks()->create([
            'name' => $task['name'],
            'project_stage' => $task['stage'],
            'requires_document' => $task['requires_document'],
            'status' => ($stage->stage === ProjectStageEnum::Monitoring || $task['stage'] === $stage->stage) ? $taskStatus : 'Pending',
            'status_changed_at' => now(),
            'sort_order' => $index,
        ]);
    }

    return $stage->isps()->create(['isp_name' => $ispName, 'network_feasibility_id' => $feasibility->id]);
}

function projectForStageTests(): Project
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

it('allows the stage owner to update field values on the active stage', function () {
    $manager = User::factory()->infrastructureManager()->create();
    $project = projectForStageTests();
    $stage = $project->stages()->where('stage', 'Planning')->first();

    $this->actingAs($manager)
        ->patch(route('projects.stages.update', [$project, $stage]), [
            'field_values' => ['contractor' => 'JTE'],
        ])
        ->assertRedirect();

    expect($stage->fresh()->field_values['contractor'])->toBe('JTE')
        ->and($stage->fresh()->status)->toBe('In Progress');
});

it('blocks updating a stage that is not currently active', function () {
    $manager = User::factory()->infrastructureManager()->create();
    $project = projectForStageTests();
    $executionStage = $project->stages()->where('stage', 'Execution')->first();

    $this->actingAs($manager)
        ->patch(route('projects.stages.update', [$project, $executionStage]), [
            'field_values' => ['kick_off_meeting' => '2026-08-01'],
        ])
        ->assertStatus(422);
});

it('denies a role without a stage permission from updating that stage', function () {
    $networkManager = User::factory()->networkManager()->create();
    $project = projectForStageTests();
    $closingStage = $project->stages()->where('stage', 'Closing')->first();
    $project->update(['current_stage' => 'Closing']);
    $closingStage->update(['status' => 'In Progress']);

    $this->actingAs($networkManager)
        ->patch(route('projects.stages.update', [$project, $closingStage]), [
            'field_values' => ['rfco_status' => 'Done'],
        ])
        ->assertForbidden();
});

it('blocks completing a stage while required fields are missing', function () {
    $manager = User::factory()->infrastructureManager()->create();
    $project = projectForStageTests();
    $stage = $project->stages()->where('stage', 'Planning')->first();

    $this->actingAs($manager)
        ->post(route('projects.stages.complete', [$project, $stage]))
        ->assertStatus(422);

    expect($project->fresh()->current_stage->value)->toBe('Planning');
});

it('completes a stage and advances the project to the next stage once fields and tasks are done', function () {
    $manager = User::factory()->infrastructureManager()->create();
    $project = projectForStageTests();
    $stage = $project->stages()->where('stage', 'Planning')->first();

    $stage->update(['field_values' => ['contractor' => 'JGD', 'target_activation_date' => '2026-08-01']]);
    createIspWithTasks($stage, 'PhilCom', 'Done');
    attachPlanningDocuments($stage);

    $this->actingAs($manager)
        ->post(route('projects.stages.complete', [$project, $stage]))
        ->assertRedirect();

    expect($stage->fresh()->status)->toBe('Completed')
        ->and($project->fresh()->current_stage->value)->toBe('Execution');
});

it('seeds Monitoring\'s Infrastructure checklist from the matching Annex 2.b matrix once Planning completes', function (string $type, int $expectedTaskCount, array $expectedSections) {
    $manager = User::factory()->infrastructureManager()->create();
    $project = projectForStageTests();
    $stage = $project->stages()->where('stage', 'Planning')->first();
    $monitoringStage = $project->stages()->where('stage', 'Monitoring')->first();

    $stage->update(['field_values' => ['contractor' => 'JGD', 'target_activation_date' => '2026-08-01']]);
    createIspWithTasks($stage, 'PhilCom', 'Done');
    attachPlanningDocuments($stage);
    completeSitePlanningAssignment($stage, $type);

    expect($monitoringStage->stageTasks()->where('field_key', 'infrastructure_deployment_status')->count())->toBe(0);

    $this->actingAs($manager)
        ->post(route('projects.stages.complete', [$project, $stage]))
        ->assertRedirect();

    $tasks = $monitoringStage->fresh()->stageTasks->where('field_key', 'infrastructure_deployment_status');

    expect($tasks)->toHaveCount($expectedTaskCount)
        ->and((float) $tasks->sum('weight'))->toBe(100.0)
        ->and($tasks->pluck('status')->unique()->all())->toBe([IspTaskStatus::Pending])
        ->and($tasks->pluck('section')->unique()->values()->all())->toBe($expectedSections);
})->with([
    'Residential' => ['Residential', 32, ['MDF Installation', 'Vertical Distribution Provision Layout', 'Backhaul Installation', 'SMDF Installation', 'Testing Infra', 'Testing with Network']],
    'Share-Connect' => ['Share-Connect', 36, ['MDF Installation', 'Vertical Provision Layout', 'Backhaul Installation', 'Horizontal Provision', 'SMDF Installation', 'Termination', 'AP Installation', 'Testing Infra', 'Testing with Network']],
    'FAMILIA' => ['FAMILIA', 24, ['MDF Installation', 'Vertical Distribution Provision Layout', 'Backhaul Installation', 'LCP and NAP Installation', 'Termination (MDF, LCP and NAP)', 'Testing Infra', 'Testing with Network']],
]);

it('does not seed Monitoring\'s Infrastructure checklist if Planning completes without a design_plan_type set', function () {
    // Not realistically reachable in production since Plans/Drawing (and its Type) must be
    // completed before Site Survey finishes, well before Planning exists — but StageTaskSeeder
    // must still degrade safely to a no-op rather than error if it's ever called with a
    // null/unrecognized type.
    $project = projectForStageTests();
    $monitoringStage = $project->stages()->where('stage', 'Monitoring')->first();

    StageTaskSeeder::seedMissingFor($monitoringStage, null);

    expect($monitoringStage->fresh()->stageTasks->where('field_key', 'infrastructure_deployment_status'))->toHaveCount(0);
});

it('replaces an existing Monitoring Infrastructure checklist with the correct type-based one via the backfill command', function () {
    $project = projectForStageTests();
    $planningStage = $project->stages()->where('stage', 'Planning')->first();
    $monitoringStage = $project->stages()->where('stage', 'Monitoring')->first();

    $planningStage->update(['field_values' => ['design_plan_type' => 'FAMILIA']]);

    // Simulate the old, incorrect, non-type-aware checklist a pre-existing project would carry.
    $oldTask = $monitoringStage->stageTasks()->create(['field_key' => 'infrastructure_deployment_status', 'name' => 'Horizontal Provision', 'weight' => 30, 'status' => 'Done', 'sort_order' => 0]);

    $this->artisan('app:backfill-monitoring-infrastructure-tasks')->assertSuccessful();

    $tasks = $monitoringStage->fresh()->stageTasks->where('field_key', 'infrastructure_deployment_status');

    expect($tasks->pluck('id'))->not->toContain($oldTask->id)
        ->and($tasks)->toHaveCount(24)
        ->and((float) $tasks->sum('weight'))->toBe(100.0)
        ->and($tasks->pluck('section')->unique()->values()->all())->toContain('LCP and NAP Installation');
});

it('skips a project in the backfill command when Planning has no design_plan_type set yet', function () {
    $project = projectForStageTests();
    $monitoringStage = $project->stages()->where('stage', 'Monitoring')->first();
    $monitoringStage->stageTasks()->create(['field_key' => 'infrastructure_deployment_status', 'name' => 'Horizontal Provision', 'weight' => 30, 'status' => 'Pending', 'sort_order' => 0]);

    $this->artisan('app:backfill-monitoring-infrastructure-tasks')->assertSuccessful();

    expect($monitoringStage->fresh()->stageTasks->where('field_key', 'infrastructure_deployment_status')->pluck('name')->all())->toBe(['Horizontal Provision']);
});

it('advances the project to Completed once the Closing stage is completed', function () {
    $manager = User::factory()->infrastructureManager()->create();
    $project = projectForStageTests();
    $project->update(['current_stage' => 'Closing']);
    $stage = $project->stages()->where('stage', 'Closing')->first();
    $stage->update(['field_values' => ['rfco_status' => 'Done', 'rfco_date' => '2026-08-01', 'acceptance_status' => 'Done', 'acceptance_date' => '2026-08-02']]);
    attachCompletedStageDocument($stage, 'As-Built Plan', null);

    $this->actingAs($manager)
        ->post(route('projects.stages.complete', [$project, $stage]))
        ->assertRedirect();

    expect($project->fresh()->status->value)->toBe('Completed')
        ->and($stage->fresh()->status)->toBe('Completed');
});

it('blocks completing the Closing stage while RFCO or Acceptance Status is On-hold without remarks', function () {
    $manager = User::factory()->infrastructureManager()->create();
    $project = projectForStageTests();
    $project->update(['current_stage' => 'Closing']);
    $stage = $project->stages()->where('stage', 'Closing')->first();
    $stage->update(['field_values' => ['rfco_status' => 'On-hold', 'rfco_date' => '2026-08-01', 'acceptance_status' => 'On-hold', 'acceptance_date' => '2026-08-02']]);
    attachCompletedStageDocument($stage, 'As-Built Plan', null);

    $this->actingAs($manager)
        ->post(route('projects.stages.complete', [$project, $stage]))
        ->assertSessionHasErrors(['field_values.rfco_status_notes', 'field_values.acceptance_status_notes']);

    expect($stage->fresh()->status)->not->toBe('Completed');

    $stage->update(['field_values' => array_merge($stage->field_values, ['rfco_status_notes' => 'Awaiting permit', 'acceptance_status_notes' => 'Client unavailable'])]);

    $this->actingAs($manager)
        ->post(route('projects.stages.complete', [$project, $stage]))
        ->assertSessionHasNoErrors();

    expect($stage->fresh()->status)->toBe('Completed');
});

it('blocks completing the Closing stage without an As-Built Plan document', function () {
    $manager = User::factory()->infrastructureManager()->create();
    $project = projectForStageTests();
    $project->update(['current_stage' => 'Closing']);
    $stage = $project->stages()->where('stage', 'Closing')->first();
    $stage->update(['field_values' => ['rfco_status' => 'Done', 'rfco_date' => '2026-08-01', 'acceptance_status' => 'Done', 'acceptance_date' => '2026-08-02']]);

    $this->actingAs($manager)
        ->post(route('projects.stages.complete', [$project, $stage]))
        ->assertStatus(422);

    expect($stage->fresh()->status)->not->toBe('Completed');
});

it('uploads an As-Built Plan document on the Closing stage', function () {
    Storage::fake('public');
    $manager = User::factory()->infrastructureManager()->create();
    $project = projectForStageTests();
    $project->update(['current_stage' => 'Closing']);
    $stage = $project->stages()->where('stage', 'Closing')->first();

    $this->actingAs($manager)
        ->post(route('projects.stages.documents.store', [$project, $stage]), [
            'document_category' => 'As-Built Plan',
            'files' => [UploadedFile::fake()->create('as-built.pdf', 100)],
        ])
        ->assertRedirect();

    expect($stage->fresh()->documents()->where('category', 'As-Built Plan')->exists())->toBeTrue();
});

it('blocks deleting a required document for the stage', function () {
    Storage::fake('public');
    $manager = User::factory()->infrastructureManager()->create();
    $project = projectForStageTests();
    $stage = $project->stages()->where('stage', 'Planning')->first();

    StageDocumentRequirement::create(['module' => 'project', 'stage' => 'Planning', 'name' => 'MOA', 'is_required' => true, 'is_active' => true]);

    $this->actingAs($manager)
        ->post(route('projects.stages.documents.store', [$project, $stage]), [
            'document_category' => 'MOA',
            'files' => [UploadedFile::fake()->create('moa.pdf', 100)],
        ]);

    $document = $stage->fresh()->documents()->where('category', 'MOA')->first();

    $this->actingAs($manager)
        ->delete(route('projects.stages.documents.delete', [$project, $stage, $document]))
        ->assertSessionHasErrors('document');

    expect($stage->fresh()->documents()->where('document_id', $document->id)->exists())->toBeTrue();
});

it('allows deleting a non-required document for the stage', function () {
    Storage::fake('public');
    $manager = User::factory()->infrastructureManager()->create();
    $project = projectForStageTests();
    $stage = $project->stages()->where('stage', 'Closing')->first();
    $project->update(['current_stage' => 'Closing']);

    $this->actingAs($manager)
        ->post(route('projects.stages.documents.store', [$project, $stage]), [
            'document_category' => 'As-Built Plan',
            'files' => [UploadedFile::fake()->create('as-built.pdf', 100)],
        ]);

    $document = $stage->fresh()->documents()->where('category', 'As-Built Plan')->first();

    $this->actingAs($manager)
        ->delete(route('projects.stages.documents.delete', [$project, $stage, $document]))
        ->assertRedirect();

    expect($stage->fresh()->documents()->where('document_id', $document->id)->exists())->toBeFalse();
});

it('auto-populates RFCO Date and Acceptance Date with today once their status is set to Done', function () {
    $manager = User::factory()->infrastructureManager()->create();
    $project = projectForStageTests();
    $closingStage = $project->stages()->where('stage', 'Closing')->first();
    $project->update(['current_stage' => 'Closing']);

    $this->actingAs($manager)
        ->patch(route('projects.stages.update', [$project, $closingStage]), [
            'field_values' => ['rfco_status' => 'Done', 'acceptance_status' => 'Done'],
        ])
        ->assertRedirect();

    expect($closingStage->fresh()->field_values['rfco_date'])->toBe(now()->toDateString())
        ->and($closingStage->fresh()->field_values['acceptance_date'])->toBe(now()->toDateString());
});

it('does not overwrite an already-set RFCO Date or Acceptance Date once Done', function () {
    $manager = User::factory()->infrastructureManager()->create();
    $project = projectForStageTests();
    $closingStage = $project->stages()->where('stage', 'Closing')->first();
    $closingStage->update(['field_values' => [
        'rfco_status' => 'Done', 'rfco_date' => '2026-07-01',
        'acceptance_status' => 'Done', 'acceptance_date' => '2026-07-02',
    ]]);
    $project->update(['current_stage' => 'Closing']);

    $this->actingAs($manager)
        ->patch(route('projects.stages.update', [$project, $closingStage]), [
            'field_values' => ['rfco_status' => 'Done', 'acceptance_status' => 'Done'],
        ])
        ->assertRedirect();

    expect($closingStage->fresh()->field_values['rfco_date'])->toBe('2026-07-01')
        ->and($closingStage->fresh()->field_values['acceptance_date'])->toBe('2026-07-02');
});

it('does not auto-populate RFCO Date or Acceptance Date while their status is not Done', function () {
    $manager = User::factory()->infrastructureManager()->create();
    $project = projectForStageTests();
    $closingStage = $project->stages()->where('stage', 'Closing')->first();
    $project->update(['current_stage' => 'Closing']);

    $this->actingAs($manager)
        ->patch(route('projects.stages.update', [$project, $closingStage]), [
            'field_values' => ['rfco_status' => 'On-going', 'acceptance_status' => 'On-going'],
        ])
        ->assertRedirect();

    expect($closingStage->fresh()->field_values['rfco_date'] ?? null)->toBeNull()
        ->and($closingStage->fresh()->field_values['acceptance_date'] ?? null)->toBeNull();
});

it('blocks a Mobilization Schedule earlier than the Kick-off Meeting date', function () {
    $manager = User::factory()->infrastructureManager()->create();
    $project = projectForStageTests();
    $executionStage = $project->stages()->where('stage', 'Execution')->first();
    $project->update(['current_stage' => 'Execution']);

    $this->actingAs($manager)
        ->patch(route('projects.stages.update', [$project, $executionStage]), [
            'field_values' => [
                'kick_off_meeting' => '2026-09-30',
                'mobilization_schedule' => '2026-09-15',
            ],
        ])
        ->assertSessionHasErrors('field_values.mobilization_schedule');

    expect($executionStage->fresh()->field_values['mobilization_schedule'] ?? null)->toBeNull();
});

it('allows a Mobilization Schedule on or after the Kick-off Meeting date', function () {
    $manager = User::factory()->infrastructureManager()->create();
    $project = projectForStageTests();
    $executionStage = $project->stages()->where('stage', 'Execution')->first();
    $project->update(['current_stage' => 'Execution']);

    $this->actingAs($manager)
        ->patch(route('projects.stages.update', [$project, $executionStage]), [
            'field_values' => [
                'kick_off_meeting' => '2026-09-30',
                'mobilization_schedule' => '2026-09-30',
            ],
        ])
        ->assertSessionDoesntHaveErrors('field_values.mobilization_schedule')
        ->assertRedirect();

    expect($executionStage->fresh()->field_values['mobilization_schedule'])->toBe('2026-09-30');
});

it('requires remarks when RFCO Status or Acceptance Status is set to On-hold', function () {
    $manager = User::factory()->infrastructureManager()->create();
    $project = projectForStageTests();
    $closingStage = $project->stages()->where('stage', 'Closing')->first();
    $project->update(['current_stage' => 'Closing']);

    $this->actingAs($manager)
        ->patch(route('projects.stages.update', [$project, $closingStage]), [
            'field_values' => ['rfco_status' => 'On-hold'],
        ])
        ->assertSessionHasErrors('field_values.rfco_status_notes');
});

it('accepts On-hold RFCO Status once remarks are provided', function () {
    $manager = User::factory()->infrastructureManager()->create();
    $project = projectForStageTests();
    $closingStage = $project->stages()->where('stage', 'Closing')->first();
    $project->update(['current_stage' => 'Closing']);

    $this->actingAs($manager)
        ->patch(route('projects.stages.update', [$project, $closingStage]), [
            'field_values' => [
                'rfco_status' => 'On-hold',
                'rfco_status_notes' => 'Waiting on the client to confirm the site handover date.',
            ],
        ])
        ->assertSessionDoesntHaveErrors('field_values.rfco_status_notes')
        ->assertRedirect();

    expect($closingStage->fresh()->field_values['rfco_status_notes'])->toBe('Waiting on the client to confirm the site handover date.');
});

it('allows reopening the most recently completed stage and moves current_stage back', function () {
    $manager = User::factory()->infrastructureManager()->create();
    $project = projectForStageTests();
    $planningStage = $project->stages()->where('stage', 'Planning')->first();
    $planningStage->update(['status' => 'Completed', 'completed_at' => now()]);
    $project->update(['current_stage' => 'Execution']);

    $this->actingAs($manager)
        ->post(route('projects.stages.reopen', [$project, $planningStage]))
        ->assertRedirect();

    expect($project->fresh()->current_stage->value)->toBe('Planning')
        ->and($planningStage->fresh()->status)->toBe('In Progress');
});

it('creates an ISP added directly on a stage with no JO checklist to gate on', function () {
    $manager = User::factory()->networkManager()->create();
    $project = projectForStageTests();
    $executionStage = $project->stages()->where('stage', 'Execution')->first();
    $project->update(['current_stage' => 'Execution']);

    $this->actingAs($manager)
        ->post(route('projects.stages.isps.store', [$project, $executionStage]), ['isp_name' => 'Some New Regional ISP'])
        ->assertRedirect();

    $isp = $executionStage->isps()->first();

    expect($isp->isp_name)->toBe('Some New Regional ISP')
        ->and($isp->network_feasibility_id)->toBeNull()
        ->and($isp->allTasksDone())->toBeTrue();
});

it('rejects an ISP with a blank isp_name on Execution', function () {
    $manager = User::factory()->networkManager()->create();
    $project = projectForStageTests();
    $executionStage = $project->stages()->where('stage', 'Execution')->first();
    $project->update(['current_stage' => 'Execution']);

    $this->actingAs($manager)
        ->post(route('projects.stages.isps.store', [$project, $executionStage]), ['isp_name' => ''])
        ->assertSessionHasErrors('isp_name');
});

it('auto-computes Execution duration in days once mobilization schedule and target finished date are both set', function () {
    $manager = User::factory()->infrastructureManager()->create();
    $project = projectForStageTests();
    $executionStage = $project->stages()->where('stage', 'Execution')->first();
    $project->update(['current_stage' => 'Execution']);

    $this->actingAs($manager)
        ->patch(route('projects.stages.update', [$project, $executionStage]), [
            'field_values' => ['mobilization_schedule' => '2026-08-01'],
        ])
        ->assertRedirect();

    expect($executionStage->fresh()->field_values['duration'] ?? null)->toBeNull();

    $this->actingAs($manager)
        ->patch(route('projects.stages.update', [$project, $executionStage]), [
            'field_values' => ['target_finished_date' => '2026-08-16'],
        ])
        ->assertRedirect();

    expect($executionStage->fresh()->field_values['duration'])->toBe('15 days');
});

it('recomputes Execution duration when the dates change and clears it when the target precedes mobilization', function () {
    $manager = User::factory()->infrastructureManager()->create();
    $project = projectForStageTests();
    $executionStage = $project->stages()->where('stage', 'Execution')->first();
    $executionStage->update(['field_values' => [
        'mobilization_schedule' => '2026-08-01', 'target_finished_date' => '2026-08-16', 'duration' => '15 days',
    ]]);
    $project->update(['current_stage' => 'Execution']);

    $this->actingAs($manager)
        ->patch(route('projects.stages.update', [$project, $executionStage]), [
            'field_values' => ['target_finished_date' => '2026-08-21'],
        ])
        ->assertRedirect();

    expect($executionStage->fresh()->field_values['duration'])->toBe('20 days');

    $this->actingAs($manager)
        ->patch(route('projects.stages.update', [$project, $executionStage]), [
            'field_values' => ['target_finished_date' => '2026-07-01'],
        ])
        ->assertRedirect();

    expect($executionStage->fresh()->field_values['duration'] ?? null)->toBeNull();
});

it('ignores a client-submitted duration value and always recomputes it server-side', function () {
    $manager = User::factory()->infrastructureManager()->create();
    $project = projectForStageTests();
    $executionStage = $project->stages()->where('stage', 'Execution')->first();
    $executionStage->update(['field_values' => [
        'mobilization_schedule' => '2026-08-01', 'target_finished_date' => '2026-08-16',
    ]]);
    $project->update(['current_stage' => 'Execution']);

    $this->actingAs($manager)
        ->patch(route('projects.stages.update', [$project, $executionStage]), [
            'field_values' => ['duration' => 'Whatever I want', 'kick_off_meeting' => '2026-07-25'],
        ])
        ->assertRedirect();

    expect($executionStage->fresh()->field_values['duration'])->toBe('15 days');
});

it('accepts a valid user id for PM In-charge on Execution', function () {
    $manager = User::factory()->infrastructureManager()->create();
    $pm = User::factory()->surveyor()->create();
    $project = projectForStageTests();
    $executionStage = $project->stages()->where('stage', 'Execution')->first();
    $project->update(['current_stage' => 'Execution']);

    $this->actingAs($manager)
        ->patch(route('projects.stages.update', [$project, $executionStage]), [
            'field_values' => ['pm_in_charge' => $pm->id],
        ])
        ->assertRedirect();

    expect($executionStage->fresh()->field_values['pm_in_charge'])->toBe($pm->id);
});

it('accepts a null PM In-charge on Execution as unassigned', function () {
    $manager = User::factory()->infrastructureManager()->create();
    $project = projectForStageTests();
    $executionStage = $project->stages()->where('stage', 'Execution')->first();
    $executionStage->update(['field_values' => ['pm_in_charge' => $manager->id]]);
    $project->update(['current_stage' => 'Execution']);

    $this->actingAs($manager)
        ->patch(route('projects.stages.update', [$project, $executionStage]), [
            'field_values' => ['pm_in_charge' => null],
        ])
        ->assertRedirect();

    expect($executionStage->fresh()->field_values['pm_in_charge'])->toBeNull();
});

it('rejects a non-existent user id for PM In-charge on Execution', function () {
    $manager = User::factory()->infrastructureManager()->create();
    $project = projectForStageTests();
    $executionStage = $project->stages()->where('stage', 'Execution')->first();
    $project->update(['current_stage' => 'Execution']);

    $this->actingAs($manager)
        ->patch(route('projects.stages.update', [$project, $executionStage]), [
            'field_values' => ['pm_in_charge' => 999999],
        ])
        ->assertSessionHasErrors('field_values.pm_in_charge');
});

it('completes the Planning stage with no ISPs added, since the JO checklist never blocks stage completion', function () {
    $manager = User::factory()->infrastructureManager()->create();
    $project = projectForStageTests();
    $stage = $project->stages()->where('stage', 'Planning')->first();

    $stage->update(['field_values' => ['contractor' => 'JGD', 'target_activation_date' => '2026-08-01']]);
    attachPlanningDocuments($stage);

    $this->actingAs($manager)
        ->post(route('projects.stages.complete', [$project, $stage]))
        ->assertRedirect();

    expect($project->fresh()->current_stage->value)->toBe('Execution');
});

it('completes the Planning stage even while an ISP JO checklist task is not Done', function () {
    $manager = User::factory()->infrastructureManager()->create();
    $project = projectForStageTests();
    $stage = $project->stages()->where('stage', 'Planning')->first();

    $stage->update(['field_values' => ['contractor' => 'JGD', 'target_activation_date' => '2026-08-01']]);
    createIspWithTasks($stage, 'PhilCom', 'Pending');
    attachPlanningDocuments($stage);

    $this->actingAs($manager)
        ->post(route('projects.stages.complete', [$project, $stage]))
        ->assertRedirect();

    expect($project->fresh()->current_stage->value)->toBe('Execution');
});

it('seeds Execution with linked ISPs and the Execution task checklist on Planning completion', function () {
    $manager = User::factory()->infrastructureManager()->create();
    $project = projectForStageTests();
    $stage = $project->stages()->where('stage', 'Planning')->first();

    $stage->update(['field_values' => ['contractor' => 'JGD', 'target_activation_date' => '2026-08-01']]);
    $planningPhilCom = createIspWithTasks($stage, 'PhilCom', 'Done');
    createIspWithTasks($stage, 'Converge', 'Done');
    attachPlanningDocuments($stage);

    $this->actingAs($manager)
        ->post(route('projects.stages.complete', [$project, $stage]))
        ->assertRedirect();

    $executionStage = $project->stages()->where('stage', 'Execution')->first();
    $executionIsps = $executionStage->isps()->with('networkFeasibility.tasks')->get();

    expect($executionIsps->pluck('isp_name')->all())->toBe(['PhilCom', 'Converge'])
        ->and($executionIsps->first()->source_project_stage_isp_id)->toBe($planningPhilCom->id)
        ->and($executionIsps->first()->network_feasibility_id)->toBe($planningPhilCom->network_feasibility_id)
        ->and($executionIsps->first()->tasksForCurrentStage()->pluck('name')->all())->toBe(['Project Implementation'])
        ->and($executionIsps->first()->tasksForCurrentStage()->pluck('status')->map->value->unique()->all())->toBe(['Pending']);
});

it('does not overwrite ISPs already added directly on Execution when Planning completes', function () {
    $manager = User::factory()->infrastructureManager()->create();
    $project = projectForStageTests();
    $stage = $project->stages()->where('stage', 'Planning')->first();
    $executionStage = $project->stages()->where('stage', 'Execution')->first();
    createIspWithTasks($executionStage, 'Existing ISP', 'Done');

    $stage->update(['field_values' => ['contractor' => 'JGD', 'target_activation_date' => '2026-08-01']]);
    createIspWithTasks($stage, 'PhilCom', 'Done');
    attachPlanningDocuments($stage);

    $this->actingAs($manager)
        ->post(route('projects.stages.complete', [$project, $stage]))
        ->assertRedirect();

    expect($executionStage->isps()->pluck('isp_name')->all())->toBe(['Existing ISP']);
});

it('seeds Monitoring with linked ISPs and the full JO checklist on Execution completion', function () {
    $manager = User::factory()->infrastructureManager()->create();
    $project = projectForStageTests();
    $project->update(['current_stage' => 'Execution']);
    $stage = $project->stages()->where('stage', 'Execution')->first();

    $stage->update(['field_values' => [
        'kick_off_meeting' => '2026-08-01', 'pm_in_charge' => $manager->id,
        'mobilization_schedule' => '2026-08-01', 'target_finished_date' => '2026-08-16', 'duration' => '15 days',
    ]]);
    $executionPhilCom = createIspWithTasks($stage, 'PhilCom', 'Done');
    createIspWithTasks($stage, 'Converge', 'Done');

    $this->actingAs($manager)
        ->post(route('projects.stages.complete', [$project, $stage]))
        ->assertRedirect();

    $monitoringStage = $project->stages()->where('stage', 'Monitoring')->first();
    $monitoringIsps = $monitoringStage->isps()->with('networkFeasibility.tasks')->get();

    // Monitoring now shows/actions the JO checklist in full (matching the Network module), not
    // just the 2 tasks tagged for Monitoring — "Project Implementation" (Execution-tagged) is
    // Done from the original createIspWithTasks() call against the Execution stage; everything
    // else is still Pending.
    expect($monitoringIsps->pluck('isp_name')->all())->toBe(['PhilCom', 'Converge'])
        ->and($monitoringIsps->first()->source_project_stage_isp_id)->toBe($executionPhilCom->id)
        ->and($monitoringIsps->first()->network_feasibility_id)->toBe($executionPhilCom->network_feasibility_id)
        ->and($monitoringIsps->first()->tasksForCurrentStage())->toHaveCount(9)
        ->and($monitoringIsps->first()->tasksForCurrentStage()->firstWhere('name', 'Project Implementation')->status->value)->toBe('Done')
        ->and($monitoringIsps->first()->tasksForCurrentStage()->firstWhere('name', 'Installation Completion')->status->value)->toBe('Pending');
});

it('blocks completing the Monitoring stage until every Infrastructure task is Done', function () {
    $manager = User::factory()->infrastructureManager()->create();
    $project = projectForStageTests();
    $project->update(['current_stage' => 'Monitoring']);
    $stage = $project->stages()->where('stage', 'Monitoring')->first();

    $stage->update(['field_values' => ['project_status' => 'Done']]);
    $stage->stageTasks()->create(['name' => 'MDF Installation', 'weight' => 100, 'status' => 'Pending', 'sort_order' => 0]);
    createIspWithTasks($stage, 'PhilCom', 'Done');

    $this->actingAs($manager)
        ->post(route('projects.stages.complete', [$project, $stage]))
        ->assertStatus(422);

    expect($project->fresh()->current_stage->value)->toBe('Monitoring');
});

it('completes the Monitoring stage once every Infrastructure task is Done, even while a Network ISP task is not Done', function () {
    $manager = User::factory()->infrastructureManager()->create();
    $project = projectForStageTests();
    $project->update(['current_stage' => 'Monitoring']);
    $stage = $project->stages()->where('stage', 'Monitoring')->first();

    $stage->update(['field_values' => ['project_status' => 'Done', 'testing' => 'Done']]);
    $stage->stageTasks()->create(['name' => 'MDF Installation', 'weight' => 100, 'status' => 'Done', 'status_changed_at' => now(), 'sort_order' => 0]);
    createIspWithTasks($stage, 'PhilCom', 'Pending');

    $this->actingAs($manager)
        ->post(route('projects.stages.complete', [$project, $stage]))
        ->assertRedirect();

    expect($stage->fresh()->status)->toBe('Completed');
});

it('computes weighted Infrastructure progress, Network done ratio, and their blended average', function () {
    $project = projectForStageTests();
    $stage = $project->stages()->where('stage', 'Monitoring')->first();

    $stage->stageTasks()->create(['name' => 'MDF Installation', 'weight' => 10, 'status' => 'Done', 'sort_order' => 0]);
    $stage->stageTasks()->create(['name' => 'Horizontal Provision', 'weight' => 30, 'status' => 'Done', 'sort_order' => 1]);
    $stage->stageTasks()->create(['name' => 'Vertical Provision', 'weight' => 10, 'status' => 'In-Progress', 'sort_order' => 2]);
    $stage->stageTasks()->create(['name' => 'SMDF Installation', 'weight' => 50, 'status' => 'Pending', 'sort_order' => 3]);

    createIspWithTasks($stage, 'PhilCom', 'Done');
    createIspWithTasks($stage, 'Converge', 'Pending');

    $stage = $stage->fresh(['stageTasks', 'isps.networkFeasibility.tasks']);

    expect($stage->infrastructure_progress_percent)->toBe(40.0)
        ->and($stage->network_progress_percent)->toBe(50)
        ->and($stage->monitoring_progress_percent)->toBe(45.0);
});

it('excludes a group with no data yet from the blended Monitoring progress average', function () {
    $project = projectForStageTests();
    $stage = $project->stages()->where('stage', 'Monitoring')->first();

    $stage->stageTasks()->create(['name' => 'MDF Installation', 'weight' => 100, 'status' => 'Done', 'sort_order' => 0]);

    $stage = $stage->fresh(['stageTasks', 'isps.networkFeasibility.tasks']);

    expect($stage->infrastructure_progress_percent)->toBe(100.0)
        ->and($stage->network_progress_percent)->toBeNull()
        ->and($stage->monitoring_progress_percent)->toBe(100.0);
});

it('shows and counts the full 9-task JO checklist on Monitoring, not just the 2 tasks tagged for it', function () {
    $project = projectForStageTests();
    $stage = $project->stages()->where('stage', 'Monitoring')->first();

    $isp = createIspWithTasks($stage, 'PhilCom', 'Done');

    expect($isp->tasksForCurrentStage())->toHaveCount(9)
        ->and($isp->progress)->toBe(['done' => 9, 'total' => 9]);
});

it('requires all 9 JO checklist tasks Done for allTasksDone() on Monitoring, not just the 2 previously tagged for it', function () {
    $project = projectForStageTests();
    $stage = $project->stages()->where('stage', 'Monitoring')->first();

    $isp = createIspWithTasks($stage, 'PhilCom', 'Pending');
    $isp->networkFeasibility->tasks()->where('project_stage', 'Monitoring')->update(['status' => 'Done']);

    expect($isp->fresh()->allTasksDone())->toBeFalse();

    $isp->networkFeasibility->tasks()->update(['status' => 'Done']);

    expect($isp->fresh()->allTasksDone())->toBeTrue();
});

it('sets the Testing dropdown value on Monitoring as a plain select field', function () {
    $manager = User::factory()->infrastructureManager()->create();
    $project = projectForStageTests();
    $stage = $project->stages()->where('stage', 'Monitoring')->first();
    $project->update(['current_stage' => 'Monitoring']);

    $this->actingAs($manager)
        ->patch(route('projects.stages.update', [$project, $stage]), [
            'field_values' => ['testing' => 'In-Progress'],
        ])
        ->assertRedirect();

    expect($stage->fresh()->field_values['testing'])->toBe('In-Progress');
});

it('sets a freeform note alongside the Testing dropdown, independent of its value', function () {
    $manager = User::factory()->infrastructureManager()->create();
    $project = projectForStageTests();
    $stage = $project->stages()->where('stage', 'Monitoring')->first();
    $project->update(['current_stage' => 'Monitoring']);

    $this->actingAs($manager)
        ->patch(route('projects.stages.update', [$project, $stage]), [
            'field_values' => ['testing_notes' => 'Waiting on the vendor to schedule re-testing.'],
        ])
        ->assertRedirect();

    expect($stage->fresh()->field_values['testing_notes'])->toBe('Waiting on the vendor to schedule re-testing.')
        ->and($stage->fresh()->field_values['testing'] ?? null)->toBeNull();
});

it('rejects a Testing note over 2000 characters', function () {
    $manager = User::factory()->infrastructureManager()->create();
    $project = projectForStageTests();
    $stage = $project->stages()->where('stage', 'Monitoring')->first();
    $project->update(['current_stage' => 'Monitoring']);

    $this->actingAs($manager)
        ->patch(route('projects.stages.update', [$project, $stage]), [
            'field_values' => ['testing_notes' => str_repeat('a', 2001)],
        ])
        ->assertSessionHasErrors('field_values.testing_notes');
});

it('allows manually setting Monitoring Project Status to Done or On-going via the field-save route', function () {
    $manager = User::factory()->infrastructureManager()->create();
    $project = projectForStageTests();
    $stage = $project->stages()->where('stage', 'Monitoring')->first();
    $project->update(['current_stage' => 'Monitoring']);

    $this->actingAs($manager)
        ->patch(route('projects.stages.update', [$project, $stage]), ['field_values' => ['project_status' => 'On-going']])
        ->assertRedirect();

    expect($stage->fresh()->field_values['project_status'])->toBe('On-going');

    $this->actingAs($manager)
        ->patch(route('projects.stages.update', [$project, $stage]), ['field_values' => ['project_status' => 'Done']])
        ->assertRedirect();

    expect($stage->fresh()->field_values['project_status'])->toBe('Done');
});

it('allows manually setting Monitoring Project Status to On-hold via the field-save route', function () {
    $manager = User::factory()->infrastructureManager()->create();
    $project = projectForStageTests();
    $stage = $project->stages()->where('stage', 'Monitoring')->first();
    $project->update(['current_stage' => 'Monitoring']);

    $this->actingAs($manager)
        ->patch(route('projects.stages.update', [$project, $stage]), ['field_values' => ['project_status' => 'On-hold']])
        ->assertRedirect();

    expect($stage->fresh()->field_values['project_status'])->toBe('On-hold');
});

it('allows uploading a Testing document without requiring one to complete Monitoring', function () {
    $manager = User::factory()->infrastructureManager()->create();
    $project = projectForStageTests();
    $project->update(['current_stage' => 'Monitoring']);
    $stage = $project->stages()->where('stage', 'Monitoring')->first();

    $this->actingAs($manager)
        ->post(route('projects.stages.documents.store', [$project, $stage]), [
            'document_category' => 'Testing',
            'files' => [UploadedFile::fake()->create('testing.pdf', 100)],
        ])
        ->assertRedirect();

    expect($stage->fresh()->documents->where('category', 'Testing'))->toHaveCount(1);

    $stage->update(['field_values' => ['project_status' => 'Done', 'testing' => 'Done']]);
    $stage->stageTasks()->create(['name' => 'MDF Installation', 'weight' => 100, 'status' => 'Done', 'status_changed_at' => now(), 'sort_order' => 0]);
    createIspWithTasks($stage, 'PhilCom', 'Done');

    // No Testing document required — it was already optional and uploaded above, but even
    // without it Monitoring should still be completable once the testing dropdown is set.
    $this->actingAs($manager)
        ->post(route('projects.stages.complete', [$project, $stage]))
        ->assertRedirect();
});

it('blocks completing the Monitoring stage until the Testing dropdown has a value', function () {
    $manager = User::factory()->infrastructureManager()->create();
    $project = projectForStageTests();
    $project->update(['current_stage' => 'Monitoring']);
    $stage = $project->stages()->where('stage', 'Monitoring')->first();

    $stage->update(['field_values' => ['project_status' => 'Done']]);
    $stage->stageTasks()->create(['name' => 'MDF Installation', 'weight' => 100, 'status' => 'Done', 'status_changed_at' => now(), 'sort_order' => 0]);
    createIspWithTasks($stage, 'PhilCom', 'Done');

    $this->actingAs($manager)
        ->post(route('projects.stages.complete', [$project, $stage]))
        ->assertStatus(422);

    expect($project->fresh()->current_stage->value)->toBe('Monitoring');
});

it('blocks completing the Planning stage until MOA, NTP, BOQ, and PR are all satisfied', function () {
    StageDocumentRequirement::create(['module' => 'project', 'stage' => 'Planning', 'name' => 'MOA', 'is_required' => true, 'is_active' => true]);
    StageDocumentRequirement::create(['module' => 'project', 'stage' => 'Planning', 'name' => 'NTP', 'is_required' => true, 'is_active' => true]);

    $manager = User::factory()->infrastructureManager()->create();
    $project = projectForStageTests();
    $stage = $project->stages()->where('stage', 'Planning')->first();

    $stage->update(['field_values' => ['contractor' => 'JGD', 'target_activation_date' => '2026-08-01']]);
    createIspWithTasks($stage, 'PhilCom', 'Done');

    $this->actingAs($manager)
        ->post(route('projects.stages.complete', [$project, $stage]))
        ->assertStatus(422);

    expect($project->fresh()->current_stage->value)->toBe('Planning');

    // MOA/NTP uploaded, but BOQ and PR are still missing — still blocks completion.
    attachCompletedStageDocument($stage, 'MOA', null);
    attachCompletedStageDocument($stage, 'NTP', null);

    $this->actingAs($manager)
        ->post(route('projects.stages.complete', [$project, $stage]))
        ->assertStatus(422);

    expect($project->fresh()->current_stage->value)->toBe('Planning');
});

it('allows completing the Planning stage once every document_upload field has at least one file', function () {
    Storage::fake('public');

    StageDocumentRequirement::create(['module' => 'project', 'stage' => 'Planning', 'name' => 'MOA', 'is_required' => true, 'is_active' => true]);
    StageDocumentRequirement::create(['module' => 'project', 'stage' => 'Planning', 'name' => 'NTP', 'is_required' => true, 'is_active' => true]);

    $manager = User::factory()->infrastructureManager()->create();
    $project = projectForStageTests();
    $stage = $project->stages()->where('stage', 'Planning')->first();

    $stage->update(['field_values' => ['contractor' => 'JGD', 'target_activation_date' => '2026-08-01']]);
    createIspWithTasks($stage, 'PhilCom', 'Done');

    foreach (['MOA', 'NTP', 'BOQ', 'PR'] as $category) {
        $this->actingAs($manager)
            ->post(route('projects.stages.documents.store', [$project, $stage]), [
                'document_category' => $category,
                'files' => [UploadedFile::fake()->create("{$category}.pdf", 100)],
            ])
            ->assertRedirect();
    }

    expect($stage->fresh()->documents)->toHaveCount(4);

    $this->actingAs($manager)
        ->post(route('projects.stages.complete', [$project, $stage]))
        ->assertRedirect();

    expect($stage->fresh()->status)->toBe('Completed')
        ->and($project->fresh()->current_stage->value)->toBe('Execution');
});

// ─── deferred JO checklist seeding (Planning MOA/NTP upload) ───────────────────

function createIspWithoutTasks(ProjectStage $stage, string $ispName): ProjectStageIsp
{
    $siteSurvey = SiteSurvey::create(['site_profile_id' => $stage->project->pipeline->site_profile_id]);
    $feasibility = $siteSurvey->networkFeasibilities()->create(['isp_name' => $ispName, 'status' => 'Checking']);

    return $stage->isps()->create(['isp_name' => $ispName, 'network_feasibility_id' => $feasibility->id]);
}

it('does not seed the JO checklist after uploading only one of MOA/NTP on Planning', function () {
    Storage::fake('public');
    $manager = User::factory()->infrastructureManager()->create();
    $project = projectForStageTests();
    $stage = $project->stages()->where('stage', 'Planning')->first();
    $isp = createIspWithoutTasks($stage, 'PhilCom');

    $this->actingAs($manager)
        ->post(route('projects.stages.documents.store', [$project, $stage]), [
            'document_category' => 'MOA',
            'files' => [UploadedFile::fake()->create('moa.pdf', 100)],
        ])
        ->assertRedirect();

    expect($isp->networkFeasibility->fresh()->tasks)->toHaveCount(0);
});

it('seeds the JO checklist for every linked ISP once both MOA and NTP are uploaded on Planning', function () {
    Storage::fake('public');
    $manager = User::factory()->infrastructureManager()->create();
    $project = projectForStageTests();
    $stage = $project->stages()->where('stage', 'Planning')->first();
    $isp = createIspWithoutTasks($stage, 'PhilCom');

    // A manually-added ISP (no network_feasibility_id) has no JO checklist to seed — must be
    // skipped without error.
    $stage->isps()->create(['isp_name' => 'Manually Added ISP']);

    $this->actingAs($manager)
        ->post(route('projects.stages.documents.store', [$project, $stage]), [
            'document_category' => 'MOA',
            'files' => [UploadedFile::fake()->create('moa.pdf', 100)],
        ])
        ->assertRedirect();

    expect($isp->networkFeasibility->fresh()->tasks)->toHaveCount(0);

    $this->actingAs($manager)
        ->post(route('projects.stages.documents.store', [$project, $stage]), [
            'document_category' => 'NTP',
            'files' => [UploadedFile::fake()->create('ntp.pdf', 100)],
        ])
        ->assertRedirect();

    $expectedTaskNames = collect(NetworkFeasibilityTaskDefinitions::all())->pluck('name')->all();

    expect($isp->networkFeasibility->fresh()->tasks)->toHaveCount(count($expectedTaskNames))
        ->and($isp->networkFeasibility->tasks->pluck('status')->unique()->all())->toBe([IspTaskStatus::Pending]);
});

it('does not duplicate or reset the JO checklist when MOA/NTP are re-uploaded after seeding', function () {
    Storage::fake('public');
    $manager = User::factory()->infrastructureManager()->create();
    $project = projectForStageTests();
    $stage = $project->stages()->where('stage', 'Planning')->first();
    $isp = createIspWithTasks($stage, 'PhilCom', 'Done');

    foreach (['MOA', 'NTP'] as $category) {
        $this->actingAs($manager)
            ->post(route('projects.stages.documents.store', [$project, $stage]), [
                'document_category' => $category,
                'files' => [UploadedFile::fake()->create("{$category}.pdf", 100)],
            ])
            ->assertRedirect();
    }

    $expectedTaskNames = collect(NetworkFeasibilityTaskDefinitions::all())->pluck('name')->all();

    expect($isp->networkFeasibility->fresh()->tasks)->toHaveCount(count($expectedTaskNames))
        ->and($isp->networkFeasibility->tasks()->where('status', 'Done')->count())->toBeGreaterThan(0);
});

it('replaces a stage document as a new version and resets its review status', function () {
    Storage::fake('public');
    $manager = User::factory()->infrastructureManager()->create();
    $project = projectForStageTests();
    $project->update(['current_stage' => 'Closing']);
    $stage = $project->stages()->where('stage', 'Closing')->first();
    $document = attachCompletedStageDocument($stage, 'RFCO', null);
    $stage->documents()->updateExistingPivot($document->id, ['status' => 'Done']);

    $this->actingAs($manager)
        ->post(route('projects.stages.documents.replace', [$project, $stage, $document]), [
            'file' => UploadedFile::fake()->create('rfco-corrected.pdf', 100),
        ])
        ->assertRedirect();

    $current = $stage->fresh()->documents()->first();
    expect($current->id)->not->toBe($document->id)
        ->and($current->version)->toBe(2)
        ->and($current->category)->toBe('RFCO')
        ->and($current->pivot->status)->toBeNull();

    expect($document->fresh()->superseded_at)->not->toBeNull();
});

it('no longer accepts a status update on a Planning document, since MOA/NTP dropped their status dropdown', function () {
    $manager = User::factory()->infrastructureManager()->create();
    $project = projectForStageTests();
    $stage = $project->stages()->where('stage', 'Planning')->first();
    $document = attachCompletedStageDocument($stage, 'MOA', null);

    $this->actingAs($manager)
        ->patch(route('projects.stages.documents.update-status', [$project, $stage, $document]), ['status' => 'Done'])
        ->assertSessionHasErrors('status');

    expect($stage->documents()->find($document->id)->pivot->status)->toBeNull();
});

it('denies a role without a stage permission from updating a stage document status', function () {
    $networkManager = User::factory()->networkManager()->create();
    $project = projectForStageTests();
    $project->update(['current_stage' => 'Closing']);
    $stage = $project->stages()->where('stage', 'Closing')->first();
    $document = attachCompletedStageDocument($stage, 'RFCO', null);

    $this->actingAs($networkManager)
        ->patch(route('projects.stages.documents.update-status', [$project, $stage, $document]), ['status' => 'Done'])
        ->assertForbidden();
});

it('exposes manageNetworkFeasibility as true for a role with ManageNetworkFeasibility', function () {
    $networkManager = User::factory()->networkManager()->create();
    $project = projectForStageTests();
    NetworkSiteAssignment::create([
        'pipeline_id' => $project->pipeline_id,
        'assigned_at' => now(),
    ]);

    $this->actingAs($networkManager)
        ->get(route('projects.show', $project))
        ->assertInertia(fn (Assert $page) => $page->where('can.manageNetworkFeasibility', true));
});

it('exposes manageNetworkFeasibility as false for a role without ManageNetworkFeasibility', function () {
    $manager = User::factory()->infrastructureManager()->create();
    $project = projectForStageTests();
    NetworkSiteAssignment::create([
        'pipeline_id' => $project->pipeline_id,
        'assigned_at' => now(),
    ]);

    $this->actingAs($manager)
        ->get(route('projects.show', $project))
        ->assertInertia(fn (Assert $page) => $page->where('can.manageNetworkFeasibility', false));
});

it('exposes manageNetworkFeasibility as false when the pipeline has no network site assignment yet', function () {
    $networkManager = User::factory()->networkManager()->create();
    $project = projectForStageTests();

    $this->actingAs($networkManager)
        ->get(route('projects.show', $project))
        ->assertInertia(fn (Assert $page) => $page->where('can.manageNetworkFeasibility', false));
});

it('exposes not-required document categories for upload separately from what actually gates completion', function () {
    $manager = User::factory()->infrastructureManager()->create();
    $project = projectForStageTests();

    StageDocumentRequirement::create([
        'module' => 'project',
        'stage' => 'Planning',
        'name' => 'Optional Extra',
        'is_required' => false,
        'is_active' => true,
    ]);

    $this->actingAs($manager)
        ->get(route('projects.show', $project))
        ->assertInertia(fn (Assert $page) => $page
            ->where('stageDocumentRequirements.Planning', fn ($names) => collect($names)->contains('Optional Extra'))
            ->where('requiredStageDocumentRequirements.Planning', fn ($names) => ! collect($names)->contains('Optional Extra')));
});

it('keeps Planning Details read-only for Network users while Infra can edit them', function () {
    Storage::fake('public');
    $networkSurveyor = User::factory()->networkSurveyor()->create();
    $infraManager = User::factory()->infrastructureManager()->create();
    $project = projectForStageTests();
    $stage = $project->stages()->where('stage', 'Planning')->first();

    $this->actingAs($networkSurveyor);

    $this->patch(route('projects.stages.update', [$project, $stage]), ['field_values' => ['contractor' => 'JGD']])->assertForbidden();
    $this->post(route('projects.stages.documents.store', [$project, $stage]), [
        'document_category' => 'BOQ',
        'files' => [UploadedFile::fake()->create('boq.pdf', 10)],
    ])->assertForbidden();

    expect($stage->fresh()->field_values['contractor'] ?? null)->toBeNull();

    $this->actingAs($infraManager)
        ->patch(route('projects.stages.update', [$project, $stage]), ['field_values' => ['contractor' => 'JGD']])
        ->assertRedirect();

    expect($stage->fresh()->field_values['contractor'])->toBe('JGD');
});

it('does not allow removing a MOA/NTP carried forward from the Commercials stage', function () {
    $infraManager = User::factory()->infrastructureManager()->create();
    $project = projectForStageTests();
    $stage = $project->stages()->where('stage', 'Planning')->first();
    $document = attachCompletedStageDocument($stage, 'MOA', null);
    $project->pipeline->documents()->attach($document->id, ['created_at' => now()]);
    $ownUpload = attachCompletedStageDocument($stage, 'NTP', null);

    $this->actingAs($infraManager)
        ->delete(route('projects.stages.documents.delete', [$project, $stage, $document]))
        ->assertSessionHasErrors('document');

    expect($stage->documents()->where('documents.id', $document->id)->exists())->toBeTrue();

    $this->actingAs($infraManager)
        ->delete(route('projects.stages.documents.delete', [$project, $stage, $ownUpload]))
        ->assertSessionHasNoErrors();
});

it('only accepts an active Surveyor as PM In-charge', function () {
    $manager = User::factory()->infrastructureManager()->create();
    $project = projectForStageTests();
    $executionStage = $project->stages()->where('stage', 'Execution')->first();
    $project->update(['current_stage' => 'Execution']);
    $nonSurveyor = User::factory()->create();
    $inactiveSurveyor = User::factory()->surveyor()->inactive()->create();

    foreach ([$nonSurveyor, $inactiveSurveyor] as $candidate) {
        $this->actingAs($manager)
            ->patch(route('projects.stages.update', [$project, $executionStage]), [
                'field_values' => ['pm_in_charge' => $candidate->id],
            ])
            ->assertSessionHasErrors('field_values.pm_in_charge');
    }
});

it('lists only active Surveyors in the PM In-charge dropdown', function () {
    $manager = User::factory()->infrastructureManager()->create();
    $surveyor = User::factory()->surveyor()->create();
    User::factory()->surveyor()->inactive()->create();
    User::factory()->create();
    $project = projectForStageTests();

    $this->actingAs($manager)
        ->get(route('projects.show', $project))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page->has('users', 1)->where('users.0.id', $surveyor->id));
});
