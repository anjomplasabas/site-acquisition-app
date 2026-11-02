<?php

use App\Enums\DocumentType;
use App\Enums\NetworkActionPermission;
use App\Enums\ProjectActionPermission;
use App\Enums\SidebarModule;
use App\Enums\SiteProfileType;
use App\Models\Address;
use App\Models\Document;
use App\Models\Pipeline;
use App\Models\Project;
use App\Models\ProjectStage;
use App\Models\Role;
use App\Models\RoleActionPermission;
use App\Models\RoleModulePermission;
use App\Models\RoleProjectStagePermission;
use App\Models\SitePlanningAssignment;
use App\Models\SiteProfile;
use App\Models\SiteSurvey;
use App\Models\StageTask;
use App\Models\Tower;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;

function projectManagementFixture(): array
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
    $stage = ProjectStage::create(['project_id' => $project->id, 'stage' => 'Planning', 'status' => 'Pending', 'order' => 1]);

    return compact('project', 'stage');
}

function userScopedToProjects(array $projectActions = [], array $projectStages = []): User
{
    $role = Role::factory()->create();
    RoleModulePermission::create(['role_id' => $role->id, 'module' => SidebarModule::Dashboard->value]);
    RoleModulePermission::create(['role_id' => $role->id, 'module' => SidebarModule::Projects->value]);

    foreach ($projectActions as $permission) {
        RoleActionPermission::create(['role_id' => $role->id, 'permission' => $permission->value]);
    }

    foreach ($projectStages as $stage) {
        RoleProjectStagePermission::create(['role_id' => $role->id, 'project_stage' => $stage]);
    }

    return User::factory()->create(['role_id' => $role->id]);
}

it('hides BOQ and PR documents from a user who only holds ViewProjects, not ManageStageFields', function () {
    ['project' => $project, 'stage' => $stage] = projectManagementFixture();
    $boq = Document::create(['name' => 'boq.pdf', 'type' => DocumentType::Document, 'filename' => 'boq.pdf', 'file_path' => 'x', 'category' => 'BOQ']);
    $pr = Document::create(['name' => 'pr.pdf', 'type' => DocumentType::Document, 'filename' => 'pr.pdf', 'file_path' => 'x', 'category' => 'PR']);
    $moa = Document::create(['name' => 'moa.pdf', 'type' => DocumentType::Document, 'filename' => 'moa.pdf', 'file_path' => 'x', 'category' => 'MOA']);
    $stage->documents()->attach([$boq->id, $pr->id, $moa->id], ['created_at' => now()]);

    $viewOnlyUser = userScopedToProjects([ProjectActionPermission::ViewProjects]);

    $this->actingAs($viewOnlyUser)
        ->get(route('projects.show', $project))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Projects/Show')
            ->has('project.stages.0.documents', 1)
            ->where('project.stages.0.documents.0.category', 'MOA')
        );

    $managerUser = userScopedToProjects([ProjectActionPermission::ViewProjects, ProjectActionPermission::ManageStageFields], ['Planning']);

    $this->actingAs($managerUser)
        ->get(route('projects.show', $project))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Projects/Show')
            ->has('project.stages.0.documents', 3)
        );
});

it('denies the project list to a role without ViewProjects', function () {
    $user = userScopedToProjects();

    $this->actingAs($user)->get(route('projects'))->assertForbidden();
});

it('allows the project list to a role with ViewProjects', function () {
    $user = userScopedToProjects([ProjectActionPermission::ViewProjects]);

    $this->actingAs($user)->get(route('projects'))->assertSuccessful();
});

it('denies updating project status without UpdateProjectStatus', function () {
    ['project' => $project] = projectManagementFixture();
    $user = userScopedToProjects([ProjectActionPermission::ManageStageFields], ['Planning']);

    $this->actingAs($user)
        ->patch(route('projects.update', $project), ['status' => 'On Hold'])
        ->assertForbidden();
});

it('allows updating project status with UpdateProjectStatus', function () {
    ['project' => $project] = projectManagementFixture();
    $user = userScopedToProjects([ProjectActionPermission::UpdateProjectStatus]);

    $this->actingAs($user)
        ->patch(route('projects.update', $project), ['status' => 'On Hold'])
        ->assertRedirect();

    expect($project->fresh()->status->value)->toBe('On Hold');
});

