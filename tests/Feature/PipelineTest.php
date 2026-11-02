<?php

use App\Enums\ApprovalStatus;
use App\Enums\DocumentType;
use App\Enums\IspTaskStatus;
use App\Enums\PipelineActionPermission;
use App\Enums\PipelineStatus;
use App\Enums\SiteProfileType;
use App\Models\Address;
use App\Models\ApprovalConfig;
use App\Models\Document;
use App\Models\NetworkFeasibility;
use App\Models\Pipeline;
use App\Models\PipelineApproval;
use App\Models\PipelineRemarkLog;
use App\Models\PipelineTouch;
use App\Models\Project;
use App\Models\ProjectStage;
use App\Models\Role;
use App\Models\RoleActionPermission;
use App\Models\RolePermission;
use App\Models\SiteContact;
use App\Models\SitePlanningAssignment;
use App\Models\SiteProfile;
use App\Models\SiteSurvey;
use App\Models\StageDocumentRequirement;
use App\Models\Tower;
use App\Models\User;
use App\Notifications\PipelineTouchedNotification;
use App\Support\PipelineActivity;
use App\Support\PipelineStageContext;
use App\Support\StageDocumentRequirements;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

// ─── Helpers ──────────────────────────────────────────────────────────────────

/**
 * @return array{pipeline: Pipeline, manager: User, surveyor: User}
 */
function pipelineWithSurvey(PipelineStatus $status = PipelineStatus::SiteSurvey): array
{
    $manager = User::factory()->projectManager()->create();
    $surveyor = User::factory()->surveyor()->create();

    $address = Address::create(['city' => 'Manila']);
    $siteProfile = SiteProfile::create([
        'name' => 'Test Site',
        'type' => SiteProfileType::Condo,
        'address_id' => $address->id,
    ]);

    $survey = SiteSurvey::create([
        'site_profile_id' => $siteProfile->id,
        'surveyor_id' => $surveyor->id,
        'survey_date' => now()->toDateString(),
    ]);

    $pipeline = Pipeline::create([
        'name' => 'Test Pipeline',
        'pipeline_id' => nextPipelineId(),
        'project_manager_id' => $manager->id,
        'site_profile_id' => $siteProfile->id,
        'site_survey_id' => $survey->id,
        'status' => $status,
    ]);

    return compact('pipeline', 'manager', 'surveyor');
}

/**
 * @return array{pipeline: Pipeline, manager: User, commercialManager: User}
 */
