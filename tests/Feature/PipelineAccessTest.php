<?php

use App\Enums\ApprovalStatus;
use App\Enums\PipelineActionPermission;
use App\Enums\PipelineStatus;
use App\Enums\SidebarModule;
use App\Enums\SiteProfileType;
use App\Models\Address;
use App\Models\Pipeline;
use App\Models\PipelineApproval;
use App\Models\Role;
use App\Models\RoleActionPermission;
use App\Models\RoleModulePermission;
use App\Models\RolePermission;
use App\Models\SiteProfile;
use App\Models\SiteSurvey;
use App\Models\StageDocumentRequirement;
use App\Models\User;

// ─── Helpers ──────────────────────────────────────────────────────────────────

function makePipeline(User $manager, PipelineStatus $status = PipelineStatus::Prospecting): Pipeline
{
    $address = Address::create(['city' => 'Manila']);
    $siteProfile = SiteProfile::create([
        'name' => 'Access Test Site',
        'type' => SiteProfileType::Condo,
        'address_id' => $address->id,
    ]);

    return Pipeline::create([
        'name' => 'Access Test Pipeline',
        'pipeline_id' => nextPipelineId(),
        'project_manager_id' => $manager->id,
        'assigned_saq_id' => $manager->isSaqSpecialist() ? $manager->id : null,
        'site_profile_id' => $siteProfile->id,
        'status' => $status,
    ]);
}

function makeSurveyPipeline(User $manager, User $surveyor, PipelineStatus $status): Pipeline
{
    $address = Address::create(['city' => 'Manila']);
    $siteProfile = SiteProfile::create([
        'name' => 'Access Test Site',
        'type' => SiteProfileType::Condo,
        'address_id' => $address->id,
    ]);

    $survey = SiteSurvey::create([
        'site_profile_id' => $siteProfile->id,
        'surveyor_id' => $surveyor->id,
        'survey_date' => now()->toDateString(),
    ]);

    return Pipeline::create([
        'name' => 'Access Test Pipeline',
        'pipeline_id' => nextPipelineId(),
        'project_manager_id' => $manager->id,
        'site_profile_id' => $siteProfile->id,
        'site_survey_id' => $survey->id,
        'status' => $status,
    ]);
}

// ─── startAcquisition access ──────────────────────────────────────────────────

it('blocks a non-assigned SAQ user from starting prospecting', function () {
    $manager = User::factory()->projectManager()->create();
    $otherSaq = User::factory()->saqSpecialist()->create();
    $pipeline = makePipeline($manager, PipelineStatus::Prospect);

    $this->actingAs($otherSaq)
        ->post(route('pipelines.start-acquisition', $pipeline), [
            'content' => 'Prospect is ready for intake.',
        ])
        ->assertForbidden();
});

it('blocks a user without initiated permission from starting acquisition', function () {
    $manager = User::factory()->projectManager()->create();
    $userWithoutPermission = User::factory()->create();
    $pipeline = makePipeline($manager, PipelineStatus::Prospect);

    $this->actingAs($userWithoutPermission)
        ->post(route('pipelines.start-acquisition', $pipeline), [
            'content' => 'Prospect is ready for intake.',
        ])
        ->assertForbidden();
});

// ─── scheduleSiteVisit access ─────────────────────────────────────────────────

it('allows the assigned SAQ to move prospecting to site survey pending', function () {
    $manager = User::factory()->projectManager()->create();
    $saq = User::factory()->saqSpecialist()->create();
    $pipeline = makePipeline($manager);
    $pipeline->update(['assigned_saq_id' => $saq->id]);

    $this->actingAs($saq)
        ->post(route('pipelines.move-to-site-survey-pending', $pipeline))
        ->assertRedirect();

    expect($pipeline->fresh()->status)->toBe(PipelineStatus::SiteSurveyPending);
});

it('blocks a non-assigned SAQ user from moving to site survey pending', function () {
    $manager = User::factory()->projectManager()->create();
    $otherSaq = User::factory()->saqSpecialist()->create();
    $pipeline = makePipeline($manager);

    $this->actingAs($otherSaq)
        ->post(route('pipelines.move-to-site-survey-pending', $pipeline))
        ->assertForbidden();
});

