<?php

use App\Enums\ApprovalStatus;
use App\Enums\DocumentType;
use App\Enums\PipelineStatus;
use App\Models\Document;
use App\Models\SitePlanningAssignment;
use App\Models\SiteSurvey;
use App\Models\Tower;
use App\Models\User;
use App\Notifications\PipelineNotification;
use App\Support\SitePlanningTaskSeeder;
use Illuminate\Support\Facades\Notification;

it('returns the pipeline to ongoing site survey when rejected', function () {
    ['pipeline' => $pipeline, 'approval' => $approval, 'approver' => $approver, 'manager' => $manager] = pipelineInApproval();

    // The approver only holds Approval-stage visibility, so once the pipeline
    // moves back to Site Survey they can no longer view it — the controller
    // should redirect to the pipeline list instead of a page they'd 403 on.
    $this->actingAs($approver)
        ->post(route('pipelines.approvals.decide', [$pipeline, $approval]), [
            'decision' => 'rejected',
            'remarks' => 'Does not meet requirements.',
        ])
        ->assertRedirect(route('pipelines'));

    expect($pipeline->fresh()->status)->toBe(PipelineStatus::SiteSurvey);
});

it('resets Network Feasibility and Design Plan completion when an approval step is rejected', function () {
    ['pipeline' => $pipeline, 'approval' => $approval, 'approver' => $approver] = pipelineInApproval();

    $survey = SiteSurvey::create([
        'site_profile_id' => $pipeline->site_profile_id,
        'survey_completed_at' => now(),
        'survey_completed_by_id' => $approver->id,
        'network_feasibility_completed_at' => now(),
        'network_feasibility_completed_by_id' => $approver->id,
    ]);
    $pipeline->update(['site_survey_id' => $survey->id]);

    $sitePlanningAssignment = SitePlanningAssignment::create([
        'pipeline_id' => $pipeline->id,
        'site_survey_id' => $survey->id,
        'assigned_at' => now(),
        'design_plan_completed_at' => now(),
        'design_plan_completed_by_id' => $approver->id,
        'design_plan_approved_at' => now(),
        'design_plan_approved_by_id' => $approver->id,
    ]);

    $this->actingAs($approver)
        ->post(route('pipelines.approvals.decide', [$pipeline, $approval]), [
            'decision' => 'rejected',
        ]);

    $survey->refresh();
    expect($pipeline->fresh()->status)->toBe(PipelineStatus::SiteSurvey)
        ->and($survey->survey_completed_at)->toBeNull()
        ->and($survey->survey_completed_by_id)->toBeNull()
        ->and($survey->network_feasibility_completed_at)->toBeNull()
        ->and($survey->network_feasibility_completed_by_id)->toBeNull();

    $sitePlanningAssignment->refresh();
    expect($sitePlanningAssignment->design_plan_completed_at)->toBeNull()
        ->and($sitePlanningAssignment->design_plan_completed_by_id)->toBeNull()
        ->and($sitePlanningAssignment->design_plan_approved_at)->toBeNull()
        ->and($sitePlanningAssignment->design_plan_approved_by_id)->toBeNull();
});

it('resets every tower\'s survey and Design Plan stamps when an approval step is rejected on a towered site', function () {
    ['pipeline' => $pipeline, 'approval' => $approval, 'approver' => $approver] = pipelineInApproval();

    $towerA = Tower::create(['site_profile_id' => $pipeline->site_profile_id, 'name' => 'Tower A']);
    $towerB = Tower::create(['site_profile_id' => $pipeline->site_profile_id, 'name' => 'Tower B']);

    $surveys = [];
    $assignments = [];
    foreach ([$towerA, $towerB] as $tower) {
        $survey = SiteSurvey::create([
            'site_profile_id' => $pipeline->site_profile_id,
            'pipeline_id' => $pipeline->id,
            'tower_id' => $tower->id,
            'survey_completed_at' => now(),
            'survey_completed_by_id' => $approver->id,
            'network_feasibility_completed_at' => now(),
            'network_feasibility_completed_by_id' => $approver->id,
        ]);
        $surveys[] = $survey;
        $assignments[] = SitePlanningAssignment::create([
            'pipeline_id' => $pipeline->id,
            'site_survey_id' => $survey->id,
            'tower_id' => $tower->id,
            'assigned_at' => now(),
            'design_plan_completed_at' => now(),
            'design_plan_completed_by_id' => $approver->id,
            'design_plan_approved_at' => now(),
            'design_plan_approved_by_id' => $approver->id,
        ]);
    }

    // Tower A's survey doubles as the pipeline's primary survey, mirroring declareTowers().
    $pipeline->update(['site_survey_id' => $surveys[0]->id]);

    $this->actingAs($approver)
        ->post(route('pipelines.approvals.decide', [$pipeline, $approval]), [
            'decision' => 'rejected',
        ]);

    expect($pipeline->fresh()->status)->toBe(PipelineStatus::SiteSurvey);

    foreach ($surveys as $survey) {
        $survey->refresh();
        expect($survey->survey_completed_at)->toBeNull()
            ->and($survey->survey_completed_by_id)->toBeNull();
    }

    foreach ($assignments as $assignment) {
        $assignment->refresh();
        expect($assignment->design_plan_completed_at)->toBeNull()
            ->and($assignment->design_plan_completed_by_id)->toBeNull()
            ->and($assignment->design_plan_approved_at)->toBeNull()
            ->and($assignment->design_plan_approved_by_id)->toBeNull();
    }
});