function pipelineInCommercials(): array
{
    $manager = User::factory()->projectManager()->create();
    $commercialManager = User::factory()->commercialManager()->create();

    $address = Address::create(['city' => 'Manila']);
    $siteProfile = SiteProfile::create([
        'name' => 'Test Site',
        'type' => SiteProfileType::Condo,
        'address_id' => $address->id,
    ]);

    $survey = SiteSurvey::create([
        'site_profile_id' => $siteProfile->id,
        'survey_completed_at' => now(),
        'network_feasibility_completed_at' => now(),
    ]);

    $pipeline = Pipeline::create([
        'name' => 'Test Pipeline',
        'pipeline_id' => nextPipelineId(),
        'project_manager_id' => $manager->id,
        'site_profile_id' => $siteProfile->id,
        'site_survey_id' => $survey->id,
        'status' => PipelineStatus::Commercials,
    ]);

    return compact('pipeline', 'manager', 'commercialManager');
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function validPipelineStorePayload(User $manager, array $overrides = []): array
{
    return array_replace([
        'endorsement_date' => now()->toDateString(),
        'site_name' => 'Marketing Test Site',
        'site_type' => SiteProfileType::Condo->value,
        'city' => 'Manila',
    ], $overrides);
}

// ─── create pipeline ─────────────────────────────────────────────────────────

it('stores optional marketing fields when creating a new lead', function () {
    $manager = User::factory()->projectManager()->create();

    $this->actingAs($manager)
        ->post(route('pipelines.store'), validPipelineStorePayload($manager, [
            'lobby_booth_allowed' => true,
            'lobby_standee_allowed' => false,
            'lobby_flyers_stand_allowed' => true,
            'roaming_sales_rep_allowed' => true,
            'marketing_paraphernalia' => 'Lobby posters and elevator decals.',
            'brochure_posting_location' => 'Main lobby bulletin board.',
        ]))
        ->assertRedirect();

    $survey = Pipeline::firstOrFail()->siteSurvey;
    $pipeline = Pipeline::firstOrFail();

    expect($pipeline->name)->toBe('Marketing Test Site')
        ->and($pipeline->project_manager_id)->toBe($manager->id)
        ->and($pipeline->assigned_saq_id)->toBeNull()
        ->and($survey->lobby_booth_allowed)->toBeTrue()
        ->and($survey->lobby_standee_allowed)->toBeFalse()
        ->and($survey->lobby_flyers_stand_allowed)->toBeTrue()
        ->and($survey->roaming_sales_rep_allowed)->toBeTrue()
        ->and($survey->marketing_paraphernalia)->toBe('Lobby posters and elevator decals.')
        ->and($survey->brochure_posting_location)->toBe('Main lobby bulletin board.');
});

it('saves the Free WiFi area/location field on the site profile when creating a new lead', function () {
    $manager = User::factory()->projectManager()->create();

    $this->actingAs($manager)
        ->post(route('pipelines.store'), validPipelineStorePayload($manager, [
            'wifi_area_location' => 'Lobby',
        ]))
        ->assertRedirect();

    expect(Pipeline::firstOrFail()->siteProfile->wifi_area_location)->toBe('Lobby');
});

it('stores optional marketing fields when creating a re-attempt lead', function () {
    $manager = User::factory()->projectManager()->create();
    $address = Address::create(['city' => 'Manila']);
    $siteProfile = SiteProfile::create([
        'name' => 'Existing Site',
        'type' => SiteProfileType::Condo,
        'address_id' => $address->id,
    ]);

    $this->actingAs($manager)
        ->post(route('pipelines.store'), [
            'endorsement_date' => now()->toDateString(),
            'site_profile_id' => $siteProfile->id,
            'lobby_booth_allowed' => true,
            'marketing_paraphernalia' => 'Table booth only.',
            'brochure_posting_location' => 'Admin office counter.',
        ])
        ->assertRedirect();

    $pipeline = Pipeline::firstOrFail();

    expect($pipeline->name)->toBe('Existing Site')
        ->and($pipeline->project_manager_id)->toBe($manager->id)
        ->and($pipeline->site_profile_id)->toBe($siteProfile->id)
        ->and($pipeline->siteSurvey->lobby_booth_allowed)->toBeTrue()
        ->and($pipeline->siteSurvey->marketing_paraphernalia)->toBe('Table booth only.')
        ->and($pipeline->siteSurvey->brochure_posting_location)->toBe('Admin office counter.');
});

it('excludes site profiles with an active pipeline from the create page picker', function () {
    $manager = User::factory()->projectManager()->create();
    $address = Address::create(['city' => 'Manila']);

    $activeProfile = SiteProfile::create(['name' => 'Active Site', 'type' => SiteProfileType::Condo, 'address_id' => $address->id]);
    Pipeline::create([
        'name' => 'Active Site', 'pipeline_id' => nextPipelineId(), 'project_manager_id' => $manager->id,
        'site_profile_id' => $activeProfile->id, 'status' => PipelineStatus::Prospecting,
    ]);

    $inactiveProfile = SiteProfile::create(['name' => 'Inactive Site', 'type' => SiteProfileType::Condo, 'address_id' => $address->id]);
    Pipeline::create([
        'name' => 'Inactive Site', 'pipeline_id' => nextPipelineId(), 'project_manager_id' => $manager->id,
        'site_profile_id' => $inactiveProfile->id, 'status' => PipelineStatus::Inactive,
    ]);

    $this->actingAs($manager)
        ->get(route('pipelines.create'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Pipelines/Create')
            ->where('siteProfiles', fn ($profiles) => collect($profiles)->pluck('id')->contains($inactiveProfile->id)
                && ! collect($profiles)->pluck('id')->contains($activeProfile->id)
            )
        );
});

it('stores dormitory site type and the new site-wide metric fields when creating a lead', function () {
    $manager = User::factory()->projectManager()->create();

    $this->actingAs($manager)
        ->post(route('pipelines.store'), validPipelineStorePayload($manager, [
            'site_type' => SiteProfileType::Dormitory->value,
            'total_commercial_unit_count' => 12,
            'turnover_rate' => 85.5,
            'occupancy_rate' => 92.25,
            'whz_location' => 'Ground Floor Lobby',
            'total_whz_allocation' => 2,
        ]))
        ->assertRedirect();

    $siteProfile = Pipeline::firstOrFail()->siteProfile;

    expect($siteProfile->type)->toBe(SiteProfileType::Dormitory)
        ->and($siteProfile->total_commercial_unit_count)->toBe(12)
        ->and((float) $siteProfile->turnover_rate)->toBe(85.5)
        ->and((float) $siteProfile->occupancy_rate)->toBe(92.25)
        ->and($siteProfile->whz_location)->toBe('Ground Floor Lobby')
        ->and($siteProfile->total_whz_allocation)->toBe(2);
});

it('stores the tower, residential starting floor and floors-not-in-plan fields when creating a lead', function () {
    $manager = User::factory()->projectManager()->create();

    $this->actingAs($manager)
        ->post(route('pipelines.store'), validPipelineStorePayload($manager, [
            'site_type' => SiteProfileType::Dormitory->value,
            'total_building_count' => 3,
            'residential_floor_starts_at' => 5,
            'floors_not_in_plan' => '13, 14',
        ]))
        ->assertRedirect();

    $siteProfile = Pipeline::firstOrFail()->siteProfile;

    expect($siteProfile->total_building_count)->toBe(3)
        ->and($siteProfile->residential_floor_starts_at)->toBe(5)
        ->and($siteProfile->floors_not_in_plan)->toBe('13, 14');
});

it('offers the new Type categories and requires a free-text type when Others is picked', function () {
    $manager = User::factory()->projectManager()->create();

    $this->actingAs($manager)
        ->get(route('pipelines.create'))
        ->assertInertia(fn ($page) => $page->where('siteProfileTypes', ['Condominium', 'School', 'Dormitory', 'Apartment', 'ROW House', 'Others']));

    $this->actingAs($manager)
        ->post(route('pipelines.store'), validPipelineStorePayload($manager, ['site_type' => 'Others']))
        ->assertSessionHasErrors('site_type_other');

    $this->actingAs($manager)
        ->post(route('pipelines.store'), validPipelineStorePayload($manager, ['site_type' => 'Others', 'site_type_other' => 'Co-working hub']))
        ->assertRedirect();

    $siteProfile = Pipeline::firstOrFail()->siteProfile;

    expect($siteProfile->type)->toBe(SiteProfileType::Others)
        ->and($siteProfile->type_other)->toBe('Co-working hub');
});

it('excludes a site profile whose pipeline is Completed and already has a project from the create page picker', function () {
    $manager = User::factory()->projectManager()->create();
    $address = Address::create(['city' => 'Manila']);

    $completedProfile = SiteProfile::create(['name' => 'Completed Site', 'type' => SiteProfileType::Condo, 'address_id' => $address->id]);
    $completedPipeline = Pipeline::create([
        'name' => 'Completed Site', 'pipeline_id' => nextPipelineId(), 'project_manager_id' => $manager->id,
        'site_profile_id' => $completedProfile->id, 'status' => PipelineStatus::Completed,
    ]);
    Project::create(['pipeline_id' => $completedPipeline->id, 'current_stage' => 'Closing', 'status' => 'Completed']);

    $this->actingAs($manager)
        ->get(route('pipelines.create'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Pipelines/Create')
            ->where('siteProfiles', fn ($profiles) => ! collect($profiles)->pluck('id')->contains($completedProfile->id))
        );
});

it('blocks re-attempting a site profile whose pipeline is Completed and already has a project', function () {
    $manager = User::factory()->projectManager()->create();
    $address = Address::create(['city' => 'Manila']);
    $siteProfile = SiteProfile::create(['name' => 'Completed Site', 'type' => SiteProfileType::Condo, 'address_id' => $address->id]);
    $completedPipeline = Pipeline::create([
        'name' => 'Completed Site', 'pipeline_id' => nextPipelineId(), 'project_manager_id' => $manager->id,
        'site_profile_id' => $siteProfile->id, 'status' => PipelineStatus::Completed,
    ]);
    Project::create(['pipeline_id' => $completedPipeline->id, 'current_stage' => 'Closing', 'status' => 'Completed']);

    $this->actingAs($manager)
        ->post(route('pipelines.store'), [
            'endorsement_date' => now()->toDateString(),
            'site_profile_id' => $siteProfile->id,
        ])
        ->assertSessionHasErrors('site_profile_id');

    expect(Pipeline::where('site_profile_id', $siteProfile->id)->count())->toBe(1);
});

it('blocks linking a site profile that already has an active pipeline', function () {
    $manager = User::factory()->projectManager()->create();
    $address = Address::create(['city' => 'Manila']);
    $siteProfile = SiteProfile::create(['name' => 'Active Site', 'type' => SiteProfileType::Condo, 'address_id' => $address->id]);
    Pipeline::create([
        'name' => 'Active Site', 'pipeline_id' => nextPipelineId(), 'project_manager_id' => $manager->id,
        'site_profile_id' => $siteProfile->id, 'status' => PipelineStatus::Prospecting,
    ]);

    $this->actingAs($manager)
        ->post(route('pipelines.store'), [
            'endorsement_date' => now()->toDateString(),
            'site_profile_id' => $siteProfile->id,
        ])
        ->assertSessionHasErrors('site_profile_id');

    expect(Pipeline::where('site_profile_id', $siteProfile->id)->count())->toBe(1);
});

it('reactivates the existing inactive pipeline on re-attempt instead of creating a duplicate', function () {
    $manager = User::factory()->projectManager()->create();
    $address = Address::create(['city' => 'Manila']);
    $siteProfile = SiteProfile::create(['name' => 'Inactive Site', 'type' => SiteProfileType::Condo, 'address_id' => $address->id]);
    $inactivePipeline = Pipeline::create([
        'name' => 'Inactive Site', 'pipeline_id' => nextPipelineId(), 'project_manager_id' => $manager->id,
        'site_profile_id' => $siteProfile->id, 'status' => PipelineStatus::Inactive,
    ]);

    $this->actingAs($manager)
        ->post(route('pipelines.store'), [
            'endorsement_date' => now()->toDateString(),
            'site_profile_id' => $siteProfile->id,
        ])
        ->assertRedirect(route('pipelines.show', $inactivePipeline));

    expect(Pipeline::where('site_profile_id', $siteProfile->id)->count())->toBe(1);

    $reactivated = $inactivePipeline->fresh();
    expect($reactivated->id)->toBe($inactivePipeline->id)
        ->and($reactivated->pipeline_id)->toBe($inactivePipeline->pipeline_id)
        ->and($reactivated->status)->not->toBe(PipelineStatus::Inactive);
});

it('creates a lead without optional marketing fields', function () {
    $manager = User::factory()->projectManager()->create();

    $this->actingAs($manager)
        ->post(route('pipelines.store'), validPipelineStorePayload($manager))
        ->assertRedirect();

    $survey = Pipeline::firstOrFail()->siteSurvey;

    expect($survey)->not->toBeNull()
        ->and($survey->marketing_paraphernalia)->toBeNull()
        ->and($survey->brochure_posting_location)->toBeNull();
});

it('validates optional marketing checkbox values when creating a lead', function () {
    $manager = User::factory()->projectManager()->create();

    $this->actingAs($manager)
        ->post(route('pipelines.store'), validPipelineStorePayload($manager, [
            'lobby_booth_allowed' => 'not-a-boolean',
        ]))
        ->assertSessionHasErrors('lobby_booth_allowed');
});

it('links an existing contact when creating a new lead', function () {
    $manager = User::factory()->projectManager()->create();
    $contact = SiteContact::create([
        'name' => 'Juan dela Cruz',
        'designation' => 'Property Manager',
        'email' => 'juan@example.com',
        'mobile_number' => '09171234567',
    ]);

    $this->actingAs($manager)
        ->post(route('pipelines.store'), validPipelineStorePayload($manager, [
            'contact_id' => $contact->id,
        ]))
        ->assertRedirect();

    $siteProfile = Pipeline::firstOrFail()->siteProfile;

    expect($siteProfile->contact_id)->toBe($contact->id)
        ->and(SiteContact::count())->toBe(1);
});

it('creates a new contact from manual contact fields when creating a lead', function () {
    $manager = User::factory()->projectManager()->create();

    $this->actingAs($manager)
        ->post(route('pipelines.store'), validPipelineStorePayload($manager, [
            'contact_name' => 'Maria Santos',
            'contact_designation' => 'Building Admin',
            'contact_email' => 'maria@example.com',
            'contact_mobile_number' => '09998887777',
        ]))
        ->assertRedirect();

    $siteProfile = Pipeline::firstOrFail()->siteProfile;
    $contact = SiteContact::firstOrFail();

    expect($siteProfile->contact_id)->toBe($contact->id)
        ->and($contact->name)->toBe('Maria Santos')
        ->and($contact->designation)->toBe('Building Admin')
        ->and($contact->email)->toBe('maria@example.com')
        ->and($contact->mobile_number)->toBe('09998887777');
});

it('creates a lead without a contact when contact data is omitted', function () {
    $manager = User::factory()->projectManager()->create();

    $this->actingAs($manager)
        ->post(route('pipelines.store'), validPipelineStorePayload($manager))
        ->assertRedirect();

    expect(Pipeline::firstOrFail()->siteProfile->contact_id)->toBeNull()
        ->and(SiteContact::count())->toBe(0);
});

it('validates the selected contact when creating a lead', function () {
    $manager = User::factory()->projectManager()->create();

    $this->actingAs($manager)
        ->post(route('pipelines.store'), validPipelineStorePayload($manager, [
            'contact_id' => 999,
        ]))
        ->assertSessionHasErrors('contact_id');
});

it('assigns newly created leads to the creator when the creator is an saq specialist', function () {
    $saq = User::factory()->saqSpecialist()->create();

    $this->actingAs($saq)
        ->post(route('pipelines.store'), validPipelineStorePayload($saq, [
            'site_name' => 'SAQ Assigned Site',
        ]))
        ->assertRedirect();

    $pipeline = Pipeline::firstOrFail();

    expect($pipeline->assigned_saq_id)->toBe($saq->id);
    expect($pipeline->status)->toBe(PipelineStatus::Prospecting);
    expect($pipeline->statusLogs()->latest('id')->first()->status)->toBe(PipelineStatus::Prospecting->value);
});

it('keeps lead saq created leads in prospect without assignment', function () {
    $leadSaq = User::factory()->leadSaqSpecialist()->create();

    $this->actingAs($leadSaq)
        ->post(route('pipelines.store'), validPipelineStorePayload($leadSaq, [
            'site_name' => 'Lead SAQ Prospect Site',
        ]))
        ->assertRedirect();

    $pipeline = Pipeline::firstOrFail();

    expect($pipeline->assigned_saq_id)->toBeNull();
    expect($pipeline->status)->toBe(PipelineStatus::Prospect);
    expect($pipeline->statusLogs()->latest('id')->first()->status)->toBe(PipelineStatus::Prospect->value);
});

it('returns a duplicate site error when creating a duplicate normalized site name', function () {
    $manager = User::factory()->projectManager()->create();
    $address = Address::create(['city' => 'Makati']);
    $siteProfile = SiteProfile::create([
        'name' => 'SM Jazz',
        'type' => SiteProfileType::Condo,
        'address_id' => $address->id,
    ]);
    $existing = Pipeline::create([
        'name' => 'SM Jazz',
        'pipeline_id' => nextPipelineId(),
        'project_manager_id' => $manager->id,
        'site_profile_id' => $siteProfile->id,
        'status' => PipelineStatus::Prospect,
    ]);

    $this->actingAs($manager)
        ->post(route('pipelines.store'), validPipelineStorePayload($manager, [
            'site_name' => '  sm   jazz  ',
        ]))
        ->assertRedirect(route('pipelines.create'))
        ->assertSessionHasErrors('site_name')
        ->assertSessionHas('duplicateSite', fn (array $site): bool => $site['id'] === $siteProfile->id
            && $site['name'] === 'SM Jazz'
            && $site['pipeline_id'] === $existing->id);

    expect(Pipeline::count())->toBe(1);
    expect(SiteProfile::count())->toBe(1);
});

it('links an existing duplicate site when submitted with site profile id', function () {
    $manager = User::factory()->projectManager()->create();
    $siteProfile = SiteProfile::create([
        'name' => 'Existing Link Site',
        'type' => SiteProfileType::Condo,
        'address_id' => Address::create(['city' => 'Makati'])->id,
    ]);

    $this->actingAs($manager)
        ->post(route('pipelines.store'), validPipelineStorePayload($manager, [
            'site_profile_id' => $siteProfile->id,
            'site_name' => null,
            'site_type' => null,
            'city' => null,
        ]))
        ->assertRedirect();

    $pipeline = Pipeline::firstOrFail();

    expect($pipeline->site_profile_id)->toBe($siteProfile->id);
    expect(SiteProfile::count())->toBe(1);
});

it('notifies previous lead touchers when a different user updates the lead', function () {
    Notification::fake();

    $manager = User::factory()->projectManager()->create();
    $previousUser = User::factory()->projectManager()->create();
    $address = Address::create(['city' => 'Manila']);
    $siteProfile = SiteProfile::create([
        'name' => 'Touched Site',
        'type' => SiteProfileType::Condo,
        'address_id' => $address->id,
    ]);
    $pipeline = Pipeline::create([
        'name' => 'Touched Site',
        'pipeline_id' => nextPipelineId(),
        'project_manager_id' => $manager->id,
        'site_profile_id' => $siteProfile->id,
        'status' => PipelineStatus::Prospect,
    ]);

    PipelineTouch::create([
        'pipeline_id' => $pipeline->id,
        'user_id' => $previousUser->id,
        'last_touched_at' => now()->subDay(),
    ]);

    $this->actingAs($manager)
        ->post(route('pipelines.add-remark', $pipeline), ['content' => 'Reached out to admin.'])
        ->assertRedirect();

    Notification::assertSentTo($previousUser, PipelineTouchedNotification::class);
    Notification::assertNotSentTo($manager, PipelineTouchedNotification::class);
});

it('notifies involved pipeline users once when a site is updated', function () {
    Notification::fake();

    ['pipeline' => $pipeline, 'manager' => $manager, 'surveyor' => $surveyor] = pipelineWithSurvey();
    $assignedSaq = User::factory()->saqSpecialist()->create();
    $previousUser = User::factory()->projectManager()->create();
    $actor = User::factory()->commercialManager()->create();

    $pipeline->update(['assigned_saq_id' => $assignedSaq->id]);
    $pipeline->statusLogs()->create([
        'status' => PipelineStatus::SiteSurvey->value,
        'changed_by' => $manager->id,
    ]);
    PipelineTouch::create([
        'pipeline_id' => $pipeline->id,
        'user_id' => $previousUser->id,
        'last_touched_at' => now()->subDay(),
    ]);

    app(PipelineActivity::class)->record($pipeline->fresh(), $actor, 'updated site details');

    Notification::assertSentTo($manager, PipelineTouchedNotification::class, 1);
    Notification::assertSentTo($assignedSaq, PipelineTouchedNotification::class);
    Notification::assertSentTo($surveyor, PipelineTouchedNotification::class);
    Notification::assertSentTo($previousUser, PipelineTouchedNotification::class);
    Notification::assertNotSentTo($actor, PipelineTouchedNotification::class);
});

it('exposes pending actions on the pipelines page', function () {
    $manager = User::factory()->projectManager()->create();
    $address = Address::create(['city' => 'Manila']);
    $siteProfile = SiteProfile::create([
        'name' => 'Pending Site',
        'type' => SiteProfileType::Condo,
        'address_id' => $address->id,
    ]);
    Pipeline::create([
        'name' => 'Pending Site',
        'pipeline_id' => nextPipelineId(),
        'project_manager_id' => $manager->id,
        'site_profile_id' => $siteProfile->id,
        'status' => PipelineStatus::Prospect,
    ]);
    Pipeline::firstOrFail()
        ->forceFill(['status_changed_at' => now()->subDays(3)])
        ->saveQuietly();

    $this->actingAs($manager)
        ->get(route('pipelines', ['tab' => 'pending']))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Pipelines/Index')
            ->has('pendingActions', 1)
            ->where('pendingActions.0.stage_context.stage', PipelineStatus::Prospect->value)
            ->where('pendingActions.0.stage_context.stage_age_days', 3)
            ->where('pendingActions.0.stage_context.owner_label', 'Lead SAQ / SAQ assignment'));
});

it('narrows the pending actions tab by search and status like the pipelines list does', function () {
    $manager = User::factory()->superAdmin()->create();
    $address = Address::create(['city' => 'Manila']);

    foreach (['Alpha Pending Site', 'Beta Pending Site'] as $siteName) {
        $siteProfile = SiteProfile::create([
            'name' => $siteName,
            'type' => SiteProfileType::Condo,
            'address_id' => $address->id,
        ]);
        Pipeline::create([
            'name' => $siteName,
            'pipeline_id' => nextPipelineId(),
            'project_manager_id' => $manager->id,
            'site_profile_id' => $siteProfile->id,
            'status' => PipelineStatus::Prospect,
        ]);
    }

    // Search and status filters on the Pending Action tab are computed against
    // every visible pipeline, ignoring both query params entirely.
    $this->actingAs($manager)
        ->get(route('pipelines', ['tab' => 'pending', 'search' => 'Alpha']))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Pipelines/Index')
            ->has('pendingActions', 1)
            ->where('pendingActions.0.pipeline.name', 'Alpha Pending Site'));

    $this->actingAs($manager)
        ->get(route('pipelines', ['tab' => 'pending', 'status' => PipelineStatus::Commercials->value]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Pipelines/Index')
            ->has('pendingActions', 0));
});

it('uses configurable thresholds for lead inactivity aging labels', function () {
    $manager = User::factory()->projectManager()->create();
    $address = Address::create(['city' => 'Manila']);
    $siteProfile = SiteProfile::create([
        'name' => 'Aging Site',
        'type' => SiteProfileType::Condo,
        'address_id' => $address->id,
    ]);
    $pipeline = Pipeline::create([
        'name' => 'Aging Site',
        'pipeline_id' => nextPipelineId(),
        'project_manager_id' => $manager->id,
        'site_profile_id' => $siteProfile->id,
        'last_activity_at' => now()->subDays(3),
    ]);

    $badge = app(PipelineActivity::class)->agingBadge($pipeline, [
        'yellow_days' => 2,
        'red_days' => 4,
    ]);

    expect($badge['level'])->toBe('yellow');
});

it('shows site survey stage age and assigned surveyor on the pipeline details page', function () {
    ['pipeline' => $pipeline, 'manager' => $manager, 'surveyor' => $surveyor] = pipelineWithSurvey(PipelineStatus::SiteSurvey);
    $surveyor->update(['full_name' => 'Survey Owner']);
    $pipeline->forceFill([
        'status_changed_at' => now()->subDays(5),
        'last_activity_at' => now()->subDay(),
    ])->saveQuietly();

    $this->actingAs($manager)
        ->get(route('pipelines.show', $pipeline))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Pipelines/Show')
            ->where('pipeline.stage_context.stage', PipelineStatus::SiteSurvey->value)
            ->where('pipeline.stage_context.stage_age_days', 5)
            ->where('pipeline.stage_context.stage_age_label', '5d in stage')
            ->where('pipeline.stage_context.owner_type', 'surveyor')
            ->where('pipeline.stage_context.owner_label', 'Survey Owner')
            ->where('pipeline.aging_badge.label', '1d'));
});

it('moves the stage owner from the Surveyor to the Design Plan assignee once survey findings are done', function () {
    ['pipeline' => $pipeline, 'manager' => $manager] = pipelineWithSurvey(PipelineStatus::SiteSurvey);
    $designer = User::factory()->infraDesigner()->create(['full_name' => 'Design Owner']);
    $pipeline->siteSurvey->update(['survey_completed_at' => now()]);
    SitePlanningAssignment::create([
        'pipeline_id' => $pipeline->id,
        'site_survey_id' => $pipeline->site_survey_id,
        'assigned_at' => now(),
        'design_plan_assignee_id' => $designer->id,
    ]);

    $this->actingAs($manager)
        ->get(route('pipelines.show', $pipeline))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Pipelines/Show')
            ->where('pipeline.stage_context.owner_type', 'design_plan_assignee')
            ->where('pipeline.stage_context.owner_label', 'Design Owner'));
});

