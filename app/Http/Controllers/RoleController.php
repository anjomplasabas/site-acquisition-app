<?php

namespace App\Http\Controllers;

use App\Enums\NetworkActionPermission;
use App\Enums\PipelineActionPermission;
use App\Enums\PipelineStatus;
use App\Enums\ProjectActionPermission;
use App\Enums\ProjectStage;
use App\Enums\SidebarModule;
use App\Enums\SitePlanningActionPermission;
use App\Events\AuditLogged;
use App\Http\Requests\StoreRoleRequest;
use App\Http\Requests\UpdateRoleRequest;
use App\Models\Role;
use App\Models\RoleActionPermission;
use App\Models\RoleModulePermission;
use App\Models\RolePermission;
use App\Models\RoleProjectStagePermission;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class RoleController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Roles/Index', [
            'roles' => Role::with(['permissions', 'modulePermissions', 'actionPermissions', 'projectStagePermissions'])->orderBy('name')->get(),
            'pipelineStatuses' => array_column(PipelineStatus::cases(), 'value'),
            'moduleOptions' => SidebarModule::options(),
            'actionPermissionOptions' => PipelineActionPermission::options(),
            'projectActionPermissionOptions' => ProjectActionPermission::options(),
            'networkActionPermissionOptions' => NetworkActionPermission::options(),
            'sitePlanningActionPermissionOptions' => SitePlanningActionPermission::options(),
            'projectStages' => array_map(fn (ProjectStage $stage): array => ['value' => $stage->value, 'label' => $stage->label()], ProjectStage::cases()),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Roles/Form', [
            'pipelineStatuses' => array_column(PipelineStatus::cases(), 'value'),
            'moduleOptions' => SidebarModule::options(),
            'actionPermissionOptions' => PipelineActionPermission::options(),
            'projectActionPermissionOptions' => ProjectActionPermission::options(),
            'networkActionPermissionOptions' => NetworkActionPermission::options(),
            'sitePlanningActionPermissionOptions' => SitePlanningActionPermission::options(),
            'projectStages' => array_map(fn (ProjectStage $stage): array => ['value' => $stage->value, 'label' => $stage->label()], ProjectStage::cases()),
        ]);
    }

    public function store(StoreRoleRequest $request): RedirectResponse
    {
        $role = Role::create(['name' => $request->name]);

        foreach ($request->permissions ?? [] as $status) {
            RolePermission::create(['role_id' => $role->id, 'pipeline_status' => $status]);
        }

        foreach ($request->module_permissions ?? [] as $module) {
            RoleModulePermission::create(['role_id' => $role->id, 'module' => $module]);
        }

        foreach ($request->action_permissions ?? [] as $permission) {
            RoleActionPermission::create(['role_id' => $role->id, 'permission' => $permission]);
        }

        foreach ($request->project_stage_permissions ?? [] as $stage) {
            RoleProjectStagePermission::create(['role_id' => $role->id, 'project_stage' => $stage]);
        }

        return redirect()->route('roles.index')
            ->with('success', 'Role created successfully.');
    }

    public function edit(Role $role): Response
    {
        return Inertia::render('Roles/Form', [
            'role' => $role->load(['permissions', 'modulePermissions', 'actionPermissions', 'projectStagePermissions']),
            'pipelineStatuses' => array_column(PipelineStatus::cases(), 'value'),
            'moduleOptions' => SidebarModule::options(),
            'actionPermissionOptions' => PipelineActionPermission::options(),
            'projectActionPermissionOptions' => ProjectActionPermission::options(),
            'networkActionPermissionOptions' => NetworkActionPermission::options(),
            'sitePlanningActionPermissionOptions' => SitePlanningActionPermission::options(),
            'projectStages' => array_map(fn (ProjectStage $stage): array => ['value' => $stage->value, 'label' => $stage->label()], ProjectStage::cases()),
        ]);
    }

    public function update(UpdateRoleRequest $request, Role $role): RedirectResponse
    {
        $role->update(['name' => $request->name]);

        $role->permissions()->delete();
        $role->modulePermissions()->delete();
        $role->actionPermissions()->delete();
        $role->projectStagePermissions()->delete();

        foreach ($request->permissions ?? [] as $status) {
            RolePermission::create(['role_id' => $role->id, 'pipeline_status' => $status]);
        }

        foreach ($request->module_permissions ?? [] as $module) {
            RoleModulePermission::create(['role_id' => $role->id, 'module' => $module]);
        }

        foreach ($request->action_permissions ?? [] as $permission) {
            RoleActionPermission::create(['role_id' => $role->id, 'permission' => $permission]);
        }

        foreach ($request->project_stage_permissions ?? [] as $stage) {
            RoleProjectStagePermission::create(['role_id' => $role->id, 'project_stage' => $stage]);
        }

        return redirect()->route('roles.index')
            ->with('success', 'Role updated successfully.');
    }

    public function destroy(Role $role): RedirectResponse
    {
        if ($role->users()->exists()) {
            AuditLogged::emit('delete_blocked', "Blocked deletion of role {$role->name} (still has assigned users)", $role);

            return redirect()->route('roles.index')
                ->with('error', "Cannot delete \"{$role->name}\" — it still has users assigned to it.");
        }

        $role->delete();

        return redirect()->route('roles.index')
            ->with('success', 'Role deleted.');
    }
}
