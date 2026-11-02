<?php

use App\Enums\DocumentType;
use App\Enums\NotificationType;
use App\Enums\PipelineStatus;
use App\Enums\SitePlanningActionPermission;
use App\Enums\SiteProfileType;
use App\Events\PipelineApproved;
use App\Models\Address;
use App\Models\ApprovalConfig;
use App\Models\Document;
use App\Models\NetworkSiteAssignment;
use App\Models\Pipeline;
use App\Models\Role;
use App\Models\RoleActionPermission;
use App\Models\SitePlanningAssignment;
use App\Models\SiteProfile;
use App\Models\SiteSurvey;
use App\Models\User;
use App\Notifications\PipelineNotification;
use App\Support\SitePlanningTaskSeeder;
use App\Support\SiteSurveyProgression;
use Illuminate\Support\Facades\Notification;

function notificationTestPipeline(User $manager, PipelineStatus $status = PipelineStatus::Prospect, array $overrides = []): Pipeline
{
    $address = Address::create(['city' => 'Manila']);
    $siteProfile = SiteProfile::create([
        'name' => 'Notification Test Site '.uniqid(),
        'type' => SiteProfileType::Condo,
        'address_id' => $address->id,
    ]);

    return Pipeline::create(array_merge([
        'name' => 'Notification Test Pipeline',
        'pipeline_id' => nextPipelineId(),
        'project_manager_id' => $manager->id,
        'site_profile_id' => $siteProfile->id,
        'status' => $status,
    ], $overrides));
}

it('notifies only the assigned SAQ specialist when a pipeline is assigned', function () {
    Notification::fake();

    $leadSaq = User::factory()->leadSaqSpecialist()->create();
    $assignee = User::factory()->saqSpecialist()->create();
    $pipeline = notificationTestPipeline($leadSaq, PipelineStatus::Prospect);

    $this->actingAs($leadSaq)
        ->patch(route('pipelines.assign-saq', $pipeline), ['assigned_saq_id' => $assignee->id])
        ->assertRedirect();

    Notification::assertSentTo(
        $assignee,
        PipelineNotification::class,
        fn (PipelineNotification $n): bool => $n->type === NotificationType::SaqAssigned && $n->pipeline->is($pipeline)
    );
    Notification::assertNotSentTo($leadSaq, PipelineNotification::class);
});

it('notifies infrastructure manager, not specialist, when a pipeline moves to site survey pending', function () {
    Notification::fake();

    $saq = User::factory()->saqSpecialist()->create();
    $infraManager = User::factory()->infrastructureManager()->create();
    $infraSpecialist = User::factory()->infrastructureSpecialist()->create();
    $unrelated = User::factory()->surveyor()->create();

    $pipeline = notificationTestPipeline($saq, PipelineStatus::Prospecting, ['assigned_saq_id' => $saq->id]);

    $this->actingAs($saq)
        ->post(route('pipelines.move-to-site-survey-pending', $pipeline))
        ->assertRedirect();

    // Only Manager holds AssignSiteSurveyor — Specialist is intentionally excluded, mirroring
    // approveDesignPlan()'s Manager-only tier.
    Notification::assertSentTo($infraManager, PipelineNotification::class, fn (PipelineNotification $n): bool => $n->type === NotificationType::SiteSurveyPending);
    Notification::assertNotSentTo($infraSpecialist, PipelineNotification::class);
    Notification::assertNotSentTo($unrelated, PipelineNotification::class);
});

it('notifies only the assigned surveyor when a site survey is scheduled', function () {
    Notification::fake();

    $infraManager = User::factory()->infrastructureManager()->create();
    $surveyor = User::factory()->surveyor()->create();
    $otherSurveyor = User::factory()->surveyor()->create();
    $pipeline = notificationTestPipeline($infraManager, PipelineStatus::SiteSurveyPending);
    $assignment = SitePlanningAssignment::ensureFor($pipeline, SiteSurveyProgression::ensureSiteSurvey($pipeline));

    $this->actingAs($infraManager)
        ->patch(route('site-planning.schedule', $assignment), [
            'surveyor_id' => $surveyor->id,
            'survey_date' => now()->toDateString(),
            'design_plan_type' => 'FIBER',
        ])
        ->assertRedirect();

    Notification::assertSentTo($surveyor, PipelineNotification::class, fn (PipelineNotification $n): bool => $n->type === NotificationType::SurveyorAssigned
        && str_contains($n->message, 'Survey date: '.now()->format('M j, Y'))
        && str_contains($n->message, $pipeline->pipeline_id));
    Notification::assertNotSentTo($otherSurveyor, PipelineNotification::class);
});

