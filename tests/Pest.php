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
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(LazilyRefreshDatabase::class)
    ->in('Feature', 'Unit');

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

// ─── Shared helpers ───────────────────────────────────────────────────────────

function nextPipelineId(): string
{
    static $seq = 0;

    return sprintf('PL-TEST-%04d', ++$seq);
}

/**
 * @return array{pipeline: Pipeline, approval: PipelineApproval, approver: User, manager: User}
 */
function pipelineInApproval(int $totalSteps = 1): array
{
    $manager = User::factory()->create();
    $approverRole = Role::factory()->create();
    RoleModulePermission::create(['role_id' => $approverRole->id, 'module' => SidebarModule::Pipelines->value]);
    RoleActionPermission::create(['role_id' => $approverRole->id, 'permission' => PipelineActionPermission::DecideApproval->value]);
    RolePermission::create(['role_id' => $approverRole->id, 'pipeline_status' => PipelineStatus::Approval->value]);
    $approver = User::factory()->create(['role_id' => $approverRole->id]);

    $address = Address::create(['city' => 'Manila']);
    $siteProfile = SiteProfile::create([
        'name' => 'Test Site',
        'type' => SiteProfileType::Condo,
        'address_id' => $address->id,
    ]);

    $pipeline = Pipeline::create([
        'name' => 'Test Pipeline',
        'pipeline_id' => nextPipelineId(),
        'project_manager_id' => $manager->id,
        'site_profile_id' => $siteProfile->id,
        'status' => PipelineStatus::Approval,
        'commercial_remarks' => 'Initial remarks.',
    ]);

    $approval = PipelineApproval::create([
        'pipeline_id' => $pipeline->id,
        'step' => 1,
        'role_id' => $approverRole->id,
        'status' => ApprovalStatus::Pending,
    ]);

    for ($step = 2; $step <= $totalSteps; $step++) {
        PipelineApproval::create([
            'pipeline_id' => $pipeline->id,
            'step' => $step,
            'role_id' => $approverRole->id,
            'status' => ApprovalStatus::Pending,
        ]);
    }

    return compact('pipeline', 'approval', 'approver', 'manager');
}
