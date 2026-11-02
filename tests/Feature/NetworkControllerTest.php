<?php

use App\Enums\DocumentType;
use App\Enums\NetworkActionPermission;
use App\Enums\PipelineStatus;
use App\Enums\ProviderStatus;
use App\Enums\SidebarModule;
use App\Enums\SiteProfileType;
use App\Events\PipelineApproved;
use App\Listeners\InitializeProject;
use App\Models\Address;
use App\Models\AuditLog;
use App\Models\Document;
use App\Models\NetworkFeasibility;
use App\Models\NetworkSiteAssignment;
use App\Models\Pipeline;
use App\Models\Project;
use App\Models\Role;
use App\Models\RoleActionPermission;
use App\Models\RoleModulePermission;
use App\Models\SitePlanningAssignment;
use App\Models\SiteProfile;
use App\Models\SiteSurvey;
use App\Models\Tower;
use App\Models\User;
use App\Support\SitePlanningTaskSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * @return array{pipeline: Pipeline, assignment: NetworkSiteAssignment}
 */
function pipelineWithNetworkAssignment(PipelineStatus $status = PipelineStatus::SiteSurvey): array
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

    $assignment = NetworkSiteAssignment::ensureFor($pipeline, $survey);

    return compact('pipeline', 'assignment');
}

function addFeasibleIsp(Pipeline $pipeline): NetworkFeasibility
{
    return NetworkFeasibility::create([
        'site_survey_id' => $pipeline->site_survey_id,
        'isp_name' => 'Fiber One',
        'status' => ProviderStatus::Feasible,
    ]);
}

function userScopedToNetwork(array $networkActions = []): User
{
    $role = Role::factory()->create();
    RoleModulePermission::create(['role_id' => $role->id, 'module' => SidebarModule::Dashboard->value]);
    RoleModulePermission::create(['role_id' => $role->id, 'module' => SidebarModule::Network->value]);

    foreach ($networkActions as $permission) {
        RoleActionPermission::create(['role_id' => $role->id, 'permission' => $permission->value]);
    }

    return User::factory()->create(['role_id' => $role->id]);
}

// ─── Module gate ──────────────────────────────────────────────────────────────

it('denies the network module to a role without the network module permission', function () {
    $role = Role::factory()->create();
    RoleModulePermission::create(['role_id' => $role->id, 'module' => SidebarModule::Dashboard->value]);
    $user = User::factory()->create(['role_id' => $role->id]);

    $this->actingAs($user)->get(route('network.index'))->assertForbidden();
});

it('allows the network list to a role with the network module and ViewNetworkAssignments', function () {
    ['assignment' => $assignment] = pipelineWithNetworkAssignment();
    $user = userScopedToNetwork([NetworkActionPermission::ViewNetworkAssignments]);

    $this->actingAs($user)
        ->get(route('network.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Network/Index')
            ->has('assignments.data', 1)
            ->where('assignments.data.0.id', $assignment->id));
});

it('denies the network list without ViewNetworkAssignments even with module access', function () {
    $user = userScopedToNetwork();

    $this->actingAs($user)->get(route('network.index'))->assertForbidden();
});

it('gives network surveyors read-only access to the pipelines module', function () {
    $networkSurveyor = User::factory()->networkSurveyor()->create();

    $this->actingAs($networkSurveyor)->get(route('pipelines'))->assertSuccessful();
});

it('blocks a network surveyor from mutating a pipeline despite read-only ViewAll access', function () {
    $networkSurveyor = User::factory()->networkSurveyor()->create();
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
        'status' => PipelineStatus::SiteSurveyPending,
    ]);
    $assignment = SitePlanningAssignment::ensureFor($pipeline, $survey);

    $this->actingAs($networkSurveyor)
        ->patch(route('site-planning.schedule', $assignment), [
            'surveyor_id' => $networkSurveyor->id,
            'survey_date' => now()->toDateString(),
            'design_plan_type' => 'FIBER',
        ])
        ->assertForbidden();
});

// ─── Feasibility CRUD ─────────────────────────────────────────────────────────

it('lets a permitted network user manage feasibility entries', function () {
    ['assignment' => $assignment] = pipelineWithNetworkAssignment(PipelineStatus::SiteSurveyPending);
    $networkSurveyor = User::factory()->networkSurveyor()->create();

    $this->actingAs($networkSurveyor)
        ->post(route('network.feasibilities.store', $assignment), [
            'isp_name' => 'Fiber One',
            'status' => 'Feasible',
            'notes' => 'MDF has available ports.',
        ])
        ->assertRedirect(route('network.show', $assignment));

    $entry = $assignment->siteSurvey->networkFeasibilities()->firstOrFail();

    expect($entry->isp_name)->toBe('Fiber One')
        ->and($entry->status->value)->toBe('Feasible');

    $this->actingAs($networkSurveyor)
        ->patch(route('network.feasibilities.update', [$assignment, $entry]), [
            'isp_name' => 'Fiber One',
            'status' => 'Checking',
            'notes' => 'Awaiting final confirmation.',
        ])
        ->assertRedirect(route('network.show', $assignment));

    expect($entry->fresh()->status->value)->toBe('Checking');

    $this->actingAs($networkSurveyor)
        ->delete(route('network.feasibilities.delete', [$assignment, $entry]))
        ->assertRedirect(route('network.show', $assignment));

    expect(NetworkFeasibility::whereKey($entry->id)->exists())->toBeFalse();
});