it('notifies network roles once when a network site assignment is first created, not on re-entry', function () {
    Notification::fake();

    $infraManager = User::factory()->infrastructureManager()->create();
    $surveyor = User::factory()->surveyor()->create();
    $networkManager = User::factory()->networkManager()->create();
    $networkSpecialist = User::factory()->networkSpecialist()->create();
    $networkSurveyor = User::factory()->networkSurveyor()->create();

    $pipeline = notificationTestPipeline($infraManager, PipelineStatus::SiteSurveyPending);
    $assignment = SitePlanningAssignment::ensureFor($pipeline, SiteSurveyProgression::ensureSiteSurvey($pipeline));

    $this->actingAs($infraManager)
        ->patch(route('site-planning.schedule', $assignment), [
            'surveyor_id' => $surveyor->id,
            'survey_date' => now()->toDateString(),
            'design_plan_type' => 'FIBER',
        ])
        ->assertRedirect();

    $assignment = NetworkSiteAssignment::where('pipeline_id', $pipeline->id)->firstOrFail();

    foreach ([$networkManager, $networkSpecialist, $networkSurveyor] as $recipient) {
        Notification::assertSentToTimes($recipient, PipelineNotification::class, 1);
        Notification::assertSentTo(
            $recipient,
            PipelineNotification::class,
            fn (PipelineNotification $n): bool => $n->type === NotificationType::NetworkAssignmentCreated
                && $n->url === route('network.show', $assignment)
        );
    }

    // Re-entering Site Survey (survey reopened from Commercials) must not create a duplicate
    // assignment or fire a second notification — NetworkSiteAssignment::ensureFor is idempotent,
    // and the controller only notifies when a new assignment row is actually created.
    $commercialManager = User::factory()->commercialManager()->create();
    $pipeline->refresh();
    $pipeline->update(['status' => PipelineStatus::Commercials]);

    $this->actingAs($commercialManager)
        ->post(route('pipelines.reopen-survey', $pipeline))
        ->assertRedirect();

    foreach ([$networkManager, $networkSpecialist, $networkSurveyor] as $recipient) {
        Notification::assertSentToTimes($recipient, PipelineNotification::class, 1);
    }
});

it('notifies infrastructure manager and specialist when network feasibility completes', function () {
    Notification::fake();

    $networkSurveyor = User::factory()->networkSurveyor()->create();
    $infraManager = User::factory()->infrastructureManager()->create();
    $infraSpecialist = User::factory()->infrastructureSpecialist()->create();

    $pipeline = notificationTestPipeline($networkSurveyor, PipelineStatus::SiteSurvey);
    $survey = SiteSurveyProgression::ensureSiteSurvey($pipeline);
    $assignment = NetworkSiteAssignment::ensureFor($pipeline, $survey);
    $survey->networkFeasibilities()->create(['isp_name' => 'Fiber One', 'status' => 'Feasible']);

    $this->actingAs($networkSurveyor)
        ->post(route('network.complete-feasibility', $assignment))
        ->assertRedirect();

    Notification::assertSentTo($infraManager, PipelineNotification::class, fn (PipelineNotification $n): bool => $n->type === NotificationType::NetworkFeasibilityCompleted);
    Notification::assertSentTo($infraSpecialist, PipelineNotification::class, fn (PipelineNotification $n): bool => $n->type === NotificationType::NetworkFeasibilityCompleted);
});