it('falls back to a generic Infra Designer label once survey findings are done but no designer is assigned yet', function () {
    ['pipeline' => $pipeline, 'manager' => $manager] = pipelineWithSurvey(PipelineStatus::SiteSurvey);
    $pipeline->siteSurvey->update(['survey_completed_at' => now()]);

    $this->actingAs($manager)
        ->get(route('pipelines.show', $pipeline))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Pipelines/Show')
            ->where('pipeline.stage_context.owner_type', 'design_plan_assignee')
            ->where('pipeline.stage_context.owner_label', 'Infra Designer'));
});

it('shows prospecting stage age and assigned saq on the pipelines list', function () {
    $leadSaq = User::factory()->leadSaqSpecialist()->create();
    $assignedSaq = User::factory()->saqSpecialist()->create(['full_name' => 'Assigned Stage SAQ']);
    $address = Address::create(['city' => 'Manila']);
    $siteProfile = SiteProfile::create([
        'name' => 'Prospecting Stage Site',
        'type' => SiteProfileType::Condo,
        'address_id' => $address->id,
    ]);
    $pipeline = Pipeline::create([
        'name' => 'Prospecting Stage Site',
        'pipeline_id' => nextPipelineId(),
        'project_manager_id' => $leadSaq->id,
        'assigned_saq_id' => $assignedSaq->id,
        'site_profile_id' => $siteProfile->id,
        'status' => PipelineStatus::Prospecting,
    ]);
    $pipeline->forceFill(['status_changed_at' => now()->subDays(2)])->saveQuietly();

    $this->actingAs($leadSaq)
        ->get(route('pipelines'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Pipelines/Index')
            ->where('pipelines.data.0.stage_context.stage', PipelineStatus::Prospecting->value)
            ->where('pipelines.data.0.stage_context.stage_age_days', 2)
            ->where('pipelines.data.0.stage_context.owner_type', 'saq_specialist')
            ->where('pipelines.data.0.stage_context.owner_label', 'Assigned Stage SAQ'));
});

it('falls back to status logs and created at for stage age when status changed at is missing', function () {
    ['pipeline' => $pipeline] = pipelineWithSurvey(PipelineStatus::Commercials);
    $pipeline->forceFill([
        'created_at' => now()->subDays(10),
        'status_changed_at' => null,
    ])->saveQuietly();
    $statusLog = $pipeline->statusLogs()->create([
        'status' => PipelineStatus::Commercials->value,
        'changed_by' => $pipeline->project_manager_id,
    ]);
    $statusLog->forceFill([
        'created_at' => now()->subDays(4),
        'updated_at' => now()->subDays(4),
    ])->saveQuietly();

    $context = app(PipelineStageContext::class)->forPipeline($pipeline->fresh(), [
        'yellow_days' => 2,
        'red_days' => 4,
    ]);

    expect($context['stage_age_days'])->toBe(4)
        ->and($context['level'])->toBe('red');

    $pipeline->statusLogs()->delete();

    $context = app(PipelineStageContext::class)->forPipeline($pipeline->fresh(), [
        'yellow_days' => 2,
        'red_days' => 4,
    ]);

    expect($context['stage_age_days'])->toBe(10);
});

it('blocks stage transitions when configured required documents are missing', function () {
    $saq = User::factory()->saqSpecialist()->create();
    $address = Address::create(['city' => 'Manila']);
    $siteProfile = SiteProfile::create([
        'name' => 'Required Doc Site',
        'type' => SiteProfileType::Condo,
        'address_id' => $address->id,
    ]);
    $pipeline = Pipeline::create([
        'name' => 'Required Doc Site',
        'pipeline_id' => nextPipelineId(),
        'project_manager_id' => $saq->id,
        'site_profile_id' => $siteProfile->id,
        'assigned_saq_id' => $saq->id,
        'status' => PipelineStatus::Prospecting,
    ]);

    StageDocumentRequirement::create([
        'stage' => PipelineStatus::Prospecting->value,
        'name' => 'LOI',
        'is_required' => true,
        'is_active' => true,
    ]);

    $this->actingAs($saq)
        ->post(route('pipelines.move-to-site-survey-pending', $pipeline))
        ->assertRedirect()
        ->assertSessionHasErrors('required_documents');
});

it('gates commercials completion on a Settings-configured required document, and respects turning it off', function () {
    ['pipeline' => $pipeline] = pipelineInCommercials();

    $requirement = StageDocumentRequirement::create([
        'module' => 'pipeline',
        'stage' => PipelineStatus::Commercials->value,
        'name' => 'MOA',
        'is_required' => true,
        'is_active' => true,
    ]);

    expect(app(StageDocumentRequirements::class)->missingFor($pipeline, PipelineStatus::Commercials)->all())
        ->toBe(['MOA']);

    // Settings is the only source of truth now — unchecking "Required" here must actually
    // take effect (this used to be silently overridden by the now-removed CommercialDocumentType).
    $requirement->update(['is_required' => false]);

    expect(app(StageDocumentRequirements::class)->missingFor($pipeline, PipelineStatus::Commercials)->all())
        ->toBe([]);
});

it('keeps a not-required document category selectable for upload, not just required ones', function () {
    ['pipeline' => $pipeline, 'commercialManager' => $commercialManager] = pipelineInCommercials();

    StageDocumentRequirement::create([
        'module' => 'pipeline',
        'stage' => PipelineStatus::Commercials->value,
        'name' => 'Optional Extra',
        'is_required' => false,
        'is_active' => true,
    ]);

    $requirements = app(StageDocumentRequirements::class);

    // activeFor() drives the upload dropdown — "not required" must still mean "uploadable",
    // not "invisible". requiredFor() is the separate, narrower list that gates completion.
    expect($requirements->activeFor(PipelineStatus::Commercials)->pluck('name')->all())
        ->toContain('Optional Extra');
    expect($requirements->requiredFor(PipelineStatus::Commercials)->pluck('name')->all())
        ->not->toContain('Optional Extra');

    $this->actingAs($commercialManager)
        ->get(route('pipelines.show', $pipeline))
        ->assertInertia(fn (Assert $page) => $page
            ->where('documentTypes', fn ($types) => collect($types)->pluck('name')->contains('Optional Extra')));
});

it('exposes commercial-stage documents on the pipeline shown at the approval stage', function () {
    ['pipeline' => $pipeline, 'approver' => $approver] = pipelineInApproval();

    RolePermission::create(['role_id' => $approver->role_id, 'pipeline_status' => PipelineStatus::Approval->value]);

    $commercialDocument = Document::create([
        'name' => 'commercial.pdf',
        'type' => DocumentType::Document,
        'filename' => 'commercial.pdf',
        'file_path' => 'commercial-documents/commercial.pdf',
        'uploaded_by' => $approver->id,
        'uploaded_status' => PipelineStatus::Commercials->value,
        'category' => 'MOA',
    ]);
    $surveyDocument = Document::create([
        'name' => 'survey.jpg',
        'type' => DocumentType::Photo,
        'filename' => 'survey.jpg',
        'file_path' => 'survey-documents/survey.jpg',
        'uploaded_by' => $approver->id,
        'uploaded_status' => PipelineStatus::SiteSurvey->value,
    ]);
    $pipeline->documents()->attach([$commercialDocument->id, $surveyDocument->id], ['created_at' => now()]);

    $this->actingAs($approver)
        ->get(route('pipelines.show', $pipeline))
        ->assertInertia(fn (Assert $page) => $page
            ->where('pipeline.documents', fn ($documents) => collect($documents)
                ->where('uploaded_status', PipelineStatus::Commercials->value)
                ->pluck('name')
                ->contains('commercial.pdf')));
});

// ─── startAcquisition ─────────────────────────────────────────────────────────

it('requires a visit note when starting acquisition', function () {
    ['pipeline' => $pipeline, 'manager' => $manager] = pipelineWithSurvey(PipelineStatus::Prospect);

    $this->actingAs($manager)
        ->post(route('pipelines.start-acquisition', $pipeline), [])
        ->assertSessionHasErrors('content');
});

it('starts acquisition and records the starting lead status as visit history', function () {
    ['pipeline' => $pipeline, 'manager' => $manager] = pipelineWithSurvey(PipelineStatus::Prospect);

    $this->actingAs($manager)
        ->post(route('pipelines.start-acquisition', $pipeline), [
            'content' => 'Developer verbally confirmed access for initial visit.',
        ])
        ->assertRedirect(route('pipelines.show', $pipeline));

    expect($pipeline->fresh()->status)->toBe(PipelineStatus::Prospecting);
    expect($pipeline->remarkLogs()->where('type', 'visit')->where('content', 'Developer verbally confirmed access for initial visit.')->exists())->toBeTrue();
});

it('uploads documents when starting acquisition', function () {
    Storage::fake('public');

    ['pipeline' => $pipeline, 'manager' => $manager] = pipelineWithSurvey(PipelineStatus::Prospect);

    $file = UploadedFile::fake()->create('endorsement.pdf', 100, 'application/pdf');

    $this->actingAs($manager)
        ->post(route('pipelines.start-acquisition', $pipeline), [
            'content' => 'All documents have been received.',
            'files' => [$file],
        ])
        ->assertRedirect(route('pipelines.show', $pipeline));

    $document = $pipeline->fresh()->documents()->first();

    expect($document)->not->toBeNull();
    expect($document->type)->toBe(DocumentType::Document);
    expect($document->uploaded_status)->toBe(PipelineStatus::Prospect->value);
    Storage::disk('public')->assertExists($document->file_path);
});

it('blocks starting acquisition when a required Prospect-stage document is missing', function () {
    ['pipeline' => $pipeline, 'manager' => $manager] = pipelineWithSurvey(PipelineStatus::Prospect);
    StageDocumentRequirement::create([
        'module' => 'pipeline',
        'stage' => PipelineStatus::Prospect->value,
        'name' => 'Site Photos',
        'is_active' => true,
        'is_required' => true,
        'sort_order' => 1,
    ]);

    $this->actingAs($manager)
        ->post(route('pipelines.start-acquisition', $pipeline), [
            'content' => 'Developer verbally confirmed access for initial visit.',
        ])
        ->assertSessionHasErrors('required_documents');

    expect($pipeline->fresh()->status)->toBe(PipelineStatus::Prospect);
});

it('allows starting acquisition once the required Prospect-stage document is uploaded', function () {
    Storage::fake('public');
    ['pipeline' => $pipeline, 'manager' => $manager] = pipelineWithSurvey(PipelineStatus::Prospect);
    StageDocumentRequirement::create([
        'module' => 'pipeline',
        'stage' => PipelineStatus::Prospect->value,
        'name' => 'Site Photos',
        'is_active' => true,
        'is_required' => true,
        'sort_order' => 1,
    ]);

    $this->actingAs($manager)
        ->post(route('pipelines.start-acquisition', $pipeline), [
            'content' => 'Developer verbally confirmed access for initial visit.',
            'document_category' => 'Site Photos',
            'files' => [UploadedFile::fake()->create('site.pdf', 100, 'application/pdf')],
        ])
        ->assertRedirect(route('pipelines.show', $pipeline));

    expect($pipeline->fresh()->status)->toBe(PipelineStatus::Prospecting);
});

// ─── scheduleSiteVisit ────────────────────────────────────────────────────────

it('moves an assigned prospecting pipeline to site survey pending', function () {
    $manager = User::factory()->projectManager()->create();
    $saq = User::factory()->saqSpecialist()->create();

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
        'assigned_saq_id' => $saq->id,
        'site_profile_id' => $siteProfile->id,
        'status' => PipelineStatus::Prospecting,
    ]);

    $this->actingAs($saq)
        ->post(route('pipelines.move-to-site-survey-pending', $pipeline))
        ->assertRedirect(route('pipelines.show', $pipeline));

    expect($pipeline->fresh()->status)->toBe(PipelineStatus::SiteSurveyPending);
    expect($pipeline->fresh()->siteSurvey)->not->toBeNull();
});