// Scheduling a site survey (assigning a surveyor + date) now lives in the Site Planning
// module — see tests/Feature/SitePlanningControllerTest.php.

it('lists assignable SAQ users by Prospecting stage permission', function () {
    $leadSaq = User::factory()->leadSaqSpecialist()->create();
    $eligibleSaq = User::factory()->saqSpecialist()->create(['full_name' => 'Eligible Prospecting User']);
    $ineligibleSurveyor = User::factory()->surveyor()->create(['full_name' => 'Survey Only User']);

    $response = $this->actingAs($leadSaq)
        ->get(route('pipelines'))
        ->assertOk();

    $specialistIds = collect($response->viewData('page')['props']['saqSpecialists'])
        ->pluck('id');

    expect($specialistIds)
        ->toContain($eligibleSaq->id)
        ->not->toContain($ineligibleSurveyor->id);
});

it('exposes can.create true only for a role holding Prospect/Prospecting permission', function () {
    $leadSaq = User::factory()->leadSaqSpecialist()->create();
    $surveyor = User::factory()->surveyor()->create();

    $this->actingAs($leadSaq)
        ->get(route('pipelines'))
        ->assertInertia(fn ($page) => $page->where('can.create', true));

    $this->actingAs($surveyor)
        ->get(route('pipelines'))
        ->assertInertia(fn ($page) => $page->where('can.create', false));
});

it('includes assigned surveyor data on the pipeline list', function () {
    $manager = User::factory()->leadSaqSpecialist()->create();
    $surveyor = User::factory()->surveyor()->create(['full_name' => 'List Surveyor User']);
    $pipeline = makeSurveyPipeline($manager, $surveyor, PipelineStatus::SiteSurvey);

    $response = $this->actingAs($manager)
        ->get(route('pipelines'))
        ->assertOk();

    $pipeline = collect($response->viewData('page')['props']['pipelines']['data'])
        ->firstWhere('site_survey.surveyor.id', $surveyor->id);

    expect($pipeline)->not->toBeNull()
        ->and($pipeline['site_survey']['surveyor']['full_name'])->toBe('List Surveyor User')
        ->and($pipeline['site_survey']['survey_date'])->not->toBeNull();
});

it('provides assignable SAQ users on the pipeline show page by Prospecting stage permission', function () {
    $leadSaq = User::factory()->leadSaqSpecialist()->create();
    $eligibleSaq = User::factory()->saqSpecialist()->create(['full_name' => 'Eligible Show User']);
    $ineligibleSurveyor = User::factory()->surveyor()->create(['full_name' => 'Survey Show User']);
    $pipeline = makePipeline($leadSaq, PipelineStatus::Prospect);

    $response = $this->actingAs($leadSaq)
        ->get(route('pipelines.show', $pipeline))
        ->assertOk();

    $props = $response->viewData('page')['props'];
    $specialistIds = collect($props['saqSpecialists'])->pluck('id');

    expect($props['can']['assignSaq'])
        ->toBeTrue()
        ->and($specialistIds)
        ->toContain($eligibleSaq->id)
        ->not->toContain($ineligibleSurveyor->id);
});

it('blocks assigning a user without Prospecting stage permission', function () {
    $leadSaq = User::factory()->leadSaqSpecialist()->create();
    $userWithoutProspecting = User::factory()->surveyor()->create();
    $pipeline = makePipeline($leadSaq, PipelineStatus::Prospect);

    $this->actingAs($leadSaq)
        ->patch(route('pipelines.assign-saq', $pipeline), [
            'assigned_saq_id' => $userWithoutProspecting->id,
        ])
        ->assertStatus(422);

    $pipeline->refresh();

    expect($pipeline->assigned_saq_id)->toBeNull()
        ->and($pipeline->status)->toBe(PipelineStatus::Prospect);
});