it('notifies the commercial manager once a pipeline becomes ready for commercials via design plan approval', function () {
    Notification::fake();

    $infraManager = User::factory()->infrastructureManager()->create();
    $designer = User::factory()->infraDesigner()->create();
    $commercialManager = User::factory()->commercialManager()->create();

    $pipeline = notificationTestPipeline($infraManager, PipelineStatus::SiteSurvey);
    $survey = SiteSurveyProgression::ensureSiteSurvey($pipeline);
    $survey->update(['network_feasibility_completed_at' => now()]);
    $assignment = SitePlanningAssignment::ensureFor($pipeline, $survey);
    $assignment->update(['design_plan_type' => 'FIBER']);
    SitePlanningTaskSeeder::seedMissingFor($assignment);
    $assignment->sitePlanningTasks()->update(['status' => 'Done', 'status_changed_at' => now()]);
    $assignment->update(['design_plan_completed_at' => now(), 'design_plan_completed_by_id' => $designer->id]);

    $this->actingAs($infraManager)
        ->patch(route('site-planning.design-plan.approve', $assignment))
        ->assertRedirect();

    Notification::assertSentTo($commercialManager, PipelineNotification::class, fn (PipelineNotification $n): bool => $n->type === NotificationType::ReadyForCommercials);
});

it('does not notify the commercial manager when network feasibility completes but the design plan is not yet approved', function () {
    Notification::fake();

    $networkSurveyor = User::factory()->networkSurveyor()->create();
    $commercialManager = User::factory()->commercialManager()->create();

    $pipeline = notificationTestPipeline($networkSurveyor, PipelineStatus::SiteSurvey);
    $survey = SiteSurveyProgression::ensureSiteSurvey($pipeline);
    $assignment = NetworkSiteAssignment::ensureFor($pipeline, $survey);

    $this->actingAs($networkSurveyor)
        ->post(route('network.complete-feasibility', $assignment))
        ->assertRedirect();

    Notification::assertNotSentTo($commercialManager, PipelineNotification::class);
});

it('notifies infrastructure roles when a project is created from an approved pipeline', function () {
    Notification::fake();

    $manager = User::factory()->leadSaqSpecialist()->create();
    $infraManager = User::factory()->infrastructureManager()->create();
    $infraSpecialist = User::factory()->infrastructureSpecialist()->create();

    $address = Address::create(['city' => 'Manila']);
    $siteProfile = SiteProfile::create(['name' => 'Approved Site', 'type' => SiteProfileType::Condo, 'address_id' => $address->id]);
    $survey = SiteSurvey::create(['site_profile_id' => $siteProfile->id]);
    $pipeline = Pipeline::create([
        'name' => 'Approved Site',
        'pipeline_id' => nextPipelineId(),
        'project_manager_id' => $manager->id,
        'site_profile_id' => $siteProfile->id,
        'site_survey_id' => $survey->id,
        'status' => PipelineStatus::ReadyForDeployment,
    ]);
    NetworkSiteAssignment::ensureFor($pipeline, $survey);

    PipelineApproved::dispatch($pipeline);

    Notification::assertSentTo($infraManager, PipelineNotification::class, fn (PipelineNotification $n): bool => $n->type === NotificationType::ProjectCreated);
    Notification::assertSentTo($infraSpecialist, PipelineNotification::class, fn (PipelineNotification $n): bool => $n->type === NotificationType::ProjectCreated);

    // Regression guard: NotifyProjectCreated must be registered exactly once. It previously fired
    // twice per PipelineApproved dispatch because AppServiceProvider::boot() explicitly registered
    // it via Event::listen() on top of Laravel's automatic event discovery already picking up its
    // handle(PipelineApproved) method — see php artisan event:list.
    Notification::assertSentToTimes($infraManager, PipelineNotification::class, 1);
    Notification::assertSentToTimes($infraSpecialist, PipelineNotification::class, 1);
});