it('stamps feasible_at when an ISP is created as Feasible or updated into it, but not on later saves', function () {
    ['assignment' => $assignment] = pipelineWithNetworkAssignment(PipelineStatus::SiteSurveyPending);
    $networkSurveyor = User::factory()->networkSurveyor()->create();

    $this->actingAs($networkSurveyor)
        ->post(route('network.feasibilities.store', $assignment), [
            'isp_name' => 'Fiber One',
            'status' => 'Checking',
        ])
        ->assertRedirect(route('network.show', $assignment));

    $entry = $assignment->siteSurvey->networkFeasibilities()->firstOrFail();
    expect($entry->feasible_at)->toBeNull();

    $this->actingAs($networkSurveyor)
        ->patch(route('network.feasibilities.update', [$assignment, $entry]), [
            'isp_name' => 'Fiber One',
            'status' => 'Feasible',
        ])
        ->assertRedirect(route('network.show', $assignment));

    $firstFeasibleAt = $entry->fresh()->feasible_at;
    expect($firstFeasibleAt)->not->toBeNull();

    $this->travel(1)->hour();

    $this->actingAs($networkSurveyor)
        ->patch(route('network.feasibilities.update', [$assignment, $entry]), [
            'isp_name' => 'Fiber One',
            'status' => 'Feasible',
            'notes' => 'Still feasible.',
        ])
        ->assertRedirect(route('network.show', $assignment));

    expect($entry->fresh()->feasible_at->equalTo($firstFeasibleAt))->toBeTrue();
});

it('stamps feasible_at immediately when an ISP is created already Feasible', function () {
    ['assignment' => $assignment] = pipelineWithNetworkAssignment(PipelineStatus::SiteSurveyPending);
    $networkSurveyor = User::factory()->networkSurveyor()->create();

    $this->actingAs($networkSurveyor)
        ->post(route('network.feasibilities.store', $assignment), [
            'isp_name' => 'Fiber One',
            'status' => 'Feasible',
        ])
        ->assertRedirect(route('network.show', $assignment));

    expect($assignment->siteSurvey->networkFeasibilities()->firstOrFail()->feasible_at)->not->toBeNull();
});

it('stores and updates an ISP contact person name and email', function () {
    ['assignment' => $assignment] = pipelineWithNetworkAssignment(PipelineStatus::SiteSurveyPending);
    $networkSurveyor = User::factory()->networkSurveyor()->create();

    $this->actingAs($networkSurveyor)
        ->post(route('network.feasibilities.store', $assignment), [
            'isp_name' => 'Fiber One',
            'status' => 'Checking',
            'contact_name' => 'Jane Dela Cruz',
            'contact_email' => 'jane@fiberone.example',
        ])
        ->assertRedirect(route('network.show', $assignment));

    $entry = $assignment->siteSurvey->networkFeasibilities()->firstOrFail();

    expect($entry->contact_name)->toBe('Jane Dela Cruz')
        ->and($entry->contact_email)->toBe('jane@fiberone.example');

    $this->actingAs($networkSurveyor)
        ->patch(route('network.feasibilities.update', [$assignment, $entry]), [
            'isp_name' => 'Fiber One',
            'status' => 'Checking',
            'contact_name' => 'John Dela Cruz',
            'contact_email' => 'john@fiberone.example',
        ])
        ->assertRedirect(route('network.show', $assignment));

    expect($entry->fresh()->contact_name)->toBe('John Dela Cruz')
        ->and($entry->fresh()->contact_email)->toBe('john@fiberone.example');
});

it('rejects an invalid ISP contact email', function () {
    ['assignment' => $assignment] = pipelineWithNetworkAssignment(PipelineStatus::SiteSurveyPending);
    $networkSurveyor = User::factory()->networkSurveyor()->create();

    $this->actingAs($networkSurveyor)
        ->post(route('network.feasibilities.store', $assignment), [
            'isp_name' => 'Fiber One',
            'status' => 'Checking',
            'contact_email' => 'not-an-email',
        ])
        ->assertSessionHasErrors('contact_email');
});

it('rejects an ISP contact person name with disallowed special characters', function () {
    ['assignment' => $assignment] = pipelineWithNetworkAssignment(PipelineStatus::SiteSurveyPending);
    $networkSurveyor = User::factory()->networkSurveyor()->create();

    $this->actingAs($networkSurveyor)
        ->post(route('network.feasibilities.store', $assignment), [
            'isp_name' => 'Fiber One',
            'status' => 'Checking',
            'contact_name' => 'Jane<script>',
        ])
        ->assertSessionHasErrors('contact_name');
});

it('rejects a duplicate ISP within the same network site assignment', function () {
    ['pipeline' => $pipeline, 'assignment' => $assignment] = pipelineWithNetworkAssignment(PipelineStatus::SiteSurveyPending);
    $networkSurveyor = User::factory()->networkSurveyor()->create();
    addFeasibleIsp($pipeline);

    $this->actingAs($networkSurveyor)
        ->post(route('network.feasibilities.store', $assignment), [
            'isp_name' => 'fiber one',
            'status' => 'Checking',
        ])
        ->assertSessionHasErrors('isp_name');
});

it('allows renaming an ISP entry to its own current name without flagging it as a duplicate', function () {
    ['pipeline' => $pipeline, 'assignment' => $assignment] = pipelineWithNetworkAssignment(PipelineStatus::SiteSurveyPending);
    $networkSurveyor = User::factory()->networkSurveyor()->create();
    $entry = addFeasibleIsp($pipeline);

    $this->actingAs($networkSurveyor)
        ->patch(route('network.feasibilities.update', [$assignment, $entry]), [
            'isp_name' => 'Fiber One',
            'status' => 'Feasible',
        ])
        ->assertSessionDoesntHaveErrors('isp_name');
});

it('rejects an ISP contact person name over 255 characters', function () {
    ['assignment' => $assignment] = pipelineWithNetworkAssignment(PipelineStatus::SiteSurveyPending);
    $networkSurveyor = User::factory()->networkSurveyor()->create();

    $this->actingAs($networkSurveyor)
        ->post(route('network.feasibilities.store', $assignment), [
            'isp_name' => 'Fiber One',
            'status' => 'Checking',
            'contact_name' => str_repeat('a', 256),
        ])
        ->assertSessionHasErrors('contact_name');
});

it('rejects ISP notes over 1000 characters', function () {
    ['assignment' => $assignment] = pipelineWithNetworkAssignment(PipelineStatus::SiteSurveyPending);
    $networkSurveyor = User::factory()->networkSurveyor()->create();

    $this->actingAs($networkSurveyor)
        ->post(route('network.feasibilities.store', $assignment), [
            'isp_name' => 'Fiber One',
            'status' => 'Checking',
            'notes' => str_repeat('a', 1001),
        ])
        ->assertSessionHasErrors('notes');
});

