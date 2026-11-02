<?php

use App\Enums\PipelineActionPermission;
use App\Enums\PipelineStatus;
use App\Models\ApprovalConfig;
use App\Models\Role;
use App\Models\RoleActionPermission;
use App\Models\RolePermission;
use App\Models\StageDocumentRequirement;
use App\Models\User;
use App\Support\StageDocumentRequirements;

function approverEligibleRole(): Role
{
    $role = Role::factory()->create();
    RoleActionPermission::create(['role_id' => $role->id, 'permission' => PipelineActionPermission::DecideApproval->value]);
    RolePermission::create(['role_id' => $role->id, 'pipeline_status' => PipelineStatus::Approval->value]);

    return $role;
}

// ─── updateApprovalStep ─────────────────────────────────────────────

it('assigns an eligible role as an approval step', function () {
    $role = approverEligibleRole();

    $this->actingAs(User::factory()->superAdmin()->create())
        ->post(route('settings.approvals.update', 1), ['role_id' => $role->id])
        ->assertRedirect(route('settings.approvals'))
        ->assertSessionHas('success');

    expect(ApprovalConfig::where('step', 1)->where('is_active', true)->first()?->role_id)->toBe($role->id);
});

it('rejects a role without approval permissions as an approval step', function () {
    $role = Role::factory()->create();

    $this->actingAs(User::factory()->superAdmin()->create())
        ->post(route('settings.approvals.update', 1), ['role_id' => $role->id])
        ->assertSessionHasErrors('role_id');
});

it('only exposes approval-eligible roles to the settings page', function () {
    $eligible = approverEligibleRole();
    $ineligible = Role::factory()->create();

    $this->actingAs(User::factory()->superAdmin()->create())
        ->get(route('settings.approvals'))
        ->assertInertia(fn ($page) => $page
            ->where('roles', fn ($roles) => collect($roles)->pluck('id')->contains($eligible->id)
                && ! collect($roles)->pluck('id')->contains($ineligible->id)
            )
        );
});

it('blocks assigning the same role to two consecutive approval steps', function () {
    $role = approverEligibleRole();

    $this->actingAs(User::factory()->superAdmin()->create())
        ->post(route('settings.approvals.update', 1), ['role_id' => $role->id])
        ->assertRedirect(route('settings.approvals'));

    $this->post(route('settings.approvals.update', 2), ['role_id' => $role->id])
        ->assertSessionHasErrors('role_id');
});

it('allows the same role on non-adjacent approval steps', function () {
    $role = approverEligibleRole();
    $otherRole = approverEligibleRole();

    $this->actingAs(User::factory()->superAdmin()->create());
    $this->post(route('settings.approvals.update', 1), ['role_id' => $role->id]);
    $this->post(route('settings.approvals.update', 2), ['role_id' => $otherRole->id]);

    $this->post(route('settings.approvals.update', 3), ['role_id' => $role->id])
        ->assertSessionDoesntHaveErrors('role_id');
});

// ─── removeApprovalStep ───────────────────────────────────────────────────

it('resequences remaining approval steps after removing an earlier one', function () {
    $roleA = approverEligibleRole();
    $roleB = approverEligibleRole();
    $roleC = approverEligibleRole();

    $admin = User::factory()->superAdmin()->create();
    $this->actingAs($admin);
    $this->post(route('settings.approvals.update', 1), ['role_id' => $roleA->id]);
    $this->post(route('settings.approvals.update', 2), ['role_id' => $roleB->id]);
    $this->post(route('settings.approvals.update', 3), ['role_id' => $roleC->id]);

    $this->delete(route('settings.approvals.remove', 1))
        ->assertRedirect(route('settings.approvals'))
        ->assertSessionHas('success');

    $remaining = ApprovalConfig::where('is_active', true)->orderBy('step')->get();

    expect($remaining)->toHaveCount(2)
        ->and($remaining[0]->step)->toBe(1)
        ->and($remaining[0]->role_id)->toBe($roleB->id)
        ->and($remaining[1]->step)->toBe(2)
        ->and($remaining[1]->role_id)->toBe($roleC->id);
});

