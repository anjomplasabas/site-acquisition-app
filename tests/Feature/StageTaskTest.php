<?php

use App\Enums\DocumentType;
use App\Enums\SiteProfileType;
use App\Models\Address;
use App\Models\Document;
use App\Models\Pipeline;
use App\Models\Project;
use App\Models\ProjectStage;
use App\Models\SiteProfile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function projectForStageTaskTests(): Project
{
    $address = Address::create(['city' => 'Manila']);
    $siteProfile = SiteProfile::create([
        'name' => 'Test Site',
        'type' => SiteProfileType::Condo,
        'address_id' => $address->id,
    ]);

    $pipeline = Pipeline::create([
        'name' => 'Test Pipeline',
        'pipeline_id' => nextPipelineId(),
        'site_profile_id' => $siteProfile->id,
    ]);

    $project = Project::create(['pipeline_id' => $pipeline->id, 'current_stage' => 'Monitoring']);

    foreach (['Planning' => 1, 'Execution' => 2, 'Monitoring' => 3, 'Closing' => 4] as $stage => $order) {
        ProjectStage::create(['project_id' => $project->id, 'stage' => $stage, 'status' => 'Pending', 'order' => $order]);
    }

    return $project;
}

it('updates a stage task status and stamps status_changed_at', function () {
    $manager = User::factory()->infrastructureManager()->create();
    $project = projectForStageTaskTests();
    $stage = $project->stages()->where('stage', 'Monitoring')->first();
    $task = $stage->stageTasks()->create(['name' => 'MDF Installation', 'weight' => 10, 'status' => 'Pending', 'status_changed_at' => now()->subDays(3), 'sort_order' => 0]);

    $this->actingAs($manager)
        ->patch(route('projects.stages.stage-tasks.update', [$project, $stage, $task]), ['status' => 'Done'])
        ->assertRedirect();

    expect($task->fresh()->status->value)->toBe('Done')
        ->and($task->fresh()->status_changed_at->diffInSeconds(now()))->toBeLessThan(5);
});

it('does not restamp a stage task status_changed_at when the status is unchanged', function () {
    $manager = User::factory()->infrastructureManager()->create();
    $project = projectForStageTaskTests();
    $stage = $project->stages()->where('stage', 'Monitoring')->first();
    $original = now()->subDays(3);
    $task = $stage->stageTasks()->create(['name' => 'MDF Installation', 'weight' => 10, 'status' => 'Pending', 'status_changed_at' => $original, 'sort_order' => 0]);

    $this->actingAs($manager)
        ->patch(route('projects.stages.stage-tasks.update', [$project, $stage, $task]), ['status' => 'Pending'])
        ->assertRedirect();

    expect($task->fresh()->status_changed_at->timestamp)->toBe($original->timestamp);
});

it('saves a stage task note without requiring a status', function () {
    $manager = User::factory()->infrastructureManager()->create();
    $project = projectForStageTaskTests();
    $stage = $project->stages()->where('stage', 'Monitoring')->first();
    $task = $stage->stageTasks()->create(['name' => 'MDF Installation', 'weight' => 10, 'status' => 'Pending', 'sort_order' => 0]);

    $this->actingAs($manager)
        ->patch(route('projects.stages.stage-tasks.update', [$project, $stage, $task]), ['notes' => 'Waiting on materials.'])
        ->assertRedirect();

    expect($task->fresh()->notes)->toBe('Waiting on materials.')
        ->and($task->fresh()->status->value)->toBe('Pending');
});

it('updates both status and notes in a single request', function () {
    $manager = User::factory()->infrastructureManager()->create();
    $project = projectForStageTaskTests();
    $stage = $project->stages()->where('stage', 'Monitoring')->first();
    $task = $stage->stageTasks()->create(['name' => 'MDF Installation', 'weight' => 10, 'status' => 'Pending', 'sort_order' => 0]);

    $this->actingAs($manager)
        ->patch(route('projects.stages.stage-tasks.update', [$project, $stage, $task]), [
            'status' => 'Done',
            'notes' => 'Completed ahead of schedule.',
        ])
        ->assertRedirect();

    expect($task->fresh()->status->value)->toBe('Done')
        ->and($task->fresh()->notes)->toBe('Completed ahead of schedule.');
});

