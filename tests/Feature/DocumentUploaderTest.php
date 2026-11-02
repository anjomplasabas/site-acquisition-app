<?php

use App\Enums\DocumentType;
use App\Enums\SiteProfileType;
use App\Models\Address;
use App\Models\Document;
use App\Models\Pipeline;
use App\Models\SiteProfile;
use App\Models\SiteSurvey;
use App\Models\User;
use App\Support\DocumentUploader;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function pipelineWithDocumentAttached(): array
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

    $file = UploadedFile::fake()->create('original.pdf', 10);
    $path = $file->store('commercial-documents', 'public');
    $document = Document::create([
        'name' => 'original.pdf',
        'type' => DocumentType::Document,
        'filename' => 'original.pdf',
        'file_path' => $path,
        'category' => 'MOA',
        'uploaded_status' => 'Commercials',
    ]);

    $pipeline->documents()->attach($document->id, ['created_at' => now()]);
    $survey->documents()->attach($document->id, ['created_at' => now()]);

    return compact('pipeline', 'survey', 'document');
}

it('sets a new document as its own version root', function () {
    Storage::fake('public');

    $file = UploadedFile::fake()->create('fresh.pdf', 5);
    $document = app(DocumentUploader::class)->store($file, 'commercial-documents');

    expect($document->version)->toBe(1)
        ->and($document->root_document_id)->toBe($document->id)
        ->and($document->superseded_at)->toBeNull();
});

it('replaces a document with a new version and carries every attachment forward', function () {
    Storage::fake('public');
    ['pipeline' => $pipeline, 'survey' => $survey, 'document' => $original] = pipelineWithDocumentAttached();
    $uploader = User::factory()->create();

    $newFile = UploadedFile::fake()->create('corrected.pdf', 10);
    $newDocument = app(DocumentUploader::class)->replace($original, $newFile, $uploader, 'commercial-documents');

    expect($newDocument->version)->toBe(2)
        ->and($newDocument->root_document_id)->toBe($original->id)
        ->and($newDocument->category)->toBe('MOA')
        ->and($newDocument->uploaded_status)->toBe('Commercials')
        ->and($newDocument->uploaded_by)->toBe($uploader->id)
        ->and($newDocument->isCurrentVersion())->toBeTrue();

    $original->refresh();
    expect($original->superseded_at)->not->toBeNull()
        ->and($original->isCurrentVersion())->toBeFalse();

    // Old file is never deleted — that's what makes it recoverable history.
    Storage::disk('public')->assertExists($original->file_path);
    expect(Document::find($original->id))->not->toBeNull();

    // The new version takes over every attachment the old one had, across every relation type.
    expect($pipeline->fresh()->documents()->pluck('documents.id')->all())->toBe([$newDocument->id])
        ->and($survey->fresh()->documents()->pluck('documents.id')->all())->toBe([$newDocument->id]);

    // versions() resolves the whole chain regardless of which version you start from.
    expect($newDocument->versions()->pluck('id')->all())->toBe([$newDocument->id, $original->id]);
});
