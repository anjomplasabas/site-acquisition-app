<?php

use App\Enums\PipelineStatus;
use App\Enums\SidebarModule;
use App\Enums\SitePlanningActionPermission;
use App\Models\Role;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('persists action permissions through role management', function () {
    $admin = User::factory()->superAdmin()->create();

    $this->actingAs($admin)
        ->post(route('roles.store'), [
            'name' => 'Custom Workflow Role',
            'permissions' => [PipelineStatus::SiteSurveyPending->value],
            'module_permissions' => [SidebarModule::Pipelines->value],
            'action_permissions' => [
                SitePlanningActionPermission::FillSurvey->value,
                SitePlanningActionPermission::Manage->value,
            ],
        ])
        ->assertRedirect(route('roles.index'));

    $role = Role::where('name', 'Custom Workflow Role')->firstOrFail();

    expect($role->actionPermissions()->pluck('permission')->all())
        ->toContain(SitePlanningActionPermission::FillSurvey->value, SitePlanningActionPermission::Manage->value);

    $this->actingAs($admin)
        ->get(route('roles.edit', $role))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Roles/Form')
            ->has('actionPermissionOptions')
            ->has('role.action_permissions', 2)
        );
});