it('blocks feasibility management without ManageNetworkFeasibility', function () {
    ['assignment' => $assignment] = pipelineWithNetworkAssignment();
    $user = userScopedToNetwork([NetworkActionPermission::ViewNetworkAssignments]);

    $this->actingAs($user)
        ->post(route('network.feasibilities.store', $assignment), [
            'isp_name' => 'Fiber One',
            'status' => 'Checking',
        ])
        ->assertForbidden();
});

it('blocks feasibility management once the pipeline has moved past site survey', function () {
    ['assignment' => $assignment] = pipelineWithNetworkAssignment(PipelineStatus::Commercials);
    $networkSurveyor = User::factory()->networkSurveyor()->create();

    $this->actingAs($networkSurveyor)
        ->post(route('network.feasibilities.store', $assignment), [
            'isp_name' => 'Fiber One',
            'status' => 'Checking',
        ])
        ->assertStatus(422);
});

it('locks ISP entries and attachments once Network Feasibility is Completed', function () {
    Storage::fake('public');
    ['pipeline' => $pipeline, 'assignment' => $assignment] = pipelineWithNetworkAssignment(PipelineStatus::SiteSurvey);
    $networkSurveyor = User::factory()->networkSurveyor()->create();
    $entry = $assignment->siteSurvey->networkFeasibilities()->create(['isp_name' => 'Fiber One', 'status' => 'Feasible']);
    $pipeline->siteSurvey->update(['network_feasibility_completed_at' => now(), 'network_feasibility_completed_by_id' => $networkSurveyor->id]);

    $this->actingAs($networkSurveyor);

    $this->post(route('network.feasibilities.store', $assignment), ['isp_name' => 'New ISP', 'status' => 'Checking'])->assertForbidden();
    $this->patch(route('network.feasibilities.update', [$assignment, $entry]), ['isp_name' => 'Edited', 'status' => 'Feasible'])->assertForbidden();
    $this->post(route('network.feasibilities.documents.store', [$assignment, $entry]), ['files' => [UploadedFile::fake()->create('a.pdf', 10)]])->assertForbidden();
    $this->delete(route('network.feasibilities.delete', [$assignment, $entry]))->assertForbidden();

    expect($assignment->siteSurvey->networkFeasibilities()->count())->toBe(1)
        ->and($entry->fresh()->isp_name)->toBe('Fiber One');
});

it('blocks updating a feasibility entry that belongs to a different assignment', function () {
    ['assignment' => $assignmentA] = pipelineWithNetworkAssignment();
    ['assignment' => $assignmentB] = pipelineWithNetworkAssignment();
    $networkSurveyor = User::factory()->networkSurveyor()->create();
    $entry = $assignmentB->siteSurvey->networkFeasibilities()->create([
        'isp_name' => 'Other Site ISP',
        'status' => 'Checking',
    ]);

    $this->actingAs($networkSurveyor)
        ->patch(route('network.feasibilities.update', [$assignmentA, $entry]), [
            'isp_name' => 'Other Site ISP',
            'status' => 'Feasible',
        ])
        ->assertForbidden();
});

// ─── Feasibility documents ─────────────────────────────────────────────────

it('uploads and removes a document on a feasibility entry', function () {
    Storage::fake('public');
    ['assignment' => $assignment] = pipelineWithNetworkAssignment(PipelineStatus::SiteSurveyPending);
    $networkSurveyor = User::factory()->networkSurveyor()->create();
    $entry = $assignment->siteSurvey->networkFeasibilities()->create([
        'isp_name' => 'Fiber One',
        'status' => 'Checking',
    ]);

    $this->actingAs($networkSurveyor)
        ->post(route('network.feasibilities.documents.store', [$assignment, $entry]), [
            'files' => [UploadedFile::fake()->create('feasibility.pdf', 100)],
        ])
        ->assertRedirect(route('network.show', $assignment));

    $document = $entry->fresh()->documents->first();
    expect($entry->fresh()->documents)->toHaveCount(1);

    $this->actingAs($networkSurveyor)
        ->delete(route('network.feasibilities.documents.delete', [$assignment, $entry, $document]))
        ->assertRedirect(route('network.show', $assignment));

    expect($entry->fresh()->documents)->toHaveCount(0)
        ->and(Document::find($document->id))->toBeNull();
});

it('replaces a feasibility document as a new version instead of deleting it', function () {
    Storage::fake('public');
    ['assignment' => $assignment] = pipelineWithNetworkAssignment(PipelineStatus::SiteSurveyPending);
    $networkSurveyor = User::factory()->networkSurveyor()->create();
    $entry = $assignment->siteSurvey->networkFeasibilities()->create([
        'isp_name' => 'Fiber One',
        'status' => 'Checking',
    ]);

    $this->actingAs($networkSurveyor)
        ->post(route('network.feasibilities.documents.store', [$assignment, $entry]), [
            'files' => [UploadedFile::fake()->create('feasibility.pdf', 100)],
        ])
        ->assertRedirect(route('network.show', $assignment));

    $document = $entry->fresh()->documents->first();

    $this->actingAs($networkSurveyor)
        ->post(route('network.feasibilities.documents.replace', [$assignment, $entry, $document]), [
            'file' => UploadedFile::fake()->create('feasibility-v2.pdf', 100),
        ])
        ->assertRedirect(route('network.show', $assignment));

    $current = $entry->fresh()->documents->first();
    expect($entry->fresh()->documents)->toHaveCount(1)
        ->and($current->id)->not->toBe($document->id)
        ->and($current->version)->toBe(2);

    expect($document->fresh()->superseded_at)->not->toBeNull()
        ->and(Document::find($document->id))->not->toBeNull();
});