it('notifies the infrastructure manager, not the specialist, when a design plan is submitted for approval', function () {
    Notification::fake();

    $infraManager = User::factory()->infrastructureManager()->create();
    $infraSpecialist = User::factory()->infrastructureSpecialist()->create();
    $designer = User::factory()->infraDesigner()->create();
    $surveyor = User::factory()->surveyor()->create();

    $pipeline = notificationTestPipeline($infraManager, PipelineStatus::SiteSurvey);
    $survey = SiteSurveyProgression::ensureSiteSurvey($pipeline);
    $survey->update(['surveyor_id' => $surveyor->id]);
    $assignment = SitePlanningAssignment::ensureFor($pipeline, $survey);
    $assignment->update(['design_plan_type' => 'FIBER', 'design_plan_assignee_id' => $designer->id]);
    SitePlanningTaskSeeder::seedMissingFor($assignment);
    $assignment->sitePlanningTasks()->update(['status' => 'Done', 'status_changed_at' => now()]);
    $assignment->designPlanDocuments()->attach(
        Document::create(['name' => 'design-plan.pdf', 'type' => DocumentType::Document, 'filename' => 'design-plan.pdf', 'file_path' => 'x'])->id,
        ['created_at' => now()]
    );

    $this->actingAs($designer)
        ->patch(route('site-planning.design-plan.complete', $assignment))
        ->assertRedirect();

    Notification::assertSentTo($infraManager, PipelineNotification::class, fn (PipelineNotification $n): bool => $n->type === NotificationType::DesignPlanPendingApproval);
    Notification::assertNotSentTo($infraSpecialist, PipelineNotification::class);
    Notification::assertNotSentTo($designer, PipelineNotification::class);
});

it('notifies users of the first pending approval role when commercials are submitted, and excludes an actor who shares that role', function () {
    Notification::fake();

    $commercialManager = User::factory()->commercialManager()->create();
    $peerApprover = User::factory()->create(['role_id' => $commercialManager->role_id]);
    $secondStepRole = Role::factory()->create();
    $secondStepUser = User::factory()->create(['role_id' => $secondStepRole->id]);

    ApprovalConfig::create(['step' => 1, 'role_id' => $commercialManager->role_id, 'is_active' => true, 'created_by' => $commercialManager->id, 'created_at' => now()]);
    ApprovalConfig::create(['step' => 2, 'role_id' => $secondStepRole->id, 'is_active' => true, 'created_by' => $commercialManager->id, 'created_at' => now()]);

    $address = Address::create(['city' => 'Manila']);
    $siteProfile = SiteProfile::create(['name' => 'Commercials Site', 'type' => SiteProfileType::Condo, 'address_id' => $address->id]);
    $survey = SiteSurvey::create(['site_profile_id' => $siteProfile->id, 'survey_completed_at' => now(), 'network_feasibility_completed_at' => now()]);
    $pipeline = Pipeline::create([
        'name' => 'Commercials Site',
        'pipeline_id' => nextPipelineId(),
        'project_manager_id' => $commercialManager->id,
        'site_profile_id' => $siteProfile->id,
        'site_survey_id' => $survey->id,
        'status' => PipelineStatus::Commercials,
    ]);

    $this->actingAs($commercialManager)
        ->post(route('pipelines.set-commercials', $pipeline), ['remarks' => 'Terms agreed.'])
        ->assertRedirect();

    // The submitting commercialManager shares role_id with step 1's approver role — must not
    // be notified about their own submission.
    Notification::assertNotSentTo($commercialManager, PipelineNotification::class);
    Notification::assertSentTo($peerApprover, PipelineNotification::class, fn (PipelineNotification $n): bool => $n->type === NotificationType::ApprovalPending);
    Notification::assertNotSentTo($secondStepUser, PipelineNotification::class);
});