it('deletes all approval records when rejected', function () {
    ['pipeline' => $pipeline, 'approval' => $approval, 'approver' => $approver, 'manager' => $manager] = pipelineInApproval(totalSteps: 2);

    expect($pipeline->approvals()->count())->toBe(2);

    $this->actingAs($approver)
        ->post(route('pipelines.approvals.decide', [$pipeline, $approval]), [
            'decision' => 'rejected',
        ]);

    expect($pipeline->approvals()->count())->toBe(0);
});

it('advances to next step when approved and more steps are pending', function () {
    ['pipeline' => $pipeline, 'approval' => $approval, 'approver' => $approver, 'manager' => $manager] = pipelineInApproval(totalSteps: 2);

    $this->actingAs($approver)
        ->post(route('pipelines.approvals.decide', [$pipeline, $approval]), [
            'decision' => 'approved',
        ])
        ->assertRedirect(route('pipelines.show', $pipeline));

    expect($pipeline->fresh()->status)->toBe(PipelineStatus::Approval);
    expect($pipeline->approvals()->where('status', ApprovalStatus::Approved)->count())->toBe(1);
    expect($pipeline->approvals()->where('status', ApprovalStatus::Pending)->count())->toBe(1);
});

it('moves pipeline to RFD and creates a project when all steps are approved', function () {
    ['pipeline' => $pipeline, 'approval' => $approval, 'approver' => $approver, 'manager' => $manager] = pipelineInApproval(totalSteps: 1);

    // Same visibility loss as the rejection case, but landing on RFD instead of Site Survey.
    $this->actingAs($approver)
        ->post(route('pipelines.approvals.decide', [$pipeline, $approval]), [
            'decision' => 'approved',
        ])
        ->assertRedirect(route('pipelines'));

    expect($pipeline->fresh()->status)->toBe(PipelineStatus::ReadyForDeployment);
    expect($pipeline->fresh()->project)->not->toBeNull();
});

it('returns 422 when deciding on a pipeline not in approval status', function () {
    ['pipeline' => $pipeline, 'approval' => $approval, 'approver' => $approver, 'manager' => $manager] = pipelineInApproval();

    $pipeline->update(['status' => PipelineStatus::Commercials]);

    $this->actingAs($approver)
        ->post(route('pipelines.approvals.decide', [$pipeline, $approval]), [
            'decision' => 'approved',
        ])
        ->assertStatus(422);
});

it('returns 422 when deciding an already-decided approval step', function () {
    ['pipeline' => $pipeline, 'approval' => $approval, 'approver' => $approver, 'manager' => $manager] = pipelineInApproval();

    $approval->update(['status' => ApprovalStatus::Approved, 'decided_at' => now()]);

    $this->actingAs($approver)
        ->post(route('pipelines.approvals.decide', [$pipeline, $approval]), [
            'decision' => 'rejected',
        ])
        ->assertStatus(422);
});

it('retains Network data, design plan data and assignments, and notifies everyone assigned, when an approval step is rejected', function () {
    Notification::fake();
    ['pipeline' => $pipeline, 'approval' => $approval, 'approver' => $approver] = pipelineInApproval();

    $surveyorUser = User::factory()->surveyor()->create();
    $designer = User::factory()->infraDesigner()->create();
    $networkSurveyor = User::factory()->networkSurveyor()->create();

    $survey = SiteSurvey::create([
        'site_profile_id' => $pipeline->site_profile_id,
        'surveyor_id' => $surveyorUser->id,
        'survey_completed_at' => now(),
        'network_feasibility_completed_at' => now(),
    ]);
    $pipeline->update(['site_survey_id' => $survey->id]);

    $feasibility = $survey->networkFeasibilities()->create(['isp_name' => 'Fiber One', 'status' => 'Feasible', 'notes' => 'Ports available']);
    $sitePlanningAssignment = SitePlanningAssignment::create([
        'pipeline_id' => $pipeline->id,
        'site_survey_id' => $survey->id,
        'assigned_at' => now(),
        'design_plan_type' => 'FIBER',
        'design_plan_assignee_id' => $designer->id,
        'design_plan_completed_at' => now(),
        'design_plan_approved_at' => now(),
    ]);
    SitePlanningTaskSeeder::seedMissingFor($sitePlanningAssignment);
    $sitePlanningAssignment->sitePlanningTasks()->update(['status' => 'Done', 'status_changed_at' => now()]);
    $sitePlanningAssignment->designPlanDocuments()->attach(
        Document::create(['name' => 'plan.pdf', 'type' => DocumentType::Document, 'filename' => 'plan.pdf', 'file_path' => 'x'])->id,
        ['created_at' => now()]
    );

    $this->actingAs($approver)
        ->post(route('pipelines.approvals.decide', [$pipeline, $approval]), ['decision' => 'rejected', 'remarks' => 'Redo the riser plan.']);

    $sitePlanningAssignment->refresh();
    expect($survey->networkFeasibilities()->count())->toBe(1)
        ->and($feasibility->fresh()->notes)->toBe('Ports available')
        ->and($survey->fresh()->surveyor_id)->toBe($surveyorUser->id)
        ->and($sitePlanningAssignment->design_plan_assignee_id)->toBe($designer->id)
        ->and($sitePlanningAssignment->design_plan_type)->toBe('FIBER')
        ->and($sitePlanningAssignment->sitePlanningTasks()->where('status', '!=', 'Done')->exists())->toBeFalse()
        ->and($sitePlanningAssignment->designPlanDocuments()->count())->toBe(1);

    Notification::assertSentTo([$surveyorUser, $designer, $networkSurveyor], PipelineNotification::class);
});
