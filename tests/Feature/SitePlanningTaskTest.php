<?php

use App\Enums\DocumentType;
use App\Enums\PipelineStatus;
use App\Enums\SiteProfileType;
use App\Models\Address;
use App\Models\Document;
use App\Models\Pipeline;
use App\Models\SitePlanningAssignment;
use App\Models\SiteProfile;
use App\Models\SiteSurvey;
use App\Models\User;
use App\Support\SitePlanningTaskSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function assignmentForSitePlanningTaskTests(): SitePlanningAssignment
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
        'status' => PipelineStatus::SiteSurvey,
    ]);

    $assignment = SitePlanningAssignment::ensureFor($pipeline, $survey);
    $assignment->update(['design_plan_type' => 'FIBER']);
    SitePlanningTaskSeeder::seedMissingFor($assignment);

    return $assignment->fresh();
}

it('updates a site planning task status and stamps status_changed_at', function () {
    $designer = User::factory()->infraDesigner()->create();
    $assignment = assignmentForSitePlanningTaskTests();
    $assignment->update(['design_plan_assignee_id' => $designer->id]);
    $task = $assignment->sitePlanningTasks()->first();
    $task->update(['status_changed_at' => now()->subDays(3)]);

    $this->actingAs($designer)
        ->patch(route('site-planning.design-plan.tasks.update', [$assignment, $task]), ['status' => 'Done'])
        ->assertRedirect();

    expect($task->fresh()->status->value)->toBe('Done')
        ->and($task->fresh()->status_changed_at->diffInSeconds(now()))->toBeLessThan(5);
});

it('does not restamp status_changed_at when the status is unchanged', function () {
    $designer = User::factory()->infraDesigner()->create();
    $assignment = assignmentForSitePlanningTaskTests();
    $assignment->update(['design_plan_assignee_id' => $designer->id]);
    $original = now()->subDays(3);
    $task = $assignment->sitePlanningTasks()->first();
    $task->update(['status_changed_at' => $original]);

    $this->actingAs($designer)
        ->patch(route('site-planning.design-plan.tasks.update', [$assignment, $task]), ['status' => 'Pending'])
        ->assertRedirect();

    expect($task->fresh()->status_changed_at->timestamp)->toBe($original->timestamp);
});

it('saves a site planning task note without requiring a status', function () {
    $designer = User::factory()->infraDesigner()->create();
    $assignment = assignmentForSitePlanningTaskTests();
    $assignment->update(['design_plan_assignee_id' => $designer->id]);
    $task = $assignment->sitePlanningTasks()->first();

    $this->actingAs($designer)
        ->patch(route('site-planning.design-plan.tasks.update', [$assignment, $task]), ['notes' => 'Waiting on materials.'])
        ->assertRedirect();

    expect($task->fresh()->notes)->toBe('Waiting on materials.')
        ->and($task->fresh()->status->value)->toBe('Pending');
});

it('clears a site planning task note when saved as an empty string', function () {
    $designer = User::factory()->infraDesigner()->create();
    $assignment = assignmentForSitePlanningTaskTests();
    $assignment->update(['design_plan_assignee_id' => $designer->id]);
    $task = $assignment->sitePlanningTasks()->first();
    $task->update(['notes' => 'Old note.']);

    $this->actingAs($designer)
        ->patch(route('site-planning.design-plan.tasks.update', [$assignment, $task]), ['notes' => ''])
        ->assertRedirect();

    expect($task->fresh()->notes)->toBeNull();
});

it('uploads and removes a document on a site planning task', function () {
    Storage::fake('public');

    $designer = User::factory()->infraDesigner()->create();
    $assignment = assignmentForSitePlanningTaskTests();
    $assignment->update(['design_plan_assignee_id' => $designer->id]);
    $task = $assignment->sitePlanningTasks()->first();

    $this->actingAs($designer)
        ->post(route('site-planning.design-plan.tasks.documents.store', [$assignment, $task]), [
            'files' => [UploadedFile::fake()->create('plan.pdf', 100)],
        ])
        ->assertRedirect();

    $document = $task->fresh()->documents->first();
    expect($task->fresh()->documents)->toHaveCount(1);

    $this->actingAs($designer)
        ->delete(route('site-planning.design-plan.tasks.documents.delete', [$assignment, $task, $document]))
        ->assertRedirect();

    expect($task->fresh()->documents)->toHaveCount(0)
        ->and(Document::find($document->id))->toBeNull();
});