// Scheduling a site survey, and completing the site survey substage, now live in the Site
// Planning module — see tests/Feature/SitePlanningControllerTest.php. Network Feasibility
// CRUD/completion lives in the Network module — see tests/Feature/NetworkControllerTest.php.
// The dual-completion → Commercials advance rule (survey + network feasibility) is covered
// end-to-end from the Network module's side in NetworkControllerTest.php.

it('shows separate site survey and network feasibility pending actions', function () {
    ['pipeline' => $pipeline, 'manager' => $manager, 'surveyor' => $surveyor] = pipelineWithSurvey();
    $surveyor->update(['full_name' => 'Pending Surveyor']);

    $this->actingAs($manager)
        ->get(route('pipelines', ['tab' => 'pending']))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Pipelines/Index')
            ->has('pendingActions', 2)
            ->where('pendingActions.0.pipeline.id', $pipeline->id)
            ->where('pendingActions.0.action_key', 'complete_site_survey')
            ->where('pendingActions.0.action_label', 'Complete Site Survey')
            ->where('pendingActions.0.owner_label', 'Pending Surveyor')
            ->where('pendingActions.1.pipeline.id', $pipeline->id)
            ->where('pendingActions.1.action_key', 'complete_network_feasibility')
            ->where('pendingActions.1.action_label', 'Complete Network Feasibility')
            ->where('pendingActions.1.owner_label', 'Network users'));
});

// Rescheduling/updating site visit progress and deleting a Site Survey-stage document now
// live in the Site Planning module — see tests/Feature/SitePlanningControllerTest.php.

it('only detaches a survey document when it is still referenced elsewhere', function () {
    Storage::fake('public');

    ['pipeline' => $pipeline, 'surveyor' => $surveyor] = pipelineWithSurvey();

    $anotherAddress = Address::create(['city' => 'Cebu']);
    $anotherProfile = SiteProfile::create([
        'name' => 'Another Site',
        'type' => SiteProfileType::Condo,
        'address_id' => $anotherAddress->id,
    ]);

    $anotherSurvey = SiteSurvey::create([
        'site_profile_id' => $anotherProfile->id,
        'surveyor_id' => $surveyor->id,
        'survey_date' => now()->toDateString(),
    ]);

    $file = UploadedFile::fake()->image('shared.jpg');
    $path = $file->store('survey-documents', 'public');

    $document = Document::create([
        'name' => 'shared.jpg',
        'type' => DocumentType::Photo,
        'filename' => 'shared.jpg',
        'file_path' => $path,
    ]);

    $pipeline->siteSurvey->documents()->attach($document->id, ['created_at' => now()]);
    $anotherSurvey->documents()->attach($document->id, ['created_at' => now()]);

    $this->actingAs($surveyor)
        ->delete(route('pipelines.survey-documents.delete', [$pipeline, $document]));

    expect(Document::find($document->id))->not->toBeNull();
    Storage::disk('public')->assertExists($path);
});

it('blocks a surveyor from deleting a document uploaded during prospecting', function () {
    ['pipeline' => $pipeline, 'surveyor' => $surveyor] = pipelineWithSurvey();

    $document = Document::create([
        'name' => 'prospecting-doc.pdf',
        'type' => DocumentType::Document,
        'filename' => 'prospecting-doc.pdf',
        'file_path' => 'survey-documents/prospecting-doc.pdf',
        'uploaded_status' => PipelineStatus::Prospecting->value,
    ]);

    $pipeline->siteSurvey->documents()->attach($document->id, ['created_at' => now()]);

    $this->actingAs($surveyor)
        ->delete(route('pipelines.survey-documents.delete', [$pipeline, $document]))
        ->assertStatus(403);

    expect($pipeline->siteSurvey->documents()->where('document_id', $document->id)->exists())->toBeTrue();
});

