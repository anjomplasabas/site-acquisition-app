<?php

use App\Enums\DocumentType;
use App\Enums\IspTaskStatus;
use App\Enums\ProviderStatus;
use App\Enums\SiteProfileType;
use App\Events\PipelineApproved;
use App\Listeners\InitializeProject;
use App\Models\Address;
use App\Models\Document;
use App\Models\NetworkFeasibility;
use App\Models\NetworkSiteAssignment;
use App\Models\Pipeline;
use App\Models\Project;
use App\Models\SiteProfile;
use App\Models\SiteSurvey;
use App\Models\Tower;
use App\Models\User;
use App\Support\NetworkFeasibilityTaskDefinitions;

function attachPipelineDocument(Pipeline $pipeline, string $category): Document
{
    $document = Document::create([
        'name' => "{$category}.pdf",
        'type' => DocumentType::Document,
        'filename' => "{$category}.pdf",
        'file_path' => "commercial-documents/{$category}.pdf",
        'category' => $category,
    ]);

    $pipeline->documents()->attach($document->id, ['created_at' => now()]);

    return $document;
}

function pipelineForInitializeProjectTests(): Pipeline
{
    $address = Address::create(['city' => 'Manila']);
    $siteProfile = SiteProfile::create([
        'name' => 'Test Site',
        'type' => SiteProfileType::Condo,
        'address_id' => $address->id,
    ]);

    $survey = SiteSurvey::create(['site_profile_id' => $siteProfile->id]);

    $pipeline = Pipeline::create([
        'name' => 'Test Pipeline',
        'pipeline_id' => nextPipelineId(),
        'site_profile_id' => $siteProfile->id,
        'site_survey_id' => $survey->id,
    ]);

    // Mirrors what PipelineController::enterSiteSurvey does in the real flow — the Network Site
    // assignment already exists by the time a pipeline reaches approval.
    NetworkSiteAssignment::ensureFor($pipeline, $survey);

    return $pipeline;
}

it('seeds the Planning stage ISP list 1:1 from the pipeline NetworkFeasibility rows, each linked to its Network Site ISP with the JO checklist seeded when MOA/NTP were already attached at Commercials', function () {
    $pipeline = pipelineForInitializeProjectTests();
    attachPipelineDocument($pipeline, 'MOA');
    attachPipelineDocument($pipeline, 'NTP');

    $philcom = NetworkFeasibility::create(['site_survey_id' => $pipeline->site_survey_id, 'isp_name' => 'PhilCom', 'status' => ProviderStatus::Feasible, 'notes' => 'Ready']);
    NetworkFeasibility::create(['site_survey_id' => $pipeline->site_survey_id, 'isp_name' => 'converge', 'status' => ProviderStatus::Checking]);
    NetworkFeasibility::create(['site_survey_id' => $pipeline->site_survey_id, 'isp_name' => 'A Regional ISP', 'status' => ProviderStatus::NonFeasible]);

    (new InitializeProject)->handle(new PipelineApproved($pipeline));

    $project = $pipeline->fresh()->project;
    $planningStage = $project->stages()->where('stage', 'Planning')->first();
    $isps = $planningStage->isps()->with('networkFeasibility.tasks')->get()->keyBy('isp_name');

    $expectedTaskNames = collect(NetworkFeasibilityTaskDefinitions::all())->pluck('name')->all();

    expect($isps)->toHaveCount(3)
        ->and($isps->get('PhilCom')->notes)->toBe('Ready')
        ->and($isps->get('PhilCom')->network_feasibility_id)->toBe($philcom->id)
        ->and($isps->get('converge')->notes)->toBeNull()
        ->and($isps->get('A Regional ISP')->networkFeasibility->tasks)->toHaveCount(count($expectedTaskNames))
        ->and($isps->get('A Regional ISP')->networkFeasibility->tasks->pluck('name')->all())->toBe($expectedTaskNames)
        ->and($isps->get('A Regional ISP')->networkFeasibility->tasks->pluck('status')->unique()->all())->toBe([IspTaskStatus::Pending]);
});

