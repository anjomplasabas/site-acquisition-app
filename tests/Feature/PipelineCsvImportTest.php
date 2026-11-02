<?php

use App\Enums\SiteProfileType;
use App\Models\Pipeline;
use App\Models\User;
use App\Support\PipelineCsvImport;
use Illuminate\Http\UploadedFile;

// ─── Upload format validation ──────────────────────────────────────

it('rejects a .txt file on the Import Leads upload step', function () {
    $admin = User::factory()->superAdmin()->create();

    $this->actingAs($admin)
        ->post(route('pipelines.import.upload'), [
            'file' => UploadedFile::fake()->createWithContent('leads.txt', "site_name\nTower A\n"),
        ])
        ->assertSessionHasErrors('file');
});

it('does not mention .txt in the Import Leads upload validation error', function () {
    $admin = User::factory()->superAdmin()->create();

    $response = $this->actingAs($admin)
        ->from(route('pipelines.import'))
        ->post(route('pipelines.import.upload'), [
            'file' => UploadedFile::fake()->create('leads.pdf', 10, 'application/pdf'),
        ]);

    $response->assertSessionHasErrors('file');
    expect(session('errors')->first('file'))->not->toContain('txt');
});

it('still accepts a .csv file on the Import Leads upload step', function () {
    $admin = User::factory()->superAdmin()->create();

    $this->actingAs($admin)
        ->post(route('pipelines.import.upload'), [
            'file' => UploadedFile::fake()->createWithContent('leads.csv', "site_name\nTower A\n"),
        ])
        ->assertSessionDoesntHaveErrors('file')
        ->assertRedirect(route('pipelines.import'));
});

function importRow(array $data = [], array $overrides = []): array
{
    return array_replace([
        'row_number' => 1,
        'site_action' => 'create_new',
        'pipeline_action' => 'create',
        'data' => array_replace([
            'site_name' => 'Import Test Site',
            'site_type' => SiteProfileType::Condo->value,
            'city' => 'Manila',
        ], $data),
    ], $overrides);
}

it('detects a duplicate pipeline when importing the same details twice', function () {
    $importer = app(PipelineCsvImport::class);

    $row = importRow();

    $summary1 = $importer->confirm([$row]);
    expect($summary1['created_pipelines'])->toBe(1)
        ->and($summary1['created_sites'])->toBe(1);

    $summary2 = $importer->confirm([$row]);
    expect($summary2['created_pipelines'])->toBe(0)
        ->and($summary2['skipped_duplicates'])->toBe(1);
});

it('rejects an invalid contact mobile number instead of importing it as-is', function () {
    $importer = app(PipelineCsvImport::class);

    $summary = $importer->confirm([
        importRow(['contact_mobile_number' => '67ghgfdqwrqbcrqui']),
    ]);

    expect($summary['invalid_rows'])->toBe(1)
        ->and($summary['created_pipelines'])->toBe(0);
});

it('rejects a contact email missing the @ symbol instead of importing it as-is', function () {
    $importer = app(PipelineCsvImport::class);

    $summary = $importer->confirm([
        importRow(['contact_email' => 'melanie.gmail.com']),
    ]);

    expect($summary['invalid_rows'])->toBe(1)
        ->and($summary['created_pipelines'])->toBe(0);
});

it('rejects a non-numeric total unit count instead of raising a database error', function () {
    $importer = app(PipelineCsvImport::class);

    $summary = $importer->confirm([
        importRow(['total_unit_count' => 'not-a-number']),
    ]);

    expect($summary['invalid_rows'])->toBe(1)
        ->and($summary['created_pipelines'])->toBe(0);
});

it('rejects a total unit count beyond the database column range on import', function () {
    $importer = app(PipelineCsvImport::class);

    $summary = $importer->confirm([
        importRow(['total_unit_count' => '99999999999']),
    ]);

    expect($summary['invalid_rows'])->toBe(1)
        ->and($summary['created_pipelines'])->toBe(0);
});

it('assigns the acting user as Registered By and resolves the Lead SAQ Specialist by email', function () {
    $admin = User::factory()->superAdmin()->create();
    $leadSaq = User::factory()->leadSaqSpecialist()->create();

    $this->actingAs($admin);

    $importer = app(PipelineCsvImport::class);
    $importer->confirm([
        importRow(['assigned_saq_email' => $leadSaq->email]),
    ]);

    $pipeline = Pipeline::firstOrFail();

    expect($pipeline->project_manager_id)->toBe($admin->id)
        ->and($pipeline->assigned_saq_id)->toBe($leadSaq->id);
});

it('defaults the endorsement date to today when the CSV row omits it', function () {
    $this->actingAs(User::factory()->superAdmin()->create());

    $importer = app(PipelineCsvImport::class);
    $importer->confirm([importRow()]);

    $pipeline = Pipeline::firstOrFail();

    expect($pipeline->endorsement_date->toDateString())->toBe(now()->toDateString());
});

it('creates the site but skips the pipeline when Pipeline Action is Skip for a brand new site', function () {
    $importer = app(PipelineCsvImport::class);

    $summary = $importer->confirm([
        importRow(overrides: ['pipeline_action' => 'skip']),
    ]);

    expect($summary['created_sites'])->toBe(1)
        ->and($summary['created_pipelines'])->toBe(0)
        ->and($summary['skipped_duplicates'])->toBe(0);
});
