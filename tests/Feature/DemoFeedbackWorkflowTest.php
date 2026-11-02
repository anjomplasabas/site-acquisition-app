<?php

use App\Enums\NetworkActionPermission;
use App\Enums\PipelineActionPermission;
use App\Enums\PipelineStatus;
use App\Enums\SidebarModule;
use App\Enums\SitePlanningActionPermission;
use App\Enums\SiteProfileType;
use App\Models\Address;
use App\Models\Pipeline;
use App\Models\Role;
use App\Models\RoleModulePermission;
use App\Models\SiteProfile;
use App\Models\SiteSurvey;
use App\Models\User;
use Database\Seeders\DemoUserSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;

it('seeds the new roles with stage and module permissions', function () {
    $this->seed(RoleSeeder::class);

    $leadSaq = Role::where('name', 'Lead SAQ Specialist')->firstOrFail();
    $infrastructure = Role::where('name', 'Infrastructure Manager')->firstOrFail();
    $networkSurveyor = Role::where('name', 'Network Surveyor')->firstOrFail();
    $surveyor = Role::where('name', 'Surveyor')->firstOrFail();
    $commercial = Role::where('name', 'COMMERCIAL_MANAGER')->firstOrFail();
    $coo = Role::where('name', 'COO')->firstOrFail();

    expect($leadSaq->permissions()->pluck('pipeline_status')->all())
        ->toContain(PipelineStatus::Prospect->value, PipelineStatus::Prospecting->value);
    expect($commercial->permissions()->pluck('pipeline_status')->all())
        ->toContain(PipelineStatus::Commercials->value, PipelineStatus::Approval->value);
    expect($coo->permissions()->pluck('pipeline_status')->all())
        ->toContain(PipelineStatus::Commercials->value, PipelineStatus::Approval->value, PipelineStatus::Completed->value);
    expect($leadSaq->modulePermissions()->pluck('module')->all())
        ->toContain(SidebarModule::Dashboard->value, SidebarModule::Pipelines->value, SidebarModule::Projects->value);
    expect($leadSaq->actionPermissions()->pluck('permission')->all())
        ->toContain(PipelineActionPermission::ViewAll->value, PipelineActionPermission::AssignSaq->value);
    expect($infrastructure->actionPermissions()->pluck('permission')->all())
        ->toContain(PipelineActionPermission::ViewAll->value, SitePlanningActionPermission::Manage->value);
    expect($networkSurveyor->actionPermissions()->pluck('permission')->all())
        ->toContain(NetworkActionPermission::ManageNetworkFeasibility->value, PipelineActionPermission::ViewAll->value);
    expect($networkSurveyor->modulePermissions()->pluck('module')->all())
        ->toContain(SidebarModule::Network->value, SidebarModule::Pipelines->value);
    expect($coo->actionPermissions()->pluck('permission')->all())
        ->toContain(PipelineActionPermission::DecideApproval->value);
});

it('seeds demo users for lead SAQ, SAQ, surveyor, and commercial roles', function () {
    $this->seed(RoleSeeder::class);
    $this->seed(DemoUserSeeder::class);

    expect(User::where('email', 'lead.saq@example.test')->whereHas('role', fn ($query) => $query->where('name', 'Lead SAQ Specialist'))->exists())->toBeTrue();
    expect(User::where('email', 'saq@example.test')->whereHas('role', fn ($query) => $query->where('name', 'SAQ Specialist'))->exists())->toBeTrue();
    expect(User::where('email', 'infrastructure.manager@example.test')->whereHas('role', fn ($query) => $query->where('name', 'Infrastructure Manager'))->exists())->toBeTrue();
    expect(User::where('email', 'surveyor@example.test')->whereHas('role', fn ($query) => $query->where('name', 'Surveyor'))->exists())->toBeTrue();
    expect(User::where('email', 'network.surveyor@example.test')->whereHas('role', fn ($query) => $query->where('name', 'Network Surveyor'))->exists())->toBeTrue();
    expect(User::where('email', 'commercial.manager@example.test')->whereHas('role', fn ($query) => $query->where('name', 'COMMERCIAL_MANAGER'))->exists())->toBeTrue();
    expect(User::where('email', 'coo@example.test')->whereHas('role', fn ($query) => $query->where('name', 'COO'))->exists())->toBeTrue();
    expect(Hash::check('DemoOnly123!', User::where('email', 'coo@example.test')->firstOrFail()->password))->toBeTrue();
});

it('enforces sidebar module permissions on routes', function () {
    $role = Role::factory()->create();
    RoleModulePermission::create(['role_id' => $role->id, 'module' => SidebarModule::Dashboard->value]);
    $user = User::factory()->create(['role_id' => $role->id]);

    $this->actingAs($user)->get('/dashboard')->assertSuccessful();
    $this->actingAs($user)
        ->from('/dashboard')
        ->get('/pipelines')
        ->assertForbidden()
        ->assertInertia(fn ($page) => $page
            ->component('Errors/Forbidden')
            ->where('backUrl', url('/dashboard')));
});