it('creates ISPs but seeds no JO checklist yet when the pipeline lacks MOA/NTP at Commercials', function () {
    $pipeline = pipelineForInitializeProjectTests();

    NetworkFeasibility::create(['site_survey_id' => $pipeline->site_survey_id, 'isp_name' => 'PhilCom', 'status' => ProviderStatus::Feasible]);

    (new InitializeProject)->handle(new PipelineApproved($pipeline));

    $project = $pipeline->fresh()->project;
    $planningStage = $project->stages()->where('stage', 'Planning')->first();
    $isp = $planningStage->isps()->with('networkFeasibility.tasks')->first();

    expect($isp)->not->toBeNull()
        ->and($isp->isp_name)->toBe('PhilCom')
        ->and($isp->networkFeasibility->tasks)->toHaveCount(0);
});

it('creates ISPs but seeds no JO checklist yet when only one of MOA/NTP was attached at Commercials', function () {
    $pipeline = pipelineForInitializeProjectTests();
    attachPipelineDocument($pipeline, 'MOA');

    NetworkFeasibility::create(['site_survey_id' => $pipeline->site_survey_id, 'isp_name' => 'PhilCom', 'status' => ProviderStatus::Feasible]);

    (new InitializeProject)->handle(new PipelineApproved($pipeline));

    $project = $pipeline->fresh()->project;
    $planningStage = $project->stages()->where('stage', 'Planning')->first();
    $isp = $planningStage->isps()->with('networkFeasibility.tasks')->first();

    expect($isp->networkFeasibility->tasks)->toHaveCount(0);
});

it('does not seed the Monitoring Infrastructure checklist at project creation, since it depends on Planning design_plan_type', function () {
    $pipeline = pipelineForInitializeProjectTests();

    (new InitializeProject)->handle(new PipelineApproved($pipeline));

    $project = $pipeline->fresh()->project;
    $monitoringStage = $project->stages()->where('stage', 'Monitoring')->first();

    expect($monitoringStage->stageTasks->where('field_key', 'infrastructure_deployment_status'))->toHaveCount(0);
});

it('no longer seeds any Plans/Drawing tasks on the Planning stage — that checklist now lives on the Site Planning assignment', function () {
    $pipeline = pipelineForInitializeProjectTests();

    (new InitializeProject)->handle(new PipelineApproved($pipeline));

    $project = $pipeline->fresh()->project;
    $planningStage = $project->stages()->where('stage', 'Planning')->first();

    expect($planningStage->stageTasks->where('field_key', 'plans_drawing'))->toHaveCount(0);
});

it('leaves the Planning ISP list empty when the pipeline has no network feasibility data', function () {
    $pipeline = pipelineForInitializeProjectTests();

    (new InitializeProject)->handle(new PipelineApproved($pipeline));

    $planningStage = $pipeline->fresh()->project->stages()->where('stage', 'Planning')->first();

    expect($planningStage->isps)->toHaveCount(0);
});

it('carries the pipeline MOA and NTP documents forward onto the Planning stage, sharing the same Document row', function () {
    $pipeline = pipelineForInitializeProjectTests();
    $moa = attachPipelineDocument($pipeline, 'MOA');
    $ntp = attachPipelineDocument($pipeline, 'NTP');

    (new InitializeProject)->handle(new PipelineApproved($pipeline));

    $planningStage = $pipeline->fresh()->project->stages()->where('stage', 'Planning')->first();
    $documents = $planningStage->documents;

    expect($documents)->toHaveCount(2)
        ->and($documents->pluck('id')->sort()->values()->all())->toBe([$moa->id, $ntp->id])
        ->and($documents->first()->pivot->status)->toBeNull();

    // Still attached to the pipeline too — shared row, not a copy.
    expect($pipeline->documents()->where('document_id', $moa->id)->exists())->toBeTrue();
});