it('does not delete a site planning task document file while it is still attached elsewhere', function () {
    Storage::fake('public');

    $designer = User::factory()->infraDesigner()->create();
    $assignment = assignmentForSitePlanningTaskTests();
    $assignment->update(['design_plan_assignee_id' => $designer->id]);
    $task = $assignment->sitePlanningTasks()->first();

    $document = Document::create([
        'name' => 'shared.pdf',
        'type' => DocumentType::Document,
        'filename' => 'shared.pdf',
        'file_path' => 'site-planning-documents/shared.pdf',
    ]);
    $task->documents()->attach($document->id, ['created_at' => now()]);
    $assignment->documents()->attach($document->id, ['created_at' => now()]);

    $this->actingAs($designer)
        ->delete(route('site-planning.design-plan.tasks.documents.delete', [$assignment, $task, $document]))
        ->assertRedirect();

    expect($task->documents()->where('document_id', $document->id)->exists())->toBeFalse()
        ->and(Document::find($document->id))->not->toBeNull();
});

it('denies a role without the manage plans/drawing permission from updating a task', function () {
    $surveyor = User::factory()->surveyor()->create();
    $assignment = assignmentForSitePlanningTaskTests();
    $task = $assignment->sitePlanningTasks()->first();

    $this->actingAs($surveyor)
        ->patch(route('site-planning.design-plan.tasks.update', [$assignment, $task]), ['status' => 'Done'])
        ->assertForbidden();
});

it('rejects a task that does not belong to the given assignment', function () {
    $designer = User::factory()->infraDesigner()->create();
    $assignment = assignmentForSitePlanningTaskTests();
    $assignment->update(['design_plan_assignee_id' => $designer->id]);
    $otherAssignment = assignmentForSitePlanningTaskTests();
    $foreignTask = $otherAssignment->sitePlanningTasks()->first();

    $this->actingAs($designer)
        ->patch(route('site-planning.design-plan.tasks.update', [$assignment, $foreignTask]), ['status' => 'Done'])
        ->assertForbidden();
});

it('marks every task Done via complete-all', function () {
    $designer = User::factory()->infraDesigner()->create();
    $assignment = assignmentForSitePlanningTaskTests();
    $assignment->update(['design_plan_assignee_id' => $designer->id]);

    $this->actingAs($designer)
        ->patch(route('site-planning.design-plan.tasks.complete-all', $assignment))
        ->assertRedirect();

    expect($assignment->sitePlanningTasks()->where('status', '!=', 'Done')->count())->toBe(0);
});

it('does not restamp status_changed_at for tasks already Done when completing all', function () {
    $designer = User::factory()->infraDesigner()->create();
    $assignment = assignmentForSitePlanningTaskTests();
    $assignment->update(['design_plan_assignee_id' => $designer->id]);
    $original = now()->subDays(3);
    $doneTask = $assignment->sitePlanningTasks()->first();
    $doneTask->update(['status' => 'Done', 'status_changed_at' => $original]);

    $this->actingAs($designer)
        ->patch(route('site-planning.design-plan.tasks.complete-all', $assignment))
        ->assertRedirect();

    expect($doneTask->fresh()->status_changed_at->timestamp)->toBe($original->timestamp);
});

it('completes only the tasks in the given section, leaving other sections untouched', function () {
    $designer = User::factory()->infraDesigner()->create();
    $assignment = assignmentForSitePlanningTaskTests();
    $assignment->update(['design_plan_assignee_id' => $designer->id]);
    $initialDraftTask = $assignment->sitePlanningTasks()->where('section', 'Initial Draft')->first();
    $finalCheckingTask = $assignment->sitePlanningTasks()->where('section', 'Final Checking')->first();

    $this->actingAs($designer)
        ->patch(route('site-planning.design-plan.tasks.complete-all', $assignment), ['section' => 'Initial Draft'])
        ->assertRedirect();

    expect($initialDraftTask->fresh()->status->value)->toBe('Done')
        ->and($finalCheckingTask->fresh()->status->value)->toBe('Pending');
});

it('denies a role without the manage plans/drawing permission from completing all tasks', function () {
    $surveyor = User::factory()->surveyor()->create();
    $assignment = assignmentForSitePlanningTaskTests();

    $this->actingAs($surveyor)
        ->patch(route('site-planning.design-plan.tasks.complete-all', $assignment))
        ->assertForbidden();
});