it('blocks marking a project Completed while any stage is not yet Completed', function () {
    ['project' => $project] = projectManagementFixture();
    $user = userScopedToProjects([ProjectActionPermission::UpdateProjectStatus]);

    $this->actingAs($user)
        ->patch(route('projects.update', $project), ['status' => 'Completed'])
        ->assertSessionHasErrors('status');

    expect($project->fresh()->status->value)->not->toBe('Completed');
});

it('blocks marking a project Completed when it has no stages at all', function () {
    $address = Address::create(['city' => 'Manila']);
    $siteProfile = SiteProfile::create(['name' => 'No Stages Site', 'type' => SiteProfileType::Condo, 'address_id' => $address->id]);
    $pipeline = Pipeline::create(['name' => 'No Stages Pipeline', 'pipeline_id' => nextPipelineId(), 'site_profile_id' => $siteProfile->id]);
    $project = Project::create(['pipeline_id' => $pipeline->id, 'current_stage' => 'Planning']);
    $user = userScopedToProjects([ProjectActionPermission::UpdateProjectStatus]);

    $this->actingAs($user)
        ->patch(route('projects.update', $project), ['status' => 'Completed'])
        ->assertSessionHasErrors('status');
});

it('allows marking a project Completed once every stage is Completed', function () {
    ['project' => $project, 'stage' => $stage] = projectManagementFixture();
    $stage->update(['status' => 'Completed']);
    $user = userScopedToProjects([ProjectActionPermission::UpdateProjectStatus]);

    $this->actingAs($user)
        ->patch(route('projects.update', $project), ['status' => 'Completed'])
        ->assertRedirect();

    expect($project->fresh()->status->value)->toBe('Completed');
});

it('blocks updating a stage field while the project is On Hold', function () {
    ['project' => $project, 'stage' => $stage] = projectManagementFixture();
    $project->update(['status' => 'On Hold']);
    $user = userScopedToProjects([ProjectActionPermission::ManageStageFields], ['Planning']);

    $this->actingAs($user)
        ->patch(route('projects.stages.update', [$project, $stage]), ['field_values' => ['contractor' => 'JGD']])
        ->assertForbidden();
});

it('blocks updating a stage field while the project is Completed', function () {
    ['project' => $project, 'stage' => $stage] = projectManagementFixture();
    $project->update(['status' => 'Completed']);
    $user = userScopedToProjects([ProjectActionPermission::ManageStageFields], ['Planning']);

    $this->actingAs($user)
        ->patch(route('projects.stages.update', [$project, $stage]), ['field_values' => ['contractor' => 'JGD']])
        ->assertForbidden();
});

it('blocks updating a stage field while the project is Cancelled', function () {
    ['project' => $project, 'stage' => $stage] = projectManagementFixture();
    $project->update(['status' => 'Cancelled']);
    $user = userScopedToProjects([ProjectActionPermission::ManageStageFields], ['Planning']);

    $this->actingAs($user)
        ->patch(route('projects.stages.update', [$project, $stage]), ['field_values' => ['contractor' => 'JGD']])
        ->assertForbidden();
});

it('still allows viewing a paused project even though stage mutation is blocked', function () {
    ['project' => $project] = projectManagementFixture();
    $project->update(['status' => 'On Hold']);
    $user = userScopedToProjects([ProjectActionPermission::ViewProjects, ProjectActionPermission::ManageStageFields], ['Planning']);

    $this->actingAs($user)->get(route('projects.show', $project))->assertSuccessful();
});

it('blocks updating Monitoring & Controlling stage fields while the project is On Hold', function () {
    $address = Address::create(['city' => 'Manila']);
    $siteProfile = SiteProfile::create(['name' => 'Test Site', 'type' => SiteProfileType::Condo, 'address_id' => $address->id]);
    $pipeline = Pipeline::create(['name' => 'Test Pipeline', 'pipeline_id' => nextPipelineId(), 'site_profile_id' => $siteProfile->id]);
    $project = Project::create(['pipeline_id' => $pipeline->id, 'current_stage' => 'Monitoring', 'status' => 'On Hold']);
    $monitoringStage = ProjectStage::create(['project_id' => $project->id, 'stage' => 'Monitoring', 'status' => 'Pending', 'order' => 3]);
    $user = userScopedToProjects([ProjectActionPermission::ManageStageFields], ['Monitoring']);

    $this->actingAs($user)
        ->patch(route('projects.stages.update', [$project, $monitoringStage]), ['field_values' => ['project_status' => 'On-going']])
        ->assertForbidden();
});