it('does not delete a feasibility document file while it is still attached elsewhere', function () {
    Storage::fake('public');
    ['assignment' => $assignment] = pipelineWithNetworkAssignment(PipelineStatus::SiteSurveyPending);
    $networkSurveyor = User::factory()->networkSurveyor()->create();
    $entry = $assignment->siteSurvey->networkFeasibilities()->create([
        'isp_name' => 'Fiber One',
        'status' => 'Checking',
    ]);

    $document = Document::create([
        'name' => 'shared.pdf',
        'type' => DocumentType::Document,
        'filename' => 'shared.pdf',
        'file_path' => 'network-feasibility-documents/shared.pdf',
    ]);
    $entry->documents()->attach($document->id, ['created_at' => now()]);
    $assignment->pipeline->documents()->attach($document->id, ['created_at' => now()]);

    $this->actingAs($networkSurveyor)
        ->delete(route('network.feasibilities.documents.delete', [$assignment, $entry, $document]))
        ->assertRedirect(route('network.show', $assignment));

    expect($entry->fresh()->documents)->toHaveCount(0)
        ->and(Document::find($document->id))->not->toBeNull();
});

it('blocks uploading a feasibility document without permission', function () {
    ['assignment' => $assignment] = pipelineWithNetworkAssignment(PipelineStatus::SiteSurveyPending);
    $user = userScopedToNetwork([NetworkActionPermission::ViewNetworkAssignments]);
    $entry = $assignment->siteSurvey->networkFeasibilities()->create([
        'isp_name' => 'Fiber One',
        'status' => 'Checking',
    ]);

    $this->actingAs($user)
        ->post(route('network.feasibilities.documents.store', [$assignment, $entry]), [
            'files' => [UploadedFile::fake()->create('feasibility.pdf', 100)],
        ])
        ->assertForbidden();
});

// ─── Complete Network Feasibility — shared business rule with Pipelines ──────

it('blocks completing network feasibility with no ISP entries with a friendly flash error, not a raw exception', function () {
    ['assignment' => $assignment] = pipelineWithNetworkAssignment();
    $networkSurveyor = User::factory()->networkSurveyor()->create();

    $this->actingAs($networkSurveyor)
        ->post(route('network.complete-feasibility', $assignment))
        ->assertRedirect(route('network.show', $assignment))
        ->assertSessionHas('error', 'Add at least one ISP feasibility entry before completing Network Feasibility.');

    expect($assignment->pipeline->fresh()->siteSurvey->network_feasibility_completed_at)->toBeNull();
});

it('completes network feasibility without moving to commercials while survey is pending', function () {
    ['pipeline' => $pipeline, 'assignment' => $assignment] = pipelineWithNetworkAssignment();
    addFeasibleIsp($pipeline);
    $networkSurveyor = User::factory()->networkSurveyor()->create();

    $this->actingAs($networkSurveyor)
        ->post(route('network.complete-feasibility', $assignment))
        ->assertRedirect(route('network.show', $assignment));

    $pipeline->refresh();

    expect($pipeline->status)->toBe(PipelineStatus::SiteSurvey)
        ->and($pipeline->siteSurvey->network_feasibility_completed_at)->not->toBeNull()
        ->and($pipeline->siteSurvey->network_feasibility_completed_by_id)->toBe($networkSurveyor->id)
        ->and($pipeline->siteSurvey->survey_completed_at)->toBeNull();
});

it('moves the pipeline to commercials once both survey and network feasibility are complete', function () {
    ['pipeline' => $pipeline, 'assignment' => $assignment] = pipelineWithNetworkAssignment();
    addFeasibleIsp($pipeline);
    $surveyor = User::factory()->surveyor()->create();
    $pipeline->siteSurvey->update(['surveyor_id' => $surveyor->id]);
    $networkSurveyor = User::factory()->networkSurveyor()->create();
    $designer = User::factory()->infraDesigner()->create();
    $infrastructureManager = User::factory()->infrastructureManager()->create();
    $sitePlanningAssignment = SitePlanningAssignment::ensureFor($pipeline, $pipeline->siteSurvey);
    $sitePlanningAssignment->update(['design_plan_type' => 'Residential', 'design_plan_assignee_id' => $designer->id]);
    SitePlanningTaskSeeder::seedMissingFor($sitePlanningAssignment);
    $sitePlanningAssignment->sitePlanningTasks()->update(['status' => 'Done', 'status_changed_at' => now()]);
    $sitePlanningAssignment->designPlanDocuments()->attach(
        Document::create(['name' => 'design-plan.pdf', 'type' => DocumentType::Document, 'filename' => 'design-plan.pdf', 'file_path' => 'x'])->id,
        ['created_at' => now()]
    );

    $this->actingAs($designer)
        ->patch(route('site-planning.design-plan.complete', $sitePlanningAssignment), [])
        ->assertRedirect(route('site-planning.show', $sitePlanningAssignment));

    expect($pipeline->fresh()->status)->toBe(PipelineStatus::SiteSurvey);

    $this->actingAs($infrastructureManager)
        ->patch(route('site-planning.design-plan.approve', $sitePlanningAssignment), [])
        ->assertRedirect(route('site-planning.show', $sitePlanningAssignment));

    expect($pipeline->fresh()->status)->toBe(PipelineStatus::SiteSurvey);

    $this->actingAs($networkSurveyor)
        ->post(route('network.complete-feasibility', $assignment))
        ->assertRedirect(route('network.show', $assignment));

    expect($pipeline->fresh()->status)->toBe(PipelineStatus::Commercials);
    expect($pipeline->fresh()->statusLogs()->latest('id')->first()->status)->toBe(PipelineStatus::Commercials->value);
});