it('carries only the most recently attached document when a category was uploaded more than once', function () {
    $pipeline = pipelineForInitializeProjectTests();
    attachPipelineDocument($pipeline, 'MOA');
    $latestMoa = attachPipelineDocument($pipeline, 'MOA');

    (new InitializeProject)->handle(new PipelineApproved($pipeline));

    $planningStage = $pipeline->fresh()->project->stages()->where('stage', 'Planning')->first();

    expect($planningStage->documents)->toHaveCount(1)
        ->and($planningStage->documents->first()->id)->toBe($latestMoa->id);
});

it('leaves Planning documents empty when the pipeline has no MOA/NTP documents', function () {
    $pipeline = pipelineForInitializeProjectTests();

    (new InitializeProject)->handle(new PipelineApproved($pipeline));

    $planningStage = $pipeline->fresh()->project->stages()->where('stage', 'Planning')->first();

    expect($planningStage->documents)->toHaveCount(0);
});

it('also carries the pipeline MOA and NTP documents forward onto the Network Site, sharing the same Document row', function () {
    $pipeline = pipelineForInitializeProjectTests();
    $moa = attachPipelineDocument($pipeline, 'MOA');
    $ntp = attachPipelineDocument($pipeline, 'NTP');

    (new InitializeProject)->handle(new PipelineApproved($pipeline));

    $documents = $pipeline->networkSiteAssignment->fresh()->documents;

    expect($documents)->toHaveCount(2)
        ->and($documents->pluck('id')->sort()->values()->all())->toBe([$moa->id, $ntp->id]);
});

// ─── per-tower fan-out ──────────────────────────────────────────────────────

it('creates exactly one untowered project for a site with no declared towers (0/1-tower regression)', function () {
    $pipeline = pipelineForInitializeProjectTests();

    (new InitializeProject)->handle(new PipelineApproved($pipeline));

    expect(Project::where('pipeline_id', $pipeline->id)->count())->toBe(1)
        ->and($pipeline->fresh()->project)->not->toBeNull()
        ->and($pipeline->fresh()->project->tower_id)->toBeNull();
});

it('creates one independent project per tower when the site has multiple towers declared', function () {
    $pipeline = pipelineForInitializeProjectTests();
    $towerA = Tower::create(['site_profile_id' => $pipeline->site_profile_id, 'name' => 'Tower A']);
    $towerB = Tower::create(['site_profile_id' => $pipeline->site_profile_id, 'name' => 'Tower B']);

    (new InitializeProject)->handle(new PipelineApproved($pipeline));

    $projects = Project::where('pipeline_id', $pipeline->id)->get()->keyBy('tower_id');

    expect($projects)->toHaveCount(2)
        ->and($projects->get($towerA->id))->not->toBeNull()
        ->and($projects->get($towerB->id))->not->toBeNull()
        ->and($pipeline->fresh()->project)->toBeNull(); // no untowered default once towers exist

    foreach ($projects as $project) {
        expect($project->stages()->count())->toBe(4);
    }
});

it('does not duplicate per-tower projects when the listener re-fires on an already-initialized multi-tower pipeline', function () {
    $pipeline = pipelineForInitializeProjectTests();
    Tower::create(['site_profile_id' => $pipeline->site_profile_id, 'name' => 'Tower A']);
    Tower::create(['site_profile_id' => $pipeline->site_profile_id, 'name' => 'Tower B']);

    (new InitializeProject)->handle(new PipelineApproved($pipeline));
    (new InitializeProject)->handle(new PipelineApproved($pipeline));

    expect(Project::where('pipeline_id', $pipeline->id)->count())->toBe(2);
});

it('defaults the Execution PM In-charge to the Surveyor assigned during Site Survey', function () {
    $pipeline = pipelineForInitializeProjectTests();
    $surveyor = User::factory()->surveyor()->create();
    $pipeline->siteSurvey->update(['surveyor_id' => $surveyor->id]);

    (new InitializeProject)->handle(new PipelineApproved($pipeline->fresh()));

    $execution = Project::firstOrFail()->stages()->where('stage', 'Execution')->firstOrFail();

    expect($execution->field_values['pm_in_charge'])->toBe($surveyor->id);
});
