<?php

use App\Models\User;
use App\Support\PipelineFileReader;

it('redirects unauthenticated users from the import template download', function () {
    $this->get('/pipelines/import/template')->assertRedirect('/');
});

it('blocks non-super-admin users from downloading the import template', function () {
    $this->actingAs(User::factory()->saqSpecialist()->create())
        ->get('/pipelines/import/template')
        ->assertForbidden();
});

it('lets a super admin download the import template with the importable field headers', function () {
    $response = $this->actingAs(User::factory()->superAdmin()->create())
        ->get('/pipelines/import/template');

    $response->assertSuccessful()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8');

    $content = $response->streamedContent();
    $headerLine = strtok($content, "\n");

    foreach (PipelineFileReader::IMPORTABLE_FIELDS as $field) {
        expect($headerLine)->toContain($field);
    }
});