it('moves a prospect to prospecting when assigning a user with Prospecting stage permission', function () {
    $leadSaq = User::factory()->leadSaqSpecialist()->create();
    $eligibleSaq = User::factory()->saqSpecialist()->create();
    $pipeline = makePipeline($leadSaq, PipelineStatus::Prospect);

    $this->actingAs($leadSaq)
        ->patch(route('pipelines.assign-saq', $pipeline), [
            'assigned_saq_id' => $eligibleSaq->id,
        ])
        ->assertRedirect();

    $pipeline->refresh();

    expect($pipeline->assigned_saq_id)->toBe($eligibleSaq->id)
        ->and($pipeline->status)->toBe(PipelineStatus::Prospecting)
        ->and($pipeline->statusLogs()->where('status', PipelineStatus::Prospecting->value)->exists())->toBeTrue()
        ->and($pipeline->remarkLogs()->where('content', 'SAQ Specialist assigned; prospecting started.')->exists())->toBeTrue();
});

it('blocks assigning an SAQ from advancing Prospect to Prospecting when a required document is missing', function () {
    $leadSaq = User::factory()->leadSaqSpecialist()->create();
    $eligibleSaq = User::factory()->saqSpecialist()->create();
    $pipeline = makePipeline($leadSaq, PipelineStatus::Prospect);

    StageDocumentRequirement::create([
        'stage' => PipelineStatus::Prospect->value,
        'name' => 'LOI',
        'is_required' => true,
        'is_active' => true,
    ]);

    $this->actingAs($leadSaq)
        ->patch(route('pipelines.assign-saq', $pipeline), [
            'assigned_saq_id' => $eligibleSaq->id,
        ])
        ->assertSessionHasErrors('required_documents');

    $pipeline->refresh();

    expect($pipeline->assigned_saq_id)->toBeNull()
        ->and($pipeline->status)->toBe(PipelineStatus::Prospect);
});

it('does not change status when reassigning SAQ outside prospect status', function () {
    $leadSaq = User::factory()->leadSaqSpecialist()->create();
    $currentSaq = User::factory()->saqSpecialist()->create();
    $newSaq = User::factory()->saqSpecialist()->create();
    $pipeline = makePipeline($leadSaq, PipelineStatus::Prospecting);
    $pipeline->update(['assigned_saq_id' => $currentSaq->id]);

    $this->actingAs($leadSaq)
        ->patch(route('pipelines.assign-saq', $pipeline), [
            'assigned_saq_id' => $newSaq->id,
        ])
        ->assertRedirect();

    $pipeline->refresh();

    expect($pipeline->assigned_saq_id)->toBe($newSaq->id)
        ->and($pipeline->status)->toBe(PipelineStatus::Prospecting)
        ->and($pipeline->statusLogs()->where('status', PipelineStatus::Prospecting->value)->count())->toBe(0);
});

// Filling in / completing the survey itself now lives in the Site Planning module — see
// tests/Feature/SitePlanningControllerTest.php.

// ─── editable survey findings access ─────────────────────────────────────────

it('allows the assigned SAQ to save survey findings in prospect stages without changing status', function (PipelineStatus $status) {
    $manager = User::factory()->projectManager()->create();
    $saq = User::factory()->saqSpecialist()->create();
    $pipeline = makePipeline($manager, $status);
    $pipeline->update(['assigned_saq_id' => $saq->id]);

    $this->actingAs($saq)
        ->post(route('pipelines.survey-findings', $pipeline), [
            'floors_surveyed' => 12,
            'actual_unit_count' => 480,
            'providers' => [
                ['provider_name' => 'Existing Fiber', 'status' => 'Feasible', 'notes' => 'Present in MDF.'],
            ],
        ])
        ->assertRedirect(route('pipelines.show', $pipeline));

    $pipeline->refresh();

    expect($pipeline->status)->toBe($status)
        ->and($pipeline->siteSurvey->floors_surveyed)->toBe(12)
        ->and($pipeline->siteSurvey->providers)->toHaveCount(1);
})->with([
    PipelineStatus::Prospect,
    PipelineStatus::Prospecting,
]);

it('rejects survey findings with a blank existing ISP provider name', function () {
    $manager = User::factory()->projectManager()->create();
    $saq = User::factory()->saqSpecialist()->create();
    $pipeline = makePipeline($manager, PipelineStatus::Prospecting);
    $pipeline->update(['assigned_saq_id' => $saq->id]);

    $this->actingAs($saq)
        ->post(route('pipelines.survey-findings', $pipeline), [
            'providers' => [
                ['provider_name' => ''],
            ],
        ])
        ->assertSessionHasErrors('providers.0.provider_name');
});