it('resets Network Feasibility and Design Plan completion when the survey is reopened, allowing the pipeline to reach Commercials again once both are redone', function () {
    ['pipeline' => $pipeline, 'assignment' => $assignment] = pipelineWithNetworkAssignment();
    addFeasibleIsp($pipeline);
    $surveyor = User::factory()->surveyor()->create();
    $pipeline->siteSurvey->update(['surveyor_id' => $surveyor->id]);
    $networkSurveyor = User::factory()->networkSurveyor()->create();
    $designer = User::factory()->infraDesigner()->create();
    $infrastructureManager = User::factory()->infrastructureManager()->create();
    $commercialManager = User::factory()->commercialManager()->create();
    $sitePlanningAssignment = SitePlanningAssignment::ensureFor($pipeline, $pipeline->siteSurvey);
    $sitePlanningAssignment->update(['design_plan_type' => 'Residential', 'design_plan_assignee_id' => $designer->id]);
    SitePlanningTaskSeeder::seedMissingFor($sitePlanningAssignment);
    $sitePlanningAssignment->sitePlanningTasks()->update(['status' => 'Done', 'status_changed_at' => now()]);
    $sitePlanningAssignment->designPlanDocuments()->attach(
        Document::create(['name' => 'design-plan.pdf', 'type' => DocumentType::Document, 'filename' => 'design-plan.pdf', 'file_path' => 'x'])->id,
        ['created_at' => now()]
    );

    $this->actingAs($designer)
        ->patch(route('site-planning.design-plan.complete', $sitePlanningAssignment), []);
    $this->actingAs($infrastructureManager)
        ->patch(route('site-planning.design-plan.approve', $sitePlanningAssignment), []);
    $this->actingAs($networkSurveyor)
        ->post(route('network.complete-feasibility', $assignment));

    expect($pipeline->fresh()->status)->toBe(PipelineStatus::Commercials);

    // Reopening the survey should reset every completion marker from both halves, not just
    // survey_completed_at — the stale ones previously left the pipeline permanently stuck
    // and the UI showing stale "Completed"/"Approved" badges.
    $this->actingAs($commercialManager)
        ->post(route('pipelines.reopen-survey', $pipeline))
        ->assertRedirect();

    $pipeline->refresh();
    expect($pipeline->status)->toBe(PipelineStatus::SiteSurvey)
        ->and($pipeline->siteSurvey->survey_completed_at)->toBeNull()
        ->and($pipeline->siteSurvey->survey_completed_by_id)->toBeNull()
        ->and($pipeline->siteSurvey->network_feasibility_completed_at)->toBeNull()
        ->and($pipeline->siteSurvey->network_feasibility_completed_by_id)->toBeNull();

    $sitePlanningAssignment->refresh();
    expect($sitePlanningAssignment->design_plan_completed_at)->toBeNull()
        ->and($sitePlanningAssignment->design_plan_completed_by_id)->toBeNull()
        ->and($sitePlanningAssignment->design_plan_approved_at)->toBeNull()
        ->and($sitePlanningAssignment->design_plan_approved_by_id)->toBeNull();

    // The underlying task data is retained, not wiped — completeDesignPlan()'s "all tasks Done"
    // check passes trivially on redo, exactly as the fix intends.
    expect($sitePlanningAssignment->sitePlanningTasks()->where('status', '!=', 'Done')->exists())->toBeFalse();

    // Redo both halves — previously blocked forever because both controllers refuse to re-run
    // while their own completion timestamp is still set.
    $this->actingAs($designer)
        ->patch(route('site-planning.design-plan.complete', $sitePlanningAssignment), [])
        ->assertRedirect(route('site-planning.show', $sitePlanningAssignment));
    $this->actingAs($infrastructureManager)
        ->patch(route('site-planning.design-plan.approve', $sitePlanningAssignment), [])
        ->assertRedirect(route('site-planning.show', $sitePlanningAssignment));

    expect($pipeline->fresh()->status)->toBe(PipelineStatus::SiteSurvey);

    $this->actingAs($networkSurveyor)
        ->post(route('network.complete-feasibility', $assignment))
        ->assertRedirect(route('network.show', $assignment));

    expect($pipeline->fresh()->status)->toBe(PipelineStatus::Commercials);
});

it('blocks completing feasibility twice', function () {
    ['pipeline' => $pipeline, 'assignment' => $assignment] = pipelineWithNetworkAssignment();
    addFeasibleIsp($pipeline);
    $networkSurveyor = User::factory()->networkSurveyor()->create();

    $this->actingAs($networkSurveyor)->post(route('network.complete-feasibility', $assignment));

    $this->actingAs($networkSurveyor)
        ->post(route('network.complete-feasibility', $assignment))
        ->assertRedirect(route('network.show', $assignment))
        ->assertSessionHas('error', 'Network Feasibility is already completed.');
});

it('blocks completing feasibility without permission', function () {
    ['assignment' => $assignment] = pipelineWithNetworkAssignment();
    $user = userScopedToNetwork([NetworkActionPermission::ViewNetworkAssignments]);

    $this->actingAs($user)
        ->post(route('network.complete-feasibility', $assignment))
        ->assertForbidden();
});

// ─── JO checklist tasks ─────────────────────────────────────────────────────

/**
 * @return array{pipeline: Pipeline, assignment: NetworkSiteAssignment, feasibility: NetworkFeasibility}
 */
function pipelineWithProjectAndFeasibilityTasks(): array
{
    ['pipeline' => $pipeline, 'assignment' => $assignment] = pipelineWithNetworkAssignment(PipelineStatus::Commercials);

    $feasibility = NetworkFeasibility::create([
        'site_survey_id' => $pipeline->site_survey_id,
        'isp_name' => 'Fiber One',
        'status' => 'Feasible',
    ]);

    // The JO checklist is only seeded at project creation when MOA/NTP were already attached at
    // Commercials — see InitializeProject::pipelineHasMoaAndNtp.
    foreach (['MOA', 'NTP'] as $category) {
        $document = Document::create([
            'name' => "{$category}.pdf",
            'type' => DocumentType::Document,
            'filename' => "{$category}.pdf",
            'file_path' => "commercial-documents/{$category}.pdf",
            'category' => $category,
        ]);
        $pipeline->documents()->attach($document->id, ['created_at' => now()]);
    }

    (new InitializeProject)->handle(new PipelineApproved($pipeline));

    return ['pipeline' => $pipeline->fresh(), 'assignment' => $assignment, 'feasibility' => $feasibility->fresh()];
}

it('has a null jo_progress before any JO tasks are seeded', function () {
    ['assignment' => $assignment] = pipelineWithNetworkAssignment(PipelineStatus::SiteSurveyPending);
    $assignment->siteSurvey->networkFeasibilities()->create(['isp_name' => 'Fiber One', 'status' => 'Checking']);

    expect($assignment->fresh()->jo_progress)->toBeNull();
});