it('returns 422 when deleting a survey document on wrong status', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    ['pipeline' => $pipeline] = pipelineWithSurvey(PipelineStatus::Commercials);

    $document = Document::create([
        'name' => 'file.pdf',
        'type' => DocumentType::Document,
        'filename' => 'file.pdf',
        'file_path' => 'survey-documents/file.pdf',
    ]);

    $pipeline->siteSurvey->documents()->attach($document->id, ['created_at' => now()]);

    $this->actingAs($superAdmin)
        ->delete(route('pipelines.survey-documents.delete', [$pipeline, $document]))
        ->assertStatus(422);
});

// ─── editSurveyFindings ────────────────────────────────────────────────────────

it('blocks a commercial manager from editing survey findings once past site survey', function () {
    ['pipeline' => $pipeline] = pipelineWithSurvey(PipelineStatus::Commercials);
    $commercialManager = User::factory()->commercialManager()->create();

    $this->actingAs($commercialManager)
        ->post(route('pipelines.survey-findings', $pipeline), [])
        ->assertForbidden();
});

// Editing survey findings while in Site Survey now lives in the Site Planning module —
// see tests/Feature/SitePlanningControllerTest.php.

// ─── saveCommercialsProgress ──────────────────────────────────────────────────

it('saves commercials progress without changing status or triggering approval', function () {
    Storage::fake('public');

    ['pipeline' => $pipeline, 'commercialManager' => $commercialManager] = pipelineInCommercials();

    $this->actingAs($commercialManager)
        ->post(route('pipelines.save-commercials-progress', $pipeline), [
            'remarks' => 'Negotiating lease terms.',
        ])
        ->assertRedirect(route('pipelines.show', $pipeline));

    expect($pipeline->fresh()->status)->toBe(PipelineStatus::Commercials);
    expect($pipeline->fresh()->commercial_remarks)->toBe('Negotiating lease terms.');
    expect($pipeline->fresh()->approvals()->count())->toBe(0);
});

it('uploads files when saving commercials progress', function () {
    Storage::fake('public');

    ['pipeline' => $pipeline, 'commercialManager' => $commercialManager] = pipelineInCommercials();

    $file = UploadedFile::fake()->create('contract.pdf', 100, 'application/pdf');

    $this->actingAs($commercialManager)
        ->post(route('pipelines.save-commercials-progress', $pipeline), [
            'files' => [$file],
        ]);

    $doc = $pipeline->fresh()->documents()->first();

    expect($doc)->not->toBeNull();
    expect($doc->type)->toBe(DocumentType::Document);
    Storage::disk('public')->assertExists($doc->file_path);
});

it('returns 422 when saving commercials progress on wrong status', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    ['pipeline' => $pipeline] = pipelineWithSurvey(PipelineStatus::SiteSurvey);

    $this->actingAs($superAdmin)
        ->post(route('pipelines.save-commercials-progress', $pipeline), [
            'remarks' => 'Some notes.',
        ])
        ->assertStatus(422);
});

// ─── setCommercials ───────────────────────────────────────────────────────────

it('sets commercials and transitions to ongoing pipeline approval', function () {
    Storage::fake('public');

    ['pipeline' => $pipeline, 'manager' => $manager, 'commercialManager' => $commercialManager] = pipelineInCommercials();

    $approverRole = Role::factory()->create();

    ApprovalConfig::create([
        'step' => 1,
        'role_id' => $approverRole->id,
        'is_active' => true,
        'created_by' => $manager->id,
        'created_at' => now(),
    ]);

    $this->actingAs($commercialManager)
        ->post(route('pipelines.set-commercials', $pipeline), [
            'remarks' => 'Final terms agreed.',
        ])
        ->assertRedirect(route('pipelines.show', $pipeline))
        // The "Commercials submitted..." success message must actually be flashed,
        // not just returned in the response body.
        ->assertSessionHas('success', 'Commercials submitted. The approval process has been triggered.');

    expect($pipeline->fresh()->status)->toBe(PipelineStatus::Approval);
    expect($pipeline->fresh()->commercial_remarks)->toBe('Final terms agreed.');
    expect($pipeline->fresh()->approvals()->count())->toBeGreaterThan(0);
});

it('returns 422 when setting commercials with no active approval config', function () {
    ['pipeline' => $pipeline, 'commercialManager' => $commercialManager] = pipelineInCommercials();

    $this->actingAs($commercialManager)
        ->post(route('pipelines.set-commercials', $pipeline), [
            'remarks' => 'Some remarks.',
        ])
        ->assertStatus(422);
});