it('blocks a commercial manager from editing survey findings once past site survey', function () {
    $manager = User::factory()->projectManager()->create();
    $commercialManager = User::factory()->commercialManager()->create();
    $pipeline = makePipeline($manager, PipelineStatus::Commercials);

    $this->actingAs($commercialManager)
        ->post(route('pipelines.survey-findings', $pipeline), [
            'marketing_paraphernalia' => 'Lobby posters allowed.',
        ])
        ->assertForbidden();

    expect($pipeline->fresh()->siteSurvey)->toBeNull();
});

it('blocks a SAQ specialist from saving later-stage findings without the required action permission', function () {
    $manager = User::factory()->projectManager()->create();
    $saq = User::factory()->saqSpecialist()->create();
    $pipeline = makePipeline($manager, PipelineStatus::Commercials);

    $this->actingAs($saq)
        ->post(route('pipelines.survey-findings', $pipeline), [
            'telco_room_location' => 'Basement 1',
        ])
        ->assertForbidden();

    RolePermission::firstOrCreate(['role_id' => $saq->role_id, 'pipeline_status' => PipelineStatus::Commercials->value]);

    $this->actingAs($saq->fresh())
        ->post(route('pipelines.survey-findings', $pipeline), ['telco_room_location' => 'Basement 1'])
        ->assertForbidden();
});

it('blocks a user without current-stage permission from saving survey findings', function () {
    $manager = User::factory()->projectManager()->create();
    $role = Role::factory()->create();
    RoleModulePermission::create(['role_id' => $role->id, 'module' => SidebarModule::Pipelines->value]);
    $user = User::factory()->create(['role_id' => $role->id]);
    $pipeline = makePipeline($manager, PipelineStatus::Commercials);

    $this->actingAs($user)
        ->post(route('pipelines.survey-findings', $pipeline), [
            'telco_room_fee' => 5000,
        ])
        ->assertForbidden();
});

it('does not pass the edit survey findings permission to a commercial manager on the pipeline details page', function () {
    $manager = User::factory()->projectManager()->create();
    $commercialManager = User::factory()->commercialManager()->create();
    $pipeline = makePipeline($manager, PipelineStatus::Commercials);

    $this->actingAs($commercialManager)
        ->get(route('pipelines.show', $pipeline))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Pipelines/Show')
            ->where('can.editSurveyFindings', false)
        );
});

// ─── handleCommercials access ─────────────────────────────────────────────────

it('allows a commercial manager to save commercials progress', function () {
    $manager = User::factory()->projectManager()->create();
    $commercialManager = User::factory()->commercialManager()->create();
    $pipeline = makePipeline($manager, PipelineStatus::Commercials);

    $this->actingAs($commercialManager)
        ->post(route('pipelines.save-commercials-progress', $pipeline), [])
        ->assertRedirect();
});

it('blocks a project manager from saving commercials progress', function () {
    $manager = User::factory()->projectManager()->create();
    $pipeline = makePipeline($manager, PipelineStatus::Commercials);

    $this->actingAs($manager)
        ->post(route('pipelines.save-commercials-progress', $pipeline), [])
        ->assertForbidden();
});

it('blocks a surveyor from saving commercials progress', function () {
    $manager = User::factory()->projectManager()->create();
    $surveyor = User::factory()->surveyor()->create();
    $pipeline = makePipeline($manager, PipelineStatus::Commercials);

    $this->actingAs($surveyor)
        ->post(route('pipelines.save-commercials-progress', $pipeline), [])
        ->assertForbidden();
});

// ─── RFD/RFS access ───────────────────────────────────────────────────────────

it('allows a user with RFD permission to mark a pipeline as RFS', function () {
    $manager = User::factory()->projectManager()->create();
    $role = Role::factory()->create();
    RolePermission::create(['role_id' => $role->id, 'pipeline_status' => PipelineStatus::ReadyForDeployment->value]);
    RoleActionPermission::create(['role_id' => $role->id, 'permission' => PipelineActionPermission::MarkInactive->value]);
    RoleModulePermission::create(['role_id' => $role->id, 'module' => SidebarModule::Pipelines->value]);
    $user = User::factory()->create(['role_id' => $role->id]);
    $pipeline = makePipeline($manager, PipelineStatus::ReadyForDeployment);

    $this->actingAs($user)
        ->post(route('pipelines.mark-active', $pipeline))
        ->assertRedirect(route('pipelines.show', $pipeline));

    expect($pipeline->fresh()->status)->toBe(PipelineStatus::ReadyForService);
});