it('blocks adding an ISP entry to a stage while the project is On Hold', function () {
    ['project' => $project, 'stage' => $stage] = projectManagementFixture();
    $project->update(['status' => 'On Hold']);
    $user = userScopedToProjects([ProjectActionPermission::ManageStageFields], ['Planning']);

    $this->actingAs($user)
        ->post(route('projects.stages.isps.store', [$project, $stage]), ['isp_name' => 'PhilCom'])
        ->assertForbidden();
});

it('denies updating a stage without ManageStageFields even with the right stage permission', function () {
    ['project' => $project, 'stage' => $stage] = projectManagementFixture();
    $user = userScopedToProjects([], ['Planning']);

    $this->actingAs($user)
        ->patch(route('projects.stages.update', [$project, $stage]), ['field_values' => ['contractor' => 'JGD']])
        ->assertForbidden();
});

it('denies updating a stage with ManageStageFields but the wrong stage permission', function () {
    ['project' => $project, 'stage' => $stage] = projectManagementFixture();
    $user = userScopedToProjects([ProjectActionPermission::ManageStageFields], ['Execution']);

    $this->actingAs($user)
        ->patch(route('projects.stages.update', [$project, $stage]), ['field_values' => ['contractor' => 'JGD']])
        ->assertForbidden();
});

it('allows updating a stage with ManageStageFields and the matching stage permission', function () {
    ['project' => $project, 'stage' => $stage] = projectManagementFixture();
    $user = userScopedToProjects([ProjectActionPermission::ManageStageFields], ['Planning']);

    $this->actingAs($user)
        ->patch(route('projects.stages.update', [$project, $stage]), ['field_values' => ['contractor' => 'JGD']])
        ->assertRedirect();

    expect($stage->fresh()->field_values['contractor'])->toBe('JGD');
});

it('persists project action permissions through role management', function () {
    $admin = User::factory()->superAdmin()->create();

    $this->actingAs($admin)
        ->post(route('roles.store'), [
            'name' => 'Custom Project Role',
            'module_permissions' => [SidebarModule::Projects->value],
            'action_permissions' => [
                ProjectActionPermission::ManageStageFields->value,
                ProjectActionPermission::ViewProjects->value,
            ],
            'project_stage_permissions' => ['Planning'],
        ])
        ->assertRedirect(route('roles.index'));

    $role = Role::where('name', 'Custom Project Role')->firstOrFail();

    expect($role->actionPermissions()->pluck('permission')->all())
        ->toContain(ProjectActionPermission::ManageStageFields->value, ProjectActionPermission::ViewProjects->value)
        ->and($role->projectStagePermissions()->pluck('project_stage')->all())
        ->toContain('Planning');

    $this->actingAs($admin)
        ->get(route('roles.edit', $role))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Roles/Form')
            ->has('projectActionPermissionOptions')
            ->has('role.action_permissions', 2)
        );
});

it('serves the tower-aware Site Planning assignment to a towered project\'s show page', function () {
    ['project' => $project] = projectManagementFixture();
    $pipeline = $project->pipeline;
    $tower = Tower::create(['site_profile_id' => $pipeline->site_profile_id, 'name' => 'Tower A']);
    $survey = SiteSurvey::create(['site_profile_id' => $pipeline->site_profile_id, 'pipeline_id' => $pipeline->id, 'tower_id' => $tower->id]);
    $assignment = SitePlanningAssignment::create([
        'pipeline_id' => $pipeline->id,
        'site_survey_id' => $survey->id,
        'tower_id' => $tower->id,
        'assigned_at' => now(),
        'design_plan_type' => 'Residential',
        'design_plan_approved_at' => now(),
    ]);
    $project->update(['tower_id' => $tower->id]);
    $user = userScopedToProjects([ProjectActionPermission::ViewProjects]);

    $this->actingAs($user)->get(route('projects.show', $project))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Projects/Show')
            ->where('sitePlanningAssignment.id', $assignment->id)
            ->where('sitePlanningAssignment.design_plan_type', 'Residential'));
});

// ─── Field-level authorization ────────────────────────────────────────

function networkUserScopedToProjects(array $projectStages = ['Planning']): User
{
    $user = userScopedToProjects([ProjectActionPermission::ViewProjects, ProjectActionPermission::ManageStageFields], $projectStages);
    RoleActionPermission::create(['role_id' => $user->role_id, 'permission' => NetworkActionPermission::ManageNetworkFeasibility->value]);

    return $user;
}

