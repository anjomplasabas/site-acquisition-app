<?php

namespace App\Support;

use App\Enums\NetworkActionPermission;
use App\Enums\ProjectStage;
use App\Models\User;

/**
 * The fixed data fields tracked per delivery stage. Project Initiation is intentionally
 * excluded because that data belongs to the Pipeline module. This field set is not
 * admin-editable.
 */
class ProjectStageFieldDefinitions
{
    /**
     * Whether the user's department may edit a field that declares an `owner`:
     * 'network' fields (the ISP sections) belong to the Network Department, 'infra' fields
     * (Planning Details) to Infra — a role holding the Network feasibility permission is Network,
     * everyone else is treated as Infra. Super Admin owns both. Fields with no `owner` are open
     * to anyone who can manage the stage.
     *
     * @param  array{owner?: string}  $field
     */
    public static function isEditableBy(User $user, array $field): bool
    {
        $owner = $field['owner'] ?? null;

        if ($owner === null || $user->role?->name === 'SUPER_ADMIN') {
            return true;
        }

        $isNetworkUser = $user->role?->hasActionPermission(NetworkActionPermission::ManageNetworkFeasibility->value) ?? false;

        return $owner === 'network' ? $isNetworkUser : ! $isNetworkUser;
    }

    /**
     * @return array<int, array{key: string, label: string, type: 'text'|'select'|'date'|'document_status'|'document_upload'|'isp_task_list'|'user_select'|'computed'|'stage_task_list', group: string, options?: array<int, string>, document_category?: string, notes?: bool}>
     */
    public static function forStage(ProjectStage $stage): array
    {
        return match ($stage) {
            // MOA, NTP, BOQ, and PR are all `document_upload` fields: a plain file list + upload
            // widget keyed by `document_category` (project_stage_documents), with no
            // status/progress dropdown — the field counts as filled once at least one document of
            // that category is attached (ProjectStageController::fieldIsFilled). MOA/NTP used to be
            // `document_status` (a dropdown describing the upload's progress); that status concept
            // was dropped per product direction — simple presence is now enough. MOA/NTP remain
            // independent from the Pipeline module's own "MOA"/"NTP" document requirement that gates
            // the Commercials stage (StageDocumentRequirement, module=pipeline) — same names,
            // different module, no data sharing.
            //
            // Plans/Drawing (the ~29-task Design Planning Status Matrix) and its Type dropdown
            // used to live here, but now happen earlier — during Site Survey, in the Site
            // Planning module (SitePlanningAssignment::sitePlanningTasks/design_plan_type) —
            // see StagePanel.vue's read-only reference block for where this stage still
            // surfaces that already-completed work.
            ProjectStage::Planning => [
                ['key' => 'moa', 'owner' => 'infra', 'label' => 'MOA', 'type' => 'document_upload', 'group' => 'Planning Details', 'document_category' => 'MOA'],
                ['key' => 'ntp', 'owner' => 'infra', 'label' => 'NTP', 'type' => 'document_upload', 'group' => 'Planning Details', 'document_category' => 'NTP'],
                ['key' => 'boq', 'owner' => 'infra', 'label' => 'BOQ', 'type' => 'document_upload', 'group' => 'Planning Details', 'document_category' => 'BOQ'],
                ['key' => 'pr', 'owner' => 'infra', 'label' => 'PR', 'type' => 'document_upload', 'group' => 'Planning Details', 'document_category' => 'PR'],
                ['key' => 'contractor', 'owner' => 'infra', 'label' => 'Contractor', 'type' => 'select', 'group' => 'Planning Details', 'options' => ['JGD', 'JTE']],
                // Dynamic list of real ProjectStageIsp rows (see ProjectStageIspController), not
                // a field_values entry — carried forward from the pipeline's NetworkFeasibility
                // entries (InitializeProject::carryForwardIsps) and freely add/removable in the
                // UI. Each ISP gets a fixed 9-task installation checklist (IspTaskDefinitions),
                // with per-task status, an age tracker (status_changed_at), and document uploads.
                ['key' => 'isp_statuses', 'owner' => 'network', 'label' => 'ISP Status', 'type' => 'isp_task_list', 'group' => 'ISP Status'],
                ['key' => 'target_activation_date', 'owner' => 'network', 'label' => 'Target Activation Date', 'type' => 'date', 'group' => 'ISP Status'],
            ],
            ProjectStage::Execution => [
                // Real ProjectStageIsp rows, same as Planning's isp_statuses — seeded from
                // Planning's final ISP list when Planning is completed
                // (ProjectStageController::complete/seedIspsForNextStage), each linked back to its
                // Planning source (ProjectStageIsp::sourceProjectStageIsp) and given Execution's
                // own fixed 3-task checklist (IspTaskDefinitions::forStage).
                ['key' => 'isp_deployment_statuses', 'owner' => 'network', 'label' => 'ISP Deployment Status', 'type' => 'isp_task_list', 'group' => 'ISP Deployment Status'],
                ['key' => 'kick_off_meeting', 'owner' => 'infra', 'label' => 'Kick-off Meeting', 'type' => 'date', 'group' => 'Execution Details'],
                // Dropdown of all Users (id stored in field_values); see ProjectController::show's
                // shared `users` prop.
                ['key' => 'pm_in_charge', 'owner' => 'infra', 'label' => 'PM In-charge', 'type' => 'user_select', 'surveyors_only' => true, 'group' => 'Execution Details'],
                // Must be on or after Kick-off Meeting — see `after_or_equal_field` in
                // UpdateProjectStageRequest::rules().
                ['key' => 'mobilization_schedule', 'owner' => 'infra', 'label' => 'Mobilization Schedule', 'type' => 'date', 'group' => 'Execution Details', 'after_or_equal_field' => 'kick_off_meeting'],
                // Read-only: auto-computed server-side from mobilization_schedule/target_finished_date
                // on every stage save (ProjectStageController::computeExecutionDuration) — never
                // accepted from the client.
                ['key' => 'duration', 'owner' => 'infra', 'label' => 'Duration', 'type' => 'computed', 'group' => 'Execution Details'],
                ['key' => 'target_finished_date', 'owner' => 'infra', 'label' => 'Target Finished Date', 'type' => 'date', 'group' => 'Execution Details'],
            ],
            ProjectStage::Monitoring => [
                // Fixed, stage-level (not per-ISP) weighted checklist (StageTaskDefinitions) —
                // seeded once when the Monitoring stage is created (InitializeProject). Each task's
                // weight contributes to ProjectStage::infrastructure_progress_percent only once Done.
                ['key' => 'infrastructure_deployment_status', 'owner' => 'infra', 'label' => 'Infrastructure Deployment Status', 'type' => 'stage_task_list', 'group' => 'Infrastructure Deployment Status'],
                // Real ProjectStageIsp rows carried from Execution (ProjectStageController::complete/
                // seedIspsForNextStage), each linked back to its Execution source and given
                // Monitoring's single-task checklist (IspTaskDefinitions::forStage) — a simple
                // Pending/In-Progress/Done tag per ISP, no weight/breakdown.
                ['key' => 'network_deployment_statuses', 'owner' => 'network', 'label' => 'Network Deployment Status', 'type' => 'isp_task_list', 'group' => 'Network Deployment Status'],
                // 'Done'/'On-going' are also auto-computed server-side from the Infrastructure
                // checklist (ProjectStage::inferredProjectStatusFromInfrastructure, StageTaskController)
                // whenever an Infrastructure task's status changes, unless the current value is
                // 'On-hold' — but the PM can still set any of the three values manually here too.
                ['key' => 'project_status', 'owner' => 'infra', 'label' => 'Project Status', 'type' => 'select', 'group' => 'Progress', 'options' => ['Done', 'On-going', 'On-hold']],
                // Plain scalar dropdown (field_values.testing), independent of any document — the
                // `document_category` here just unlocks an optional, non-required upload widget
                // alongside it (see StageFieldInput.vue/uploadDocument's $validCategories), not a
                // status-per-document coupling like MOA/NTP's document_status fields. `notes` adds
                // a freeform textarea (field_values.testing_notes) alongside the dropdown/upload —
                // Testing-only for now, not a generic attribute every select field supports.
                ['key' => 'testing', 'owner' => 'infra', 'label' => 'Testing', 'type' => 'select', 'group' => 'Progress', 'options' => ['Pending', 'In-Progress', 'Done'], 'document_category' => 'Testing', 'notes' => true],
            ],
            ProjectStage::Closing => [
                // rfco_date/acceptance_date auto-populate with today's date the first time their
                // matching status flips to 'Done' (ProjectStageController::populateClosingDates) —
                // still a plain, manually-editable `date` field otherwise, so a PM can backdate/
                // correct it; auto-fill only ever writes when the date is currently blank, so it
                // never clobbers a value someone already entered.
                // Remarks are mandatory when the status is set to 'On-hold' — see
                // `notes_required_when` in UpdateProjectStageRequest::rules().
                ['key' => 'rfco_status', 'label' => 'RFCO Status', 'type' => 'select', 'group' => 'Details', 'options' => ['RFCO', 'Done', 'On-going', 'On-hold'], 'notes' => true, 'notes_required_when' => ['On-hold']],
                ['key' => 'rfco_date', 'label' => 'RFCO Date', 'type' => 'date', 'group' => 'Details'],
                ['key' => 'acceptance_status', 'label' => 'Acceptance Status', 'type' => 'select', 'group' => 'Details', 'options' => ['Done', 'On-going', 'On-hold'], 'notes' => true, 'notes_required_when' => ['On-hold']],
                ['key' => 'acceptance_date', 'label' => 'Acceptance Date', 'type' => 'date', 'group' => 'Details'],
                ['key' => 'as_built_plan', 'label' => 'As-Built Plan', 'type' => 'document_upload', 'group' => 'Details', 'document_category' => 'As-Built Plan'],
            ],
        };
    }

    /** @return array<string> */
    public static function requiredKeys(ProjectStage $stage): array
    {
        return array_column(self::forStage($stage), 'key');
    }

    /** @return array<string, array{type: string, options?: array<int, string>}> */
    public static function indexed(ProjectStage $stage): array
    {
        return collect(self::forStage($stage))->keyBy('key')->all();
    }
}
