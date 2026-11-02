<?php

namespace Database\Factories;

use App\Enums\NetworkActionPermission;
use App\Enums\PipelineActionPermission;
use App\Enums\PipelineStatus;
use App\Enums\ProjectActionPermission;
use App\Enums\ProjectStage;
use App\Enums\SidebarModule;
use App\Enums\SitePlanningActionPermission;
use App\Models\Department;
use App\Models\Role;
use App\Models\RoleActionPermission;
use App\Models\RoleModulePermission;
use App\Models\RolePermission;
use App\Models\RoleProjectStagePermission;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'full_name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$password ??= Hash::make('password'),
            'role_id' => Role::factory(),
            'department_id' => Department::factory(),
            'status' => 'ACTIVE',
            'remember_token' => Str::random(10),
        ];
    }

    public function inactive(): static
    {
        return $this->state(['status' => 'INACTIVE']);
    }

    public function superAdmin(): static
    {
        return $this->state(['role_id' => Role::firstOrCreate(['name' => 'SUPER_ADMIN'])->id]);
    }

    public function projectManager(): static
    {
        return $this->leadSaqSpecialist();
    }

    public function leadSaqSpecialist(): static
    {
        return $this->state(function () {
            $role = Role::firstOrCreate(['name' => 'Lead SAQ Specialist']);

            foreach ([PipelineStatus::Prospect, PipelineStatus::Prospecting] as $status) {
                RolePermission::firstOrCreate([
                    'role_id' => $role->id,
                    'pipeline_status' => $status->value,
                ]);
            }

            $this->ensureDefaultModulePermissions($role);
            $this->ensureActionPermissions($role, [
                PipelineActionPermission::ViewAll,
                PipelineActionPermission::AssignSaq,
                PipelineActionPermission::MarkInactive,
            ]);
            $this->ensureActionPermissions($role, [ProjectActionPermission::ViewProjects]);

            return ['role_id' => $role->id];
        });
    }

    public function saqSpecialist(): static
    {
        return $this->state(function () {
            $role = Role::firstOrCreate(['name' => 'SAQ Specialist']);

            foreach ([PipelineStatus::Prospect, PipelineStatus::Prospecting] as $status) {
                RolePermission::firstOrCreate([
                    'role_id' => $role->id,
                    'pipeline_status' => $status->value,
                ]);
            }

            $this->ensureDefaultModulePermissions($role);
            $this->ensureActionPermissions($role, [
                PipelineActionPermission::ViewAssignedSaq,
                PipelineActionPermission::MoveToSiteSurveyPending,
                PipelineActionPermission::MarkInactive,
            ]);
            $this->ensureActionPermissions($role, [ProjectActionPermission::ViewProjects]);

            return ['role_id' => $role->id];
        });
    }

    public function infrastructureManager(): static
    {
        return $this->state(function () {
            $role = Role::firstOrCreate(['name' => 'Infrastructure Manager']);

            // Kept solely so MarkInactive's current-stage check still works for this role.
            RolePermission::firstOrCreate([
                'role_id' => $role->id,
                'pipeline_status' => PipelineStatus::SiteSurveyPending->value,
            ]);

            $this->ensureDefaultModulePermissions($role);
            $this->ensureSitePlanningModulePermissions($role);
            $this->ensureActionPermissions($role, [
                PipelineActionPermission::ViewAll,
                PipelineActionPermission::MarkInactive,
            ]);
            // ApproveDesignPlan and AssignSiteSurveyor are the two places this role and
            // Infrastructure Specialist intentionally diverge (see RoleSeeder).
            $this->ensureActionPermissions($role, [
                SitePlanningActionPermission::ListAssignments,
                SitePlanningActionPermission::Manage,
                SitePlanningActionPermission::AssignSiteSurveyor,
                SitePlanningActionPermission::ApproveDesignPlan,
                SitePlanningActionPermission::AssignDesignPlan,
            ]);
            // Infrastructure owns most of the project delivery work breakdown —
            // full Project Management action set and every stage.
            $this->ensureActionPermissions($role, ProjectActionPermission::cases());
            $this->ensureProjectStagePermissions($role, ProjectStage::cases());

            return ['role_id' => $role->id];
        });
    }

    public function infrastructureSpecialist(): static
    {
        return $this->state(function () {
            $role = Role::firstOrCreate(['name' => 'Infrastructure Specialist']);

            RolePermission::firstOrCreate([
                'role_id' => $role->id,
                'pipeline_status' => PipelineStatus::SiteSurveyPending->value,
            ]);

            $this->ensureDefaultModulePermissions($role);
            $this->ensureSitePlanningModulePermissions($role);
            $this->ensureActionPermissions($role, [
                PipelineActionPermission::ViewAll,
                PipelineActionPermission::MarkInactive,
            ]);
            $this->ensureActionPermissions($role, [
                SitePlanningActionPermission::ListAssignments,
                SitePlanningActionPermission::Manage,
                SitePlanningActionPermission::AssignDesignPlan,
            ]);
            // Intentionally identical to Infrastructure Manager — see RoleSeeder.
            $this->ensureActionPermissions($role, ProjectActionPermission::cases());
            $this->ensureProjectStagePermissions($role, ProjectStage::cases());

            return ['role_id' => $role->id];
        });
    }

    public function surveyor(): static
    {
        return $this->state(function () {
            $role = Role::firstOrCreate(['name' => 'Surveyor']);

            // Kept solely so MarkInactive's current-stage check still works for this role.
            RolePermission::firstOrCreate([
                'role_id' => $role->id,
                'pipeline_status' => PipelineStatus::SiteSurvey->value,
            ]);

            $this->ensureDefaultModulePermissions($role);
            $this->ensureSitePlanningModulePermissions($role);
            // Deliberately not ViewAll: a Surveyor should only see/act on the pipeline they
            // are actually assigned to survey (siteSurvey.surveyor_id), not every pipeline in
            // the system — see PipelineVisibility/PipelinePolicy's SitePlanningActionPermission
            // ::FillSurvey-gated checks.
            $this->ensureActionPermissions($role, [PipelineActionPermission::MarkInactive]);
            $this->ensureActionPermissions($role, [
                SitePlanningActionPermission::ListAssignments,
                SitePlanningActionPermission::FillSurvey,
            ]);
            $this->ensureActionPermissions($role, [ProjectActionPermission::ViewProjects]);

            return ['role_id' => $role->id];
        });
    }

    public function infraDesigner(): static
    {
        return $this->state(function () {
            $role = Role::firstOrCreate(['name' => 'Infra Designer']);

            $this->ensureDefaultModulePermissions($role);
            $this->ensureSitePlanningModulePermissions($role);
            $this->ensureActionPermissions($role, [
                PipelineActionPermission::ViewAll,
                PipelineActionPermission::MarkInactive,
            ]);
            $this->ensureActionPermissions($role, [
                SitePlanningActionPermission::ListAssignments,
                SitePlanningActionPermission::DesignPlan,
            ]);
            $this->ensureActionPermissions($role, [ProjectActionPermission::ViewProjects]);

            return ['role_id' => $role->id];
        });
    }

    public function networkSurveyor(): static
    {
        return $this->state(function () {
            $role = Role::firstOrCreate(['name' => 'Network Surveyor']);

            $this->ensureNetworkModulePermissions($role);
            $this->ensureActionPermissions($role, [
                NetworkActionPermission::ViewNetworkAssignments,
                NetworkActionPermission::ManageNetworkFeasibility,
            ]);
            // NETWORK: progresses ISP-related stage fields; no structural WBS rights.
            $this->ensureActionPermissions($role, [
                ProjectActionPermission::ViewProjects,
                ProjectActionPermission::ManageStageFields,
            ]);
            // Read-only Pipelines visibility (no mutating pipeline actions).
            $this->ensureActionPermissions($role, [PipelineActionPermission::ViewAll]);
            $this->ensureProjectStagePermissions($role, [ProjectStage::Planning, ProjectStage::Execution, ProjectStage::Monitoring]);

            return ['role_id' => $role->id];
        });
    }

    public function networkManager(): static
    {
        return $this->state(function () {
            $role = Role::firstOrCreate(['name' => 'Network Manager']);

            $this->ensureNetworkModulePermissions($role);
            // Manager-level NETWORK role: same Network module scope as Network Surveyor.
            $this->ensureActionPermissions($role, [
                NetworkActionPermission::ViewNetworkAssignments,
                NetworkActionPermission::ManageNetworkFeasibility,
            ]);
            $this->ensureActionPermissions($role, [
                ProjectActionPermission::ViewProjects,
                ProjectActionPermission::ManageStageFields,
            ]);
            // Read-only Pipelines visibility (no mutating pipeline actions).
            $this->ensureActionPermissions($role, [PipelineActionPermission::ViewAll]);
            $this->ensureProjectStagePermissions($role, [ProjectStage::Planning, ProjectStage::Execution, ProjectStage::Monitoring]);

            return ['role_id' => $role->id];
        });
    }

    public function networkSpecialist(): static
    {
        return $this->state(function () {
            $role = Role::firstOrCreate(['name' => 'Network Specialist']);

            $this->ensureNetworkModulePermissions($role);
            // Intentionally identical to Network Manager — see RoleSeeder.
            $this->ensureActionPermissions($role, [
                NetworkActionPermission::ViewNetworkAssignments,
                NetworkActionPermission::ManageNetworkFeasibility,
            ]);
            $this->ensureActionPermissions($role, [
                ProjectActionPermission::ViewProjects,
                ProjectActionPermission::ManageStageFields,
            ]);
            $this->ensureActionPermissions($role, [PipelineActionPermission::ViewAll]);
            $this->ensureProjectStagePermissions($role, [ProjectStage::Planning, ProjectStage::Execution, ProjectStage::Monitoring]);

            return ['role_id' => $role->id];
        });
    }

    public function commercialManager(): static
    {
        return $this->state(function () {
            $role = Role::firstOrCreate(['name' => 'COMMERCIAL_MANAGER']);

            foreach ([PipelineStatus::Commercials, PipelineStatus::Approval] as $status) {
                RolePermission::firstOrCreate(['role_id' => $role->id, 'pipeline_status' => $status->value]);
            }

            $this->ensureDefaultModulePermissions($role);
            $this->ensureActionPermissions($role, [
                PipelineActionPermission::HandleCommercials,
                PipelineActionPermission::DecideApproval,
                PipelineActionPermission::MarkInactive,
            ]);
            $this->ensureActionPermissions($role, [ProjectActionPermission::ViewProjects]);

            return ['role_id' => $role->id];
        });
    }

    public function coo(): static
    {
        return $this->state(function () {
            $role = Role::firstOrCreate(['name' => 'COO']);

            foreach ([PipelineStatus::Commercials, PipelineStatus::Approval, PipelineStatus::Completed] as $status) {
                RolePermission::firstOrCreate(['role_id' => $role->id, 'pipeline_status' => $status->value]);
            }

            RoleModulePermission::firstOrCreate(['role_id' => $role->id, 'module' => SidebarModule::Dashboard->value]);
            RoleModulePermission::firstOrCreate(['role_id' => $role->id, 'module' => SidebarModule::Pipelines->value]);
            RoleModulePermission::firstOrCreate(['role_id' => $role->id, 'module' => SidebarModule::Projects->value]);

            $this->ensureActionPermissions($role, [PipelineActionPermission::DecideApproval]);
            $this->ensureActionPermissions($role, [ProjectActionPermission::ViewProjects]);

            return ['role_id' => $role->id];
        });
    }

    private function ensureDefaultModulePermissions(Role $role): void
    {
        foreach ([SidebarModule::Dashboard, SidebarModule::Pipelines, SidebarModule::Projects] as $module) {
            RoleModulePermission::firstOrCreate([
                'role_id' => $role->id,
                'module' => $module->value,
            ]);
        }
    }

    private function ensureNetworkModulePermissions(Role $role): void
    {
        foreach ([SidebarModule::Dashboard, SidebarModule::Network, SidebarModule::Pipelines, SidebarModule::Projects] as $module) {
            RoleModulePermission::firstOrCreate([
                'role_id' => $role->id,
                'module' => $module->value,
            ]);
        }
    }

    private function ensureSitePlanningModulePermissions(Role $role): void
    {
        RoleModulePermission::firstOrCreate([
            'role_id' => $role->id,
            'module' => SidebarModule::SitePlanning->value,
        ]);
    }

    /**
     * @param  array<int, PipelineActionPermission|ProjectActionPermission|NetworkActionPermission|SitePlanningActionPermission>  $permissions
     */
    private function ensureActionPermissions(Role $role, array $permissions): void
    {
        foreach ($permissions as $permission) {
            RoleActionPermission::firstOrCreate([
                'role_id' => $role->id,
                'permission' => $permission->value,
            ]);
        }
    }

    /**
     * @param  array<int, ProjectStage>  $stages
     */
    private function ensureProjectStagePermissions(Role $role, array $stages): void
    {
        foreach ($stages as $stage) {
            RoleProjectStagePermission::firstOrCreate([
                'role_id' => $role->id,
                'project_stage' => $stage->value,
            ]);
        }
    }
}