it('requires all configured required commercial document types before approval', function () {
    Storage::fake('public');

    ['pipeline' => $pipeline, 'manager' => $manager, 'commercialManager' => $commercialManager] = pipelineInCommercials();
    $approverRole = Role::factory()->create();

    ApprovalConfig::create([
        'step' => 1,
        'role_id' => $approverRole->id,
        'is_active' => true,
        'created_by' => $manager->id,
        'created_at' => now(),
    ]);

    StageDocumentRequirement::create(['module' => 'pipeline', 'stage' => PipelineStatus::Commercials->value, 'name' => 'NTP', 'is_required' => true, 'is_active' => true]);
    StageDocumentRequirement::create(['module' => 'pipeline', 'stage' => PipelineStatus::Commercials->value, 'name' => 'MOA', 'is_required' => true, 'is_active' => true]);

    $this->actingAs($commercialManager)
        ->post(route('pipelines.set-commercials', $pipeline), [
            'document_category' => 'NTP',
            'files' => [UploadedFile::fake()->create('ntp.pdf', 100, 'application/pdf')],
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('required_documents');
});

it('submits commercials when required commercial document types are attached', function () {
    Storage::fake('public');

    ['pipeline' => $pipeline, 'manager' => $manager, 'commercialManager' => $commercialManager] = pipelineInCommercials();
    $approverRole = Role::factory()->create();

    ApprovalConfig::create([
        'step' => 1,
        'role_id' => $approverRole->id,
        'is_active' => true,
        'created_by' => $manager->id,
        'created_at' => now(),
    ]);

    StageDocumentRequirement::create(['module' => 'pipeline', 'stage' => PipelineStatus::Commercials->value, 'name' => 'NTP', 'is_required' => true, 'is_active' => true]);
    StageDocumentRequirement::create(['module' => 'pipeline', 'stage' => PipelineStatus::Commercials->value, 'name' => 'MOA', 'is_required' => true, 'is_active' => true]);

    $ntp = Document::create([
        'name' => 'ntp.pdf',
        'type' => DocumentType::Document,
        'filename' => 'ntp.pdf',
        'file_path' => 'commercial-documents/ntp.pdf',
        'category' => 'NTP',
    ]);

    $pipeline->documents()->attach($ntp->id, ['created_at' => now()]);

    $this->actingAs($commercialManager)
        ->post(route('pipelines.set-commercials', $pipeline), [
            'document_category' => 'MOA',
            'files' => [UploadedFile::fake()->create('moa.pdf', 100, 'application/pdf')],
        ])
        ->assertRedirect(route('pipelines.show', $pipeline));

    expect($pipeline->fresh()->status)->toBe(PipelineStatus::Approval);
});

it('creates one approval row per active config step', function () {
    ['pipeline' => $pipeline, 'manager' => $manager, 'commercialManager' => $commercialManager] = pipelineInCommercials();

    $approverRole = Role::factory()->create();

    foreach ([1, 2, 3] as $step) {
        ApprovalConfig::create([
            'step' => $step,
            'role_id' => $approverRole->id,
            'is_active' => true,
            'created_by' => $manager->id,
            'created_at' => now(),
        ]);
    }

    $this->actingAs($commercialManager)
        ->post(route('pipelines.set-commercials', $pipeline), [])
        ->assertRedirect();

    expect($pipeline->fresh()->approvals()->count())->toBe(3);
});

it('ignores inactive approval configs when submitting commercials', function () {
    ['pipeline' => $pipeline, 'manager' => $manager, 'commercialManager' => $commercialManager] = pipelineInCommercials();

    $approverRole = Role::factory()->create();

    ApprovalConfig::create([
        'step' => 1,
        'role_id' => $approverRole->id,
        'is_active' => true,
        'created_by' => $manager->id,
        'created_at' => now(),
    ]);

    ApprovalConfig::create([
        'step' => 2,
        'role_id' => $approverRole->id,
        'is_active' => false,
        'created_by' => $manager->id,
        'created_at' => now(),
    ]);

    $this->actingAs($commercialManager)
        ->post(route('pipelines.set-commercials', $pipeline), [])
        ->assertRedirect();

    expect($pipeline->fresh()->approvals()->count())->toBe(1);
});

it('creates approval rows with correct step numbers and roles', function () {
    ['pipeline' => $pipeline, 'manager' => $manager, 'commercialManager' => $commercialManager] = pipelineInCommercials();

    $roleA = Role::factory()->create();
    $roleB = Role::factory()->create();

    ApprovalConfig::create(['step' => 1, 'role_id' => $roleA->id, 'is_active' => true, 'created_by' => $manager->id, 'created_at' => now()]);
    ApprovalConfig::create(['step' => 2, 'role_id' => $roleB->id, 'is_active' => true, 'created_by' => $manager->id, 'created_at' => now()]);

    $this->actingAs($commercialManager)
        ->post(route('pipelines.set-commercials', $pipeline), [])
        ->assertRedirect();

    $approvals = $pipeline->fresh()->approvals()->orderBy('step')->get();

    expect($approvals[0]->step)->toBe(1);
    expect($approvals[0]->role_id)->toBe($roleA->id);
    expect($approvals[1]->step)->toBe(2);
    expect($approvals[1]->role_id)->toBe($roleB->id);
});

// ─── deleteCommercialDocument ─────────────────────────────────────────────────

it('detaches and deletes a commercial document when no other references exist', function () {
    Storage::fake('public');

    ['pipeline' => $pipeline, 'commercialManager' => $commercialManager] = pipelineInCommercials();

    $file = UploadedFile::fake()->create('contract.pdf', 100, 'application/pdf');
    $path = $file->store('commercial-documents', 'public');

    $document = Document::create([
        'name' => 'contract.pdf',
        'type' => DocumentType::Document,
        'filename' => 'contract.pdf',
        'file_path' => $path,
    ]);

    $pipeline->documents()->attach($document->id, ['created_at' => now()]);

    $this->actingAs($commercialManager)
        ->delete(route('pipelines.commercial-documents.delete', [$pipeline, $document]))
        ->assertRedirect(route('pipelines.show', $pipeline));

    expect(Document::find($document->id))->toBeNull();
    Storage::disk('public')->assertMissing($path);
});

it('replaces a commercial document as a new version instead of deleting it', function () {
    Storage::fake('public');

    ['pipeline' => $pipeline, 'commercialManager' => $commercialManager] = pipelineInCommercials();

    $file = UploadedFile::fake()->create('contract.pdf', 100, 'application/pdf');
    $path = $file->store('commercial-documents', 'public');

    $document = Document::create([
        'name' => 'contract.pdf',
        'type' => DocumentType::Document,
        'filename' => 'contract.pdf',
        'file_path' => $path,
        'category' => 'MOA',
    ]);

    $pipeline->documents()->attach($document->id, ['created_at' => now()]);

    $this->actingAs($commercialManager)
        ->post(route('pipelines.commercial-documents.replace', [$pipeline, $document]), [
            'file' => UploadedFile::fake()->create('contract-v2.pdf', 100, 'application/pdf'),
        ])
        ->assertRedirect(route('pipelines.show', $pipeline));

    $current = $pipeline->fresh()->documents()->first();
    expect($current->id)->not->toBe($document->id)
        ->and($current->version)->toBe(2)
        ->and($current->category)->toBe('MOA');

    expect($document->fresh()->superseded_at)->not->toBeNull();
    expect(Document::find($document->id))->not->toBeNull();
    Storage::disk('public')->assertExists($path);
});

it('detaches but does not delete a commercial document still attached to a ProjectStage', function () {
    Storage::fake('public');

    ['pipeline' => $pipeline, 'commercialManager' => $commercialManager] = pipelineInCommercials();

    $file = UploadedFile::fake()->create('moa.pdf', 100, 'application/pdf');
    $path = $file->store('commercial-documents', 'public');

    $document = Document::create([
        'name' => 'moa.pdf',
        'type' => DocumentType::Document,
        'filename' => 'moa.pdf',
        'file_path' => $path,
        'category' => 'MOA',
    ]);

    $pipeline->documents()->attach($document->id, ['created_at' => now()]);

    $project = Project::create(['pipeline_id' => $pipeline->id]);
    $planningStage = ProjectStage::create(['project_id' => $project->id, 'stage' => 'Planning', 'status' => 'Pending', 'order' => 1]);
    $planningStage->documents()->attach($document->id, ['created_at' => now()]);

    $this->actingAs($commercialManager)
        ->delete(route('pipelines.commercial-documents.delete', [$pipeline, $document]))
        ->assertRedirect(route('pipelines.show', $pipeline));

    expect(Document::find($document->id))->not->toBeNull();
    expect($pipeline->documents()->where('document_id', $document->id)->exists())->toBeFalse();
    expect($planningStage->documents()->where('document_id', $document->id)->exists())->toBeTrue();
    Storage::disk('public')->assertExists($path);
});

it('blocks removing a required commercial document while the pipeline is in approval', function () {
    Storage::fake('public');

    ['pipeline' => $pipeline] = pipelineWithSurvey(PipelineStatus::Approval);
    $commercialManager = User::factory()->commercialManager()->create();

    StageDocumentRequirement::create([
        'module' => 'pipeline', 'stage' => PipelineStatus::Commercials->value,
        'name' => 'MOA', 'is_required' => true, 'is_active' => true, 'sort_order' => 1,
    ]);

    $file = UploadedFile::fake()->create('moa.pdf', 100, 'application/pdf');
    $path = $file->store('commercial-documents', 'public');
    $document = Document::create([
        'name' => 'moa.pdf', 'type' => DocumentType::Document, 'filename' => 'moa.pdf',
        'file_path' => $path, 'category' => 'MOA',
    ]);
    $pipeline->documents()->attach($document->id, ['created_at' => now()]);

    $this->actingAs($commercialManager)
        ->delete(route('pipelines.commercial-documents.delete', [$pipeline, $document]))
        ->assertSessionHasErrors('document');

    expect($pipeline->documents()->where('document_id', $document->id)->exists())->toBeTrue();
});

it('allows removing a non-required commercial document while the pipeline is in approval', function () {
    Storage::fake('public');

    ['pipeline' => $pipeline] = pipelineWithSurvey(PipelineStatus::Approval);
    $commercialManager = User::factory()->commercialManager()->create();

    $file = UploadedFile::fake()->create('extra.pdf', 100, 'application/pdf');
    $path = $file->store('commercial-documents', 'public');
    $document = Document::create([
        'name' => 'extra.pdf', 'type' => DocumentType::Document, 'filename' => 'extra.pdf',
        'file_path' => $path,
    ]);
    $pipeline->documents()->attach($document->id, ['created_at' => now()]);

    $this->actingAs($commercialManager)
        ->delete(route('pipelines.commercial-documents.delete', [$pipeline, $document]))
        ->assertRedirect(route('pipelines.show', $pipeline));

    expect($pipeline->documents()->where('document_id', $document->id)->exists())->toBeFalse();
});

it('returns 403 when deleting a commercial document not belonging to the pipeline', function () {
    ['pipeline' => $pipeline, 'commercialManager' => $commercialManager] = pipelineInCommercials();

    $document = Document::create([
        'name' => 'other.pdf',
        'type' => DocumentType::Document,
        'filename' => 'other.pdf',
        'file_path' => 'commercial-documents/other.pdf',
    ]);

    $this->actingAs($commercialManager)
        ->delete(route('pipelines.commercial-documents.delete', [$pipeline, $document]))
        ->assertStatus(403);
});

it('returns 422 when deleting a commercial document on wrong status', function () {
    $commercialManager = User::factory()->commercialManager()->create();
    ['pipeline' => $pipeline] = pipelineWithSurvey(PipelineStatus::SiteSurvey);

    $document = Document::create([
        'name' => 'file.pdf',
        'type' => DocumentType::Document,
        'filename' => 'file.pdf',
        'file_path' => 'commercial-documents/file.pdf',
    ]);

    $pipeline->documents()->attach($document->id, ['created_at' => now()]);

    $this->actingAs($commercialManager)
        ->delete(route('pipelines.commercial-documents.delete', [$pipeline, $document]))
        ->assertStatus(422);
});

// ─── reviseCommercials ────────────────────────────────────────────────────────

it('revises commercials and resets all approval steps to pending', function () {
    Storage::fake('public');

    $commercialManager = User::factory()->commercialManager()->create();
    ['pipeline' => $pipeline, 'approval' => $approval] = pipelineInApproval();

    $approval->update(['status' => ApprovalStatus::Approved, 'decided_at' => now()]);

    $this->actingAs($commercialManager)
        ->patch(route('pipelines.revise-commercials', $pipeline), [
            'remarks' => 'Revised terms.',
        ])
        ->assertRedirect(route('pipelines.show', $pipeline));

    expect($pipeline->fresh()->commercial_remarks)->toBe('Revised terms.');
    expect($pipeline->approvals()->where('status', ApprovalStatus::Pending)->count())
        ->toBe($pipeline->approvals()->count());
});

it('categorizes documents uploaded during a commercials revision', function () {
    Storage::fake('public');

    $commercialManager = User::factory()->commercialManager()->create();
    ['pipeline' => $pipeline] = pipelineInApproval();

    StageDocumentRequirement::create([
        'module' => 'pipeline', 'stage' => PipelineStatus::Commercials->value,
        'name' => 'MOA', 'is_required' => true, 'is_active' => true, 'sort_order' => 1,
    ]);

    $file = UploadedFile::fake()->create('moa-replacement.pdf', 100, 'application/pdf');

    $this->actingAs($commercialManager)
        ->patch(route('pipelines.revise-commercials', $pipeline), [
            'remarks' => 'Replaced MOA.',
            'document_category' => 'MOA',
            'files' => [$file],
        ])
        ->assertRedirect(route('pipelines.show', $pipeline));

    $document = $pipeline->fresh()->documents()->latest('documents.id')->first();
    expect($document)->not->toBeNull()
        ->and($document->category)->toBe('MOA');
});

// ─── markInactive ─────────────────────────────────────────────────────────────

it('marks an active pipeline as inactive', function () {
    ['pipeline' => $pipeline, 'manager' => $manager] = pipelineWithSurvey(PipelineStatus::ReadyForService);
    $admin = User::factory()->superAdmin()->create();

    $this->actingAs($admin)
        ->post(route('pipelines.mark-inactive', $pipeline))
        ->assertRedirect(route('pipelines.show', $pipeline));

    expect($pipeline->fresh()->status)->toBe(PipelineStatus::Inactive);
});

it('returns 422 when marking an already inactive pipeline as inactive', function () {
    ['pipeline' => $pipeline, 'manager' => $manager] = pipelineWithSurvey(PipelineStatus::Inactive);

    $this->actingAs($manager)
        ->post(route('pipelines.mark-inactive', $pipeline))
        ->assertStatus(422);
});

it('returns 422 when marking a completed pipeline as inactive', function () {
    ['pipeline' => $pipeline, 'manager' => $manager] = pipelineWithSurvey(PipelineStatus::Completed);

    $this->actingAs($manager)
        ->post(route('pipelines.mark-inactive', $pipeline))
        ->assertStatus(422);
});

it('blocks marking a pipeline inactive while it is mid-approval', function () {
    ['pipeline' => $pipeline] = pipelineWithSurvey(PipelineStatus::Approval);
    $admin = User::factory()->superAdmin()->create();

    $this->actingAs($admin)
        ->post(route('pipelines.mark-inactive', $pipeline))
        ->assertStatus(422);

    expect($pipeline->fresh()->status)->toBe(PipelineStatus::Approval);
});

it('redirects to the pipeline list instead of 403 when marking inactive removes the actor\'s view access', function () {
    ['pipeline' => $pipeline, 'commercialManager' => $commercialManager] = pipelineInCommercials();

    $this->actingAs($commercialManager)
        ->post(route('pipelines.mark-inactive', $pipeline))
        ->assertRedirect(route('pipelines'));

    expect($pipeline->fresh()->status)->toBe(PipelineStatus::Inactive);
});

it('keeps the marking actor\'s view access, and only clears survey/design-plan progress on re-attempt', function () {
    ['pipeline' => $pipeline, 'surveyor' => $surveyor] = pipelineWithSurvey(PipelineStatus::SiteSurvey);
    $admin = User::factory()->superAdmin()->create();
    $saq = User::factory()->saqSpecialist()->create();

    $pipeline->siteSurvey->update([
        'survey_completed_at' => now(),
        'survey_completed_by_id' => $surveyor->id,
        'network_feasibility_completed_at' => now(),
        'network_feasibility_completed_by_id' => $surveyor->id,
    ]);

    $assignment = SitePlanningAssignment::create([
        'pipeline_id' => $pipeline->id,
        'site_survey_id' => $pipeline->site_survey_id,
        'assigned_at' => now(),
        'design_plan_type' => 'FIBER',
        'design_plan_completed_at' => now(),
        'design_plan_completed_by_id' => $surveyor->id,
        'design_plan_approved_at' => now(),
        'design_plan_approved_by_id' => $admin->id,
    ]);
    $assignment->sitePlanningTasks()->create([
        'name' => 'Sample task', 'weight' => 100, 'status' => IspTaskStatus::Done, 'status_changed_at' => now(), 'sort_order' => 0,
    ]);

    // The surveyor who marks their own assigned pipeline inactive should still be able to view
    // it afterward — marking inactive must not itself clear surveyor_id (PipelinePolicy::view()
    // is scoped to it), only re-attempting a fresh lead should.
    $this->actingAs($surveyor)
        ->post(route('pipelines.mark-inactive', $pipeline))
        ->assertRedirect(route('pipelines.show', $pipeline));

    $survey = $pipeline->siteSurvey->fresh();
    expect($survey->surveyor_id)->toBe($surveyor->id)
        ->and($assignment->fresh()->design_plan_type)->toBe('FIBER');

    $this->actingAs($saq)
        ->post(route('pipelines.store'), [
            'endorsement_date' => now()->toDateString(),
            'site_profile_id' => $pipeline->site_profile_id,
        ])
        ->assertRedirect(route('pipelines.show', $pipeline));

    $survey = $survey->fresh();
    expect($survey->surveyor_id)->toBeNull()
        ->and($survey->survey_date)->toBeNull()
        ->and($survey->survey_completed_at)->toBeNull()
        ->and($survey->survey_completed_by_id)->toBeNull()
        ->and($survey->network_feasibility_completed_at)->toBeNull()
        ->and($survey->network_feasibility_completed_by_id)->toBeNull();

    $assignment = $assignment->fresh();
    expect($assignment->design_plan_type)->toBeNull()
        ->and($assignment->design_plan_completed_at)->toBeNull()
        ->and($assignment->design_plan_completed_by_id)->toBeNull()
        ->and($assignment->design_plan_approved_at)->toBeNull()
        ->and($assignment->design_plan_approved_by_id)->toBeNull()
        ->and($assignment->sitePlanningTasks()->count())->toBe(0);
});

it('lets a reactivated pipeline be rescheduled instead of getting stuck at Site Survey Pending (PTC-23)', function () {
    $newSurveyor = User::factory()->surveyor()->create();
    $saq = User::factory()->saqSpecialist()->create();
    $infraManager = User::factory()->infrastructureManager()->create();

    ['pipeline' => $pipeline, 'surveyor' => $originalSurveyor] = pipelineWithSurvey(PipelineStatus::SiteSurvey);

    $this->actingAs($originalSurveyor)
        ->post(route('pipelines.mark-inactive', $pipeline))
        ->assertRedirect();

    $this->actingAs($saq)
        ->post(route('pipelines.store'), [
            'endorsement_date' => now()->toDateString(),
            'site_profile_id' => $pipeline->site_profile_id,
        ])
        ->assertRedirect(route('pipelines.show', $pipeline));

    $reactivated = $pipeline->fresh();
    expect($reactivated->status)->toBe(PipelineStatus::Prospecting)
        ->and($reactivated->assigned_saq_id)->toBe($saq->id);

    $this->actingAs($saq)
        ->post(route('pipelines.move-to-site-survey-pending', $reactivated))
        ->assertRedirect(route('pipelines.show', $reactivated));

    $reactivated = $reactivated->fresh();
    expect($reactivated->status)->toBe(PipelineStatus::SiteSurveyPending)
        ->and($reactivated->siteSurvey->surveyor_id)->toBeNull()
        ->and($reactivated->siteSurvey->survey_date)->toBeNull();

    $assignment = $reactivated->sitePlanningAssignment;
    expect($assignment)->not->toBeNull();

    $this->actingAs($infraManager)
        ->patch(route('site-planning.schedule', $assignment), [
            'surveyor_id' => $newSurveyor->id,
            'survey_date' => now()->addDay()->toDateString(),
            'design_plan_type' => 'FIBER',
        ])
        ->assertRedirect(route('site-planning.show', $assignment));

    expect($reactivated->fresh()->status)->toBe(PipelineStatus::SiteSurvey)
        ->and($reactivated->fresh()->siteSurvey->surveyor_id)->toBe($newSurveyor->id);
});

// ─── reopenSurvey ─────────────────────────────────────────────────────────

it('redirects to the pipeline list instead of 403 when re-opening the survey removes the actor\'s view access', function () {
    ['pipeline' => $pipeline, 'commercialManager' => $commercialManager] = pipelineInCommercials();

    // The Commercial Manager role only has view permission for the Commercials/Approval
    // stages, so re-opening the survey (which moves the pipeline back to Site Survey)
    // would 403 them on the destination page without the redirect-fallback.
    $this->actingAs($commercialManager)
        ->post(route('pipelines.reopen-survey', $pipeline))
        ->assertRedirect(route('pipelines'));

    expect($pipeline->fresh()->status)->toBe(PipelineStatus::SiteSurvey);
});

it('re-opening the survey resets every tower\'s survey and Design Plan stamps so the site can recover', function () {
    ['pipeline' => $pipeline, 'commercialManager' => $commercialManager] = pipelineInCommercials();

    $towerA = Tower::create(['site_profile_id' => $pipeline->site_profile_id, 'name' => 'Tower A']);
    $towerB = Tower::create(['site_profile_id' => $pipeline->site_profile_id, 'name' => 'Tower B']);

    // Tower A reuses the pipeline's primary survey, tower B gets its own — mirroring declareTowers().
    $pipeline->siteSurvey->update(['pipeline_id' => $pipeline->id, 'tower_id' => $towerA->id]);
    $towerBSurvey = SiteSurvey::create([
        'site_profile_id' => $pipeline->site_profile_id,
        'pipeline_id' => $pipeline->id,
        'tower_id' => $towerB->id,
        'survey_completed_at' => now(),
    ]);

    $assignments = collect([[$towerA, $pipeline->siteSurvey], [$towerB, $towerBSurvey]])
        ->map(fn (array $pair) => SitePlanningAssignment::create([
            'pipeline_id' => $pipeline->id,
            'site_survey_id' => $pair[1]->id,
            'tower_id' => $pair[0]->id,
            'assigned_at' => now(),
            'design_plan_completed_at' => now(),
            'design_plan_approved_at' => now(),
        ]));

    $this->actingAs($commercialManager)->post(route('pipelines.reopen-survey', $pipeline));

    expect($pipeline->fresh()->status)->toBe(PipelineStatus::SiteSurvey);

    foreach ([$pipeline->siteSurvey->fresh(), $towerBSurvey->fresh()] as $survey) {
        expect($survey->survey_completed_at)->toBeNull();
    }

    foreach ($assignments as $assignment) {
        $assignment->refresh();
        expect($assignment->design_plan_completed_at)->toBeNull()
            ->and($assignment->design_plan_approved_at)->toBeNull();
    }
});

// ─── markActive ───────────────────────────────────────────────────────────────

it('marks an ongoing-deployment pipeline as active', function () {
    ['pipeline' => $pipeline] = pipelineWithSurvey(PipelineStatus::ReadyForDeployment);
    $admin = User::factory()->superAdmin()->create();

    $this->actingAs($admin)
        ->post(route('pipelines.mark-active', $pipeline))
        ->assertRedirect(route('pipelines.show', $pipeline));

    expect($pipeline->fresh()->status)->toBe(PipelineStatus::ReadyForService);
});

it('returns 422 when marking an active pipeline as active again', function () {
    $manager = User::factory()->superAdmin()->create();
    ['pipeline' => $pipeline] = pipelineWithSurvey(PipelineStatus::ReadyForService);

    $this->actingAs($manager)
        ->post(route('pipelines.mark-active', $pipeline))
        ->assertStatus(422);
});

// ─── Site Management visibility ───────────────────────────────────────────────

it('shows ongoing-deployment pipelines in site management', function () {
    ['pipeline' => $pipeline, 'manager' => $manager] = pipelineWithSurvey(PipelineStatus::ReadyForDeployment);

    $this->actingAs($manager)
        ->get('/site-management')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->has('sites.data', 1)
            ->where('sites.data.0.id', $pipeline->id)
        );
});

it('shows active pipelines in site management', function () {
    ['pipeline' => $pipeline, 'manager' => $manager] = pipelineWithSurvey(PipelineStatus::ReadyForService);

    $this->actingAs($manager)
        ->get('/site-management')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->has('sites.data', 1)
            ->where('sites.data.0.id', $pipeline->id)
        );
});