it('only lets the assigned lead SAQ start prospecting', function () {
    $owner = User::factory()->projectManager()->create();
    $other = User::factory()->saqSpecialist()->create();
    $pipeline = demoPipeline($owner, PipelineStatus::Prospect);

    $this->actingAs($other)
        ->post(route('pipelines.start-acquisition', $pipeline), ['content' => 'Ready'])
        ->assertForbidden();

    $this->actingAs($owner)
        ->post(route('pipelines.start-acquisition', $pipeline), ['content' => 'Ready'])
        ->assertRedirect(route('pipelines.show', $pipeline));

    expect($pipeline->fresh()->status)->toBe(PipelineStatus::Prospecting);
});

it('imports a csv row as a new site and prospect pipeline', function () {
    $lead = User::factory()->projectManager()->create(['email' => 'lead@example.com']);
    $admin = User::factory()->superAdmin()->create();

    $file = csvUpload("pipeline_name,assigned_saq_email,site_name,site_type,city\nTower A,lead@example.com,Tower A,Condo,Manila\n");

    $this->actingAs($admin)
        ->post(route('pipelines.import.upload'), ['file' => $file])
        ->assertRedirect(route('pipelines.import'));

    $tempPath = session('tempPath');
    $columns = session('columns');
    $mapping = collect($columns)->mapWithKeys(fn ($col) => [$col['header'] => $col['header']])->all();

    $this->actingAs($admin)
        ->post(route('pipelines.import.preview'), [
            'temp_path' => $tempPath,
            'file_extension' => 'csv',
            'mapping' => $mapping,
        ])
        ->assertRedirect(route('pipelines.import'));

    $preview = session('previewRows');

    $this->actingAs($admin)
        ->post(route('pipelines.import.confirm'), [
            'rows' => [[
                'row_number' => $preview[0]['row_number'],
                'data' => $preview[0]['data'],
                'site_action' => 'create_new',
                'pipeline_action' => 'create',
            ]],
        ])
        ->assertRedirect(route('pipelines.import'));

    expect(SiteProfile::where('name', 'Tower A')->exists())->toBeTrue();

    // "Registered By" (project_manager_id) is the importing user, not the CSV's
    // assigned_saq_email match — that resolves into assigned_saq_id, the actual Lead
    // SAQ Specialist field, matching what manual pipeline creation does.
    $pipeline = Pipeline::where('name', 'Tower A')->where('status', PipelineStatus::Prospect)->first();
    expect($pipeline)->not->toBeNull()
        ->and($pipeline->project_manager_id)->toBe($admin->id)
        ->and($pipeline->assigned_saq_id)->toBe($lead->id);
});

it('skips duplicate imported pipelines by default', function () {
    $lead = User::factory()->projectManager()->create(['email' => 'lead@example.com']);
    $site = demoSite('Tower A', 'Manila');

    Pipeline::create([
        'name' => 'Tower A',
        'pipeline_id' => nextPipelineId(),
        'project_manager_id' => $lead->id,
        'site_profile_id' => $site->id,
        'status' => PipelineStatus::Prospecting,
    ]);

    $admin = User::factory()->superAdmin()->create();
    $file = csvUpload("pipeline_name,assigned_saq_email,site_name,site_type,city\nTower A,lead@example.com,Tower A,Condo,Manila\n");

    $this->actingAs($admin)
        ->post(route('pipelines.import.upload'), ['file' => $file]);

    $tempPath = session('tempPath');
    $columns = session('columns');
    $mapping = collect($columns)->mapWithKeys(fn ($col) => [$col['header'] => $col['header']])->all();

    $this->actingAs($admin)
        ->post(route('pipelines.import.preview'), [
            'temp_path' => $tempPath,
            'file_extension' => 'csv',
            'mapping' => $mapping,
        ]);

    $preview = session('previewRows');

    $this->actingAs($admin)
        ->post(route('pipelines.import.confirm'), [
            'rows' => [[
                'row_number' => $preview[0]['row_number'],
                'data' => $preview[0]['data'],
                'site_action' => 'use_existing',
                'site_profile_id' => $site->id,
                'pipeline_action' => 'create',
            ]],
        ]);

    expect(Pipeline::where('site_profile_id', $site->id)->count())->toBe(1);
});

function demoPipeline(User $owner, PipelineStatus $status, ?User $surveyor = null): Pipeline
{
    $site = demoSite('Demo Site', 'Manila');
    $survey = SiteSurvey::create([
        'site_profile_id' => $site->id,
        'surveyor_id' => $surveyor?->id,
        'survey_date' => $surveyor ? now()->toDateString() : null,
    ]);

    return Pipeline::create([
        'name' => 'Demo Pipeline',
        'pipeline_id' => nextPipelineId(),
        'project_manager_id' => $owner->id,
        'site_profile_id' => $site->id,
        'site_survey_id' => $survey->id,
        'status' => $status,
    ]);
}

function demoSite(string $name, string $city): SiteProfile
{
    $address = Address::create(['city' => $city]);

    return SiteProfile::create([
        'name' => $name,
        'type' => SiteProfileType::Condo,
        'address_id' => $address->id,
    ]);
}

function csvUpload(string $contents): UploadedFile
{
    return UploadedFile::fake()->createWithContent('pipelines.csv', $contents);
}