it('clears a stage task note when saved as an empty string', function () {
    $manager = User::factory()->infrastructureManager()->create();
    $project = projectForStageTaskTests();
    $stage = $project->stages()->where('stage', 'Monitoring')->first();
    $task = $stage->stageTasks()->create(['name' => 'MDF Installation', 'weight' => 10, 'status' => 'Pending', 'notes' => 'Old note.', 'sort_order' => 0]);

    $this->actingAs($manager)
        ->patch(route('projects.stages.stage-tasks.update', [$project, $stage, $task]), ['notes' => ''])
        ->assertRedirect();

    expect($task->fresh()->notes)->toBeNull();
});

it('uploads and removes a document on a stage task', function () {
    Storage::fake('public');

    $manager = User::factory()->infrastructureManager()->create();
    $project = projectForStageTaskTests();
    $stage = $project->stages()->where('stage', 'Monitoring')->first();
    $task = $stage->stageTasks()->create(['name' => 'MDF Installation', 'weight' => 10, 'status' => 'Pending', 'sort_order' => 0]);

    $this->actingAs($manager)
        ->post(route('projects.stages.stage-tasks.documents.store', [$project, $stage, $task]), [
            'files' => [UploadedFile::fake()->create('mdf.pdf', 100)],
        ])
        ->assertRedirect();

    $document = $task->fresh()->documents->first();
    expect($task->fresh()->documents)->toHaveCount(1);

    $this->actingAs($manager)
        ->delete(route('projects.stages.stage-tasks.documents.delete', [$project, $stage, $task, $document]))
        ->assertRedirect();

    expect($task->fresh()->documents)->toHaveCount(0)
        ->and(Document::find($document->id))->toBeNull();
});

it('does not delete a stage task document file while it is still attached elsewhere', function () {
    Storage::fake('public');

    $manager = User::factory()->infrastructureManager()->create();
    $project = projectForStageTaskTests();
    $stage = $project->stages()->where('stage', 'Monitoring')->first();
    $task = $stage->stageTasks()->create(['name' => 'MDF Installation', 'weight' => 10, 'status' => 'Pending', 'sort_order' => 0]);

    $document = Document::create([
        'name' => 'shared.pdf',
        'type' => DocumentType::Document,
        'filename' => 'shared.pdf',
        'file_path' => 'stage-task-documents/shared.pdf',
    ]);
    $task->documents()->attach($document->id, ['created_at' => now()]);
    $stage->documents()->attach($document->id, ['created_at' => now()]);

    $this->actingAs($manager)
        ->delete(route('projects.stages.stage-tasks.documents.delete', [$project, $stage, $task, $document]))
        ->assertRedirect();

    expect($task->documents()->where('document_id', $document->id)->exists())->toBeFalse()
        ->and(Document::find($document->id))->not->toBeNull();
});

it('denies a role without a stage permission from managing stage tasks', function () {
    $commercialManager = User::factory()->commercialManager()->create();
    $project = projectForStageTaskTests();
    $stage = $project->stages()->where('stage', 'Monitoring')->first();
    $task = $stage->stageTasks()->create(['name' => 'MDF Installation', 'weight' => 10, 'status' => 'Pending', 'sort_order' => 0]);

    $this->actingAs($commercialManager)
        ->patch(route('projects.stages.stage-tasks.update', [$project, $stage, $task]), ['status' => 'Done'])
        ->assertForbidden();
});

it('sets Monitoring Project Status to On-going once an Infrastructure task moves off Pending', function () {
    $manager = User::factory()->infrastructureManager()->create();
    $project = projectForStageTaskTests();
    $stage = $project->stages()->where('stage', 'Monitoring')->first();
    $task = $stage->stageTasks()->create(['name' => 'MDF Installation', 'weight' => 10, 'status' => 'Pending', 'sort_order' => 0]);

    $this->actingAs($manager)
        ->patch(route('projects.stages.stage-tasks.update', [$project, $stage, $task]), ['status' => 'In-Progress'])
        ->assertRedirect();

    expect($stage->fresh()->field_values['project_status'])->toBe('On-going');
});

