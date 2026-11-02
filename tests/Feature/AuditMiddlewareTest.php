<?php

use App\Enums\PipelineStatus;
use App\Enums\SiteProfileType;
use App\Models\Address;
use App\Models\AuditLog;
use App\Models\Pipeline;
use App\Models\SiteProfile;
use App\Models\User;

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function validAuditPipelinePayload(array $overrides = []): array
{
    return array_replace([
        'endorsement_date' => now()->toDateString(),
        'site_name' => 'Audit Metadata Test Site',
        'site_type' => SiteProfileType::Condo->value,
        'city' => 'Manila',
    ], $overrides);
}

function makeAuditPipeline(User $manager): Pipeline
{
    $address = Address::create(['city' => 'Manila']);
    $siteProfile = SiteProfile::create([
        'name' => 'Audit Test Site',
        'type' => SiteProfileType::Condo,
        'address_id' => $address->id,
    ]);

    return Pipeline::create([
        'name' => 'Audit Test Pipeline',
        'pipeline_id' => nextPipelineId(),
        'project_manager_id' => $manager->id,
        'site_profile_id' => $siteProfile->id,
        'status' => PipelineStatus::Prospect,
    ]);
}

// --- Filtering ---

it('does not log GET requests', function () {
    $user = User::factory()->superAdmin()->create();

    $this->actingAs($user)->get('/audit-logs');

    expect(AuditLog::where('user_id', $user->id)->count())->toBe(0);
});

it('does not log unauthenticated requests', function () {
    $initialCount = AuditLog::count();

    $this->post('/logout');

    expect(AuditLog::count())->toBe($initialCount);
});

// --- Logging ---

it('logs a successful named route action', function () {
    $user = User::factory()->superAdmin()->create();

    $this->actingAs($user)->post('/logout');

    expect(AuditLog::where('user_id', $user->id)->where('action', 'logout')->exists())->toBeTrue();
});

it('includes pipeline_ref in metadata for pipeline actions', function () {
    $user = User::factory()->superAdmin()->create();
    $pipeline = makeAuditPipeline($user);

    $this->actingAs($user)->patch("/pipelines/{$pipeline->id}", [
        'name' => $pipeline->name,
        'endorsement_date' => null,
        'project_manager_id' => $user->id,
        'site_profile_id' => $pipeline->site_profile_id,
    ]);

    $log = AuditLog::where('user_id', $user->id)
        ->where('action', 'pipelines_update')
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->metadata['pipeline_ref'])->toBe($pipeline->pipeline_id);
});

it('sets the correct subject type and id for pipeline actions', function () {
    $user = User::factory()->superAdmin()->create();
    $pipeline = makeAuditPipeline($user);

    $this->actingAs($user)->patch("/pipelines/{$pipeline->id}", [
        'name' => $pipeline->name,
        'endorsement_date' => null,
        'project_manager_id' => $user->id,
        'site_profile_id' => $pipeline->site_profile_id,
    ]);

    $log = AuditLog::where('user_id', $user->id)->where('action', 'pipelines_update')->first();

    expect($log->subject_type)->toBe(Pipeline::class)
        ->and($log->subject_id)->toBe($pipeline->id);
});

it('captures the pipeline_ref and subject on pipeline creation', function () {
    $manager = User::factory()->projectManager()->create();

    $this->actingAs($manager)->post(route('pipelines.store'), validAuditPipelinePayload());

    $pipeline = Pipeline::firstOrFail();
    $log = AuditLog::where('user_id', $manager->id)
        ->where('action', 'pipelines_store')
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->subject_type)->toBe(Pipeline::class)
        ->and($log->subject_id)->toBe($pipeline->id)
        ->and($log->metadata['pipeline_ref'])->toBe($pipeline->pipeline_id)
        ->and($log->description)->toContain($pipeline->pipeline_id);
});

it('does not log a pipeline creation attempt that fails validation', function () {
    $manager = User::factory()->projectManager()->create();

    $this->actingAs($manager)->post(route('pipelines.store'), validAuditPipelinePayload([
        'site_name' => '',
    ]))->assertSessionHasErrors('site_name');

    expect(AuditLog::where('user_id', $manager->id)->where('action', 'pipelines_store')->exists())->toBeFalse();
});

it('summarizes the imported rows in the audit log metadata instead of dumping the raw payload', function () {
    $admin = User::factory()->superAdmin()->create();

    $this->actingAs($admin)->post(route('pipelines.import.confirm'), [
        'rows' => [
            [
                'row_number' => 1,
                'data' => ['site_name' => 'Imported Audit Site', 'site_type' => SiteProfileType::Condo->value, 'city' => 'Manila'],
                'site_action' => 'create_new',
                'pipeline_action' => 'create',
            ],
        ],
    ]);

    $log = AuditLog::where('user_id', $admin->id)->where('action', 'pipelines_import_confirm')->firstOrFail();

    expect($log->metadata['fields']['rows'][0])->toBe([
        'row_number' => 1,
        'site_name' => 'Imported Audit Site',
        'site_action' => 'create_new',
        'pipeline_action' => 'create',
    ]);
});