it('shows prospect pipelines in site management', function () {
    ['pipeline' => $pipeline, 'manager' => $manager] = pipelineWithSurvey(PipelineStatus::Prospect);

    $this->actingAs($manager)
        ->get('/site-management')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->has('sites.data', 1)
            ->where('sites.data.0.id', $pipeline->id)
        );
});

it('includes document urls on the pipeline details page', function () {
    ['pipeline' => $pipeline, 'manager' => $manager] = pipelineWithSurvey(PipelineStatus::SiteSurvey);

    $document = Document::create([
        'name' => 'survey-photo.jpg',
        'type' => DocumentType::Photo,
        'filename' => 'survey-photo.jpg',
        'file_path' => 'survey-documents/survey-photo.jpg',
        'uploaded_by' => $manager->id,
        'uploaded_status' => PipelineStatus::SiteSurvey->value,
    ]);

    $pipeline->siteSurvey->documents()->attach($document->id, ['created_at' => now()]);

    $this->actingAs($manager)
        ->get(route('pipelines.show', $pipeline))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Pipelines/Show')
            ->where('pipeline.site_survey.documents.0.url', Storage::disk('public')->url('survey-documents/survey-photo.jpg'))
        );
});

it('shows survey findings visits and documents on the site details page', function () {
    ['pipeline' => $pipeline, 'manager' => $manager] = pipelineWithSurvey(PipelineStatus::SiteSurvey);
    $assignedSaq = User::factory()->saqSpecialist()->create(['full_name' => 'Assigned SAQ User']);
    $approvalRole = Role::factory()->create(['name' => 'COO Test']);
    $pipeline->update([
        'assigned_saq_id' => $assignedSaq->id,
        'commercial_remarks' => 'Commercial terms ready.',
    ]);

    $pipeline->siteSurvey->update([
        'floors_surveyed' => 12,
        'building_network_system' => 'FTTH',
        'marketing_paraphernalia' => 'Lobby posters allowed.',
    ]);

    NetworkFeasibility::create([
        'site_survey_id' => $pipeline->siteSurvey->id,
        'isp_name' => 'Converge',
        'status' => 'Feasible',
        'notes' => 'Fiber available.',
    ]);

    PipelineApproval::create([
        'pipeline_id' => $pipeline->id,
        'step' => 1,
        'role_id' => $approvalRole->id,
        'status' => ApprovalStatus::Approved,
        'decided_by_id' => $manager->id,
        'decided_at' => now(),
        'remarks' => 'Approved by COO.',
    ]);

    $pipeline->statusLogs()->create([
        'status' => PipelineStatus::SiteSurvey->value,
        'changed_by' => $manager->id,
    ]);

    $pipeline->remarkLogs()->create([
        'status' => PipelineStatus::SiteSurvey->value,
        'type' => 'visit',
        'content' => 'Admin confirmed lobby access.',
        'added_by' => $manager->id,
    ]);

    $surveyDocument = Document::create([
        'name' => 'survey-photo.jpg',
        'type' => DocumentType::Photo,
        'filename' => 'survey-photo.jpg',
        'file_path' => 'survey-documents/survey-photo.jpg',
        'uploaded_by' => $manager->id,
        'uploaded_status' => PipelineStatus::SiteSurvey->value,
    ]);

    $commercialDocument = Document::create([
        'name' => 'commercial.pdf',
        'type' => DocumentType::Document,
        'filename' => 'commercial.pdf',
        'file_path' => 'commercial-documents/commercial.pdf',
        'uploaded_by' => $manager->id,
        'uploaded_status' => PipelineStatus::Commercials->value,
        'category' => 'Commercial Terms',
    ]);

    $pipeline->siteSurvey->documents()->attach($surveyDocument->id, ['created_at' => now()]);
    $pipeline->documents()->attach($commercialDocument->id, ['created_at' => now()]);

    $this->actingAs($manager)
        ->get(route('site-management.show', $pipeline->siteProfile))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('SiteManagement/Show')
            ->where('site.pipelines.0.site_survey.floors_surveyed', 12)
            ->where('site.pipelines.0.site_survey.building_network_system', 'FTTH')
            ->where('site.pipelines.0.site_survey.marketing_paraphernalia', 'Lobby posters allowed.')
            ->where('site.pipelines.0.assigned_saq.full_name', 'Assigned SAQ User')
            ->where('site.pipelines.0.commercial_remarks', 'Commercial terms ready.')
            ->where('site.pipelines.0.site_survey.network_feasibilities.0.isp_name', 'Converge')
            ->where('site.pipelines.0.site_survey.network_feasibilities.0.notes', 'Fiber available.')
            ->where('site.pipelines.0.approvals.0.role.name', 'COO Test')
            ->where('site.pipelines.0.approvals.0.decided_by.full_name', $manager->full_name)
            ->where('site.pipelines.0.approvals.0.remarks', 'Approved by COO.')
            ->where('site.pipelines.0.status_logs.0.status', PipelineStatus::SiteSurvey->value)
            ->where('site.pipelines.0.status_logs.0.changed_by.full_name', $manager->full_name)
            ->where('site.pipelines.0.remark_logs.0.content', 'Admin confirmed lobby access.')
            ->where('site.pipelines.0.site_survey.documents.0.name', 'survey-photo.jpg')
            ->where('site.pipelines.0.site_survey.documents.0.url', Storage::disk('public')->url('survey-documents/survey-photo.jpg'))
            ->where('site.pipelines.0.documents.0.name', 'commercial.pdf')
            ->where('site.pipelines.0.documents.0.url', Storage::disk('public')->url('commercial-documents/commercial.pdf'))
        );
});