it('sets Monitoring Project Status to Done once every Infrastructure task is Done', function () {
    $manager = User::factory()->infrastructureManager()->create();
    $project = projectForStageTaskTests();
    $stage = $project->stages()->where('stage', 'Monitoring')->first();
    $taskOne = $stage->stageTasks()->create(['name' => 'MDF Installation', 'weight' => 10, 'status' => 'Done', 'sort_order' => 0]);
    $taskTwo = $stage->stageTasks()->create(['name' => 'Horizontal Provision', 'weight' => 30, 'status' => 'Pending', 'sort_order' => 1]);

    $this->actingAs($manager)
        ->patch(route('projects.stages.stage-tasks.update', [$project, $stage, $taskTwo]), ['status' => 'Done'])
        ->assertRedirect();

    expect($stage->fresh()->field_values['project_status'])->toBe('Done');
});

it('does not overwrite Monitoring Project Status once manually put On-hold', function () {
    $manager = User::factory()->infrastructureManager()->create();
    $project = projectForStageTaskTests();
    $stage = $project->stages()->where('stage', 'Monitoring')->first();
    $stage->update(['field_values' => ['project_status' => 'On-hold']]);
    $task = $stage->stageTasks()->create(['name' => 'MDF Installation', 'weight' => 10, 'status' => 'Pending', 'sort_order' => 0]);

    $this->actingAs($manager)
        ->patch(route('projects.stages.stage-tasks.update', [$project, $stage, $task]), ['status' => 'Done'])
        ->assertRedirect();

    expect($stage->fresh()->field_values['project_status'])->toBe('On-hold');
});

it('leaves Monitoring Project Status untouched while every Infrastructure task is still Pending', function () {
    $manager = User::factory()->infrastructureManager()->create();
    $project = projectForStageTaskTests();
    $stage = $project->stages()->where('stage', 'Monitoring')->first();
    $taskOne = $stage->stageTasks()->create(['name' => 'MDF Installation', 'weight' => 10, 'status' => 'Pending', 'sort_order' => 0]);
    $stage->stageTasks()->create(['name' => 'Horizontal Provision', 'weight' => 30, 'status' => 'Pending', 'sort_order' => 1]);

    $this->actingAs($manager)
        ->patch(route('projects.stages.stage-tasks.update', [$project, $stage, $taskOne]), ['status' => 'Pending'])
        ->assertRedirect();

    expect($stage->fresh()->field_values['project_status'] ?? null)->toBeNull();
});

it('blocks managing stage tasks on a stage that is not currently active', function () {
    $manager = User::factory()->infrastructureManager()->create();
    $project = projectForStageTaskTests();
    $project->update(['current_stage' => 'Execution']);
    $stage = $project->stages()->where('stage', 'Monitoring')->first();
    $task = $stage->stageTasks()->create(['name' => 'MDF Installation', 'weight' => 10, 'status' => 'Pending', 'sort_order' => 0]);

    $this->actingAs($manager)
        ->patch(route('projects.stages.stage-tasks.update', [$project, $stage, $task]), ['status' => 'Done'])
        ->assertStatus(422);
});

it('marks every task in a field_key as Done via complete-all', function () {
    $manager = User::factory()->infrastructureManager()->create();
    $project = projectForStageTaskTests();
    $stage = $project->stages()->where('stage', 'Monitoring')->first();
    $taskOne = $stage->stageTasks()->create(['field_key' => 'infrastructure_deployment_status', 'name' => 'MDF Installation', 'weight' => 10, 'status' => 'Pending', 'sort_order' => 0]);
    $taskTwo = $stage->stageTasks()->create(['field_key' => 'infrastructure_deployment_status', 'name' => 'Horizontal Provision', 'weight' => 30, 'status' => 'In-Progress', 'sort_order' => 1]);

    $this->actingAs($manager)
        ->patch(route('projects.stages.stage-tasks.complete-all', [$project, $stage]), ['field_key' => 'infrastructure_deployment_status'])
        ->assertRedirect();

    expect($taskOne->fresh()->status->value)->toBe('Done')
        ->and($taskTwo->fresh()->status->value)->toBe('Done');
});

