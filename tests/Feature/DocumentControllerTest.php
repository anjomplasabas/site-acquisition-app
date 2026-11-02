<?php

use App\Enums\DocumentType;
use App\Models\Document;
use App\Models\User;
use App\Support\DocumentUploader;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('returns the version chain for a document, newest first', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $original = Document::create([
        'name' => 'original.pdf',
        'type' => DocumentType::Document,
        'filename' => 'original.pdf',
        'file_path' => UploadedFile::fake()->create('original.pdf', 5)->store('commercial-documents', 'public'),
    ]);

    $replacement = app(DocumentUploader::class)->replace(
        $original,
        UploadedFile::fake()->create('replacement.pdf', 5),
        $user,
        'commercial-documents'
    );

    $this->actingAs($user)
        ->getJson(route('documents.versions', $replacement))
        ->assertOk()
        ->assertJsonPath('versions.0.id', $replacement->id)
        ->assertJsonPath('versions.0.version', 2)
        ->assertJsonPath('versions.0.is_current', true)
        ->assertJsonPath('versions.1.id', $original->id)
        ->assertJsonPath('versions.1.version', 1)
        ->assertJsonPath('versions.1.is_current', false);
});

it('requires authentication to read a document version chain', function () {
    $document = Document::create([
        'name' => 'file.pdf',
        'type' => DocumentType::Document,
        'filename' => 'file.pdf',
        'file_path' => 'commercial-documents/file.pdf',
    ]);

    $this->getJson(route('documents.versions', $document))->assertUnauthorized();
});