it('aggregates jo_progress across every ISP on the site once JO tasks are seeded', function () {
    ['assignment' => $assignment, 'feasibility' => $feasibility] = pipelineWithProjectAndFeasibilityTasks();
    $feasibility->tasks()->where('name', 'JO Approval')->update(['status' => 'Done']);
    $feasibility->tasks()->where('name', 'Send JO to ISP')->update(['status' => 'Done']);

    NetworkFeasibility::create([
        'site_survey_id' => $assignment->site_survey_id,
        'isp_name' => 'Converge',
        'status' => 'Checking',
    ])->tasks()->create([
        'name' => 'JO Approval', 'project_stage' => 'Planning', 'status' => 'Pending', 'sort_order' => 0,
    ]);

    // 9 tasks for Fiber One (2 Done) + 1 task for Converge (0 Done) = 10 total, 2 done.
    expect($assignment->fresh()->jo_progress)->toBe(['done' => 2, 'total' => 10]);
});

it('exposes jo_progress on the network index list', function () {
    ['assignment' => $assignment, 'feasibility' => $feasibility] = pipelineWithProjectAndFeasibilityTasks();
    $feasibility->tasks()->where('name', 'JO Approval')->update(['status' => 'Done']);
    $user = User::factory()->networkSurveyor()->create();

    $this->actingAs($user)
        ->get(route('network.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('assignments.data.0.jo_progress.done', 1)
            ->where('assignments.data.0.jo_progress.total', 9));
});

it('blocks updating a feasibility task before the project exists', function () {
    ['assignment' => $assignment] = pipelineWithNetworkAssignment(PipelineStatus::Commercials);
    $feasibility = NetworkFeasibility::create([
        'site_survey_id' => $assignment->site_survey_id,
        'isp_name' => 'Fiber One',
        'status' => 'Feasible',
    ]);
    $networkSurveyor = User::factory()->networkSurveyor()->create();

    $task = $feasibility->tasks()->create([
        'name' => 'Send JO to ISP',
        'project_stage' => 'Planning',
        'sort_order' => 1,
    ]);

    $this->actingAs($networkSurveyor)
        ->patch(route('network.feasibilities.tasks.update', [$assignment, $feasibility, $task]), [
            'status' => 'Done',
        ])
        ->assertStatus(422);
});

it('updates a feasibility task status and stamps status_changed_at once the project exists', function () {
    ['assignment' => $assignment, 'feasibility' => $feasibility] = pipelineWithProjectAndFeasibilityTasks();
    $networkSurveyor = User::factory()->networkSurveyor()->create();
    $task = $feasibility->tasks()->where('name', 'Send JO to ISP')->firstOrFail();

    $this->actingAs($networkSurveyor)
        ->patch(route('network.feasibilities.tasks.update', [$assignment, $feasibility, $task]), [
            'status' => 'Done',
        ])
        ->assertRedirect(route('network.show', $assignment));

    expect($task->fresh()->status->value)->toBe('Done')
        ->and($task->fresh()->status_changed_at)->not->toBeNull();
});

it('does not restamp status_changed_at when the status is unchanged', function () {
    ['assignment' => $assignment, 'feasibility' => $feasibility] = pipelineWithProjectAndFeasibilityTasks();
    $networkSurveyor = User::factory()->networkSurveyor()->create();
    $task = $feasibility->tasks()->where('name', 'Send JO to ISP')->firstOrFail();
    $task->update(['status' => 'In-Progress', 'status_changed_at' => now()->subDay()]);
    $stampedAt = $task->fresh()->status_changed_at;

    $this->actingAs($networkSurveyor)
        ->patch(route('network.feasibilities.tasks.update', [$assignment, $feasibility, $task]), [
            'status' => 'In-Progress',
        ])
        ->assertRedirect(route('network.show', $assignment));

    expect($task->fresh()->status_changed_at->equalTo($stampedAt))->toBeTrue();
});

it('saves a feasibility task note without requiring a status', function () {
    ['assignment' => $assignment, 'feasibility' => $feasibility] = pipelineWithProjectAndFeasibilityTasks();
    $networkSurveyor = User::factory()->networkSurveyor()->create();
    $task = $feasibility->tasks()->where('name', 'Send JO to ISP')->firstOrFail();

    $this->actingAs($networkSurveyor)
        ->patch(route('network.feasibilities.tasks.update', [$assignment, $feasibility, $task]), [
            'notes' => 'Waiting on the ISP to confirm.',
        ])
        ->assertRedirect(route('network.show', $assignment));

    expect($task->fresh()->notes)->toBe('Waiting on the ISP to confirm.')
        ->and($task->fresh()->status->value)->toBe('Pending');
});

it('updates both status and notes on a feasibility task in a single request', function () {
    ['assignment' => $assignment, 'feasibility' => $feasibility] = pipelineWithProjectAndFeasibilityTasks();
    $networkSurveyor = User::factory()->networkSurveyor()->create();
    $task = $feasibility->tasks()->where('name', 'Send JO to ISP')->firstOrFail();

    $this->actingAs($networkSurveyor)
        ->patch(route('network.feasibilities.tasks.update', [$assignment, $feasibility, $task]), [
            'status' => 'Done',
            'notes' => 'Confirmed by ISP rep.',
        ])
        ->assertRedirect(route('network.show', $assignment));

    expect($task->fresh()->status->value)->toBe('Done')
        ->and($task->fresh()->notes)->toBe('Confirmed by ISP rep.');
});

it('clears a feasibility task note when saved as an empty string', function () {
    ['assignment' => $assignment, 'feasibility' => $feasibility] = pipelineWithProjectAndFeasibilityTasks();
    $networkSurveyor = User::factory()->networkSurveyor()->create();
    $task = $feasibility->tasks()->where('name', 'Send JO to ISP')->firstOrFail();
    $task->update(['notes' => 'Old note.']);

    $this->actingAs($networkSurveyor)
        ->patch(route('network.feasibilities.tasks.update', [$assignment, $feasibility, $task]), [
            'notes' => '',
        ])
        ->assertRedirect(route('network.show', $assignment));

    expect($task->fresh()->notes)->toBeNull();
});

it('blocks marking the JO Approval task Done without an attached document', function () {
    ['assignment' => $assignment, 'feasibility' => $feasibility] = pipelineWithProjectAndFeasibilityTasks();
    $networkSurveyor = User::factory()->networkSurveyor()->create();
    $task = $feasibility->tasks()->where('name', 'JO Approval')->firstOrFail();

    $this->actingAs($networkSurveyor)
        ->patch(route('network.feasibilities.tasks.update', [$assignment, $feasibility, $task]), [
            'status' => 'Done',
        ])
        ->assertSessionHasErrors('status');
});

it('uploads a document on a feasibility task and auto-marks a requires_document task Done', function () {
    Storage::fake('public');
    ['assignment' => $assignment, 'feasibility' => $feasibility] = pipelineWithProjectAndFeasibilityTasks();
    $networkSurveyor = User::factory()->networkSurveyor()->create();
    $task = $feasibility->tasks()->where('name', 'JO Approval')->firstOrFail();

    $this->actingAs($networkSurveyor)
        ->post(route('network.feasibilities.tasks.documents.store', [$assignment, $feasibility, $task]), [
            'files' => [UploadedFile::fake()->create('jo.pdf', 100)],
        ])
        ->assertRedirect(route('network.show', $assignment));

    $document = $task->fresh()->documents->first();

    expect($task->fresh()->documents)->toHaveCount(1)
        ->and($task->fresh()->status->value)->toBe('Done')
        ->and($task->fresh()->status_changed_at)->not->toBeNull();
});

it('blocks removing the only attachment from a completed requires_document task', function () {
    Storage::fake('public');
    ['assignment' => $assignment, 'feasibility' => $feasibility] = pipelineWithProjectAndFeasibilityTasks();
    $networkSurveyor = User::factory()->networkSurveyor()->create();
    $task = $feasibility->tasks()->where('name', 'JO Approval')->firstOrFail();

    $this->actingAs($networkSurveyor)
        ->post(route('network.feasibilities.tasks.documents.store', [$assignment, $feasibility, $task]), [
            'files' => [UploadedFile::fake()->create('jo.pdf', 100)],
        ]);

    $document = $task->fresh()->documents->first();

    $this->actingAs($networkSurveyor)
        ->delete(route('network.feasibilities.tasks.documents.delete', [$assignment, $feasibility, $task, $document]))
        ->assertSessionHasErrors('document');

    expect($task->fresh()->documents)->toHaveCount(1)
        ->and(Document::find($document->id))->not->toBeNull();
});

it('allows removing a document from a JO Approval task that is not yet Done', function () {
    Storage::fake('public');
    ['assignment' => $assignment, 'feasibility' => $feasibility] = pipelineWithProjectAndFeasibilityTasks();
    $networkSurveyor = User::factory()->networkSurveyor()->create();
    $task = $feasibility->tasks()->where('name', 'JO Approval')->firstOrFail();

    $document = Document::create([
        'name' => 'draft.pdf',
        'type' => DocumentType::Document,
        'filename' => 'draft.pdf',
        'file_path' => 'network-feasibility-task-documents/draft.pdf',
    ]);
    $task->documents()->attach($document->id, ['created_at' => now()]);

    $this->actingAs($networkSurveyor)
        ->delete(route('network.feasibilities.tasks.documents.delete', [$assignment, $feasibility, $task, $document]))
        ->assertRedirect(route('network.show', $assignment));

    expect($task->fresh()->documents)->toHaveCount(0);
});

it('allows removing one of several attachments from a completed requires_document task', function () {
    Storage::fake('public');
    ['assignment' => $assignment, 'feasibility' => $feasibility] = pipelineWithProjectAndFeasibilityTasks();
    $networkSurveyor = User::factory()->networkSurveyor()->create();
    $task = $feasibility->tasks()->where('name', 'JO Approval')->firstOrFail();
    $task->update(['status' => 'Done', 'status_changed_at' => now()]);

    $firstDocument = Document::create([
        'name' => 'jo-1.pdf', 'type' => DocumentType::Document, 'filename' => 'jo-1.pdf',
        'file_path' => 'network-feasibility-task-documents/jo-1.pdf',
    ]);
    $secondDocument = Document::create([
        'name' => 'jo-2.pdf', 'type' => DocumentType::Document, 'filename' => 'jo-2.pdf',
        'file_path' => 'network-feasibility-task-documents/jo-2.pdf',
    ]);
    $task->documents()->attach($firstDocument->id, ['created_at' => now()]);
    $task->documents()->attach($secondDocument->id, ['created_at' => now()]);

    $this->actingAs($networkSurveyor)
        ->delete(route('network.feasibilities.tasks.documents.delete', [$assignment, $feasibility, $task, $firstDocument]))
        ->assertRedirect(route('network.show', $assignment));

    expect($task->fresh()->documents)->toHaveCount(1)
        ->and($task->fresh()->status->value)->toBe('Done');
});

it('does not restamp status_changed_at on upload when a requires_document task is already Done', function () {
    Storage::fake('public');
    ['assignment' => $assignment, 'feasibility' => $feasibility] = pipelineWithProjectAndFeasibilityTasks();
    $networkSurveyor = User::factory()->networkSurveyor()->create();
    $task = $feasibility->tasks()->where('name', 'JO Approval')->firstOrFail();
    $task->update(['status' => 'Done', 'status_changed_at' => now()->subDay()]);
    $stampedAt = $task->fresh()->status_changed_at;

    $this->actingAs($networkSurveyor)
        ->post(route('network.feasibilities.tasks.documents.store', [$assignment, $feasibility, $task]), [
            'files' => [UploadedFile::fake()->create('jo.pdf', 100)],
        ])
        ->assertRedirect(route('network.show', $assignment));

    expect($task->fresh()->status_changed_at->equalTo($stampedAt))->toBeTrue();
});

it('does not delete a feasibility task document file while it is still attached elsewhere', function () {
    Storage::fake('public');
    ['assignment' => $assignment, 'feasibility' => $feasibility] = pipelineWithProjectAndFeasibilityTasks();
    $networkSurveyor = User::factory()->networkSurveyor()->create();
    $task = $feasibility->tasks()->where('name', 'JO Approval')->firstOrFail();

    $document = Document::create([
        'name' => 'shared.pdf',
        'type' => DocumentType::Document,
        'filename' => 'shared.pdf',
        'file_path' => 'network-feasibility-task-documents/shared.pdf',
    ]);
    $task->documents()->attach($document->id, ['created_at' => now()]);
    $assignment->pipeline->documents()->attach($document->id, ['created_at' => now()]);

    $this->actingAs($networkSurveyor)
        ->delete(route('network.feasibilities.tasks.documents.delete', [$assignment, $feasibility, $task, $document]))
        ->assertRedirect(route('network.show', $assignment));

    expect($task->fresh()->documents)->toHaveCount(0)
        ->and(Document::find($document->id))->not->toBeNull();
});

it('blocks updating a feasibility task without permission', function () {
    ['assignment' => $assignment, 'feasibility' => $feasibility] = pipelineWithProjectAndFeasibilityTasks();
    $user = userScopedToNetwork([NetworkActionPermission::ViewNetworkAssignments]);
    $task = $feasibility->tasks()->where('name', 'Send JO to ISP')->firstOrFail();

    $this->actingAs($user)
        ->patch(route('network.feasibilities.tasks.update', [$assignment, $feasibility, $task]), [
            'status' => 'Done',
        ])
        ->assertForbidden();
});

it('blocks editing a feasibility task once Network Feasibility is marked Completed', function () {
    ['pipeline' => $pipeline, 'assignment' => $assignment, 'feasibility' => $feasibility] = pipelineWithProjectAndFeasibilityTasks();
    $networkSurveyor = User::factory()->networkSurveyor()->create();
    $task = $feasibility->tasks()->where('name', 'Send JO to ISP')->firstOrFail();
    $pipeline->siteSurvey->update(['network_feasibility_completed_at' => now(), 'network_feasibility_completed_by_id' => $networkSurveyor->id]);

    $this->actingAs($networkSurveyor)
        ->patch(route('network.feasibilities.tasks.update', [$assignment, $feasibility, $task]), [
            'status' => 'Done',
        ])
        ->assertForbidden();
});

it('reopens editing of a completed Network Feasibility once the survey is reopened', function () {
    ['pipeline' => $pipeline, 'assignment' => $assignment, 'feasibility' => $feasibility] = pipelineWithProjectAndFeasibilityTasks();
    $networkSurveyor = User::factory()->networkSurveyor()->create();
    $commercialManager = User::factory()->commercialManager()->create();
    $task = $feasibility->tasks()->where('name', 'Send JO to ISP')->firstOrFail();
    $pipeline->update(['status' => PipelineStatus::Commercials]);
    $pipeline->siteSurvey->update(['network_feasibility_completed_at' => now(), 'network_feasibility_completed_by_id' => $networkSurveyor->id]);

    $this->actingAs($commercialManager)
        ->post(route('pipelines.reopen-survey', $pipeline));

    $this->actingAs($networkSurveyor)
        ->patch(route('network.feasibilities.tasks.update', [$assignment, $feasibility, $task]), [
            'status' => 'Done',
        ])
        ->assertRedirect(route('network.show', $assignment));

    expect($task->fresh()->status->value)->toBe('Done');
});

// ─── Assignment lifecycle ─────────────────────────────────────────────────────

it('creates exactly one assignment even if the pipeline re-enters site survey', function () {
    $manager = User::factory()->projectManager()->create();
    $infrastructureManager = User::factory()->infrastructureManager()->create();
    $surveyor = User::factory()->surveyor()->create();
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
        'project_manager_id' => $manager->id,
        'site_profile_id' => $siteProfile->id,
        'site_survey_id' => $survey->id,
        'status' => PipelineStatus::SiteSurveyPending,
    ]);
    $sitePlanningAssignment = SitePlanningAssignment::ensureFor($pipeline, $survey);

    $this->actingAs($infrastructureManager)
        ->patch(route('site-planning.schedule', $sitePlanningAssignment), [
            'surveyor_id' => $surveyor->id,
            'survey_date' => now()->toDateString(),
            'design_plan_type' => 'FIBER',
        ])
        ->assertRedirect();

    expect(NetworkSiteAssignment::where('pipeline_id', $pipeline->id)->count())->toBe(1);
    $firstAssignment = NetworkSiteAssignment::where('pipeline_id', $pipeline->id)->firstOrFail();
    $firstAssignedAt = $firstAssignment->assigned_at;

    $commercialManager = User::factory()->commercialManager()->create();
    $pipeline->update(['status' => PipelineStatus::Commercials]);

    $this->actingAs($commercialManager)
        ->post(route('pipelines.reopen-survey', $pipeline))
        ->assertRedirect();

    expect(NetworkSiteAssignment::where('pipeline_id', $pipeline->id)->count())->toBe(1);
    expect($firstAssignment->fresh()->id)->toBe($firstAssignment->id)
        ->and($firstAssignment->fresh()->assigned_at->equalTo($firstAssignedAt))->toBeTrue();
});