it('blocks a user without RFD permission from marking a pipeline as RFS', function () {
    $manager = User::factory()->projectManager()->create();
    $userWithoutPermission = User::factory()->create();
    $pipeline = makePipeline($manager, PipelineStatus::ReadyForDeployment);

    $this->actingAs($userWithoutPermission)
        ->post(route('pipelines.mark-active', $pipeline))
        ->assertForbidden();
});

it('allows a user with current-stage permission to mark a pipeline inactive', function () {
    $manager = User::factory()->projectManager()->create();
    $surveyor = User::factory()->surveyor()->create();
    $pipeline = makeSurveyPipeline($manager, $surveyor, PipelineStatus::SiteSurvey);

    // The Surveyor is the pipeline's own assigned surveyor (siteSurvey.surveyor_id), so
    // it keeps view access — and therefore keeps seeing the pipeline — after marking it
    // inactive.
    $this->actingAs($surveyor)
        ->post(route('pipelines.mark-inactive', $pipeline))
        ->assertRedirect(route('pipelines.show', $pipeline));

    expect($pipeline->fresh()->status)->toBe(PipelineStatus::Inactive);
});

// ─── Surveyor scoped to their own assigned pipeline only ────────────────

it('blocks a surveyor from viewing a pipeline assigned to a different surveyor', function () {
    $manager = User::factory()->projectManager()->create();
    $assignedSurveyor = User::factory()->surveyor()->create();
    $otherSurveyor = User::factory()->surveyor()->create();
    $pipeline = makeSurveyPipeline($manager, $assignedSurveyor, PipelineStatus::SiteSurvey);

    $this->actingAs($otherSurveyor)
        ->get(route('pipelines.show', $pipeline))
        ->assertForbidden();
});

it('blocks a surveyor from adding an activity remark to a pipeline assigned to a different surveyor', function () {
    $manager = User::factory()->projectManager()->create();
    $assignedSurveyor = User::factory()->surveyor()->create();
    $otherSurveyor = User::factory()->surveyor()->create();
    $pipeline = makeSurveyPipeline($manager, $assignedSurveyor, PipelineStatus::SiteSurvey);

    $this->actingAs($otherSurveyor)
        ->post(route('pipelines.add-remark', $pipeline), ['content' => 'Should not be allowed.'])
        ->assertForbidden();
});

it('blocks a surveyor from marking inactive a pipeline assigned to a different surveyor', function () {
    $manager = User::factory()->projectManager()->create();
    $assignedSurveyor = User::factory()->surveyor()->create();
    $otherSurveyor = User::factory()->surveyor()->create();
    $pipeline = makeSurveyPipeline($manager, $assignedSurveyor, PipelineStatus::SiteSurvey);

    $this->actingAs($otherSurveyor)
        ->post(route('pipelines.mark-inactive', $pipeline))
        ->assertForbidden();

    expect($pipeline->fresh()->status)->toBe(PipelineStatus::SiteSurvey);
});

it('allows a surveyor to view and add a remark to their own assigned pipeline', function () {
    $manager = User::factory()->projectManager()->create();
    $assignedSurveyor = User::factory()->surveyor()->create();
    $pipeline = makeSurveyPipeline($manager, $assignedSurveyor, PipelineStatus::SiteSurvey);

    $this->actingAs($assignedSurveyor)
        ->get(route('pipelines.show', $pipeline))
        ->assertSuccessful();

    $this->actingAs($assignedSurveyor)
        ->post(route('pipelines.add-remark', $pipeline), ['content' => 'Site visited.'])
        ->assertRedirect(route('pipelines.show', $pipeline));
});

// ─── decideApproval access ────────────────────────────────────────────────────

