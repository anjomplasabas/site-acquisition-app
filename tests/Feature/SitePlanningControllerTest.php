<?php

use App\Enums\DocumentType;
use App\Enums\IspTaskStatus;
use App\Enums\NotificationType;
use App\Enums\PipelineStatus;
use App\Enums\SidebarModule;
use App\Enums\SitePlanningActionPermission;
use App\Enums\SiteProfileType;
use App\Events\PipelineApproved;
use App\Listeners\InitializeProject;
use App\Models\Address;
use App\Models\Document;
use App\Models\NetworkSiteAssignment;
use App\Models\Pipeline;
use App\Models\Project;
use App\Models\ProjectStage;
use App\Models\Role;
use App\Models\RoleActionPermission;
use App\Models\RoleModulePermission;
use App\Models\SitePlanningAssignment;
use App\Models\SiteProfile;
use App\Models\SiteSurvey;
use App\Models\StageDocumentRequirement;
use App\Models\Tower;
use App\Models\User;
use App\Notifications\PipelineNotification;
use App\Support\SitePlanningTaskDefinitions;
use App\Support\SitePlanningTaskSeeder;
use App\Support\SiteSurveyTaskSeeder;
use App\Support\StageTaskDefinitions;
use App\Support\StageTaskSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * @return array{pipeline: Pipeline, assignment: SitePlanningAssignment}
 */
function pipelineWithSitePlanningAssignment(PipelineStatus $status = PipelineStatus::SiteSurveyPending): array
{
    $address = Address::create(['city' => 'Manila']);
    $siteProfile = SiteProfile::create([
        'name' => 'Test Site',
        'type' => SiteProfileType::Condo,
        'address_id' => $address->id,
    ]);

    $survey = SiteSurvey::create(['site_profile_id' => $siteProfile->id]);

    $pipeline = Pipeline::create([
        'name' => 'Test Pipeline',
        'pipeline_id' => nextPipelineId(),
        'site_profile_id' => $siteProfile->id,
        'site_survey_id' => $survey->id,
        'status' => $status,
    ]);

    $assignment = SitePlanningAssignment::ensureFor($pipeline, $survey);

    return compact('pipeline', 'assignment');
}

function userScopedToSitePlanning(array $actions = []): User
{
    $role = Role::factory()->create();
    RoleModulePermission::create(['role_id' => $role->id, 'module' => SidebarModule::Dashboard->value]);
    RoleModulePermission::create(['role_id' => $role->id, 'module' => SidebarModule::SitePlanning->value]);

    foreach ($actions as $permission) {
        RoleActionPermission::create(['role_id' => $role->id, 'permission' => $permission->value]);
    }

    return User::factory()->create(['role_id' => $role->id]);
}

function completeAllPlansDrawingTasks(SitePlanningAssignment $assignment, string $type = 'FIBER'): void
{
    $assignment->update(['design_plan_type' => $type]);
    SitePlanningTaskSeeder::seedMissingFor($assignment);
    $assignment->sitePlanningTasks()->update(['status' => IspTaskStatus::Done->value, 'status_changed_at' => now()]);
}

// ─── Module gate ────────────────────────────────────────────────────────────

it('blocks a user without site planning module access', function () {
    ['assignment' => $assignment] = pipelineWithSitePlanningAssignment();
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('site-planning.index'))->assertForbidden();
    $this->actingAs($user)->get(route('site-planning.show', $assignment))->assertForbidden();
});

it('allows infrastructure manager and surveyor to view the module', function () {
    ['assignment' => $assignment] = pipelineWithSitePlanningAssignment();
    $infrastructureManager = User::factory()->infrastructureManager()->create();
    $surveyor = User::factory()->surveyor()->create();

    $this->actingAs($infrastructureManager)->get(route('site-planning.index'))
        ->assertInertia(fn (Assert $page) => $page->component('SitePlanning/Index'));

    $this->actingAs($surveyor)->get(route('site-planning.show', $assignment))
        ->assertInertia(fn (Assert $page) => $page->component('SitePlanning/Show'));
});