it('notifies the next approval step role when a non-final step is approved', function () {
    Notification::fake();

    $commercialManager = User::factory()->commercialManager()->create();
    $peerApprover = User::factory()->create(['role_id' => $commercialManager->role_id]);
    $secondStepRole = Role::factory()->create();
    $secondStepUser = User::factory()->create(['role_id' => $secondStepRole->id]);

    ApprovalConfig::create(['step' => 1, 'role_id' => $commercialManager->role_id, 'is_active' => true, 'created_by' => $commercialManager->id, 'created_at' => now()]);
    ApprovalConfig::create(['step' => 2, 'role_id' => $secondStepRole->id, 'is_active' => true, 'created_by' => $commercialManager->id, 'created_at' => now()]);

    $address = Address::create(['city' => 'Manila']);
    $siteProfile = SiteProfile::create(['name' => 'Two Step Site', 'type' => SiteProfileType::Condo, 'address_id' => $address->id]);
    $survey = SiteSurvey::create(['site_profile_id' => $siteProfile->id, 'survey_completed_at' => now(), 'network_feasibility_completed_at' => now()]);
    $pipeline = Pipeline::create([
        'name' => 'Two Step Site',
        'pipeline_id' => nextPipelineId(),
        'project_manager_id' => $commercialManager->id,
        'site_profile_id' => $siteProfile->id,
        'site_survey_id' => $survey->id,
        'status' => PipelineStatus::Commercials,
    ]);

    $this->actingAs($commercialManager)
        ->post(route('pipelines.set-commercials', $pipeline), ['remarks' => 'Terms agreed.'])
        ->assertRedirect();

    $stepOneApproval = $pipeline->approvals()->where('step', 1)->firstOrFail();

    $this->actingAs($peerApprover)
        ->post(route('pipelines.approvals.decide', [$pipeline, $stepOneApproval]), ['decision' => 'approved'])
        ->assertRedirect();

    Notification::assertSentTo($secondStepUser, PipelineNotification::class, fn (PipelineNotification $n): bool => $n->type === NotificationType::ApprovalPending);
});

it('notifies infrastructure manager and specialist when an approval step is rejected', function () {
    Notification::fake();

    $commercialManager = User::factory()->commercialManager()->create();
    $infraManager = User::factory()->infrastructureManager()->create();
    $infraSpecialist = User::factory()->infrastructureSpecialist()->create();

    ApprovalConfig::create(['step' => 1, 'role_id' => $commercialManager->role_id, 'is_active' => true, 'created_by' => $commercialManager->id, 'created_at' => now()]);

    $address = Address::create(['city' => 'Manila']);
    $siteProfile = SiteProfile::create(['name' => 'Rejected Site', 'type' => SiteProfileType::Condo, 'address_id' => $address->id]);
    $survey = SiteSurvey::create(['site_profile_id' => $siteProfile->id, 'survey_completed_at' => now(), 'network_feasibility_completed_at' => now()]);
    $pipeline = Pipeline::create([
        'name' => 'Rejected Site',
        'pipeline_id' => nextPipelineId(),
        'project_manager_id' => $commercialManager->id,
        'site_profile_id' => $siteProfile->id,
        'site_survey_id' => $survey->id,
        'status' => PipelineStatus::Commercials,
    ]);

    $this->actingAs($commercialManager)
        ->post(route('pipelines.set-commercials', $pipeline), ['remarks' => 'Terms agreed.'])
        ->assertRedirect();

    $stepOneApproval = $pipeline->approvals()->where('step', 1)->firstOrFail();

    $this->actingAs($commercialManager)
        ->post(route('pipelines.approvals.decide', [$pipeline, $stepOneApproval]), ['decision' => 'rejected'])
        ->assertRedirect();

    expect($pipeline->fresh()->status)->toBe(PipelineStatus::SiteSurvey);
    Notification::assertSentTo($infraManager, PipelineNotification::class, fn (PipelineNotification $n): bool => $n->type === NotificationType::ApprovalRejected);
    Notification::assertSentTo($infraSpecialist, PipelineNotification::class, fn (PipelineNotification $n): bool => $n->type === NotificationType::ApprovalRejected);
});

// ─── Permission-derived recipients, not hardcoded role names ──────────────────

it('derives site survey pending recipients from the AssignSiteSurveyor permission, not a hardcoded role name', function () {
    Notification::fake();

    $saq = User::factory()->saqSpecialist()->create();

    $customRole = Role::factory()->create(['name' => 'Custom Regional Coordinator']);
    RoleActionPermission::create(['role_id' => $customRole->id, 'permission' => SitePlanningActionPermission::AssignSiteSurveyor->value]);
    $customRoleUser = User::factory()->create(['role_id' => $customRole->id]);

    $pipeline = notificationTestPipeline($saq, PipelineStatus::Prospecting, ['assigned_saq_id' => $saq->id]);

    $this->actingAs($saq)
        ->post(route('pipelines.move-to-site-survey-pending', $pipeline))
        ->assertRedirect();

    Notification::assertSentTo($customRoleUser, PipelineNotification::class, fn (PipelineNotification $n): bool => $n->type === NotificationType::SiteSurveyPending);
});