// ─── Activity / audit trail ────────────────────────────────────────────────────

it('audits network feasibility actions scoped to the assignment', function () {
    ['assignment' => $assignment] = pipelineWithNetworkAssignment();
    $networkSurveyor = User::factory()->networkSurveyor()->create();

    $this->actingAs($networkSurveyor)
        ->post(route('network.feasibilities.store', $assignment), [
            'isp_name' => 'Fiber One',
            'status' => 'Checking',
        ]);

    expect(AuditLog::where('subject_type', NetworkSiteAssignment::class)->where('subject_id', $assignment->id)->exists())->toBeTrue();

    $this->actingAs($networkSurveyor)
        ->get(route('network.show', $assignment))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Network/Show')
            ->has('activityLogs', 1));
});

it('serves projects tower-aware so the JO checklist renders for towered sites', function () {
    ['pipeline' => $pipeline, 'assignment' => $assignment] = pipelineWithNetworkAssignment();
    $networkSurveyor = User::factory()->networkSurveyor()->create();
    $tower = Tower::create(['site_profile_id' => $pipeline->site_profile_id, 'name' => 'Tower A']);
    Project::create(['pipeline_id' => $pipeline->id, 'tower_id' => $tower->id, 'current_stage' => 'Planning']);

    $this->actingAs($networkSurveyor)
        ->get(route('network.show', $assignment))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Network/Show')
            ->has('assignment.pipeline.projects', 1));
});
