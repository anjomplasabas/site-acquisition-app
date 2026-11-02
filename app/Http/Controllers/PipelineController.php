<?php

namespace App\Http\Controllers;

use App\Enums\PipelineActionPermission;
use App\Enums\PipelineStatus;
use App\Enums\SiteProfileType;
use App\Http\Requests\CompleteSurveyRequest;
use App\Http\Requests\StorePipelineRequest;
use App\Http\Requests\UpdatePipelineRequest;
use App\Models\Address;
use App\Models\ApprovalConfig;
use App\Models\Pipeline;
use App\Models\PipelineRemarkLog;
use App\Models\PipelineStatusLog;
use App\Models\SiteContact;
use App\Models\SitePlanningAssignment;
use App\Models\SitePlanningTask;
use App\Models\SiteProfile;
use App\Models\SiteSurvey;
use App\Models\StageDocumentRequirement;
use App\Models\User;
use App\Support\DocumentUploader;
use App\Support\LeadName;
use App\Support\PendingPipelineActions;
use App\Support\PipelineActivity;
use App\Support\PipelineStageContext;
use App\Support\PipelineVisibility;
use App\Support\SiteSurveyProgression;
use App\Support\StageDocumentRequirements;
use App\Support\WorkflowSettings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PipelineController extends Controller
{
    public function __construct(protected readonly DocumentUploader $documents) {}

    public function index(Request $request): Response
    {
        $activity = app(PipelineActivity::class);
        $stageContext = app(PipelineStageContext::class);
        $thresholds = app(WorkflowSettings::class)->alarmThresholds();

        $visiblePipelines = Pipeline::query()
            ->tap(fn (Builder $query) => PipelineVisibility::apply($query, $request->user()));

        $searchAndStatusFilter = function (Builder $query) use ($request): void {
            $query
                ->when($request->search, function ($query, $search) {
                    $query->where(function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%")
                            ->orWhere('pipeline_id', 'like', "%{$search}%");
                    });
                })
                ->when($request->status, fn ($query, $status) => $query->where('status', $status));
        };

        $pipelines = Pipeline::query()
            ->with(['projectManager', 'assignedSaq', 'siteProfile', 'siteSurvey.surveyor', 'approvals.role', 'sitePlanningAssignment.designPlanAssignee:id,full_name'])
            ->tap(fn ($query) => PipelineVisibility::apply($query, $request->user()))
            ->tap($searchAndStatusFilter)
            ->latest()
            ->paginate(15)
            ->through(function (Pipeline $pipeline) use ($activity, $stageContext, $thresholds): Pipeline {
                $pipeline->setAttribute('aging_badge', $activity->agingBadge($pipeline, $thresholds));
                $pipeline->setAttribute('stage_context', $stageContext->forPipeline($pipeline, $thresholds));

                return $pipeline;
            })
            ->withQueryString();

        return Inertia::render('Pipelines/Index', [
            'pipelines' => $pipelines,
            // Filtered separately from $visiblePipelines (used below for stats) so the
            // Pending Action tab's own search/status filter actually narrows its rows,
            // without also affecting the always-unfiltered dashboard stat counts.
            'pendingActions' => fn () => app(PendingPipelineActions::class)
                ->forVisiblePipelines((clone $visiblePipelines)->tap($searchAndStatusFilter), $thresholds)
                ->map(function (array $row) use ($activity, $thresholds): array {
                    /** @var Pipeline $pipeline */
                    $pipeline = $row['pipeline'];
                    $pipeline->setAttribute('aging_badge', $activity->agingBadge($pipeline, $thresholds));

                    return $row;
                }),
            'stats' => fn (): array => [
                'total' => (clone $visiblePipelines)->count(),
                'in_acquisition' => (clone $visiblePipelines)->whereNotIn('status', [
                    PipelineStatus::Prospect->value,
                    PipelineStatus::ReadyForService->value,
                    PipelineStatus::Inactive->value,
                    PipelineStatus::Completed->value,
                ])->count(),
                'pending_approval' => (clone $visiblePipelines)->where('status', PipelineStatus::Approval->value)->count(),
                'active_sites' => (clone $visiblePipelines)->where('status', PipelineStatus::ReadyForService->value)->count(),
            ],
            'filters' => $request->only(['search', 'status', 'tab']),
            'alarmThresholds' => $thresholds,
            'statuses' => array_column(PipelineStatus::cases(), 'value'),
            'saqSpecialists' => fn () => User::whereHas('role.permissions', fn ($q) => $q->where('pipeline_status', PipelineStatus::Prospecting->value))
                ->select('id', 'full_name')->orderBy('full_name')->get(),
            'can' => [
                'assignSaq' => $request->user()->role?->hasActionPermission(PipelineActionPermission::AssignSaq->value) ?? false,
                'create' => $request->user()->can('create', Pipeline::class),
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Pipelines/Create', [
            // Eligible for re-attempt only if every one of the site's pipelines is Inactive and
            // has no project yet — a Completed pipeline (or any pipeline with an existing
            // project) makes the whole site ineligible, even if it also has an older Inactive
            // one.
            'siteProfiles' => SiteProfile::select('id', 'name')
                ->whereDoesntHave('pipelines', fn (Builder $query) => $query
                    ->where('status', '!=', PipelineStatus::Inactive->value)
                    ->orWhereHas('project'))
                ->orderBy('name')
                ->get(),
            'siteContacts' => SiteContact::select('id', 'name', 'designation', 'email', 'mobile_number')->orderBy('name')->get(),
            'siteProfileTypes' => SiteProfileType::selectable(),
            'duplicateSite' => session('duplicateSite'),
        ]);
    }

    public function show(Pipeline $pipeline): Response
    {
        $this->authorize('view', $pipeline);

        $pipeline->load([
            'projectManager:id,full_name',
            'assignedSaq:id,full_name',
            'siteProfile.address',
            'siteProfile.contact',
            'siteSurvey.surveyor:id,full_name',
            'siteSurvey.surveyCompletedBy:id,full_name',
            'siteSurvey.networkFeasibilityCompletedBy:id,full_name',
            'siteSurvey.providers',
            'siteSurvey.networkFeasibilities',
            'siteSurvey.documents.uploadedBy:id,full_name',
            'sitePlanningAssignment.documents.uploadedBy:id,full_name',
            'sitePlanningAssignment.sitePlanningTasks',
            'sitePlanningAssignment.designPlanAssignee:id,full_name',
            'sitePlanningAssignment.completedBy:id,full_name',
            'sitePlanningAssignment.approvedBy:id,full_name',
            'documents.uploadedBy:id,full_name',
            'approvals.role:id,name',
            'approvals.decidedBy:id,full_name',
            'statusLogs.changedBy:id,full_name',
            'remarkLogs.addedBy:id,full_name',
            'project:id,pipeline_id',
        ]);

        $user = auth()->user();
        $activity = app(PipelineActivity::class);
        $stageContext = app(PipelineStageContext::class);
        $thresholds = app(WorkflowSettings::class)->alarmThresholds();
        $requirements = app(StageDocumentRequirements::class);
        $pipeline->setAttribute('aging_badge', $activity->agingBadge($pipeline, $thresholds));
        $pipeline->setAttribute('stage_context', $stageContext->forPipeline($pipeline, $thresholds));

        return Inertia::render('Pipelines/Show', [
            'pipeline' => $pipeline,
            'saqSpecialists' => fn () => User::whereHas('role.permissions', fn ($q) => $q->where('pipeline_status', PipelineStatus::Prospecting->value))
                ->select('id', 'full_name')->orderBy('full_name')->get(),
            'siteProfileTypes' => SiteProfileType::selectable(),
            'can' => [
                'update' => $user->can('update', $pipeline),
                'assignSaq' => $user->can('assignSaq', $pipeline),
                'startAcquisition' => $user->can('startAcquisition', $pipeline),
                'manageSiteVisit' => $user->can('manageSiteVisit', $pipeline),
                'moveToSiteSurveyPending' => $user->can('moveToSiteSurveyPending', $pipeline),
                'editSurveyFindings' => $user->can('editSurveyFindings', $pipeline),
                'handleCommercials' => $user->can('handleCommercials', $pipeline),
                'markActive' => $user->can('markActive', $pipeline),
                'markInactive' => $user->can('markInactive', $pipeline),
                'markCompleted' => $user->can('markCompleted', $pipeline),
            ],
            'hasApprovalConfig' => fn () => ApprovalConfig::where('is_active', true)->exists(),
            'documentTypes' => fn () => $requirements->activeFor($pipeline->status)->values(),
            'allDocumentTypes' => fn () => StageDocumentRequirement::active()->orderBy('stage')->orderBy('sort_order')->orderBy('name')->get(),
            'missingDocumentRequirements' => fn () => $requirements->missingFor($pipeline)->all(),
            'alarmThresholds' => $thresholds,
        ]);
    }

    public function update(UpdatePipelineRequest $request, Pipeline $pipeline): RedirectResponse
    {
        $this->authorize('update', $pipeline);
        // The policy already limits updates to Prospect/Prospecting, but Super Admin bypasses
        // policies via before() — a finalized record must stay immutable for everyone.
        abort_if($pipeline->status === PipelineStatus::Completed, 422, 'Completed pipelines are read-only.');

        $pipeline->update($request->validated());
        $this->recordActivity($pipeline, 'updated pipeline details');

        return redirect()->route('pipelines.show', $pipeline)
            ->with('success', 'Pipeline details updated successfully.');
    }

    protected function logStatus(Pipeline $pipeline, PipelineStatus $status): void
    {
        PipelineStatusLog::record($pipeline, $status);
    }

    protected function logRemark(Pipeline $pipeline, PipelineStatus $status, ?string $content, string $type = 'visit'): void
    {
        if (filled($content)) {
            PipelineRemarkLog::create([
                'pipeline_id' => $pipeline->id,
                'status' => $status->value,
                'type' => $type,
                'content' => $content,
                'added_by' => auth()->id(),
            ]);
        }
    }

    protected function persistSurveyData(CompleteSurveyRequest $request, Pipeline $pipeline, string $remarkType = 'survey'): void
    {
        $validated = $request->validated();
        SiteSurveyProgression::ensureSiteSurvey($pipeline);

        $pipeline->siteSurvey->update(
            collect($validated)->except(['providers', 'files', 'document_category'])->all()
        );

        $this->logRemark($pipeline, $pipeline->status, $validated['remarks'] ?? null, $remarkType);

        $pipeline->siteSurvey->providers()->delete();

        if (! empty($validated['providers'])) {
            $pipeline->siteSurvey->providers()->createMany(
                collect($validated['providers'])
                    ->map(fn (array $provider): array => ['provider_name' => $provider['provider_name']])
                    ->all()
            );
        }

        if (! empty($validated['files'])) {
            foreach ($validated['files'] as $file) {
                $document = $this->documents->store($file, 'survey-documents', auth()->user(), $validated['document_category'] ?? null, $pipeline->status->value);
                $pipeline->siteSurvey->documents()->syncWithoutDetaching([$document->id => ['created_at' => now()]]);
            }
        }
    }

    /**
     * A re-attempt (see store()'s reactivation branch) reuses the same
     * Pipeline/SiteSurvey/SitePlanningAssignment rows instead of creating new ones, so without
     * this reset those rows would still carry the previous attempt's surveyor/design-plan
     * progress — leaving the reactivated pipeline permanently stuck (e.g. the Infrastructure
     * Manager sees no "assign surveyor" action because ScheduleForm.vue's `isScheduled` check
     * already finds a surveyor/date from the old attempt). Deliberately run at re-attempt time
     * rather than in markInactive() — the actor who just marked a pipeline inactive should still
     * be able to view it afterward (PipelinePolicy::view()'s FillSiteSurvey/surveyor_id check),
     * which a mark-inactive-time reset would immediately break.
     */
    private function resetSiteSurveyProgress(Pipeline $pipeline): void
    {
        $pipeline->siteSurvey?->update([
            'surveyor_id' => null,
            'survey_date' => null,
            'survey_completed_at' => null,
            'survey_completed_by_id' => null,
            'network_feasibility_completed_at' => null,
            'network_feasibility_completed_by_id' => null,
        ]);

        if ($assignment = $pipeline->sitePlanningAssignment) {
            $assignment->sitePlanningTasks->each(function (SitePlanningTask $task): void {
                $task->documents()->detach();
                $task->delete();
            });

            $assignment->update([
                'design_plan_type' => null,
                'design_plan_completed_at' => null,
                'design_plan_completed_by_id' => null,
                'design_plan_approved_at' => null,
                'design_plan_approved_by_id' => null,
            ]);
        }
    }

    public function store(StorePipelineRequest $request): RedirectResponse
    {
        $this->authorize('create', Pipeline::class);

        $validated = $request->validated();
        $initialSurveyData = $this->initialSurveyData($validated);
        $user = $request->user();
        $initialStatus = $user?->isSaqSpecialist()
            ? PipelineStatus::Prospecting
            : PipelineStatus::Prospect;

        if (isset($validated['site_profile_id'])) {
            $siteProfileId = $validated['site_profile_id'];
            $pipelineName = SiteProfile::whereKey($siteProfileId)->value('name');

            // Mirrors the eligibility filter on the site picker itself: a
            // Completed pipeline, or any pipeline that already has a project, blocks a new
            // re-attempt just as much as a genuinely still-active one does.
            $hasIneligiblePipeline = Pipeline::where('site_profile_id', $siteProfileId)
                ->where(fn (Builder $query) => $query
                    ->where('status', '!=', PipelineStatus::Inactive->value)
                    ->orWhereHas('project'))
                ->exists();

            if ($hasIneligiblePipeline) {
                return redirect()->route('pipelines.create')
                    ->withErrors(['site_profile_id' => 'This site already has an active or completed pipeline. View it instead of starting a new attempt.'])
                    ->withInput();
            }
        } else {
            $duplicateSite = $this->findDuplicateSiteProfile($validated['site_name']);

            if ($duplicateSite !== null) {
                return redirect()->route('pipelines.create')
                    ->withErrors(['site_name' => 'This site already exists. Link the existing site instead.'])
                    ->withInput()
                    ->with('duplicateSite', $this->duplicateSitePayload($duplicateSite));
            }

            $address = Address::create([
                'street' => $validated['street'] ?? null,
                'barangay' => $validated['barangay'] ?? null,
                'city' => $validated['city'],
                'province' => $validated['province'] ?? null,
            ]);

            $contactId = $validated['contact_id'] ?? null;
            if ($contactId === null && ! empty($validated['contact_name'])) {
                // Typed-in details matching an existing contact reuse it instead of creating a
                // twin in the Contacts list.
                $contactId = (SiteContact::firstMatching(
                    $validated['contact_name'],
                    $validated['contact_mobile_number'] ?? null,
                    $validated['contact_email'] ?? null,
                ) ?? SiteContact::create([
                    'name' => $validated['contact_name'],
                    'designation' => $validated['contact_designation'] ?? null,
                    'email' => $validated['contact_email'] ?? null,
                    'mobile_number' => $validated['contact_mobile_number'] ?? null,
                ]))->id;
            }

            $siteProfile = SiteProfile::create([
                'name' => $validated['site_name'],
                'type' => $validated['site_type'],
                'type_other' => $validated['site_type'] === SiteProfileType::Others->value ? ($validated['site_type_other'] ?? null) : null,
                'developer' => $validated['developer'] ?? null,
                'total_unit_count' => $validated['total_unit_count'] ?? null,
                'total_floors_per_tower' => $validated['total_floors_per_tower'] ?? null,
                'total_commercial_unit_count' => $validated['total_commercial_unit_count'] ?? null,
                'total_building_count' => $validated['total_building_count'] ?? null,
                'residential_floor_starts_at' => $validated['residential_floor_starts_at'] ?? null,
                'floors_not_in_plan' => $validated['floors_not_in_plan'] ?? null,
                'turnover_rate' => $validated['turnover_rate'] ?? null,
                'occupancy_rate' => $validated['occupancy_rate'] ?? null,
                'whz_location' => $validated['whz_location'] ?? null,
                'total_whz_allocation' => $validated['total_whz_allocation'] ?? null,
                'wifi_area_location' => $validated['wifi_area_location'] ?? null,
                'address_id' => $address->id,
                'contact_id' => $contactId,
            ]);

            $siteProfileId = $siteProfile->id;
            $pipelineName = $siteProfile->name;
        }

        $year = now()->year;

        $pipeline = DB::transaction(function () use ($validated, $siteProfileId, $year, $initialSurveyData, $pipelineName, $user, $initialStatus) {
            $inactivePipeline = Pipeline::where('site_profile_id', $siteProfileId)
                ->where('status', PipelineStatus::Inactive->value)
                ->lockForUpdate()
                ->latest()
                ->first();

            if ($inactivePipeline !== null) {
                $inactivePipeline->update([
                    'name' => $pipelineName,
                    'project_manager_id' => auth()->id(),
                    'assigned_saq_id' => $user?->isSaqSpecialist() ? $user->id : null,
                    'endorsement_date' => $validated['endorsement_date'] ?? null,
                    'status' => $initialStatus,
                ]);

                if ($inactivePipeline->siteSurvey) {
                    $inactivePipeline->siteSurvey->update($initialSurveyData);
                } else {
                    $survey = SiteSurvey::create([
                        'site_profile_id' => $siteProfileId,
                        ...$initialSurveyData,
                    ]);
                    $inactivePipeline->update(['site_survey_id' => $survey->id]);
                }

                $this->resetSiteSurveyProgress($inactivePipeline);

                return $inactivePipeline;
            }

            $lastPipeline = Pipeline::where('pipeline_id', 'like', "PL-{$year}-%")
                ->lockForUpdate()
                ->orderByDesc('pipeline_id')
                ->first();

            $sequence = $lastPipeline
                ? ((int) substr($lastPipeline->pipeline_id, strrpos($lastPipeline->pipeline_id, '-') + 1)) + 1
                : 1;

            $pipeline = Pipeline::create([
                'name' => $pipelineName,
                'project_manager_id' => auth()->id(),
                'assigned_saq_id' => $user?->isSaqSpecialist() ? $user->id : null,
                'endorsement_date' => $validated['endorsement_date'] ?? null,
                'site_profile_id' => $siteProfileId,
                'pipeline_id' => sprintf('PL-%d-%04d', $year, $sequence),
                'status' => $initialStatus,
            ]);

            $survey = SiteSurvey::create([
                'site_profile_id' => $siteProfileId,
                ...$initialSurveyData,
            ]);
            $pipeline->update(['site_survey_id' => $survey->id]);

            return $pipeline;
        });

        // Set on the container-bound request (not the injected FormRequest instance,
        // which is a distinct object) so AuditMiddleware::terminate() can read it.
        request()->attributes->set('_audit_subject', $pipeline);

        $this->logStatus($pipeline, $initialStatus);
        $this->recordActivity($pipeline, $pipeline->wasRecentlyCreated ? 'created the site' : 're-attempted the site (reactivated from Inactive)');

        return redirect()->route('pipelines.show', $pipeline)
            ->with('success', $pipeline->wasRecentlyCreated ? 'Site created successfully.' : 'Site re-attempt started; existing pipeline reactivated.');
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function initialSurveyData(array $validated): array
    {
        return collect($validated)->only([
            'lobby_booth_allowed',
            'lobby_standee_allowed',
            'lobby_flyers_stand_allowed',
            'roaming_sales_rep_allowed',
            'marketing_paraphernalia',
            'brochure_posting_location',
        ])->all();
    }

    private function findDuplicateSiteProfile(string $name): ?SiteProfile
    {
        $normalizedName = LeadName::normalize($name);

        if ($normalizedName === null) {
            return null;
        }

        return SiteProfile::query()
            ->with(['address', 'pipelines' => fn ($query) => $query
                ->whereNotIn('status', [PipelineStatus::Inactive->value, PipelineStatus::Completed->value])
                ->latest()])
            ->where('normalized_name', $normalizedName)
            ->first();
    }

    /** @return array<string, mixed> */
    private function duplicateSitePayload(SiteProfile $siteProfile): array
    {
        $activePipeline = $siteProfile->pipelines->first();

        return [
            'id' => $siteProfile->id,
            'name' => $siteProfile->name,
            'type' => $siteProfile->type?->value,
            'location' => collect([
                $siteProfile->address?->street,
                $siteProfile->address?->barangay,
                $siteProfile->address?->city,
                $siteProfile->address?->province,
            ])->filter()->implode(', '),
            'pipeline_id' => $activePipeline?->id,
            'pipeline_name' => $activePipeline?->name,
            'pipeline_ref' => $activePipeline?->pipeline_id,
        ];
    }

    protected function ensureRequiredDocumentsAreAttached(Pipeline $pipeline, PipelineStatus $stage): void
    {
        app(StageDocumentRequirements::class)->ensureSatisfied($pipeline->fresh(['documents', 'siteSurvey.documents']), $stage);
    }

    protected function recordActivity(Pipeline $pipeline, string $action): void
    {
        app(PipelineActivity::class)->record($pipeline->fresh(), auth()->user(), $action);
    }

    protected function redirectToVisiblePipelineDestination(Pipeline $pipeline, string $message): RedirectResponse
    {
        $pipeline->refresh();

        if (! auth()->user()?->can('view', $pipeline)) {
            return redirect()->route('pipelines')->with('success', $message);
        }

        return redirect()->route('pipelines.show', $pipeline)->with('success', $message);
    }
}