it('allows the assigned approver to decide an approval step', function () {
    $manager = User::factory()->projectManager()->create();
    $approverRole = Role::factory()->create();
    RoleModulePermission::create(['role_id' => $approverRole->id, 'module' => SidebarModule::Pipelines->value]);
    RolePermission::create(['role_id' => $approverRole->id, 'pipeline_status' => PipelineStatus::Approval->value]);
    RoleActionPermission::create(['role_id' => $approverRole->id, 'permission' => PipelineActionPermission::DecideApproval->value]);
    $approver = User::factory()->create(['role_id' => $approverRole->id]);
    $pipeline = makePipeline($manager, PipelineStatus::Approval);

    $approval = PipelineApproval::create([
        'pipeline_id' => $pipeline->id,
        'step' => 1,
        'role_id' => $approverRole->id,
        'status' => ApprovalStatus::Pending,
    ]);

    $this->actingAs($approver)
        ->post(route('pipelines.approvals.decide', [$pipeline, $approval]), [
            'decision' => 'approved',
        ])
        ->assertRedirect();
});

it('allows approval decision roles to view approval stage pipelines', function () {
    $manager = User::factory()->projectManager()->create();
    $approverRole = Role::factory()->create();
    RoleModulePermission::create(['role_id' => $approverRole->id, 'module' => SidebarModule::Pipelines->value]);
    RolePermission::create(['role_id' => $approverRole->id, 'pipeline_status' => PipelineStatus::Approval->value]);
    RoleActionPermission::create(['role_id' => $approverRole->id, 'permission' => PipelineActionPermission::DecideApproval->value]);
    $approver = User::factory()->create(['role_id' => $approverRole->id]);
    $pipeline = makePipeline($manager, PipelineStatus::Approval);

    PipelineApproval::create([
        'pipeline_id' => $pipeline->id,
        'step' => 1,
        'role_id' => $approverRole->id,
        'status' => ApprovalStatus::Pending,
    ]);

    $this->actingAs($approver)
        ->get(route('pipelines.show', $pipeline))
        ->assertOk();

    $response = $this->actingAs($approver)
        ->get(route('pipelines'))
        ->assertOk();

    $pipelineIds = collect($response->viewData('page')['props']['pipelines']['data'])->pluck('id');

    expect($pipelineIds)->toContain($pipeline->id);
});

it('blocks approval decision roles from viewing approval pipelines without approval stage permission', function () {
    $manager = User::factory()->projectManager()->create();
    $role = Role::factory()->create();
    RoleModulePermission::create(['role_id' => $role->id, 'module' => SidebarModule::Pipelines->value]);
    RoleActionPermission::create(['role_id' => $role->id, 'permission' => PipelineActionPermission::DecideApproval->value]);
    $approverWithoutStage = User::factory()->create(['role_id' => $role->id]);
    $pipeline = makePipeline($manager, PipelineStatus::Approval);

    PipelineApproval::create([
        'pipeline_id' => $pipeline->id,
        'step' => 1,
        'role_id' => $role->id,
        'status' => ApprovalStatus::Pending,
    ]);

    $this->actingAs($approverWithoutStage)
        ->get(route('pipelines.show', $pipeline))
        ->assertForbidden();

    $response = $this->actingAs($approverWithoutStage)
        ->get(route('pipelines'))
        ->assertOk();

    $pipelineIds = collect($response->viewData('page')['props']['pipelines']['data'])->pluck('id');

    expect($pipelineIds)->not->toContain($pipeline->id);
});

it('blocks a non-approver from deciding an approval step', function () {
    $manager = User::factory()->projectManager()->create();
    $approverRole = Role::factory()->create();
    RoleModulePermission::create(['role_id' => $approverRole->id, 'module' => SidebarModule::Pipelines->value]);
    $approver = User::factory()->create(['role_id' => $approverRole->id]);
    $intruder = User::factory()->create(); // different role
    $pipeline = makePipeline($manager, PipelineStatus::Approval);

    $approval = PipelineApproval::create([
        'pipeline_id' => $pipeline->id,
        'step' => 1,
        'role_id' => $approverRole->id,
        'status' => ApprovalStatus::Pending,
    ]);

    $this->actingAs($intruder)
        ->post(route('pipelines.approvals.decide', [$pipeline, $approval]), [
            'decision' => 'approved',
        ])
        ->assertForbidden();
});