it('exposes designers and assignDesignPlan capability on the index page', function () {
    pipelineWithSitePlanningAssignment();
    $infrastructureManager = User::factory()->infrastructureManager()->create();
    $surveyor = User::factory()->surveyor()->create();

    $this->actingAs($infrastructureManager)->get(route('site-planning.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('SitePlanning/Index')
            ->has('designers')
            ->where('can.assignDesignPlan', true));

    $this->actingAs($surveyor)->get(route('site-planning.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('SitePlanning/Index')
            ->where('can.assignDesignPlan', false));
});

it('filters the site planning report to pipelines currently in Site Survey', function () {
    ['assignment' => $inSurvey] = pipelineWithSitePlanningAssignment(PipelineStatus::SiteSurvey);
    ['assignment' => $notYetSurveying] = pipelineWithSitePlanningAssignment(PipelineStatus::SiteSurveyPending);

    $infrastructureManager = User::factory()->infrastructureManager()->create();

    $this->actingAs($infrastructureManager)->get(route('site-planning.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('SitePlanning/Index')
            ->where('statusFilter', 'all')
            ->where('assignments.total', 2));

    $this->actingAs($infrastructureManager)->get(route('site-planning.index', ['status' => 'site_survey']))
        ->assertInertia(fn (Assert $page) => $page
            ->component('SitePlanning/Index')
            ->where('statusFilter', 'site_survey')
            ->where('assignments.total', 1)
            ->where('assignments.data.0.id', $inSurvey->id));

    expect($notYetSurveying)->not->toBeNull();
});

it('filters the index page by pipeline status, surveyor, design plan assignee, and survey status', function () {
    $infrastructureManager = User::factory()->infrastructureManager()->create();
    $surveyor = User::factory()->surveyor()->create(['full_name' => 'Filter Surveyor']);
    $designer = User::factory()->infraDesigner()->create(['full_name' => 'Filter Designer']);

    ['pipeline' => $matchingPipeline, 'assignment' => $matchingAssignment] = pipelineWithSitePlanningAssignment(PipelineStatus::SiteSurvey);
    $matchingPipeline->siteSurvey->update(['surveyor_id' => $surveyor->id, 'survey_completed_at' => now()]);
    $matchingAssignment->update(['design_plan_assignee_id' => $designer->id]);

    pipelineWithSitePlanningAssignment(PipelineStatus::SiteSurveyPending);

    $this->actingAs($infrastructureManager)
        ->get(route('site-planning.index', [
            'pipeline_status' => PipelineStatus::SiteSurvey->value,
            'surveyor_id' => $surveyor->id,
            'design_plan_assignee_id' => $designer->id,
            'survey_status' => 'completed',
        ]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('SitePlanning/Index')
            ->has('assignments.data', 1)
            ->where('assignments.data.0.id', $matchingAssignment->id)
            ->where('filters.pipeline_status', PipelineStatus::SiteSurvey->value)
        );
});

it('exports a CSV report of Site Planning records currently in the Site Survey stage', function () {
    $infrastructureManager = User::factory()->infrastructureManager()->create();
    $surveyor = User::factory()->surveyor()->create(['full_name' => 'Report Surveyor']);

    ['pipeline' => $inStage] = pipelineWithSitePlanningAssignment(PipelineStatus::SiteSurvey);
    $inStage->siteSurvey->update(['surveyor_id' => $surveyor->id]);

    pipelineWithSitePlanningAssignment(PipelineStatus::SiteSurveyPending);

    ['pipeline' => $completed] = pipelineWithSitePlanningAssignment(PipelineStatus::Commercials);

    $response = $this->actingAs($infrastructureManager)->get(route('site-planning.reports.site-survey'));

    $response->assertSuccessful();
    $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

    $csv = $response->streamedContent();
    expect($csv)
        ->toContain('Report Surveyor')
        ->toContain($inStage->pipeline_id)
        ->not->toContain($completed->pipeline_id);

    expect(substr_count($csv, "\n"))->toBe(3); // header + 2 in-stage rows
});

it('excludes Manage-only holders (Infrastructure Manager/Specialist) from the design plan designer list, keeping Infra Designer', function () {
    ['assignment' => $assignment] = pipelineWithSitePlanningAssignment();
    $infrastructureManager = User::factory()->infrastructureManager()->create();
    $designer = User::factory()->infraDesigner()->create();

    $this->actingAs($infrastructureManager)->get(route('site-planning.show', $assignment))
        ->assertInertia(fn (Assert $page) => $page
            ->component('SitePlanning/Show')
            ->where('designers', fn ($designers) => collect($designers)->pluck('id')->contains($designer->id)
                && ! collect($designers)->pluck('id')->contains($infrastructureManager->id)));
});

it('excludes an inactive Infra Designer from the design plan designer list and rejects assigning them', function () {
    ['assignment' => $assignment] = pipelineWithSitePlanningAssignment();
    $infrastructureManager = User::factory()->infrastructureManager()->create();
    $activeDesigner = User::factory()->infraDesigner()->create();
    $inactiveDesigner = User::factory()->infraDesigner()->inactive()->create();

    $this->actingAs($infrastructureManager)->get(route('site-planning.show', $assignment))
        ->assertInertia(fn (Assert $page) => $page
            ->component('SitePlanning/Show')
            ->where('designers', fn ($designers) => collect($designers)->pluck('id')->contains($activeDesigner->id)
                && ! collect($designers)->pluck('id')->contains($inactiveDesigner->id)));

    $this->actingAs($infrastructureManager)
        ->patch(route('site-planning.design-plan.assign', $assignment), [
            'design_plan_assignee_id' => $inactiveDesigner->id,
        ])
        ->assertStatus(422);

    expect($assignment->fresh()->design_plan_assignee_id)->toBeNull();
});

it('lets a user with only the view site planning permission list and view assignments, read-only', function () {
    ['assignment' => $assignment] = pipelineWithSitePlanningAssignment();
    $viewer = userScopedToSitePlanning([SitePlanningActionPermission::ListAssignments]);

    $this->actingAs($viewer)->get(route('site-planning.index'))
        ->assertInertia(fn (Assert $page) => $page->component('SitePlanning/Index'));

    $this->actingAs($viewer)->get(route('site-planning.show', $assignment))
        ->assertInertia(fn (Assert $page) => $page
            ->component('SitePlanning/Show')
            ->where('can.manageAssignment', false)
            ->where('can.manageDesignPlan', false)
            ->where('can.assignDesignPlan', false));
});

// ─── Assignment creation timing ───────────────────────────────────────────────

it('creates a site planning assignment the moment a pipeline moves to site survey pending, before any surveyor is scheduled', function () {
    $manager = User::factory()->projectManager()->create();
    $saq = User::factory()->saqSpecialist()->create();
    $address = Address::create(['city' => 'Manila']);
    $siteProfile = SiteProfile::create(['name' => 'Test Site', 'type' => SiteProfileType::Condo, 'address_id' => $address->id]);
    $pipeline = Pipeline::create([
        'name' => 'Test Pipeline',
        'pipeline_id' => nextPipelineId(),
        'project_manager_id' => $manager->id,
        'assigned_saq_id' => $saq->id,
        'site_profile_id' => $siteProfile->id,
        'status' => PipelineStatus::Prospecting,
    ]);

    expect(SitePlanningAssignment::where('pipeline_id', $pipeline->id)->exists())->toBeFalse();

    $this->actingAs($saq)
        ->post(route('pipelines.move-to-site-survey-pending', $pipeline))
        ->assertRedirect();

    $assignment = SitePlanningAssignment::where('pipeline_id', $pipeline->id)->first();

    expect($assignment)->not->toBeNull()
        ->and($assignment->site_survey_id)->not->toBeNull()
        ->and($assignment->siteSurvey->surveyor_id)->toBeNull();
});

// ─── Scheduling ────────────────────────────────────────────────────────────────

it('lets infrastructure manager schedule a site survey and set the type', function () {
    ['pipeline' => $pipeline, 'assignment' => $assignment] = pipelineWithSitePlanningAssignment();
    $infrastructureManager = User::factory()->infrastructureManager()->create();
    $surveyor = User::factory()->surveyor()->create();
    $surveyDate = now()->toDateString();

    $this->actingAs($infrastructureManager)
        ->patch(route('site-planning.schedule', $assignment), [
            'surveyor_id' => $surveyor->id,
            'survey_date' => $surveyDate,
            'design_plan_type' => 'FIBER',
        ])
        ->assertRedirect(route('site-planning.show', $assignment));

    $pipeline->refresh();

    expect($pipeline->status)->toBe(PipelineStatus::SiteSurvey)
        ->and($pipeline->siteSurvey->surveyor_id)->toBe($surveyor->id)
        ->and($pipeline->siteSurvey->survey_date->toDateString())->toBe($surveyDate)
        ->and($assignment->fresh()->design_plan_type)->toBe('FIBER');
});

it('blocks an infrastructure specialist from scheduling a site survey, since AssignSiteSurveyor is Manager-only', function () {
    ['assignment' => $assignment] = pipelineWithSitePlanningAssignment();
    $infrastructureSpecialist = User::factory()->infrastructureSpecialist()->create();
    $surveyor = User::factory()->surveyor()->create();

    $this->actingAs($infrastructureSpecialist)
        ->patch(route('site-planning.schedule', $assignment), [
            'surveyor_id' => $surveyor->id,
            'survey_date' => now()->toDateString(),
            'design_plan_type' => 'FIBER',
        ])
        ->assertForbidden();
});

it('requires a design plan type when scheduling', function () {
    ['assignment' => $assignment] = pipelineWithSitePlanningAssignment();
    $infrastructureManager = User::factory()->infrastructureManager()->create();
    $surveyor = User::factory()->surveyor()->create();

    $this->actingAs($infrastructureManager)
        ->patch(route('site-planning.schedule', $assignment), [
            'surveyor_id' => $surveyor->id,
            'survey_date' => now()->toDateString(),
        ])
        ->assertSessionHasErrors('design_plan_type');
});

it('rejects an invalid design plan type when scheduling', function () {
    ['assignment' => $assignment] = pipelineWithSitePlanningAssignment();
    $infrastructureManager = User::factory()->infrastructureManager()->create();
    $surveyor = User::factory()->surveyor()->create();

    $this->actingAs($infrastructureManager)
        ->patch(route('site-planning.schedule', $assignment), [
            'surveyor_id' => $surveyor->id,
            'survey_date' => now()->toDateString(),
            'design_plan_type' => 'Bogus',
        ])
        ->assertSessionHasErrors('design_plan_type');
});

it('locks the design plan type once the checklist has been seeded', function () {
    ['pipeline' => $pipeline, 'assignment' => $assignment] = pipelineWithSitePlanningAssignment(PipelineStatus::SiteSurvey);
    $infrastructureManager = User::factory()->infrastructureManager()->create();
    $surveyor = User::factory()->surveyor()->create();
    $pipeline->siteSurvey->update(['surveyor_id' => $surveyor->id]);
    $assignment->update(['design_plan_type' => 'FIBER']);
    SitePlanningTaskSeeder::seedMissingFor($assignment);

    $this->actingAs($infrastructureManager)
        ->patch(route('site-planning.schedule', $assignment), [
            'surveyor_id' => $surveyor->id,
            'survey_date' => now()->toDateString(),
            'design_plan_type' => 'Residential',
        ])
        ->assertStatus(422);

    expect($assignment->fresh()->design_plan_type)->toBe('FIBER');
});

it('rejects a surveyor who cannot fill site surveys', function () {
    ['assignment' => $assignment] = pipelineWithSitePlanningAssignment();
    $infrastructureManager = User::factory()->infrastructureManager()->create();
    $saq = User::factory()->saqSpecialist()->create();

    $this->actingAs($infrastructureManager)
        ->patch(route('site-planning.schedule', $assignment), [
            'surveyor_id' => $saq->id,
            'survey_date' => now()->toDateString(),
            'design_plan_type' => 'FIBER',
        ])
        ->assertStatus(422);
});

it('blocks a user without schedule permission from scheduling', function () {
    ['assignment' => $assignment] = pipelineWithSitePlanningAssignment();
    $surveyor = User::factory()->surveyor()->create();
    $userWithoutPermission = User::factory()->create();

    $this->actingAs($userWithoutPermission)
        ->patch(route('site-planning.schedule', $assignment), [
            'surveyor_id' => $surveyor->id,
            'survey_date' => now()->toDateString(),
            'design_plan_type' => 'FIBER',
        ])
        ->assertForbidden();
});

it('blocks scheduling once the pipeline has moved past site survey', function () {
    ['assignment' => $assignment] = pipelineWithSitePlanningAssignment(PipelineStatus::Commercials);
    $infrastructureManager = User::factory()->infrastructureManager()->create();
    $surveyor = User::factory()->surveyor()->create();

    $this->actingAs($infrastructureManager)
        ->patch(route('site-planning.schedule', $assignment), [
            'surveyor_id' => $surveyor->id,
            'survey_date' => now()->toDateString(),
            'design_plan_type' => 'FIBER',
        ])
        ->assertForbidden();
});

it('rejects a past survey date', function () {
    ['assignment' => $assignment] = pipelineWithSitePlanningAssignment();
    $infrastructureManager = User::factory()->infrastructureManager()->create();
    $surveyor = User::factory()->surveyor()->create();

    $this->actingAs($infrastructureManager)
        ->patch(route('site-planning.schedule', $assignment), [
            'surveyor_id' => $surveyor->id,
            'survey_date' => now()->subDay()->toDateString(),
            'design_plan_type' => 'FIBER',
        ])
        ->assertSessionHasErrors('survey_date');
});

it('rejects scheduling remarks over 2000 characters', function () {
    ['assignment' => $assignment] = pipelineWithSitePlanningAssignment();
    $infrastructureManager = User::factory()->infrastructureManager()->create();
    $surveyor = User::factory()->surveyor()->create();

    $this->actingAs($infrastructureManager)
        ->patch(route('site-planning.schedule', $assignment), [
            'surveyor_id' => $surveyor->id,
            'survey_date' => now()->toDateString(),
            'design_plan_type' => 'FIBER',
            'remarks' => str_repeat('a', 2001),
        ])
        ->assertSessionHasErrors('remarks');
});

it('stores schedule documents on the configured document disk', function () {
    config(['filesystems.documents_disk' => 's3']);
    Storage::fake('s3');

    ['assignment' => $assignment] = pipelineWithSitePlanningAssignment();
    $infrastructureManager = User::factory()->infrastructureManager()->create();
    $surveyor = User::factory()->surveyor()->create();

    $this->actingAs($infrastructureManager)
        ->patch(route('site-planning.schedule', $assignment), [
            'surveyor_id' => $surveyor->id,
            'survey_date' => now()->toDateString(),
            'design_plan_type' => 'FIBER',
            'files' => [UploadedFile::fake()->image('roof.jpg')],
        ])
        ->assertRedirect();

    $document = $assignment->fresh()->documents()->first();

    expect($document)->not->toBeNull()
        ->and(Storage::disk('s3')->exists($document->file_path))->toBeTrue();
});

// ─── Inspection form ──────────────────────────────────────────────────────────

it('lets the assigned surveyor save inspection findings', function () {
    ['pipeline' => $pipeline, 'assignment' => $assignment] = pipelineWithSitePlanningAssignment(PipelineStatus::SiteSurvey);
    $surveyor = User::factory()->surveyor()->create();
    $pipeline->siteSurvey->update(['surveyor_id' => $surveyor->id]);

    $this->actingAs($surveyor)
        ->post(route('site-planning.inspection.save', $assignment), [
            'floors_surveyed' => 12,
        ])
        ->assertRedirect(route('site-planning.show', $assignment));

    expect($pipeline->fresh()->siteSurvey->floors_surveyed)->toBe(12);
});

it('serves the declared tower totals to a towered assignment\'s survey form', function () {
    ['pipeline' => $pipeline, 'assignment' => $assignment] = pipelineWithSitePlanningAssignment(PipelineStatus::SiteSurvey);
    $surveyor = User::factory()->surveyor()->create();
    $pipeline->siteSurvey->update(['surveyor_id' => $surveyor->id]);
    $tower = Tower::create(['site_profile_id' => $pipeline->site_profile_id, 'name' => 'Tower A', 'total_units' => 100, 'total_floors' => 10]);
    $assignment->update(['tower_id' => $tower->id]);

    $this->actingAs($surveyor)->get(route('site-planning.show', $assignment))
        ->assertInertia(fn (Assert $page) => $page
            ->component('SitePlanning/Show')
            ->where('assignment.tower.total_units', 100)
            ->where('assignment.tower.total_floors', 10));
});

it('shows a readable error when a provider is added without a name', function () {
    ['pipeline' => $pipeline, 'assignment' => $assignment] = pipelineWithSitePlanningAssignment(PipelineStatus::SiteSurvey);
    $surveyor = User::factory()->surveyor()->create();
    $pipeline->siteSurvey->update(['surveyor_id' => $surveyor->id]);

    $this->actingAs($surveyor)
        ->post(route('site-planning.inspection.save', $assignment), [
            'providers' => [['provider_name' => '']],
        ])
        ->assertSessionHasErrors(['providers.0.provider_name' => 'Provider Name is required.']);
});

it('rejects a non-numeric Telco Room Fee', function () {
    ['pipeline' => $pipeline, 'assignment' => $assignment] = pipelineWithSitePlanningAssignment(PipelineStatus::SiteSurvey);
    $surveyor = User::factory()->surveyor()->create();
    $pipeline->siteSurvey->update(['surveyor_id' => $surveyor->id]);

    $this->actingAs($surveyor)
        ->post(route('site-planning.inspection.save', $assignment), [
            'telco_room_fee' => 'not-a-number',
        ])
        ->assertSessionHasErrors('telco_room_fee');

    expect($pipeline->fresh()->siteSurvey->telco_room_fee)->toBeNull();
});

it('accepts but does not persist Telco Room Fee submitted from the Infra-side inspection form', function () {
    ['pipeline' => $pipeline, 'assignment' => $assignment] = pipelineWithSitePlanningAssignment(PipelineStatus::SiteSurvey);
    $surveyor = User::factory()->surveyor()->create();
    $pipeline->siteSurvey->update(['surveyor_id' => $surveyor->id, 'telco_room_fee' => 3000]);

    // Telco Room Fee is SAQ-owned data (moved off the Infra side per Tina's request) — the
    // inspection form no longer exposes a control for it, and even a raw request carrying the
    // field validates fine but is silently ignored rather than overwriting the SAQ-entered value.
    $this->actingAs($surveyor)
        ->post(route('site-planning.inspection.save', $assignment), [
            'telco_room_fee' => 5000,
        ])
        ->assertSessionDoesntHaveErrors('telco_room_fee');

    expect($pipeline->fresh()->siteSurvey->telco_room_fee)->toBe('3000');
});

it('lets the assigned SAQ specialist set Telco Room Fee via survey findings during Prospecting', function () {
    $manager = User::factory()->projectManager()->create();
    $saq = User::factory()->saqSpecialist()->create();
    $address = Address::create(['city' => 'Manila']);
    $siteProfile = SiteProfile::create(['name' => 'SAQ Fee Site', 'type' => SiteProfileType::Condo, 'address_id' => $address->id]);
    $pipeline = Pipeline::create([
        'name' => 'SAQ Fee Site',
        'pipeline_id' => nextPipelineId(),
        'project_manager_id' => $manager->id,
        'assigned_saq_id' => $saq->id,
        'site_profile_id' => $siteProfile->id,
        'status' => PipelineStatus::Prospecting,
    ]);

    $this->actingAs($saq)
        ->post(route('pipelines.survey-findings', $pipeline), [
            'telco_room_fee' => 5000,
        ])
        ->assertSessionDoesntHaveErrors('telco_room_fee');

    expect($pipeline->fresh()->siteSurvey->telco_room_fee)->toBe('5000');
});

it('blocks a different surveyor from saving inspection findings', function () {
    ['pipeline' => $pipeline, 'assignment' => $assignment] = pipelineWithSitePlanningAssignment(PipelineStatus::SiteSurvey);
    $assignedSurveyor = User::factory()->surveyor()->create();
    $otherSurveyor = User::factory()->surveyor()->create();
    $pipeline->siteSurvey->update(['surveyor_id' => $assignedSurveyor->id]);

    $this->actingAs($otherSurveyor)
        ->post(route('site-planning.inspection.save', $assignment), [
            'floors_surveyed' => 12,
        ])
        ->assertForbidden();
});

it('submitting the inspection seeds the plans/drawing checklist for the type chosen at scheduling, instead of completing the survey', function () {
    ['pipeline' => $pipeline, 'assignment' => $assignment] = pipelineWithSitePlanningAssignment(PipelineStatus::SiteSurvey);
    $surveyor = User::factory()->surveyor()->create();
    $pipeline->siteSurvey->update(['surveyor_id' => $surveyor->id]);
    $assignment->update(['design_plan_type' => 'Share-Connect']);

    $this->actingAs($surveyor)
        ->post(route('site-planning.inspection.submit', $assignment), [
            'remarks' => 'All good.',
        ])
        ->assertRedirect(route('site-planning.show', $assignment));

    $pipeline->refresh();

    expect($assignment->fresh()->sitePlanningTasks()->count())->toBe(27)
        ->and($pipeline->siteSurvey->survey_completed_at)->toBeNull()
        ->and($pipeline->status)->toBe(PipelineStatus::SiteSurvey);
});

it('blocks submitting the inspection when a required Site Survey document is missing', function () {
    ['pipeline' => $pipeline, 'assignment' => $assignment] = pipelineWithSitePlanningAssignment(PipelineStatus::SiteSurvey);
    $surveyor = User::factory()->surveyor()->create();
    $pipeline->siteSurvey->update(['surveyor_id' => $surveyor->id]);
    $assignment->update(['design_plan_type' => 'FIBER']);
    StageDocumentRequirement::create([
        'module' => 'pipeline',
        'stage' => PipelineStatus::SiteSurvey->value,
        'name' => 'Site Photos',
        'is_active' => true,
        'is_required' => true,
        'sort_order' => 1,
    ]);

    $this->actingAs($surveyor)
        ->post(route('site-planning.inspection.submit', $assignment), [
            'remarks' => 'All good.',
        ])
        ->assertSessionHasErrors('required_documents');

    expect($assignment->fresh()->sitePlanningTasks()->count())->toBe(0);
});

it('allows submitting the inspection once the required Site Survey document is uploaded', function () {
    Storage::fake('public');
    ['pipeline' => $pipeline, 'assignment' => $assignment] = pipelineWithSitePlanningAssignment(PipelineStatus::SiteSurvey);
    $surveyor = User::factory()->surveyor()->create();
    $pipeline->siteSurvey->update(['surveyor_id' => $surveyor->id]);
    $assignment->update(['design_plan_type' => 'FIBER']);
    StageDocumentRequirement::create([
        'module' => 'pipeline',
        'stage' => PipelineStatus::SiteSurvey->value,
        'name' => 'Site Photos',
        'is_active' => true,
        'is_required' => true,
        'sort_order' => 1,
    ]);

    $this->actingAs($surveyor)
        ->post(route('site-planning.inspection.submit', $assignment), [
            'remarks' => 'All good.',
            'document_category' => 'Site Photos',
            'files' => [UploadedFile::fake()->image('site.jpg')],
        ])
        ->assertRedirect(route('site-planning.show', $assignment));

    expect($assignment->fresh()->sitePlanningTasks()->count())->toBe(29);
});

it('resubmitting the inspection does not duplicate the checklist', function () {
    ['pipeline' => $pipeline, 'assignment' => $assignment] = pipelineWithSitePlanningAssignment(PipelineStatus::SiteSurvey);
    $surveyor = User::factory()->surveyor()->create();
    $pipeline->siteSurvey->update(['surveyor_id' => $surveyor->id]);
    $assignment->update(['design_plan_type' => 'FIBER']);

    $this->actingAs($surveyor)->post(route('site-planning.inspection.submit', $assignment), []);
    $this->actingAs($surveyor)->post(route('site-planning.inspection.submit', $assignment), []);

    expect($assignment->fresh()->sitePlanningTasks()->count())->toBe(29);
});

it('does not seed a checklist if no type has been set yet (defensive; unreachable via the UI since scheduling requires a type)', function () {
    ['pipeline' => $pipeline, 'assignment' => $assignment] = pipelineWithSitePlanningAssignment(PipelineStatus::SiteSurvey);
    $surveyor = User::factory()->surveyor()->create();
    $pipeline->siteSurvey->update(['surveyor_id' => $surveyor->id]);

    $this->actingAs($surveyor)->post(route('site-planning.inspection.submit', $assignment), []);

    expect($assignment->fresh()->sitePlanningTasks()->count())->toBe(0);
});

it('blocks a different surveyor from submitting the inspection', function () {
    ['pipeline' => $pipeline, 'assignment' => $assignment] = pipelineWithSitePlanningAssignment(PipelineStatus::SiteSurvey);
    $assignedSurveyor = User::factory()->surveyor()->create();
    $otherSurveyor = User::factory()->surveyor()->create();
    $pipeline->siteSurvey->update(['surveyor_id' => $assignedSurveyor->id]);

    $this->actingAs($otherSurveyor)
        ->post(route('site-planning.inspection.submit', $assignment), [])
        ->assertForbidden();
});

// ─── Additional Task Fields on the Site Survey Form ────────────────────────

it('seeds the 10 Additional Task Fields on the Site Survey Form when it is opened', function () {
    ['pipeline' => $pipeline, 'assignment' => $assignment] = pipelineWithSitePlanningAssignment(PipelineStatus::SiteSurvey);
    $surveyor = User::factory()->surveyor()->create();
    $pipeline->siteSurvey->update(['surveyor_id' => $surveyor->id]);

    $this->actingAs($surveyor)
        ->get(route('site-planning.show', $assignment))
        ->assertInertia(fn (Assert $page) => $page
            ->component('SitePlanning/Show')
            ->has('assignment.site_survey.site_survey_tasks', 10)
            ->where('assignment.site_survey.site_survey_tasks.0.name', 'MDR Room Location')
            ->where('assignment.site_survey.site_survey_tasks.9.name', 'Others')
        );

    expect($pipeline->fresh()->siteSurvey->siteSurveyTasks()->count())->toBe(10);
});

it('does not duplicate Additional Task Fields on repeat visits to the Site Survey Form', function () {
    ['pipeline' => $pipeline, 'assignment' => $assignment] = pipelineWithSitePlanningAssignment(PipelineStatus::SiteSurvey);
    $surveyor = User::factory()->surveyor()->create();
    $pipeline->siteSurvey->update(['surveyor_id' => $surveyor->id]);

    $this->actingAs($surveyor)->get(route('site-planning.show', $assignment));
    $this->actingAs($surveyor)->get(route('site-planning.show', $assignment));

    expect($pipeline->fresh()->siteSurvey->siteSurveyTasks()->count())->toBe(10);
});

it('lets the assigned surveyor save notes on an Additional Task Field', function () {
    ['pipeline' => $pipeline, 'assignment' => $assignment] = pipelineWithSitePlanningAssignment(PipelineStatus::SiteSurvey);
    $surveyor = User::factory()->surveyor()->create();
    $pipeline->siteSurvey->update(['surveyor_id' => $surveyor->id]);
    SiteSurveyTaskSeeder::seedMissingFor($pipeline->siteSurvey);
    $task = $pipeline->siteSurvey->siteSurveyTasks()->first();

    $this->actingAs($surveyor)
        ->patch(route('site-planning.survey-tasks.update', [$assignment, $task]), [
            'notes' => 'Special instruction: use rear entrance.',
        ])
        ->assertRedirect(route('site-planning.show', $assignment));

    expect($task->fresh()->notes)->toBe('Special instruction: use rear entrance.');
});

it('blocks a different surveyor from saving notes on an Additional Task Field', function () {
    ['pipeline' => $pipeline, 'assignment' => $assignment] = pipelineWithSitePlanningAssignment(PipelineStatus::SiteSurvey);
    $assignedSurveyor = User::factory()->surveyor()->create();
    $otherSurveyor = User::factory()->surveyor()->create();
    $pipeline->siteSurvey->update(['surveyor_id' => $assignedSurveyor->id]);
    SiteSurveyTaskSeeder::seedMissingFor($pipeline->siteSurvey);
    $task = $pipeline->siteSurvey->siteSurveyTasks()->first();

    $this->actingAs($otherSurveyor)
        ->patch(route('site-planning.survey-tasks.update', [$assignment, $task]), ['notes' => 'nope'])
        ->assertForbidden();
});

it('rejects an Additional Task Field that does not belong to the assignment', function () {
    ['pipeline' => $pipeline, 'assignment' => $assignment] = pipelineWithSitePlanningAssignment(PipelineStatus::SiteSurvey);
    $surveyor = User::factory()->surveyor()->create();
    $pipeline->siteSurvey->update(['surveyor_id' => $surveyor->id]);

    ['pipeline' => $otherPipeline] = pipelineWithSitePlanningAssignment(PipelineStatus::SiteSurvey);
    SiteSurveyTaskSeeder::seedMissingFor($otherPipeline->siteSurvey);
    $foreignTask = $otherPipeline->siteSurvey->siteSurveyTasks()->first();

    $this->actingAs($surveyor)
        ->patch(route('site-planning.survey-tasks.update', [$assignment, $foreignTask]), ['notes' => 'nope'])
        ->assertForbidden();
});

it('lets the assigned surveyor upload, replace, and delete a document on an Additional Task Field', function () {
    ['pipeline' => $pipeline, 'assignment' => $assignment] = pipelineWithSitePlanningAssignment(PipelineStatus::SiteSurvey);
    $surveyor = User::factory()->surveyor()->create();
    $pipeline->siteSurvey->update(['surveyor_id' => $surveyor->id]);
    SiteSurveyTaskSeeder::seedMissingFor($pipeline->siteSurvey);
    $task = $pipeline->siteSurvey->siteSurveyTasks()->first();

    Storage::fake('public');

    $this->actingAs($surveyor)
        ->post(route('site-planning.survey-tasks.documents.store', [$assignment, $task]), [
            'files' => [UploadedFile::fake()->image('mdr-room.jpg')],
        ])
        ->assertRedirect(route('site-planning.show', $assignment));

    $document = $task->fresh()->documents()->first();
    expect($document)->not->toBeNull();

    $this->actingAs($surveyor)
        ->post(route('site-planning.survey-tasks.documents.replace', [$assignment, $task, $document]), [
            'file' => UploadedFile::fake()->image('mdr-room-v2.jpg'),
        ])
        ->assertRedirect(route('site-planning.show', $assignment));

    expect($task->fresh()->documents()->count())->toBe(1);
    $replacement = $task->fresh()->documents()->first();

    $this->actingAs($surveyor)
        ->delete(route('site-planning.survey-tasks.documents.delete', [$assignment, $task, $replacement]))
        ->assertRedirect(route('site-planning.show', $assignment));

    expect($task->fresh()->documents()->count())->toBe(0);
});

// ─── Plans/Drawing checklist — worked by any Infra Designer, open access ──────────

it('only lets the assigned infra designer manage the plans/drawing checklist, and nobody while unassigned', function () {
    ['assignment' => $assignment] = pipelineWithSitePlanningAssignment(PipelineStatus::SiteSurvey);
    $assignment->update(['design_plan_type' => 'FIBER']);
    SitePlanningTaskSeeder::seedMissingFor($assignment);
    $assignedDesigner = User::factory()->infraDesigner()->create();
    $otherDesigner = User::factory()->infraDesigner()->create();
    $task = $assignment->fresh()->sitePlanningTasks()->first();

    $this->actingAs($assignedDesigner)
        ->patch(route('site-planning.design-plan.tasks.update', [$assignment, $task]), ['status' => 'In-Progress'])
        ->assertForbidden();

    $assignment->update(['design_plan_assignee_id' => $assignedDesigner->id]);

    $this->actingAs($otherDesigner)
        ->patch(route('site-planning.design-plan.tasks.update', [$assignment, $task]), ['status' => 'Done'])
        ->assertForbidden();

    $this->actingAs($assignedDesigner)
        ->patch(route('site-planning.design-plan.tasks.update', [$assignment, $task]), ['status' => 'Done'])
        ->assertRedirect(route('site-planning.show', $assignment));

    expect($task->fresh()->status)->toBe(IspTaskStatus::Done);
});

it('blocks a user without the manage plans/drawing permission from updating a task', function () {
    ['assignment' => $assignment] = pipelineWithSitePlanningAssignment(PipelineStatus::SiteSurvey);
    $assignment->update(['design_plan_type' => 'FIBER']);
    SitePlanningTaskSeeder::seedMissingFor($assignment);
    $task = $assignment->sitePlanningTasks()->first();
    $user = userScopedToSitePlanning();

    $this->actingAs($user)
        ->patch(route('site-planning.design-plan.tasks.update', [$assignment, $task]), ['status' => 'Done'])
        ->assertForbidden();
});

it('also lets an infrastructure manager manage the plans/drawing checklist, covering the work until infra designer users exist', function () {
    ['assignment' => $assignment] = pipelineWithSitePlanningAssignment(PipelineStatus::SiteSurvey);
    $assignment->update(['design_plan_type' => 'FIBER']);
    SitePlanningTaskSeeder::seedMissingFor($assignment);
    $task = $assignment->sitePlanningTasks()->first();
    $infrastructureManager = User::factory()->infrastructureManager()->create();

    $this->actingAs($infrastructureManager)
        ->patch(route('site-planning.design-plan.tasks.update', [$assignment, $task]), ['status' => 'Done'])
        ->assertRedirect(route('site-planning.show', $assignment));

    expect($task->fresh()->status)->toBe(IspTaskStatus::Done);
});

// ─── Assigning the design plan — locks the checklist to that Infra Designer ────────

it('lets an infrastructure manager assign an infra designer to the design plan', function () {
    Notification::fake();

    ['assignment' => $assignment] = pipelineWithSitePlanningAssignment(PipelineStatus::SiteSurvey);
    $infrastructureManager = User::factory()->infrastructureManager()->create();
    $designer = User::factory()->infraDesigner()->create();

    $this->actingAs($infrastructureManager)
        ->patch(route('site-planning.design-plan.assign', $assignment), [
            'design_plan_assignee_id' => $designer->id,
        ])
        ->assertRedirect(route('site-planning.show', $assignment));

    expect($assignment->fresh()->design_plan_assignee_id)->toBe($designer->id);

    Notification::assertSentTo(
        $designer,
        PipelineNotification::class,
        fn (PipelineNotification $n): bool => $n->type === NotificationType::DesignPlanAssigned
    );
});

it('rejects an assignee who cannot manage plans/drawing', function () {
    ['assignment' => $assignment] = pipelineWithSitePlanningAssignment(PipelineStatus::SiteSurvey);
    $infrastructureManager = User::factory()->infrastructureManager()->create();
    $surveyor = User::factory()->surveyor()->create();

    $this->actingAs($infrastructureManager)
        ->patch(route('site-planning.design-plan.assign', $assignment), [
            'design_plan_assignee_id' => $surveyor->id,
        ])
        ->assertStatus(422);

    expect($assignment->fresh()->design_plan_assignee_id)->toBeNull();
});

it('rejects an infrastructure manager as a design plan assignee, since Manage does not imply DesignPlan', function () {
    ['assignment' => $assignment] = pipelineWithSitePlanningAssignment(PipelineStatus::SiteSurvey);
    $assigner = User::factory()->infrastructureManager()->create();
    $otherInfrastructureManager = User::factory()->infrastructureManager()->create();

    $this->actingAs($assigner)
        ->patch(route('site-planning.design-plan.assign', $assignment), [
            'design_plan_assignee_id' => $otherInfrastructureManager->id,
        ])
        ->assertStatus(422);

    expect($assignment->fresh()->design_plan_assignee_id)->toBeNull();
});

it('blocks reassigning the design plan once a task has moved past Pending', function () {
    ['assignment' => $assignment] = pipelineWithSitePlanningAssignment(PipelineStatus::SiteSurvey);
    $assignment->update(['design_plan_type' => 'FIBER']);
    SitePlanningTaskSeeder::seedMissingFor($assignment);
    $infrastructureManager = User::factory()->infrastructureManager()->create();
    $originalDesigner = User::factory()->infraDesigner()->create();
    $newDesigner = User::factory()->infraDesigner()->create();
    $assignment->update(['design_plan_assignee_id' => $originalDesigner->id]);
    $assignment->sitePlanningTasks()->first()->update(['status' => 'In-Progress', 'status_changed_at' => now()]);

    $this->actingAs($infrastructureManager)
        ->patch(route('site-planning.design-plan.assign', $assignment), [
            'design_plan_assignee_id' => $newDesigner->id,
        ])
        ->assertSessionHasErrors('design_plan_assignee_id');

    expect($assignment->fresh()->design_plan_assignee_id)->toBe($originalDesigner->id);
});

it('still allows assigning the design plan while every task remains Pending', function () {
    ['assignment' => $assignment] = pipelineWithSitePlanningAssignment(PipelineStatus::SiteSurvey);
    $assignment->update(['design_plan_type' => 'FIBER']);
    SitePlanningTaskSeeder::seedMissingFor($assignment);
    $infrastructureManager = User::factory()->infrastructureManager()->create();
    $designer = User::factory()->infraDesigner()->create();

    $this->actingAs($infrastructureManager)
        ->patch(route('site-planning.design-plan.assign', $assignment), [
            'design_plan_assignee_id' => $designer->id,
        ])
        ->assertRedirect(route('site-planning.show', $assignment));

    expect($assignment->fresh()->design_plan_assignee_id)->toBe($designer->id);
});

it('blocks a user without the assign design plan permission from assigning', function () {
    ['assignment' => $assignment] = pipelineWithSitePlanningAssignment(PipelineStatus::SiteSurvey);
    $designer = User::factory()->infraDesigner()->create();
    $user = userScopedToSitePlanning();

    $this->actingAs($user)
        ->patch(route('site-planning.design-plan.assign', $assignment), [
            'design_plan_assignee_id' => $designer->id,
        ])
        ->assertForbidden();
});

it('blocks a user who only holds Manage, not AssignDesignPlan, from assigning a designer', function () {
    ['assignment' => $assignment] = pipelineWithSitePlanningAssignment(PipelineStatus::SiteSurvey);
    $designer = User::factory()->infraDesigner()->create();
    $manageOnlyUser = userScopedToSitePlanning([SitePlanningActionPermission::Manage]);

    $this->actingAs($manageOnlyUser)
        ->patch(route('site-planning.design-plan.assign', $assignment), [
            'design_plan_assignee_id' => $designer->id,
        ])
        ->assertForbidden();
});

it('lets a user who only holds AssignDesignPlan, without Manage, assign a designer', function () {
    ['assignment' => $assignment] = pipelineWithSitePlanningAssignment(PipelineStatus::SiteSurvey);
    $designer = User::factory()->infraDesigner()->create();
    $assignOnlyUser = userScopedToSitePlanning([SitePlanningActionPermission::AssignDesignPlan]);

    $this->actingAs($assignOnlyUser)
        ->patch(route('site-planning.design-plan.assign', $assignment), [
            'design_plan_assignee_id' => $designer->id,
        ])
        ->assertRedirect(route('site-planning.show', $assignment));

    expect($assignment->fresh()->design_plan_assignee_id)->toBe($designer->id);
});

it('locks the plans/drawing checklist to the assigned designer once one has been set', function () {
    ['assignment' => $assignment] = pipelineWithSitePlanningAssignment(PipelineStatus::SiteSurvey);
    $assignment->update(['design_plan_type' => 'FIBER']);
    SitePlanningTaskSeeder::seedMissingFor($assignment);
    $task = $assignment->fresh()->sitePlanningTasks()->first();
    $assignedDesigner = User::factory()->infraDesigner()->create();
    $otherDesigner = User::factory()->infraDesigner()->create();
    $assignment->update(['design_plan_assignee_id' => $assignedDesigner->id]);

    $this->actingAs($otherDesigner)
        ->patch(route('site-planning.design-plan.tasks.update', [$assignment, $task]), ['status' => 'Done'])
        ->assertForbidden();

    $this->actingAs($assignedDesigner)
        ->patch(route('site-planning.design-plan.tasks.update', [$assignment, $task]), ['status' => 'Done'])
        ->assertRedirect(route('site-planning.show', $assignment));

    expect($task->fresh()->status)->toBe(IspTaskStatus::Done);
});

it('still lets an infrastructure manager manage the checklist once it is locked to a different assignee', function () {
    ['assignment' => $assignment] = pipelineWithSitePlanningAssignment(PipelineStatus::SiteSurvey);
    $assignment->update(['design_plan_type' => 'FIBER']);
    SitePlanningTaskSeeder::seedMissingFor($assignment);
    $task = $assignment->fresh()->sitePlanningTasks()->first();
    $assignedDesigner = User::factory()->infraDesigner()->create();
    $infrastructureManager = User::factory()->infrastructureManager()->create();
    $assignment->update(['design_plan_assignee_id' => $assignedDesigner->id]);

    $this->actingAs($infrastructureManager)
        ->patch(route('site-planning.design-plan.tasks.update', [$assignment, $task]), ['status' => 'Done'])
        ->assertRedirect(route('site-planning.show', $assignment));

    expect($task->fresh()->status)->toBe(IspTaskStatus::Done);
});

it('returns a files.0 session error when uploading a disallowed file type to a design plan task', function () {
    Storage::fake('public');

    ['assignment' => $assignment] = pipelineWithSitePlanningAssignment(PipelineStatus::SiteSurvey);
    $assignment->update(['design_plan_type' => 'FIBER']);
    SitePlanningTaskSeeder::seedMissingFor($assignment);
    $task = $assignment->fresh()->sitePlanningTasks()->first();
    $designer = User::factory()->infraDesigner()->create();
    $assignment->update(['design_plan_assignee_id' => $designer->id]);

    $this->actingAs($designer)
        ->post(route('site-planning.design-plan.tasks.documents.store', [$assignment, $task]), [
            'files' => [UploadedFile::fake()->create('malware.exe', 10)],
        ])
        ->assertSessionHasErrors('files.0');
});

it('returns a file session error when replacing a design plan task document with a disallowed file type', function () {
    Storage::fake('public');

    ['assignment' => $assignment] = pipelineWithSitePlanningAssignment(PipelineStatus::SiteSurvey);
    $assignment->update(['design_plan_type' => 'FIBER']);
    SitePlanningTaskSeeder::seedMissingFor($assignment);
    $task = $assignment->fresh()->sitePlanningTasks()->first();
    $designer = User::factory()->infraDesigner()->create();
    $assignment->update(['design_plan_assignee_id' => $designer->id]);

    $path = UploadedFile::fake()->image('plan.jpg')->store('site-planning-documents', 'public');
    $document = Document::create([
        'name' => 'plan.jpg',
        'type' => DocumentType::Photo,
        'filename' => 'plan.jpg',
        'file_path' => $path,
    ]);
    $task->documents()->attach($document->id, ['created_at' => now()]);

    $this->actingAs($designer)
        ->post(route('site-planning.design-plan.tasks.documents.replace', [$assignment, $task, $document]), [
            'file' => UploadedFile::fake()->create('malware.exe', 10),
        ])
        ->assertSessionHasErrors('file');
});

it('seeds the correct type-specific checklist, weight summing to 100, for each of the 4 types', function (string $type, int $expectedTaskCount) {
    ['pipeline' => $pipeline, 'assignment' => $assignment] = pipelineWithSitePlanningAssignment(PipelineStatus::SiteSurvey);
    $surveyor = User::factory()->surveyor()->create();
    $pipeline->siteSurvey->update(['surveyor_id' => $surveyor->id]);
    $assignment->update(['design_plan_type' => $type]);

    $this->actingAs($surveyor)
        ->post(route('site-planning.inspection.submit', $assignment), [])
        ->assertRedirect();

    $tasks = $assignment->fresh()->sitePlanningTasks;

    expect($tasks)->toHaveCount($expectedTaskCount)
        ->and((float) $tasks->sum('weight'))->toBe(100.0);
})->with([
    'FIBER' => ['FIBER', 29],
    'Residential' => ['Residential', 30],
    'Share-Connect' => ['Share-Connect', 27],
    'FAMILIA' => ['FAMILIA', 35],
]);

// ─── Completing the design plan — submits it for Infra Manager approval ──────────

it('submits the design plan for approval and notifies the infrastructure manager, without completing the survey', function () {
    Notification::fake();

    ['pipeline' => $pipeline, 'assignment' => $assignment] = pipelineWithSitePlanningAssignment(PipelineStatus::SiteSurvey);
    $surveyor = User::factory()->surveyor()->create();
    $designer = User::factory()->infraDesigner()->create();
    $assignment->update(['design_plan_assignee_id' => $designer->id]);
    $infrastructureManager = User::factory()->infrastructureManager()->create();
    $pipeline->siteSurvey->update(['surveyor_id' => $surveyor->id]);
    completeAllPlansDrawingTasks($assignment);
    $assignment->designPlanDocuments()->attach(
        Document::create(['name' => 'design-plan.pdf', 'type' => DocumentType::Document, 'filename' => 'design-plan.pdf', 'file_path' => 'x'])->id,
        ['created_at' => now()]
    );

    $this->actingAs($designer)
        ->patch(route('site-planning.design-plan.complete', $assignment))
        ->assertRedirect(route('site-planning.show', $assignment));

    expect($assignment->fresh()->design_plan_completed_at)->not->toBeNull()
        ->and($assignment->fresh()->design_plan_completed_by_id)->toBe($designer->id)
        ->and($pipeline->fresh()->siteSurvey->survey_completed_at)->toBeNull()
        ->and($pipeline->fresh()->status)->toBe(PipelineStatus::SiteSurvey);

    Notification::assertSentTo(
        $infrastructureManager,
        PipelineNotification::class,
        fn (PipelineNotification $n): bool => $n->type === NotificationType::DesignPlanPendingApproval
    );
    Notification::assertNotSentTo($designer, PipelineNotification::class);
});

it('blocks completing the design plan while tasks are incomplete', function () {
    ['pipeline' => $pipeline, 'assignment' => $assignment] = pipelineWithSitePlanningAssignment(PipelineStatus::SiteSurvey);
    $designer = User::factory()->infraDesigner()->create();
    $assignment->update(['design_plan_assignee_id' => $designer->id]);
    $assignment->update(['design_plan_type' => 'Residential']);
    SitePlanningTaskSeeder::seedMissingFor($assignment);

    $this->actingAs($designer)
        ->patch(route('site-planning.design-plan.complete', $assignment))
        ->assertStatus(422);

    expect($assignment->fresh()->design_plan_completed_at)->toBeNull();
});

it('blocks completing the design plan without a type set', function () {
    ['pipeline' => $pipeline, 'assignment' => $assignment] = pipelineWithSitePlanningAssignment(PipelineStatus::SiteSurvey);
    $designer = User::factory()->infraDesigner()->create();
    $assignment->update(['design_plan_assignee_id' => $designer->id]);
    SitePlanningTaskSeeder::seedMissingFor($assignment);

    $this->actingAs($designer)
        ->patch(route('site-planning.design-plan.complete', $assignment))
        ->assertStatus(422);

    expect($assignment->fresh()->design_plan_completed_at)->toBeNull();
});

it('blocks completing the design plan a second time once already submitted', function () {
    ['assignment' => $assignment] = pipelineWithSitePlanningAssignment(PipelineStatus::SiteSurvey);
    $designer = User::factory()->infraDesigner()->create();
    $assignment->update(['design_plan_assignee_id' => $designer->id]);
    completeAllPlansDrawingTasks($assignment);
    $assignment->update(['design_plan_completed_at' => now(), 'design_plan_completed_by_id' => $designer->id]);

    $this->actingAs($designer)
        ->patch(route('site-planning.design-plan.complete', $assignment))
        ->assertStatus(422);
});

it('blocks a user without the manage plans/drawing permission from completing the design plan', function () {
    ['assignment' => $assignment] = pipelineWithSitePlanningAssignment(PipelineStatus::SiteSurvey);
    completeAllPlansDrawingTasks($assignment);
    $user = userScopedToSitePlanning();

    $this->actingAs($user)
        ->patch(route('site-planning.design-plan.complete', $assignment))
        ->assertForbidden();
});

it('blocks completing the design plan with no Photos & Documents file attached', function () {
    ['pipeline' => $pipeline, 'assignment' => $assignment] = pipelineWithSitePlanningAssignment(PipelineStatus::SiteSurvey);
    $designer = User::factory()->infraDesigner()->create();
    $assignment->update(['design_plan_assignee_id' => $designer->id]);
    completeAllPlansDrawingTasks($assignment);

    $this->actingAs($designer)
        ->patch(route('site-planning.design-plan.complete', $assignment))
        ->assertStatus(422);

    expect($assignment->fresh()->design_plan_completed_at)->toBeNull();
});

it('lets an infra designer upload, replace, and delete a Design Plan Photos & Documents file, separate from task-level attachments', function () {
    ['pipeline' => $pipeline, 'assignment' => $assignment] = pipelineWithSitePlanningAssignment(PipelineStatus::SiteSurvey);
    $designer = User::factory()->infraDesigner()->create();
    $assignment->update(['design_plan_assignee_id' => $designer->id]);
    completeAllPlansDrawingTasks($assignment);

    Storage::fake('public');

    $this->actingAs($designer)
        ->post(route('site-planning.design-plan.documents.store', $assignment), [
            'files' => [UploadedFile::fake()->image('design-plan.jpg')],
        ])
        ->assertRedirect(route('site-planning.show', $assignment));

    $document = $assignment->fresh()->designPlanDocuments()->first();
    expect($document)->not->toBeNull()
        ->and($assignment->fresh()->documents()->count())->toBe(0)
        ->and($assignment->fresh()->sitePlanningTasks()->first()->documents()->count())->toBe(0);

    $this->actingAs($designer)
        ->patch(route('site-planning.design-plan.complete', $assignment))
        ->assertRedirect(route('site-planning.show', $assignment));
    expect($assignment->fresh()->design_plan_completed_at)->not->toBeNull();

    $this->actingAs($designer)
        ->post(route('site-planning.design-plan.documents.replace', [$assignment, $document]), [
            'file' => UploadedFile::fake()->image('design-plan-v2.jpg'),
        ])
        ->assertRedirect(route('site-planning.show', $assignment));

    expect($assignment->fresh()->designPlanDocuments()->count())->toBe(1);
    $replacement = $assignment->fresh()->designPlanDocuments()->first();

    $this->actingAs($designer)
        ->delete(route('site-planning.design-plan.documents.delete', [$assignment, $replacement]))
        ->assertRedirect(route('site-planning.show', $assignment));

    expect($assignment->fresh()->designPlanDocuments()->count())->toBe(0);
});

it('lets an infra designer save Design Plan remarks/notes', function () {
    ['pipeline' => $pipeline, 'assignment' => $assignment] = pipelineWithSitePlanningAssignment(PipelineStatus::SiteSurvey);
    $designer = User::factory()->infraDesigner()->create();
    $assignment->update(['design_plan_assignee_id' => $designer->id]);

    $this->actingAs($designer)
        ->patch(route('site-planning.design-plan.remarks.update', $assignment), [
            'design_plan_remarks' => 'Escalated to Infra Manager for a site revisit.',
        ])
        ->assertRedirect(route('site-planning.show', $assignment));

    expect($assignment->fresh()->design_plan_remarks)->toBe('Escalated to Infra Manager for a site revisit.');
});

it('blocks a user without the manage plans/drawing permission from uploading a Design Plan document', function () {
    ['assignment' => $assignment] = pipelineWithSitePlanningAssignment(PipelineStatus::SiteSurvey);
    $user = userScopedToSitePlanning();

    Storage::fake('public');

    $this->actingAs($user)
        ->post(route('site-planning.design-plan.documents.store', $assignment), [
            'files' => [UploadedFile::fake()->image('design-plan.jpg')],
        ])
        ->assertForbidden();
});

it('accepts a 25MB zip archive in the design plan Photos & Documents section', function () {
    Storage::fake('public');
    ['assignment' => $assignment] = pipelineWithSitePlanningAssignment(PipelineStatus::SiteSurvey);
    $designer = User::factory()->infraDesigner()->create();
    $assignment->update(['design_plan_assignee_id' => $designer->id]);

    $this->actingAs($designer)
        ->post(route('site-planning.design-plan.documents.store', $assignment), [
            'files' => [UploadedFile::fake()->create('photos.zip', 25 * 1024, 'application/zip')],
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('site-planning.show', $assignment));

    expect($assignment->fresh()->designPlanDocuments()->count())->toBe(1);
});

// ─── Approving the design plan — the real survey-completion gate, Infra Manager only ──

function completeDesignPlanFor(SitePlanningAssignment $assignment, User $designer): void
{
    $assignment->update([
        'design_plan_completed_at' => now(),
        'design_plan_completed_by_id' => $designer->id,
    ]);
}

it('approves the design plan and advances once network feasibility is already done', function () {
    ['pipeline' => $pipeline, 'assignment' => $assignment] = pipelineWithSitePlanningAssignment(PipelineStatus::SiteSurvey);
    $surveyor = User::factory()->surveyor()->create();
    $designer = User::factory()->infraDesigner()->create();
    $infrastructureManager = User::factory()->infrastructureManager()->create();
    $pipeline->siteSurvey->update([
        'surveyor_id' => $surveyor->id,
        'network_feasibility_completed_at' => now(),
    ]);
    completeAllPlansDrawingTasks($assignment);
    completeDesignPlanFor($assignment, $designer);

    $this->actingAs($infrastructureManager)
        ->patch(route('site-planning.design-plan.approve', $assignment))
        ->assertRedirect(route('site-planning.show', $assignment));

    $pipeline->refresh();

    expect($assignment->fresh()->design_plan_approved_at)->not->toBeNull()
        ->and($assignment->fresh()->design_plan_approved_by_id)->toBe($infrastructureManager->id)
        ->and($pipeline->siteSurvey->survey_completed_at)->not->toBeNull()
        ->and($pipeline->siteSurvey->survey_completed_by_id)->toBe($surveyor->id)
        ->and($pipeline->status)->toBe(PipelineStatus::Commercials);
});

it('lets the infrastructure manager send a submitted design plan back for revision with remarks, retaining its data', function () {
    Notification::fake();
    ['assignment' => $assignment] = pipelineWithSitePlanningAssignment(PipelineStatus::SiteSurvey);
    $designer = User::factory()->infraDesigner()->create();
    $infrastructureManager = User::factory()->infrastructureManager()->create();
    completeAllPlansDrawingTasks($assignment);
    completeDesignPlanFor($assignment, $designer);
    $assignment->update(['design_plan_assignee_id' => $designer->id]);
    $assignment->designPlanDocuments()->attach(
        Document::create(['name' => 'plan.pdf', 'type' => DocumentType::Document, 'filename' => 'plan.pdf', 'file_path' => 'x'])->id,
        ['created_at' => now()]
    );

    $this->actingAs($infrastructureManager)
        ->patch(route('site-planning.design-plan.request-revision', $assignment), [])
        ->assertSessionHasErrors('remarks');

    $this->actingAs($infrastructureManager)
        ->patch(route('site-planning.design-plan.request-revision', $assignment), ['remarks' => 'Riser diagram is missing.'])
        ->assertRedirect(route('site-planning.show', $assignment));

    $assignment->refresh();
    expect($assignment->design_plan_completed_at)->toBeNull()
        ->and($assignment->design_plan_revision_remarks)->toBe('Riser diagram is missing.')
        ->and($assignment->design_plan_assignee_id)->toBe($designer->id)
        ->and($assignment->sitePlanningTasks()->where('status', '!=', 'Done')->exists())->toBeFalse();

    Notification::assertSentTo($designer, PipelineNotification::class);

    $this->actingAs($designer)
        ->patch(route('site-planning.design-plan.complete', $assignment))
        ->assertRedirect(route('site-planning.show', $assignment));

    expect($assignment->fresh()->design_plan_revision_remarks)->toBeNull()
        ->and($assignment->fresh()->design_plan_completed_at)->not->toBeNull();
});

it('blocks requesting a revision from a user who cannot approve design plans', function () {
    ['assignment' => $assignment] = pipelineWithSitePlanningAssignment(PipelineStatus::SiteSurvey);
    $designer = User::factory()->infraDesigner()->create();
    completeAllPlansDrawingTasks($assignment);
    completeDesignPlanFor($assignment, $designer);

    $this->actingAs($designer)
        ->patch(route('site-planning.design-plan.request-revision', $assignment), ['remarks' => 'x'])
        ->assertForbidden();
});

it('approves the design plan without advancing while network feasibility is still pending', function () {
    ['pipeline' => $pipeline, 'assignment' => $assignment] = pipelineWithSitePlanningAssignment(PipelineStatus::SiteSurvey);
    $surveyor = User::factory()->surveyor()->create();
    $designer = User::factory()->infraDesigner()->create();
    $infrastructureManager = User::factory()->infrastructureManager()->create();
    $pipeline->siteSurvey->update(['surveyor_id' => $surveyor->id]);
    completeAllPlansDrawingTasks($assignment);
    completeDesignPlanFor($assignment, $designer);

    $this->actingAs($infrastructureManager)
        ->patch(route('site-planning.design-plan.approve', $assignment))
        ->assertRedirect(route('site-planning.show', $assignment));

    $pipeline->refresh();

    expect($pipeline->siteSurvey->survey_completed_at)->not->toBeNull()
        ->and($pipeline->status)->toBe(PipelineStatus::SiteSurvey);
});

it('records the surveyor as who completed the survey, even though an infrastructure manager approved it', function () {
    ['pipeline' => $pipeline, 'assignment' => $assignment] = pipelineWithSitePlanningAssignment(PipelineStatus::SiteSurvey);
    $surveyor = User::factory()->surveyor()->create();
    $designer = User::factory()->infraDesigner()->create();
    $infrastructureManager = User::factory()->infrastructureManager()->create();
    $pipeline->siteSurvey->update(['surveyor_id' => $surveyor->id]);
    completeAllPlansDrawingTasks($assignment);
    completeDesignPlanFor($assignment, $designer);

    $this->actingAs($infrastructureManager)->patch(route('site-planning.design-plan.approve', $assignment));

    expect($pipeline->fresh()->siteSurvey->survey_completed_by_id)->toBe($surveyor->id)
        ->and($pipeline->fresh()->siteSurvey->survey_completed_by_id)->not->toBe($infrastructureManager->id);
});

it('blocks approving a design plan that has not been submitted yet', function () {
    ['pipeline' => $pipeline, 'assignment' => $assignment] = pipelineWithSitePlanningAssignment(PipelineStatus::SiteSurvey);
    $infrastructureManager = User::factory()->infrastructureManager()->create();
    completeAllPlansDrawingTasks($assignment);

    $this->actingAs($infrastructureManager)
        ->patch(route('site-planning.design-plan.approve', $assignment))
        ->assertStatus(422);

    expect($pipeline->fresh()->siteSurvey->survey_completed_at)->toBeNull();
});

it('blocks approving the same design plan twice', function () {
    ['assignment' => $assignment] = pipelineWithSitePlanningAssignment(PipelineStatus::SiteSurvey);
    $designer = User::factory()->infraDesigner()->create();
    $infrastructureManager = User::factory()->infrastructureManager()->create();
    completeAllPlansDrawingTasks($assignment);
    completeDesignPlanFor($assignment, $designer);
    $assignment->update(['design_plan_approved_at' => now(), 'design_plan_approved_by_id' => $infrastructureManager->id]);

    $this->actingAs($infrastructureManager)
        ->patch(route('site-planning.design-plan.approve', $assignment))
        ->assertStatus(422);
});

it('blocks an infra designer without the approve permission from approving the design plan', function () {
    ['assignment' => $assignment] = pipelineWithSitePlanningAssignment(PipelineStatus::SiteSurvey);
    $designer = User::factory()->infraDesigner()->create();
    completeAllPlansDrawingTasks($assignment);
    completeDesignPlanFor($assignment, $designer);

    $this->actingAs($designer)
        ->patch(route('site-planning.design-plan.approve', $assignment))
        ->assertForbidden();
});

it('blocks an infrastructure specialist from approving the design plan', function () {
    ['assignment' => $assignment] = pipelineWithSitePlanningAssignment(PipelineStatus::SiteSurvey);
    $designer = User::factory()->infraDesigner()->create();
    $infrastructureSpecialist = User::factory()->infrastructureSpecialist()->create();
    completeAllPlansDrawingTasks($assignment);
    completeDesignPlanFor($assignment, $designer);

    $this->actingAs($infrastructureSpecialist)
        ->patch(route('site-planning.design-plan.approve', $assignment))
        ->assertForbidden();
});

// ─── Documents ─────────────────────────────────────────────────────────────────

it('detaches and deletes a document when no other references exist', function () {
    Storage::fake('public');

    ['assignment' => $assignment] = pipelineWithSitePlanningAssignment(PipelineStatus::SiteSurvey);
    $infrastructureManager = User::factory()->infrastructureManager()->create();

    $file = UploadedFile::fake()->image('site.jpg');
    $path = $file->store('site-planning-documents', 'public');
    $document = Document::create([
        'name' => 'site.jpg',
        'type' => DocumentType::Photo,
        'filename' => 'site.jpg',
        'file_path' => $path,
    ]);
    $assignment->documents()->attach($document->id, ['created_at' => now()]);

    $this->actingAs($infrastructureManager)
        ->delete(route('site-planning.documents.delete', [$assignment, $document]))
        ->assertRedirect(route('site-planning.show', $assignment));

    expect(Document::find($document->id))->toBeNull();
    Storage::disk('public')->assertMissing($path);
});

it('replaces a document with a new version instead of deleting the old one', function () {
    Storage::fake('public');

    ['assignment' => $assignment] = pipelineWithSitePlanningAssignment(PipelineStatus::SiteSurvey);
    $infrastructureManager = User::factory()->infrastructureManager()->create();

    $file = UploadedFile::fake()->image('site.jpg');
    $path = $file->store('site-planning-documents', 'public');
    $document = Document::create([
        'name' => 'site.jpg',
        'type' => DocumentType::Photo,
        'filename' => 'site.jpg',
        'file_path' => $path,
    ]);
    $assignment->documents()->attach($document->id, ['created_at' => now()]);

    $this->actingAs($infrastructureManager)
        ->post(route('site-planning.documents.replace', [$assignment, $document]), [
            'file' => UploadedFile::fake()->image('site-corrected.jpg'),
        ])
        ->assertRedirect(route('site-planning.show', $assignment));

    $current = $assignment->fresh()->documents()->first();
    expect($current->id)->not->toBe($document->id)
        ->and($current->version)->toBe(2);

    // The old version is superseded, not deleted — still recoverable via version history.
    expect($document->fresh()->superseded_at)->not->toBeNull();
    expect(Document::find($document->id))->not->toBeNull();
    Storage::disk('public')->assertExists($path);
});

it('denies a role without manageDocuments from replacing a document', function () {
    Storage::fake('public');

    ['assignment' => $assignment] = pipelineWithSitePlanningAssignment(PipelineStatus::SiteSurvey);
    $networkManager = User::factory()->networkManager()->create();

    $path = UploadedFile::fake()->image('site.jpg')->store('site-planning-documents', 'public');
    $document = Document::create([
        'name' => 'site.jpg',
        'type' => DocumentType::Photo,
        'filename' => 'site.jpg',
        'file_path' => $path,
    ]);
    $assignment->documents()->attach($document->id, ['created_at' => now()]);

    $this->actingAs($networkManager)
        ->post(route('site-planning.documents.replace', [$assignment, $document]), [
            'file' => UploadedFile::fake()->image('site-corrected.jpg'),
        ])
        ->assertForbidden();
});

it('lets the assigned surveyor delete their own uploaded Site Survey document', function () {
    Storage::fake('public');

    ['pipeline' => $pipeline, 'assignment' => $assignment] = pipelineWithSitePlanningAssignment(PipelineStatus::SiteSurvey);
    $surveyor = User::factory()->surveyor()->create();
    $pipeline->siteSurvey->update(['surveyor_id' => $surveyor->id]);

    $file = UploadedFile::fake()->image('site.jpg');
    $path = $file->store('site-planning-documents', 'public');
    $document = Document::create([
        'name' => 'site.jpg',
        'type' => DocumentType::Photo,
        'filename' => 'site.jpg',
        'file_path' => $path,
    ]);
    $assignment->documents()->attach($document->id, ['created_at' => now()]);

    $this->actingAs($surveyor)
        ->delete(route('site-planning.documents.delete', [$assignment, $document]))
        ->assertRedirect(route('site-planning.show', $assignment));

    expect(Document::find($document->id))->toBeNull();
});

it('blocks a surveyor not assigned to this Site Survey from deleting its documents', function () {
    Storage::fake('public');

    ['pipeline' => $pipeline, 'assignment' => $assignment] = pipelineWithSitePlanningAssignment(PipelineStatus::SiteSurvey);
    $assignedSurveyor = User::factory()->surveyor()->create();
    $otherSurveyor = User::factory()->surveyor()->create();
    $pipeline->siteSurvey->update(['surveyor_id' => $assignedSurveyor->id]);

    $file = UploadedFile::fake()->image('site.jpg');
    $path = $file->store('site-planning-documents', 'public');
    $document = Document::create([
        'name' => 'site.jpg',
        'type' => DocumentType::Photo,
        'filename' => 'site.jpg',
        'file_path' => $path,
    ]);
    $assignment->documents()->attach($document->id, ['created_at' => now()]);

    $this->actingAs($otherSurveyor)
        ->delete(route('site-planning.documents.delete', [$assignment, $document]))
        ->assertForbidden();
});

// ─── Persistent history ────────────────────────────────────────────────────────

it('keeps the site planning assignment visible after the pipeline advances into a project', function () {
    ['pipeline' => $pipeline, 'assignment' => $assignment] = pipelineWithSitePlanningAssignment(PipelineStatus::ReadyForDeployment);
    $infrastructureManager = User::factory()->infrastructureManager()->create();
    NetworkSiteAssignment::ensureFor($pipeline, $pipeline->siteSurvey);

    (new InitializeProject)->handle(new PipelineApproved($pipeline));

    expect($pipeline->fresh()->project)->not->toBeNull();

    $this->actingAs($infrastructureManager)
        ->get(route('site-planning.show', $assignment))
        ->assertSuccessful();

    $this->actingAs($infrastructureManager)
        ->get(route('site-planning.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('SitePlanning/Index')
            ->has('assignments.data', 1));
});

// ─── Declare towers ─────────────────────────────────────────────────────────

it('lets the assigned surveyor declare towers, reusing the existing assignment for the first tower', function () {
    ['pipeline' => $pipeline, 'assignment' => $assignment] = pipelineWithSitePlanningAssignment(PipelineStatus::SiteSurvey);
    $surveyor = User::factory()->surveyor()->create();
    $pipeline->siteSurvey->update(['surveyor_id' => $surveyor->id]);
    $assignment->update(['design_plan_type' => 'FIBER']);

    $this->actingAs($surveyor)
        ->post(route('site-planning.towers.declare', $assignment), [
            'towers' => [
                ['name' => 'Tower A', 'total_units' => 100, 'total_floors' => 10],
                ['name' => 'Tower B', 'total_units' => 80, 'total_floors' => 8],
            ],
        ])
        ->assertRedirect(route('site-planning.show', $assignment));

    $pipeline->refresh();
    $towers = Tower::where('site_profile_id', $pipeline->site_profile_id)->orderBy('name')->get();

    expect($towers)->toHaveCount(2)
        ->and($towers[0]->name)->toBe('Tower A')
        ->and($towers[0]->total_units)->toBe(100);

    // The original assignment/survey is reused for Tower A, not left dangling.
    $assignment->refresh();
    expect($assignment->tower_id)->toBe($towers[0]->id)
        ->and($assignment->siteSurvey->fresh()->tower_id)->toBe($towers[0]->id);

    // A brand new assignment/survey exists for Tower B, carrying the same surveyor forward —
    // without this, SitePlanningPolicy::fillSurvey() locks everyone out of the new tower (#bug
    // reported: "I have 2 towers but I cannot update tower B").
    $towerBAssignment = SitePlanningAssignment::where('pipeline_id', $pipeline->id)->where('tower_id', $towers[1]->id)->first();
    expect($towerBAssignment)->not->toBeNull()
        ->and($towerBAssignment->siteSurvey)->not->toBeNull()
        ->and($towerBAssignment->siteSurvey->pipeline_id)->toBe($pipeline->id)
        ->and($towerBAssignment->siteSurvey->surveyor_id)->toBe($surveyor->id)
        ->and($towerBAssignment->design_plan_type)->toBe('FIBER');

    // The surveyor can actually fill in Tower B's survey afterward — the real end-to-end check.
    $this->actingAs($surveyor)
        ->post(route('site-planning.inspection.save', $towerBAssignment), [
            'floors_surveyed' => 8,
        ])
        ->assertRedirect(route('site-planning.show', $towerBAssignment));

    expect($towerBAssignment->siteSurvey->fresh()->floors_surveyed)->toBe(8);

    // ...and can submit it through to the Plans/Drawing checklist — without design_plan_type
    // carried forward, SitePlanningTaskSeeder::seedMissingFor() silently no-ops here, leaving
    // tower B stuck on the inspection form forever (#bug reported: "tower b cannot proceed to
    // site planning").
    $this->actingAs($surveyor)
        ->post(route('site-planning.inspection.submit', $towerBAssignment))
        ->assertRedirect(route('site-planning.show', $towerBAssignment));

    expect($towerBAssignment->sitePlanningTasks()->count())->toBeGreaterThan(0);
});

it('blocks declaring towers a second time once a site already has them', function () {
    ['pipeline' => $pipeline, 'assignment' => $assignment] = pipelineWithSitePlanningAssignment(PipelineStatus::SiteSurvey);
    $surveyor = User::factory()->surveyor()->create();
    $pipeline->siteSurvey->update(['surveyor_id' => $surveyor->id]);
    Tower::create(['site_profile_id' => $pipeline->site_profile_id, 'name' => 'Existing Tower']);

    $this->actingAs($surveyor)
        ->post(route('site-planning.towers.declare', $assignment), [
            'towers' => [['name' => 'Tower A']],
        ])
        ->assertStatus(422);
});

it('blocks a different surveyor from declaring towers', function () {
    ['pipeline' => $pipeline, 'assignment' => $assignment] = pipelineWithSitePlanningAssignment(PipelineStatus::SiteSurvey);
    $surveyor = User::factory()->surveyor()->create();
    $otherSurveyor = User::factory()->surveyor()->create();
    $pipeline->siteSurvey->update(['surveyor_id' => $surveyor->id]);

    $this->actingAs($otherSurveyor)
        ->post(route('site-planning.towers.declare', $assignment), [
            'towers' => [['name' => 'Tower A']],
        ])
        ->assertForbidden();
});

it('shows which tower each assignment belongs to on the site planning list', function () {
    ['pipeline' => $pipeline, 'assignment' => $assignment] = pipelineWithSitePlanningAssignment(PipelineStatus::SiteSurvey);
    $surveyor = User::factory()->surveyor()->create();
    $pipeline->siteSurvey->update(['surveyor_id' => $surveyor->id]);

    $this->actingAs($surveyor)
        ->post(route('site-planning.towers.declare', $assignment), [
            'towers' => [
                ['name' => 'Tower A'],
                ['name' => 'Tower B'],
            ],
        ]);

    $infrastructureManager = User::factory()->infrastructureManager()->create();

    $this->actingAs($infrastructureManager)->get(route('site-planning.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('SitePlanning/Index')
            ->where('assignments.total', 2)
            ->where(
                'assignments.data',
                fn ($assignments) => collect($assignments)->pluck('tower.name')->sort()->values()->all() === ['Tower A', 'Tower B']
            ));
});

it('adds Tower and Pipeline Aging (Days) columns to the Site Survey report', function () {
    ['pipeline' => $pipeline, 'assignment' => $assignment] = pipelineWithSitePlanningAssignment(PipelineStatus::SiteSurvey);
    $pipeline->update(['endorsement_date' => now()->subDays(12)]);
    $tower = Tower::create(['site_profile_id' => $pipeline->site_profile_id, 'name' => 'Tower B']);
    $assignment->update(['tower_id' => $tower->id]);
    $manager = User::factory()->infrastructureManager()->create();

    $csv = $this->actingAs($manager)->get(route('site-planning.reports.site-survey'))->assertSuccessful()->streamedContent();
    $rows = array_map('str_getcsv', array_filter(explode("\n", $csv)));

    expect($rows[0])->toContain('Tower', 'Pipeline Aging (Days)')
        ->and($rows[1][array_search('Tower', $rows[0], true)])->toBe('Tower B')
        ->and($rows[1][array_search('Pipeline Aging (Days)', $rows[0], true)])->toBe('12');
});

it('filters Site Planning to design plans pending approval', function () {
    ['assignment' => $pending] = pipelineWithSitePlanningAssignment(PipelineStatus::SiteSurvey);
    ['assignment' => $other] = pipelineWithSitePlanningAssignment(PipelineStatus::SiteSurvey);
    ['assignment' => $approved] = pipelineWithSitePlanningAssignment(PipelineStatus::SiteSurvey);
    $pending->update(['design_plan_completed_at' => now()]);
    $approved->update(['design_plan_completed_at' => now(), 'design_plan_approved_at' => now()]);
    $manager = User::factory()->infrastructureManager()->create();

    $this->actingAs($manager)
        ->get(route('site-planning.index', ['design_plan_status' => 'for_approval']))
        ->assertInertia(fn ($page) => $page->has('assignments.data', 1)->where('assignments.data.0.id', $pending->id));
});

it('shows an infra designer only the design plans assigned to them', function () {
    ['assignment' => $mine] = pipelineWithSitePlanningAssignment(PipelineStatus::SiteSurvey);
    ['assignment' => $unassigned] = pipelineWithSitePlanningAssignment(PipelineStatus::SiteSurvey);
    ['assignment' => $theirs] = pipelineWithSitePlanningAssignment(PipelineStatus::SiteSurvey);
    $designer = User::factory()->infraDesigner()->create();
    $otherDesigner = User::factory()->infraDesigner()->create();
    $mine->update(['design_plan_assignee_id' => $designer->id]);
    $theirs->update(['design_plan_assignee_id' => $otherDesigner->id]);

    $this->actingAs($designer)
        ->get(route('site-planning.index'))
        ->assertInertia(fn ($page) => $page->has('assignments.data', 1)->where('assignments.data.0.id', $mine->id));

    $this->actingAs($designer)->get(route('site-planning.show', $theirs))->assertForbidden();
    $this->actingAs($designer)->get(route('site-planning.show', $mine))->assertSuccessful();

    $manager = User::factory()->infrastructureManager()->create();
    $this->actingAs($manager)
        ->get(route('site-planning.index'))
        ->assertInertia(fn ($page) => $page->has('assignments.data', 3));
});

it('lets the assigned surveyor and the infrastructure manager change the project type, but not a designer', function () {
    ['pipeline' => $pipeline, 'assignment' => $assignment] = pipelineWithSitePlanningAssignment(PipelineStatus::SiteSurvey);
    $surveyor = User::factory()->surveyor()->create();
    $pipeline->siteSurvey->update(['surveyor_id' => $surveyor->id]);
    $manager = User::factory()->infrastructureManager()->create();
    $designer = User::factory()->infraDesigner()->create();

    $this->actingAs($designer)
        ->patch(route('site-planning.project-type.update', $assignment), ['type' => 'School'])
        ->assertForbidden();

    $this->actingAs($surveyor)
        ->patch(route('site-planning.project-type.update', $assignment), ['type' => 'School'])
        ->assertRedirect(route('site-planning.show', $assignment));

    expect($pipeline->siteProfile->fresh()->type)->toBe(SiteProfileType::School);

    $this->actingAs($manager)
        ->patch(route('site-planning.project-type.update', $assignment), ['type' => 'Others'])
        ->assertSessionHasErrors('type_other');

    $this->actingAs($manager)
        ->patch(route('site-planning.project-type.update', $assignment), ['type' => 'Others', 'type_other' => 'Hospital'])
        ->assertRedirect();

    expect($pipeline->siteProfile->fresh()->type_other)->toBe('Hospital');
});

// ─── Design plan type reclassification ───────────────────────────

it('blocks a non-manager from changing the design plan type', function () {
    ['pipeline' => $pipeline, 'assignment' => $assignment] = pipelineWithSitePlanningAssignment(PipelineStatus::SiteSurvey);
    $surveyor = User::factory()->surveyor()->create();
    $pipeline->siteSurvey->update(['surveyor_id' => $surveyor->id]);
    completeAllPlansDrawingTasks($assignment, 'FAMILIA');

    $this->actingAs($surveyor)
        ->post(route('site-planning.design-plan-type.update', $assignment), ['design_plan_type' => 'Residential'])
        ->assertForbidden();

    expect($assignment->fresh()->design_plan_type)->toBe('FAMILIA');
});

it('rejects an unknown design plan type', function () {
    ['assignment' => $assignment] = pipelineWithSitePlanningAssignment(PipelineStatus::SiteSurvey);
    $manager = User::factory()->infrastructureManager()->create();
    completeAllPlansDrawingTasks($assignment, 'FAMILIA');

    $this->actingAs($manager)
        ->post(route('site-planning.design-plan-type.update', $assignment), ['design_plan_type' => 'Castle'])
        ->assertSessionHasErrors('design_plan_type');
});

it('changing to the same type leaves the checklist untouched', function () {
    ['assignment' => $assignment] = pipelineWithSitePlanningAssignment(PipelineStatus::SiteSurvey);
    $manager = User::factory()->infrastructureManager()->create();
    completeAllPlansDrawingTasks($assignment, 'FAMILIA');
    $taskIds = $assignment->sitePlanningTasks()->pluck('id')->sort()->values();

    $this->actingAs($manager)
        ->post(route('site-planning.design-plan-type.update', $assignment), ['design_plan_type' => 'FAMILIA'])
        ->assertRedirect(route('site-planning.show', $assignment));

    expect($assignment->sitePlanningTasks()->pluck('id')->sort()->values()->all())->toBe($taskIds->all());
});

it('rebuilds the Plans/Drawing checklist for the new type, carrying over matching tasks', function () {
    Storage::fake('public');
    ['assignment' => $assignment] = pipelineWithSitePlanningAssignment(PipelineStatus::SiteSurvey);
    $manager = User::factory()->infrastructureManager()->create();
    completeAllPlansDrawingTasks($assignment, 'FAMILIA');

    // "Initial Draft" section tasks are shared across every type — pick one, give it notes + a document.
    $shared = $assignment->sitePlanningTasks()->where('section', 'Initial Draft')->firstOrFail();
    $shared->update(['notes' => 'Carried note']);
    $document = Document::create(['name' => 'plan.pdf', 'type' => DocumentType::Document, 'filename' => 'plan.pdf', 'file_path' => 'x', 'category' => null]);
    $shared->documents()->attach($document->id, ['created_at' => now()]);

    $familiaOnlyCount = $assignment->sitePlanningTasks()->count();

    $this->actingAs($manager)
        ->post(route('site-planning.design-plan-type.update', $assignment), ['design_plan_type' => 'Residential'])
        ->assertRedirect(route('site-planning.show', $assignment));

    $assignment->refresh();
    expect($assignment->design_plan_type)->toBe('Residential');

    $expected = SitePlanningTaskDefinitions::tasks('Residential');
    $tasks = $assignment->sitePlanningTasks()->orderBy('sort_order')->get();
    expect($tasks)->toHaveCount(count($expected))
        ->and($tasks->pluck('name')->all())->toBe(array_column($expected, 'name'));

    // The shared task kept its progress, notes, and document.
    $carried = $tasks->firstWhere(fn ($task) => $task->section === $shared->section && $task->name === $shared->name);
    expect($carried)->not->toBeNull()
        ->and($carried->status->value)->toBe('Done')
        ->and($carried->notes)->toBe('Carried note')
        ->and($carried->documents()->pluck('documents.id')->all())->toBe([$document->id]);

    // The dropped tasks' document rows survive even though their tasks are gone.
    expect(Document::find($document->id))->not->toBeNull();
});

it('rebuilds an already-seeded Monitoring matrix when the type changes', function () {
    ['pipeline' => $pipeline, 'assignment' => $assignment] = pipelineWithSitePlanningAssignment(PipelineStatus::SiteSurvey);
    $manager = User::factory()->infrastructureManager()->create();
    completeAllPlansDrawingTasks($assignment, 'FAMILIA');

    $project = Project::create(['pipeline_id' => $pipeline->id, 'current_stage' => 'Monitoring']);
    $monitoringStage = ProjectStage::create(['project_id' => $project->id, 'stage' => 'Monitoring', 'status' => 'Pending', 'order' => 3]);
    StageTaskSeeder::seedMissingFor($monitoringStage, 'FAMILIA');
    $monitoringStage->stageTasks()->where('name', 'UPS')->update(['status' => 'Done', 'status_changed_at' => now()]);

    $this->actingAs($manager)
        ->post(route('site-planning.design-plan-type.update', $assignment), ['design_plan_type' => 'Residential'])
        ->assertRedirect();

    $expected = StageTaskDefinitions::forField(App\Enums\ProjectStage::Monitoring, 'infrastructure_deployment_status', 'Residential');
    $tasks = $monitoringStage->fresh()->stageTasks()->where('field_key', 'infrastructure_deployment_status')->orderBy('sort_order')->get();
    expect($tasks->pluck('name')->all())->toBe(array_column($expected, 'name'))
        // Shared MDF task's completion carried over into the new matrix.
        ->and($tasks->firstWhere('name', 'UPS')->status->value)->toBe('Done');
});

it('leaves Monitoring alone when the matrix has not been seeded yet', function () {
    ['pipeline' => $pipeline, 'assignment' => $assignment] = pipelineWithSitePlanningAssignment(PipelineStatus::SiteSurvey);
    $manager = User::factory()->infrastructureManager()->create();
    completeAllPlansDrawingTasks($assignment, 'FAMILIA');

    $project = Project::create(['pipeline_id' => $pipeline->id, 'current_stage' => 'Planning']);
    $monitoringStage = ProjectStage::create(['project_id' => $project->id, 'stage' => 'Monitoring', 'status' => 'Pending', 'order' => 3]);

    $this->actingAs($manager)
        ->post(route('site-planning.design-plan-type.update', $assignment), ['design_plan_type' => 'Residential'])
        ->assertRedirect();

    expect($monitoringStage->fresh()->stageTasks()->count())->toBe(0);
});

it('copies each declared tower\'s totals into its own survey, superseding site-level values', function () {
    ['pipeline' => $pipeline, 'assignment' => $assignment] = pipelineWithSitePlanningAssignment(PipelineStatus::SiteSurvey);
    $surveyor = User::factory()->surveyor()->create();
    // The SAQ's site-level figures, entered before towers were known.
    $pipeline->siteSurvey->update(['surveyor_id' => $surveyor->id, 'floors_surveyed' => 20, 'actual_unit_count' => 200]);

    $this->actingAs($surveyor)
        ->post(route('site-planning.towers.declare', $assignment), [
            'towers' => [
                ['name' => 'Tower 1', 'total_units' => 300, 'total_floors' => 30],
                ['name' => 'Tower 2', 'total_units' => 80, 'total_floors' => 8],
            ],
        ])
        ->assertRedirect();

    $surveys = SiteSurvey::whereNotNull('tower_id')->where('site_profile_id', $pipeline->site_profile_id)->get()->keyBy(fn ($s) => $s->tower->name);
    expect((int) $surveys['Tower 1']->floors_surveyed)->toBe(30)
        ->and((int) $surveys['Tower 1']->actual_unit_count)->toBe(300)
        ->and((int) $surveys['Tower 2']->floors_surveyed)->toBe(8)
        ->and((int) $surveys['Tower 2']->actual_unit_count)->toBe(80);
});

// ─── Validation sweep ───────────────────

it('rejects an ISP Count Per Unit beyond the database integer range with a validation message', function () {
    ['pipeline' => $pipeline, 'assignment' => $assignment] = pipelineWithSitePlanningAssignment(PipelineStatus::SiteSurvey);
    $surveyor = User::factory()->surveyor()->create();
    $pipeline->siteSurvey->update(['surveyor_id' => $surveyor->id]);

    $this->actingAs($surveyor)
        ->post(route('site-planning.inspection.save', $assignment), ['isp_count_per_unit' => 99999999999])
        ->assertSessionHasErrors('isp_count_per_unit');

    expect($pipeline->fresh()->siteSurvey->isp_count_per_unit)->toBeNull();
});

it('uses friendly field names in oversized-text validation messages', function () {
    ['pipeline' => $pipeline, 'assignment' => $assignment] = pipelineWithSitePlanningAssignment(PipelineStatus::SiteSurvey);
    $surveyor = User::factory()->surveyor()->create();
    $pipeline->siteSurvey->update(['surveyor_id' => $surveyor->id]);
    $long = str_repeat('x', 300);

    $this->actingAs($surveyor)
        ->post(route('site-planning.inspection.save', $assignment), [
            'building_network_system' => $long,
            'telco_room_location' => $long,
            'electrical_room_location' => $long,
            'providers' => [['provider_name' => $long]],
        ])
        ->assertSessionHasErrors([
            'building_network_system' => 'The Building Network System field must not be greater than 255 characters.',
            'telco_room_location' => 'The Telco Room Location field must not be greater than 255 characters.',
            'electrical_room_location' => 'The Electrical Room Location field must not be greater than 255 characters.',
            'providers.0.provider_name' => 'The Provider Name field must not be greater than 255 characters.',
        ]);
});

it('shows a friendly message for unsupported file formats', function () {
    Storage::fake('public');
    ['pipeline' => $pipeline, 'assignment' => $assignment] = pipelineWithSitePlanningAssignment(PipelineStatus::SiteSurvey);
    $surveyor = User::factory()->surveyor()->create();
    $pipeline->siteSurvey->update(['surveyor_id' => $surveyor->id]);

    $this->actingAs($surveyor)
        ->post(route('site-planning.inspection.save', $assignment), [
            'document_category' => null,
            'files' => [UploadedFile::fake()->create('malware.xyz', 10)],
        ])
        ->assertSessionHasErrors(['files.0' => 'Invalid file format. Please upload a file in one of the supported formats: JPG, JPEG, PNG, GIF, WEBP, PDF, DOC(X), XLS(X), PPT(X), TXT, CSV, ZIP.']);
});