// ─── Design plan assignment needed ─────────────────────────────────────────────

it('notifies assign-design-plan-permission holders once inspection is submitted with no assignee yet, not again on resubmission', function () {
    Notification::fake();

    $infraManager = User::factory()->infrastructureManager()->create();
    $surveyor = User::factory()->surveyor()->create();

    $pipeline = notificationTestPipeline($infraManager, PipelineStatus::SiteSurvey);
    $survey = SiteSurveyProgression::ensureSiteSurvey($pipeline);
    $survey->update(['surveyor_id' => $surveyor->id]);
    $assignment = SitePlanningAssignment::ensureFor($pipeline, $survey);
    $assignment->update(['design_plan_type' => 'FIBER']);

    $this->actingAs($surveyor)
        ->post(route('site-planning.inspection.submit', $assignment), [])
        ->assertRedirect();

    Notification::assertSentTo($infraManager, PipelineNotification::class, fn (PipelineNotification $n): bool => $n->type === NotificationType::DesignPlanAssignmentNeeded);

    // Resubmitting must not duplicate the notification — the checklist already exists.
    $this->actingAs($surveyor)
        ->post(route('site-planning.inspection.submit', $assignment), [])
        ->assertRedirect();

    Notification::assertSentToTimes($infraManager, PipelineNotification::class, 1);
});

it('does not notify about a needed design plan assignment once an assignee is already set', function () {
    Notification::fake();

    $infraManager = User::factory()->infrastructureManager()->create();
    $surveyor = User::factory()->surveyor()->create();
    $designer = User::factory()->infraDesigner()->create();

    $pipeline = notificationTestPipeline($infraManager, PipelineStatus::SiteSurvey);
    $survey = SiteSurveyProgression::ensureSiteSurvey($pipeline);
    $survey->update(['surveyor_id' => $surveyor->id]);
    $assignment = SitePlanningAssignment::ensureFor($pipeline, $survey);
    $assignment->update(['design_plan_type' => 'FIBER', 'design_plan_assignee_id' => $designer->id]);

    $this->actingAs($surveyor)
        ->post(route('site-planning.inspection.submit', $assignment), [])
        ->assertRedirect();

    Notification::assertNotSentTo($infraManager, PipelineNotification::class, fn (PipelineNotification $n): bool => $n->type === NotificationType::DesignPlanAssignmentNeeded);
});

it('delivers PipelineNotification via both the database and mail channels', function () {
    $manager = User::factory()->infrastructureManager()->create();
    $pipeline = notificationTestPipeline($manager);
    $notification = new PipelineNotification(NotificationType::SaqAssigned, $pipeline, 'A message.', 'https://example.test/pipelines/1');

    expect($notification->via($manager))->toBe(['database', 'mail']);
});

it('builds the mail representation of PipelineNotification from its type, message, url, and pipeline', function () {
    $manager = User::factory()->infrastructureManager()->create();
    $pipeline = notificationTestPipeline($manager);
    $notification = new PipelineNotification(
        NotificationType::SiteSurveyPending,
        $pipeline,
        "{$pipeline->name} is ready for a surveyor to be assigned.",
        'https://example.test/site-planning/1'
    );

    $mail = $notification->toMail($manager);

    expect($mail->subject)->toBe("Site Survey Needed: {$pipeline->name}")
        ->and($mail->greeting)->toBe('Site Survey Needed')
        ->and($mail->introLines)->toBe([
            "{$pipeline->name} is ready for a surveyor to be assigned.",
            "Site: {$pipeline->name} ({$pipeline->pipeline_id})",
            "Current stage: {$pipeline->status->value}",
        ])
        ->and($mail->actionText)->toBe('View Site')
        ->and($mail->actionUrl)->toBe('https://example.test/site-planning/1');
});