// ─── Pipeline edit hardening ─────────────────────────────────────────────────

it('ignores a client-sent Registered By on pipeline update', function () {
    ['pipeline' => $pipeline, 'manager' => $manager] = pipelineWithSurvey(PipelineStatus::Prospecting);
    $otherUser = User::factory()->create();

    $this->actingAs($manager)
        ->patch(route('pipelines.update', $pipeline), [
            'name' => 'Renamed Pipeline',
            'project_manager_id' => $otherUser->id,
            'site_profile_id' => $pipeline->site_profile_id,
            'endorsement_date' => now()->toDateString(),
        ])
        ->assertRedirect(route('pipelines.show', $pipeline));

    $pipeline->refresh();
    expect($pipeline->name)->toBe('Renamed Pipeline')
        ->and($pipeline->project_manager_id)->toBe($manager->id);
});

it('rejects an endorsement date outside the permitted range on update', function () {
    ['pipeline' => $pipeline, 'manager' => $manager] = pipelineWithSurvey(PipelineStatus::Prospecting);

    $payload = fn (string $date) => [
        'name' => $pipeline->name,
        'site_profile_id' => $pipeline->site_profile_id,
        'endorsement_date' => $date,
    ];

    $this->actingAs($manager)
        ->patch(route('pipelines.update', $pipeline), $payload('2018-12-31'))
        ->assertSessionHasErrors(['endorsement_date' => 'Endorsement date cannot be earlier than January 1, 2019.']);

    $this->actingAs($manager)
        ->patch(route('pipelines.update', $pipeline), $payload(now()->addDay()->toDateString()))
        ->assertSessionHasErrors(['endorsement_date' => 'Endorsement date cannot be later than today.']);

    $this->actingAs($manager)
        ->patch(route('pipelines.update', $pipeline), $payload('2020-06-15'))
        ->assertSessionDoesntHaveErrors('endorsement_date');
});

it('blocks updating a Completed pipeline even for the Super Admin', function () {
    ['pipeline' => $pipeline] = pipelineWithSurvey(PipelineStatus::Completed);
    $admin = User::factory()->superAdmin()->create();
    $originalProfileId = $pipeline->site_profile_id;
    $otherProfile = SiteProfile::create(['name' => 'Other Site', 'type' => SiteProfileType::Condo, 'address_id' => Address::create(['city' => 'Cebu'])->id]);

    $this->actingAs($admin)
        ->patch(route('pipelines.update', $pipeline), [
            'name' => 'Should Not Save',
            'site_profile_id' => $otherProfile->id,
        ])
        ->assertStatus(422);

    expect($pipeline->fresh()->site_profile_id)->toBe($originalProfileId);
});

// ─── Contact dedup on manual creation ─────────────────────────────────

it('reuses an existing contact when identical details are typed on pipeline creation', function () {
    $manager = User::factory()->projectManager()->create();
    $existing = SiteContact::create(['name' => 'Melanie Cruz', 'designation' => 'Admin', 'email' => 'melanie@site.com', 'mobile_number' => '09171234567']);

    $this->actingAs($manager)
        ->post(route('pipelines.store'), validPipelineStorePayload($manager, [
            'site_name' => 'Dedup Site',
            'contact_name' => 'Melanie Cruz',
            'contact_email' => 'melanie@site.com',
            'contact_mobile_number' => '09171234567',
        ]))
        ->assertRedirect();

    expect(SiteContact::where('name', 'Melanie Cruz')->count())->toBe(1)
        ->and(SiteProfile::where('name', 'Dedup Site')->first()->contact_id)->toBe($existing->id);
});

it('still creates a contact when only the name matches an existing one', function () {
    $manager = User::factory()->projectManager()->create();
    SiteContact::create(['name' => 'Juan Reyes', 'email' => 'juan@other.com', 'mobile_number' => '09170000001']);

    $this->actingAs($manager)
        ->post(route('pipelines.store'), validPipelineStorePayload($manager, [
            'site_name' => 'Name Only Site',
            'contact_name' => 'Juan Reyes',
        ]))
        ->assertRedirect();

    expect(SiteContact::where('name', 'Juan Reyes')->count())->toBe(2);
});

// ─── Admin SAQ reassignment with remarks ──────────────────────────────

it('still blocks a non-admin from reassigning the SAQ after Prospecting', function () {
    ['pipeline' => $pipeline] = pipelineWithSurvey(PipelineStatus::SiteSurvey);
    $role = Role::factory()->create();
    RolePermission::create(['role_id' => $role->id, 'pipeline_status' => PipelineStatus::SiteSurvey->value]);
    RoleActionPermission::create(['role_id' => $role->id, 'permission' => PipelineActionPermission::AssignSaq->value]);
    $user = User::factory()->create(['role_id' => $role->id]);
    $saq = User::factory()->saqSpecialist()->create();

    $this->actingAs($user)
        ->patch(route('pipelines.assign-saq', $pipeline), ['assigned_saq_id' => $saq->id])
        ->assertForbidden();
});

it('requires remarks when a Super Admin reassigns the SAQ after Prospecting', function () {
    ['pipeline' => $pipeline] = pipelineWithSurvey(PipelineStatus::SiteSurvey);
    $admin = User::factory()->superAdmin()->create();
    $saq = User::factory()->saqSpecialist()->create();

    $this->actingAs($admin)
        ->patch(route('pipelines.assign-saq', $pipeline), ['assigned_saq_id' => $saq->id])
        ->assertSessionHasErrors('remarks');

    expect($pipeline->fresh()->assigned_saq_id)->not->toBe($saq->id);
});

it('lets a Super Admin reassign the SAQ after Prospecting with remarks, and logs the reason', function () {
    ['pipeline' => $pipeline] = pipelineWithSurvey(PipelineStatus::SiteSurvey);
    $admin = User::factory()->superAdmin()->create();
    $saq = User::factory()->saqSpecialist()->create();

    $this->actingAs($admin)
        ->patch(route('pipelines.assign-saq', $pipeline), [
            'assigned_saq_id' => $saq->id,
            'remarks' => 'Previous SAQ resigned effective Oct 10.',
        ])
        ->assertRedirect();

    $pipeline->refresh();
    expect($pipeline->assigned_saq_id)->toBe($saq->id)
        ->and($pipeline->status)->toBe(PipelineStatus::SiteSurvey)
        ->and(PipelineRemarkLog::where('pipeline_id', $pipeline->id)->where('content', 'like', '%resigned%')->exists())->toBeTrue();
});

it('persists Total Unit per Tower and Total Floor per Tower on creation', function () {
    $manager = User::factory()->projectManager()->create();

    $this->actingAs($manager)
        ->post(route('pipelines.store'), validPipelineStorePayload($manager, [
            'site_name' => 'Per Tower Site',
            'total_unit_count' => 500,
            'total_floors_per_tower' => 30,
        ]))
        ->assertRedirect();

    $profile = SiteProfile::where('name', 'Per Tower Site')->firstOrFail();
    expect((int) $profile->total_unit_count)->toBe(500)
        ->and((int) $profile->total_floors_per_tower)->toBe(30);
});

it('rejects a negative Total Floor per Tower', function () {
    $manager = User::factory()->projectManager()->create();

    $this->actingAs($manager)
        ->post(route('pipelines.store'), validPipelineStorePayload($manager, [
            'total_floors_per_tower' => -5,
        ]))
        ->assertSessionHasErrors('total_floors_per_tower');
});

it('ignores a client-sent Site Profile change on pipeline update', function () {
    ['pipeline' => $pipeline, 'manager' => $manager] = pipelineWithSurvey(PipelineStatus::Prospecting);
    $otherProfile = SiteProfile::create(['name' => 'Decoy Site', 'type' => SiteProfileType::Condo, 'address_id' => Address::create(['city' => 'Davao'])->id]);
    $originalProfileId = $pipeline->site_profile_id;

    $this->actingAs($manager)
        ->patch(route('pipelines.update', $pipeline), [
            'name' => 'Renamed Again',
            'site_profile_id' => $otherProfile->id,
            'endorsement_date' => now()->toDateString(),
        ])
        ->assertRedirect();

    $pipeline->refresh();
    expect($pipeline->name)->toBe('Renamed Again')
        ->and($pipeline->site_profile_id)->toBe($originalProfileId);
});

it('exposes assignSaq capability to the Super Admin on a Site Survey pipeline', function () {
    ['pipeline' => $pipeline] = pipelineWithSurvey(PipelineStatus::SiteSurvey);
    $admin = User::factory()->superAdmin()->create();
    $manager = User::factory()->projectManager()->create();

    $this->actingAs($admin)->get(route('pipelines.show', $pipeline))
        ->assertInertia(fn ($page) => $page->where('can.assignSaq', true));

    // Normal roles keep the Prospect/Prospecting-only rule.
    $this->actingAs($manager)->get(route('pipelines.show', $pipeline))
        ->assertInertia(fn ($page) => $page->where('can.assignSaq', false));
});