it('blocks a Network user from editing Infra-owned Planning fields', function () {
    ['project' => $project, 'stage' => $stage] = projectManagementFixture();
    $user = networkUserScopedToProjects(['Planning']);

    $this->actingAs($user)
        ->patch(route('projects.stages.update', [$project, $stage]), ['field_values' => ['contractor' => 'JGD']])
        ->assertForbidden();

    expect($stage->fresh()->field_values['contractor'] ?? null)->toBeNull();
});

it('blocks a Network user from uploading a BOQ document', function () {
    Storage::fake('public');
    ['project' => $project, 'stage' => $stage] = projectManagementFixture();
    $user = networkUserScopedToProjects(['Planning']);

    $this->actingAs($user)
        ->post(route('projects.stages.documents.store', [$project, $stage]), [
            'document_category' => 'BOQ',
            'files' => [UploadedFile::fake()->create('boq.pdf', 10, 'application/pdf')],
        ])
        ->assertForbidden();

    expect($stage->fresh()->documents()->count())->toBe(0);
});

it('blocks a Network user from editing Infra-owned Execution fields', function () {
    ['project' => $project] = projectManagementFixture();
    $executionStage = ProjectStage::create(['project_id' => $project->id, 'stage' => 'Execution', 'status' => 'Pending', 'order' => 2]);
    $project->update(['current_stage' => 'Execution']);
    $user = networkUserScopedToProjects(['Execution']);

    $this->actingAs($user)
        ->patch(route('projects.stages.update', [$project, $executionStage]), ['field_values' => ['kick_off_meeting' => '2026-10-20']])
        ->assertForbidden();
});

it('blocks a Network user from updating an Infra stage task on Monitoring', function () {
    ['project' => $project] = projectManagementFixture();
    $monitoringStage = ProjectStage::create(['project_id' => $project->id, 'stage' => 'Monitoring', 'status' => 'Pending', 'order' => 3]);
    $project->update(['current_stage' => 'Monitoring']);
    $task = StageTask::create(['project_stage_id' => $monitoringStage->id, 'field_key' => 'infrastructure_deployment_status', 'name' => 'Pole erection', 'status' => 'Pending', 'weight' => 10, 'sort_order' => 1]);
    $user = networkUserScopedToProjects(['Monitoring']);

    $this->actingAs($user)
        ->patch(route('projects.stages.stage-tasks.update', [$project, $monitoringStage, $task]), ['status' => 'Done'])
        ->assertForbidden();

    expect($task->fresh()->status->value)->toBe('Pending');
});

it('still lets an Infra-side user edit Infra-owned fields and upload BOQ', function () {
    Storage::fake('public');
    ['project' => $project, 'stage' => $stage] = projectManagementFixture();
    $user = userScopedToProjects([ProjectActionPermission::ManageStageFields], ['Planning']);

    $this->actingAs($user)
        ->patch(route('projects.stages.update', [$project, $stage]), ['field_values' => ['contractor' => 'JTE']])
        ->assertRedirect();
    expect($stage->fresh()->field_values['contractor'])->toBe('JTE');

    $this->actingAs($user)
        ->post(route('projects.stages.documents.store', [$project, $stage]), [
            'document_category' => 'BOQ',
            'files' => [UploadedFile::fake()->create('boq.pdf', 10, 'application/pdf')],
        ])
        ->assertRedirect();
    expect($stage->fresh()->documents()->where('category', 'BOQ')->count())->toBe(1);
});

it('still lets a Network user edit the shared ISP Status date field', function () {
    ['project' => $project, 'stage' => $stage] = projectManagementFixture();
    $user = networkUserScopedToProjects(['Planning']);

    $this->actingAs($user)
        ->patch(route('projects.stages.update', [$project, $stage]), ['field_values' => ['target_activation_date' => '2026-12-01']])
        ->assertRedirect();

    expect($stage->fresh()->field_values['target_activation_date'])->toBe('2026-12-01');
});

it('still lets a Super Admin edit Infra-owned fields', function () {
    ['project' => $project, 'stage' => $stage] = projectManagementFixture();
    $admin = User::factory()->superAdmin()->create();

    $this->actingAs($admin)
        ->patch(route('projects.stages.update', [$project, $stage]), ['field_values' => ['contractor' => 'JGD']])
        ->assertRedirect();

    expect($stage->fresh()->field_values['contractor'])->toBe('JGD');
});
