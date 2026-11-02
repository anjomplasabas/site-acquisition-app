<?php

namespace Database\Seeders;

use App\Enums\NetworkActionPermission;
use App\Enums\PipelineActionPermission;
use App\Enums\PipelineStatus;
use App\Enums\ProjectActionPermission;
use App\Enums\ProjectStage;
use App\Enums\SidebarModule;
use App\Enums\SitePlanningActionPermission;
use App\Models\Role;
use App\Models\RoleActionPermission;
use App\Models\RoleModulePermission;
use App\Models\RolePermission;
use App\Models\RoleProjectStagePermission;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            'SUPER_ADMIN' => [
                'statuses' => [],
                'modules' => array_column(SidebarModule::cases(), 'value'),
                'actions' => [
                    ...array_column(PipelineActionPermission::cases(), 'value'),
                    ...array_column(ProjectActionPermission::cases(), 'value'),
                    ...array_column(NetworkActionPermission::cases(), 'value'),
                    ...array_column(SitePlanningActionPermission::cases(), 'value'),
                ],
            ],
            'Lead SAQ Specialist' => [
                'statuses' => [
                    PipelineStatus::Prospect,
                    PipelineStatus::Prospecting,
                ],
                'actions' => [
                    PipelineActionPermission::ViewAll,
                    PipelineActionPermission::AssignSaq,
                    PipelineActionPermission::MarkInactive,
                ],
                'modules' => [
                    SidebarModule::Dashboard,
                    SidebarModule::Pipelines,
                    SidebarModule::Projects,
                ],
                // SAQ owns Project Initiation (Pipeline), not WBS execution — visibility only.
                'project_actions' => [
                    ProjectActionPermission::ViewProjects,
                ],
            ],
            'SAQ Specialist' => [
                'statuses' => [
                    PipelineStatus::Prospect,
                    PipelineStatus::Prospecting,
                ],
                'actions' => [
                    PipelineActionPermission::ViewAssignedSaq,
                    PipelineActionPermission::MoveToSiteSurveyPending,
                    PipelineActionPermission::MarkInactive,
                ],
                'modules' => [
                    SidebarModule::Dashboard,
                    SidebarModule::Pipelines,
                    SidebarModule::Projects,
                ],
                'project_actions' => [
                    ProjectActionPermission::ViewProjects,
                ],
            ],
            'Infrastructure Manager' => [
                // Kept solely so MarkInactive's current-stage check still works for this role —
                // scheduling/assignment permissions themselves moved to Site Planning below.
                'statuses' => [
                    PipelineStatus::SiteSurveyPending,
                ],
                // Assignment/scheduling now lives in the Site Planning module (see
                // 'site_planning_actions' below) — this role keeps only read-only Pipelines
                // visibility, mirroring how the Network roles relate to Pipelines.
                'actions' => [
                    PipelineActionPermission::ViewAll,
                    PipelineActionPermission::MarkInactive,
                ],
                'modules' => [
                    SidebarModule::Dashboard,
                    SidebarModule::SitePlanning,
                    SidebarModule::Pipelines,
                    SidebarModule::Projects,
                ],
                // ApproveDesignPlan and AssignSiteSurveyor are the two places this role and
                // Infrastructure Specialist intentionally diverge — sign-off on the design plan,
                // and who's allowed to assign/schedule the Surveyor, both imply a tier above
                // Specialist, so only Manager gets them.
                'site_planning_actions' => [
                    SitePlanningActionPermission::ListAssignments,
                    SitePlanningActionPermission::Manage,
                    SitePlanningActionPermission::AssignSiteSurveyor,
                    SitePlanningActionPermission::ApproveDesignPlan,
                    SitePlanningActionPermission::AssignDesignPlan,
                ],
                // INFRA is the dominant tag across almost all Project Management stages —
                // this role owns the WBS, so it keeps the full action set and every stage.
                'project_actions' => ProjectActionPermission::cases(),
                'project_stages' => ProjectStage::cases(),
            ],
            // Intentionally identical to Infrastructure Manager everywhere except
            // ApproveDesignPlan and AssignSiteSurveyor (see that role's comment) — a
            // title/org-chart tier, not a reduced-permission role otherwise, per product decision.
            'Infrastructure Specialist' => [
                'statuses' => [
                    PipelineStatus::SiteSurveyPending,
                ],
                'actions' => [
                    PipelineActionPermission::ViewAll,
                    PipelineActionPermission::MarkInactive,
                ],
                'modules' => [
                    SidebarModule::Dashboard,
                    SidebarModule::SitePlanning,
                    SidebarModule::Pipelines,
                    SidebarModule::Projects,
                ],
                'site_planning_actions' => [
                    SitePlanningActionPermission::ListAssignments,
                    SitePlanningActionPermission::Manage,
                    SitePlanningActionPermission::AssignDesignPlan,
                ],
                'project_actions' => ProjectActionPermission::cases(),
                'project_stages' => ProjectStage::cases(),
            ],
            'Surveyor' => [
                // Kept solely so MarkInactive's current-stage check still works for this role —
                // filling in the inspection form itself moved to Site Planning below.
                'statuses' => [
                    PipelineStatus::SiteSurvey,
                ],
                // Filling in the inspection form now lives in the Site Planning module (see
                // 'site_planning_actions' below) — this role keeps only read-only Pipelines
                // visibility, mirroring how the Network roles relate to Pipelines.
                'actions' => [
                    PipelineActionPermission::ViewAll,
                    PipelineActionPermission::MarkInactive,
                ],
                'modules' => [
                    SidebarModule::Dashboard,
                    SidebarModule::SitePlanning,
                    SidebarModule::Pipelines,
                    SidebarModule::Projects,
                ],
                'site_planning_actions' => [
                    SitePlanningActionPermission::ListAssignments,
                    SitePlanningActionPermission::FillSurvey,
                ],
                'project_actions' => [
                    ProjectActionPermission::ViewProjects,
                ],
            ],
            // Works the Plans/Drawing checklist within Site Planning once a Surveyor submits
            // their inspection form — open to any holder, not individually assigned per site
            // (unlike Surveyor's fillSurvey scoping).
            'Infra Designer' => [
                'statuses' => [],
                'actions' => [
                    PipelineActionPermission::ViewAll,
                    PipelineActionPermission::MarkInactive,
                ],
                'modules' => [
                    SidebarModule::Dashboard,
                    SidebarModule::SitePlanning,
                    SidebarModule::Pipelines,
                    SidebarModule::Projects,
                ],
                'site_planning_actions' => [
                    SitePlanningActionPermission::ListAssignments,
                    SitePlanningActionPermission::DesignPlan,
                ],
                'project_actions' => [
                    ProjectActionPermission::ViewProjects,
                ],
            ],
            'Network Surveyor' => [
                'statuses' => [],
                // Read-only visibility into Pipelines (no mutating pipeline actions) — lets
                // NETWORK see the pipeline behind a site without needing the full Pipelines
                // workflow access that was intentionally removed when Network was split out.
                'actions' => [
                    PipelineActionPermission::ViewAll,
                ],
                'modules' => [
                    SidebarModule::Dashboard,
                    SidebarModule::Network,
                    SidebarModule::Pipelines,
                    SidebarModule::Projects,
                ],
                // NETWORK: manages ISP feasibility for sites in Site Survey via the
                // standalone Network module (not the Pipelines module).
                'network_actions' => [
                    NetworkActionPermission::ViewNetworkAssignments,
                    NetworkActionPermission::ManageNetworkFeasibility,
                ],
                // NETWORK: progresses ISP-related stage fields; owns the stages that carry
                // NETWORK-tagged fields — not Closing (RFCO sign-off is INFRA-only).
                'project_actions' => [
                    ProjectActionPermission::ViewProjects,
                    ProjectActionPermission::ManageStageFields,
                ],
                'project_stages' => [
                    ProjectStage::Planning,
                    ProjectStage::Execution,
                    ProjectStage::Monitoring,
                ],
            ],
            'Network Manager' => [
                'statuses' => [],
                // See Network Surveyor above — same read-only Pipelines visibility.
                'actions' => [
                    PipelineActionPermission::ViewAll,
                ],
                'modules' => [
                    SidebarModule::Dashboard,
                    SidebarModule::Network,
                    SidebarModule::Pipelines,
                    SidebarModule::Projects,
                ],
                // Manager-level NETWORK role: same Network module scope as Network Surveyor.
                'network_actions' => [
                    NetworkActionPermission::ViewNetworkAssignments,
                    NetworkActionPermission::ManageNetworkFeasibility,
                ],
                'project_actions' => [
                    ProjectActionPermission::ViewProjects,
                    ProjectActionPermission::ManageStageFields,
                ],
                'project_stages' => [
                    ProjectStage::Planning,
                    ProjectStage::Execution,
                    ProjectStage::Monitoring,
                ],
            ],
            // Intentionally identical to Network Manager — a title/org-chart tier, not a
            // reduced-permission role, per product decision.
            'Network Specialist' => [
                'statuses' => [],
                'actions' => [
                    PipelineActionPermission::ViewAll,
                ],
                'modules' => [
                    SidebarModule::Dashboard,
                    SidebarModule::Network,
                    SidebarModule::Pipelines,
                    SidebarModule::Projects,
                ],
                'network_actions' => [
                    NetworkActionPermission::ViewNetworkAssignments,
                    NetworkActionPermission::ManageNetworkFeasibility,
                ],
                'project_actions' => [
                    ProjectActionPermission::ViewProjects,
                    ProjectActionPermission::ManageStageFields,
                ],
                'project_stages' => [
                    ProjectStage::Planning,
                    ProjectStage::Execution,
                    ProjectStage::Monitoring,
                ],
            ],
            'COMMERCIAL_MANAGER' => [
                'statuses' => [
                    PipelineStatus::Commercials,
                    PipelineStatus::Approval,
                ],
                'actions' => [
                    PipelineActionPermission::HandleCommercials,
                    PipelineActionPermission::DecideApproval,
                    PipelineActionPermission::MarkInactive,
                ],
                'modules' => [
                    SidebarModule::Dashboard,
                    SidebarModule::Pipelines,
                    SidebarModule::Projects,
                ],
                'project_actions' => [
                    ProjectActionPermission::ViewProjects,
                ],
            ],
            'COO' => [
                'statuses' => [
                    PipelineStatus::Commercials,
                    PipelineStatus::Approval,
                    PipelineStatus::Completed,
                ],
                'actions' => [
                    PipelineActionPermission::DecideApproval,
                ],
                'modules' => [
                    SidebarModule::Dashboard,
                    SidebarModule::Pipelines,
                    SidebarModule::Projects,
                ],
                // Read-only WBS visibility, matching every other non-admin role's baseline.
                'project_actions' => [
                    ProjectActionPermission::ViewProjects,
                ],
            ],
        ];

        foreach ($roles as $name => $config) {
            $role = Role::firstOrCreate(['name' => $name]);

            foreach ($config['statuses'] as $status) {
                RolePermission::firstOrCreate([
                    'role_id' => $role->id,
                    'pipeline_status' => $status->value,
                ]);
            }

            foreach ($config['modules'] as $module) {
                RoleModulePermission::firstOrCreate([
                    'role_id' => $role->id,
                    'module' => $module instanceof SidebarModule ? $module->value : $module,
                ]);
            }

            foreach ($config['actions'] as $permission) {
                RoleActionPermission::firstOrCreate([
                    'role_id' => $role->id,
                    'permission' => $permission instanceof PipelineActionPermission ? $permission->value : $permission,
                ]);
            }

            // Project Management action access is tag-informed per role (SAQ/INFRA/NETWORK,
            // rather than a blanket grant.
            // Adjust further per-role via Role Management.
            foreach ($config['project_actions'] ?? [] as $permission) {
                RoleActionPermission::firstOrCreate([
                    'role_id' => $role->id,
                    'permission' => $permission instanceof ProjectActionPermission ? $permission->value : $permission,
                ]);
            }

            foreach ($config['project_stages'] ?? [] as $stage) {
                RoleProjectStagePermission::firstOrCreate([
                    'role_id' => $role->id,
                    'project_stage' => $stage instanceof ProjectStage ? $stage->value : $stage,
                ]);
            }

            foreach ($config['network_actions'] ?? [] as $permission) {
                RoleActionPermission::firstOrCreate([
                    'role_id' => $role->id,
                    'permission' => $permission instanceof NetworkActionPermission ? $permission->value : $permission,
                ]);
            }

            foreach ($config['site_planning_actions'] ?? [] as $permission) {
                RoleActionPermission::firstOrCreate([
                    'role_id' => $role->id,
                    'permission' => $permission instanceof SitePlanningActionPermission ? $permission->value : $permission,
                ]);
            }
        }

        $legacySaqRole = Role::where('name', 'SAQ Specialist')->first();

        if ($legacySaqRole !== null) {
            foreach ([
                PipelineStatus::Prospect,
                PipelineStatus::Prospecting,
            ] as $status) {
                RolePermission::firstOrCreate([
                    'role_id' => $legacySaqRole->id,
                    'pipeline_status' => $status->value,
                ]);
            }
        }
    }
}