// ─── storeStageDocumentRequirement / updateStageDocumentRequirement ──

it('rejects creating a duplicate stage document requirement', function () {
    StageDocumentRequirement::create([
        'module' => 'pipeline', 'stage' => PipelineStatus::Commercials->value,
        'name' => 'MOA', 'is_required' => true, 'is_active' => true, 'sort_order' => 1,
    ]);

    $this->actingAs(User::factory()->superAdmin()->create())
        ->post(route('settings.stage-document-requirements.store'), [
            'module' => 'pipeline', 'stage' => PipelineStatus::Commercials->value, 'name' => 'MOA',
        ])
        ->assertSessionHasErrors('name');

    expect(StageDocumentRequirement::where('name', 'MOA')->count())->toBe(1);
});

it('rejects updating a stage document requirement into a duplicate', function () {
    StageDocumentRequirement::create([
        'module' => 'pipeline', 'stage' => PipelineStatus::Commercials->value,
        'name' => 'MOA', 'is_required' => true, 'is_active' => true, 'sort_order' => 1,
    ]);
    $ntp = StageDocumentRequirement::create([
        'module' => 'pipeline', 'stage' => PipelineStatus::Commercials->value,
        'name' => 'NTP', 'is_required' => true, 'is_active' => true, 'sort_order' => 2,
    ]);

    $this->actingAs(User::factory()->superAdmin()->create())
        ->patch(route('settings.stage-document-requirements.update', $ntp), [
            'module' => 'pipeline', 'stage' => PipelineStatus::Commercials->value, 'name' => 'MOA',
        ])
        ->assertSessionHasErrors('name');

    expect($ntp->fresh()->name)->toBe('NTP');
});

it('allows updating a stage document requirement without changing its name', function () {
    $moa = StageDocumentRequirement::create([
        'module' => 'pipeline', 'stage' => PipelineStatus::Commercials->value,
        'name' => 'MOA', 'is_required' => true, 'is_active' => true, 'sort_order' => 1,
    ]);

    $this->actingAs(User::factory()->superAdmin()->create())
        ->patch(route('settings.stage-document-requirements.update', $moa), [
            'module' => 'pipeline', 'stage' => PipelineStatus::Commercials->value, 'name' => 'MOA', 'sort_order' => 5,
        ])
        ->assertSessionDoesntHaveErrors('name');

    expect($moa->fresh()->sort_order)->toBe(5);
});

// ─── destroyStageDocumentRequirement ────────────────────────────────────────────

it('deactivates a stage document requirement', function () {
    $moaRequirement = StageDocumentRequirement::create([
        'module' => 'pipeline', 'stage' => PipelineStatus::Commercials->value,
        'name' => 'MOA', 'is_required' => true, 'is_active' => true, 'sort_order' => 1,
    ]);

    $this->actingAs(User::factory()->superAdmin()->create())
        ->delete(route('settings.stage-document-requirements.destroy', $moaRequirement))
        ->assertRedirect(route('settings.approvals'));

    expect($moaRequirement->fresh()->is_active)->toBeFalse();
});

// ─── deactivated Commercials requirement is no longer enforced ────────────

it('stops enforcing a required Commercials document once its category is deactivated', function () {
    $moaRequirement = StageDocumentRequirement::create([
        'module' => 'pipeline', 'stage' => PipelineStatus::Commercials->value,
        'name' => 'MOA', 'is_required' => true, 'is_active' => true, 'sort_order' => 1,
    ]);

    $this->actingAs(User::factory()->superAdmin()->create())
        ->delete(route('settings.stage-document-requirements.destroy', $moaRequirement));

    $missing = app(StageDocumentRequirements::class)->requiredFor(PipelineStatus::Commercials);

    expect($missing->pluck('name'))->not->toContain('MOA');
});
