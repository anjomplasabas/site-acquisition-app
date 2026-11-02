<?php

use App\Enums\ProjectActionPermission;
use App\Enums\ProjectStage;
use App\Enums\SiteProfileType;
use App\Models\Address;
use App\Models\Pipeline;
use App\Models\Project;
use App\Models\Role;
use App\Models\RoleActionPermission;
use App\Models\RoleProjectStagePermission;
use App\Models\SiteProfile;
use App\Models\User;
use App\Policies\ProjectPolicy;

function projectForPolicyTests(): Project
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

    return Project::create(['pipeline_id' => $pipeline->id]);
}

function userWithProjectActions(array $permissions = [], array $stages = []): User
{
    $role = Role::factory()->create();

    foreach ($permissions as $permission) {
        RoleActionPermission::create(['role_id' => $role->id, 'permission' => $permission->value]);
    }

    foreach ($stages as $stage) {
        RoleProjectStagePermission::create(['role_id' => $role->id, 'project_stage' => $stage->value]);
    }

    return User::factory()->create(['role_id' => $role->id]);
}

it('denies every project action when the role has no project permissions', function () {
    $project = projectForPolicyTests();
    $user = userWithProjectActions();
    $policy = new ProjectPolicy;

    expect($policy->viewAny($user))->toBeFalse()
        ->and($policy->view($user, $project))->toBeFalse()
        ->and($policy->updateStatus($user, $project))->toBeFalse()
        ->and($policy->manageStage($user, $project, ProjectStage::Planning))->toBeFalse();
});

it('grants each project action only when its specific permission is present', function (ProjectActionPermission $permission, string $method) {
    $project = projectForPolicyTests();
    $user = userWithProjectActions([$permission]);
    $policy = new ProjectPolicy;

    expect($policy->{$method}($user, $project))->toBeTrue();
})->with([
    [ProjectActionPermission::ViewProjects, 'view'],
    [ProjectActionPermission::UpdateProjectStatus, 'updateStatus'],
]);

it('requires both ManageStageFields and a stage permission to manage a stage', function () {
    $project = projectForPolicyTests();
    $policy = new ProjectPolicy;

    $actionOnly = userWithProjectActions([ProjectActionPermission::ManageStageFields]);
    $stageOnly = userWithProjectActions([], [ProjectStage::Planning]);
    $both = userWithProjectActions([ProjectActionPermission::ManageStageFields], [ProjectStage::Planning]);

    expect($policy->manageStage($actionOnly, $project, ProjectStage::Planning))->toBeFalse()
        ->and($policy->manageStage($stageOnly, $project, ProjectStage::Planning))->toBeFalse()
        ->and($policy->manageStage($both, $project, ProjectStage::Planning))->toBeTrue()
        ->and($policy->manageStage($both, $project, ProjectStage::Execution))->toBeFalse()
        ->and($policy->completeStage($both, $project, ProjectStage::Planning))->toBeTrue()
        ->and($policy->reopenStage($both, $project, ProjectStage::Planning))->toBeTrue();
});

it('lets SUPER_ADMIN bypass all project action checks via before()', function () {
    $project = projectForPolicyTests();
    $superAdminRole = Role::factory()->create(['name' => 'SUPER_ADMIN']);
    $user = User::factory()->create(['role_id' => $superAdminRole->id]);

    expect($user->can('manageStage', [$project, ProjectStage::Planning]))->toBeTrue()
        ->and($user->can('updateStatus', $project))->toBeTrue();
});