it('does not restamp status_changed_at for tasks already Done when completing all', function () {
    $manager = User::factory()->infrastructureManager()->create();
    $project = projectForStageTaskTests();
    $stage = $project->stages()->where('stage', 'Monitoring')->first();
    $original = now()->subDays(3);
    $doneTask = $stage->stageTasks()->create(['field_key' => 'infrastructure_deployment_status', 'name' => 'MDF Installation', 'weight' => 10, 'status' => 'Done', 'status_changed_at' => $original, 'sort_order' => 0]);
    $pendingTask = $stage->stageTasks()->create(['field_key' => 'infrastructure_deployment_status', 'name' => 'Horizontal Provision', 'weight' => 30, 'status' => 'Pending', 'sort_order' => 1]);

    $this->actingAs($manager)
        ->patch(route('projects.stages.stage-tasks.complete-all', [$project, $stage]), ['field_key' => 'infrastructure_deployment_status'])
        ->assertRedirect();

    expect($doneTask->fresh()->status_changed_at->timestamp)->toBe($original->timestamp)
        ->and($pendingTask->fresh()->status_changed_at->diffInSeconds(now()))->toBeLessThan(5);
});

it('only completes tasks matching the given field_key, leaving other stage_task_list fields untouched', function () {
    $manager = User::factory()->infrastructureManager()->create();
    $project = projectForStageTaskTests();
    $stage = $project->stages()->where('stage', 'Monitoring')->first();
    $infrastructureTask = $stage->stageTasks()->create(['field_key' => 'infrastructure_deployment_status', 'name' => 'MDF Installation', 'weight' => 2, 'status' => 'Pending', 'sort_order' => 0]);
    $otherFieldTask = $stage->stageTasks()->create(['field_key' => 'some_other_field', 'name' => 'Unrelated Task', 'weight' => 5, 'status' => 'Pending', 'sort_order' => 1]);

    $this->actingAs($manager)
        ->patch(route('projects.stages.stage-tasks.complete-all', [$project, $stage]), ['field_key' => 'infrastructure_deployment_status'])
        ->assertRedirect();

    expect($infrastructureTask->fresh()->status->value)->toBe('Done')
        ->and($otherFieldTask->fresh()->status->value)->toBe('Pending');
});

it('sets Monitoring Project Status to Done when completing all Infrastructure tasks at once', function () {
    $manager = User::factory()->infrastructureManager()->create();
    $project = projectForStageTaskTests();
    $stage = $project->stages()->where('stage', 'Monitoring')->first();
    $stage->stageTasks()->create(['field_key' => 'infrastructure_deployment_status', 'name' => 'MDF Installation', 'weight' => 10, 'status' => 'Pending', 'sort_order' => 0]);
    $stage->stageTasks()->create(['field_key' => 'infrastructure_deployment_status', 'name' => 'Horizontal Provision', 'weight' => 30, 'status' => 'Pending', 'sort_order' => 1]);

    $this->actingAs($manager)
        ->patch(route('projects.stages.stage-tasks.complete-all', [$project, $stage]), ['field_key' => 'infrastructure_deployment_status'])
        ->assertRedirect();

    expect($stage->fresh()->field_values['project_status'])->toBe('Done');
});

it('completes only the tasks in the given section, leaving other sections in the same field_key untouched', function () {
    $manager = User::factory()->infrastructureManager()->create();
    $project = projectForStageTaskTests();
    $stage = $project->stages()->where('stage', 'Monitoring')->first();
    $mdfTask = $stage->stageTasks()->create(['field_key' => 'infrastructure_deployment_status', 'section' => 'MDF Installation', 'name' => 'MDF Cabinet Installation', 'weight' => 2, 'status' => 'Pending', 'sort_order' => 0]);
    $testingTask = $stage->stageTasks()->create(['field_key' => 'infrastructure_deployment_status', 'section' => 'Testing Infra', 'name' => 'Continuity Test', 'weight' => 7.5, 'status' => 'Pending', 'sort_order' => 1]);

    $this->actingAs($manager)
        ->patch(route('projects.stages.stage-tasks.complete-all', [$project, $stage]), [
            'field_key' => 'infrastructure_deployment_status',
            'section' => 'MDF Installation',
        ])
        ->assertRedirect();

    expect($mdfTask->fresh()->status->value)->toBe('Done')
        ->and($testingTask->fresh()->status->value)->toBe('Pending');
});

it('completes tasks across every section when no section is sent, powering the top-level Complete All button', function () {
    $manager = User::factory()->infrastructureManager()->create();
    $project = projectForStageTaskTests();
    $stage = $project->stages()->where('stage', 'Monitoring')->first();
    $mdfTask = $stage->stageTasks()->create(['field_key' => 'infrastructure_deployment_status', 'section' => 'MDF Installation', 'name' => 'MDF Cabinet Installation', 'weight' => 2, 'status' => 'Pending', 'sort_order' => 0]);
    $testingTask = $stage->stageTasks()->create(['field_key' => 'infrastructure_deployment_status', 'section' => 'Testing Infra', 'name' => 'Continuity Test', 'weight' => 7.5, 'status' => 'Pending', 'sort_order' => 1]);

    $this->actingAs($manager)
        ->patch(route('projects.stages.stage-tasks.complete-all', [$project, $stage]), [
            'field_key' => 'infrastructure_deployment_status',
        ])
        ->assertRedirect();

    expect($mdfTask->fresh()->status->value)->toBe('Done')
        ->and($testingTask->fresh()->status->value)->toBe('Done');
});

it('completes only tasks with a null section when section is explicitly sent as null', function () {
    $manager = User::factory()->infrastructureManager()->create();
    $project = projectForStageTaskTests();
    $stage = $project->stages()->where('stage', 'Monitoring')->first();
    $ungroupedTask = $stage->stageTasks()->create(['field_key' => 'infrastructure_deployment_status', 'section' => null, 'name' => 'Ungrouped Task', 'weight' => 10, 'status' => 'Pending', 'sort_order' => 0]);
    $sectionedTask = $stage->stageTasks()->create(['field_key' => 'infrastructure_deployment_status', 'section' => 'MDF Installation', 'name' => 'MDF Cabinet Installation', 'weight' => 10, 'status' => 'Pending', 'sort_order' => 1]);

    $this->actingAs($manager)
        ->patch(route('projects.stages.stage-tasks.complete-all', [$project, $stage]), [
            'field_key' => 'infrastructure_deployment_status',
            'section' => null,
        ])
        ->assertRedirect();

    expect($ungroupedTask->fresh()->status->value)->toBe('Done')
        ->and($sectionedTask->fresh()->status->value)->toBe('Pending');
});

it('denies a role without a stage permission from completing all stage tasks', function () {
    $commercialManager = User::factory()->commercialManager()->create();
    $project = projectForStageTaskTests();
    $stage = $project->stages()->where('stage', 'Monitoring')->first();
    $stage->stageTasks()->create(['field_key' => 'infrastructure_deployment_status', 'name' => 'MDF Installation', 'weight' => 10, 'status' => 'Pending', 'sort_order' => 0]);

    $this->actingAs($commercialManager)
        ->patch(route('projects.stages.stage-tasks.complete-all', [$project, $stage]), ['field_key' => 'infrastructure_deployment_status'])
        ->assertForbidden();
});

it('blocks completing all stage tasks on a stage that is not currently active', function () {
    $manager = User::factory()->infrastructureManager()->create();
    $project = projectForStageTaskTests();
    $project->update(['current_stage' => 'Execution']);
    $stage = $project->stages()->where('stage', 'Monitoring')->first();
    $stage->stageTasks()->create(['field_key' => 'infrastructure_deployment_status', 'name' => 'MDF Installation', 'weight' => 10, 'status' => 'Pending', 'sort_order' => 0]);

    $this->actingAs($manager)
        ->patch(route('projects.stages.stage-tasks.complete-all', [$project, $stage]), ['field_key' => 'infrastructure_deployment_status'])
        ->assertStatus(422);
});
