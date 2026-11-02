<?php

namespace App\Http\Controllers;

use App\Enums\PipelineStatus;
use App\Enums\SitePlanningActionPermission;
use App\Enums\SiteProfileType;
use App\Http\Requests\CompleteSurveyRequest;
use App\Models\AuditLog;
use App\Models\Pipeline;
use App\Models\Project;
use App\Models\SitePlanningAssignment;
use App\Models\SitePlanningTask;
use App\Models\SiteSurvey;
use App\Models\SiteSurveyTask;
use App\Models\Tower;
use App\Models\User;
use App\Policies\SitePlanningPolicy;
use App\Support\DocumentUploader;
use App\Support\SiteSurveyProgression;
use App\Support\SiteSurveyTaskSeeder;
use App\Support\StageDocumentRequirements;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SitePlanningController extends Controller
{
    public const DESIGN_PLAN_TYPES = ['FIBER', 'Residential', 'Share-Connect', 'FAMILIA'];

    public function __construct(protected readonly DocumentUploader $documents) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', SitePlanningAssignment::class);

        $statusFilter = $request->string('status')->value();
        $onlySiteSurvey = $statusFilter === 'site_survey';

        $assignments = SitePlanningAssignment::query()
            ->with(['pipeline.siteProfile', 'siteSurvey.surveyor', 'designPlanAssignee:id,full_name', 'tower'])
            ->withExists('sitePlanningTasks')
            ->when(
                app(SitePlanningPolicy::class)->isDesignerOnly($request->user()),
                fn (Builder $query) => $query->where('design_plan_assignee_id', $request->user()->id),
            )
            ->when(
                $onlySiteSurvey,
                fn (Builder $query) => $query->whereHas(
                    'pipeline',
                    fn (Builder $query) => $query->where('status', PipelineStatus::SiteSurvey->value)
                ),
            )
            // Additional filters — layered on top of the existing All/Site Survey tabs.
            ->when($request->search, function (Builder $query, string $search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->whereHas('pipeline', fn (Builder $query) => $query->where('name', 'like', "%{$search}%")
                        ->orWhere('pipeline_id', 'like', "%{$search}%"));
                });
            })
            ->when($request->pipeline_status, fn (Builder $query, string $status) => $query->whereHas(
                'pipeline', fn (Builder $query) => $query->where('status', $status)
            ))
            ->when($request->surveyor_id, fn (Builder $query, string $surveyorId) => $query->whereHas(
                'siteSurvey', fn (Builder $query) => $query->where('surveyor_id', $surveyorId)
            ))
            ->when($request->design_plan_assignee_id, fn (Builder $query, string $designerId) => $query->where('design_plan_assignee_id', $designerId))
            // "For Approval" — design plans submitted by the Designer and waiting on the Infra Manager.
            ->when($request->design_plan_status === 'for_approval', fn (Builder $query) => $query
                ->whereNotNull('design_plan_completed_at')
                ->whereNull('design_plan_approved_at'))
            ->when($request->survey_status, function (Builder $query, string $surveyStatus): void {
                $query->whereHas('siteSurvey', fn (Builder $query) => $surveyStatus === 'completed'
                    ? $query->whereNotNull('survey_completed_at')
                    : $query->whereNull('survey_completed_at'));
            })
            ->latest('assigned_at')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('SitePlanning/Index', [
            'assignments' => $assignments,
            'statusFilter' => $onlySiteSurvey ? 'site_survey' : 'all',
            'designers' => fn () => $this->designPlanEligibleUsers()->select('id', 'full_name')->orderBy('full_name')->get(),
            'surveyors' => fn () => User::withActionPermission(SitePlanningActionPermission::FillSurvey->value)
                ->select('id', 'full_name')->orderBy('full_name')->get(),
            'pipelineStatuses' => fn () => array_column(PipelineStatus::cases(), 'value'),
            'filters' => $request->only(['search', 'pipeline_status', 'surveyor_id', 'design_plan_assignee_id', 'survey_status', 'design_plan_status']),
            'can' => [
                'assignDesignPlan' => auth()->user()->role?->hasActionPermission(SitePlanningActionPermission::AssignDesignPlan->value) ?? false,
            ],
        ]);
    }

    /**
     * CSV export for the Infra Manager to extract every Site Planning record currently in the
     * Site Survey stage (surveyor not yet assigned, or survey in progress) for monitoring,
     * scheduling, and workload management.
     */
    public function siteSurveyReport(): StreamedResponse
    {
        $this->authorize('viewAny', SitePlanningAssignment::class);

        $assignments = SitePlanningAssignment::query()
            ->with(['pipeline', 'siteSurvey.surveyor', 'designPlanAssignee:id,full_name', 'tower'])
            ->whereHas('pipeline', fn (Builder $query) => $query->whereIn('status', [
                PipelineStatus::SiteSurveyPending->value,
                PipelineStatus::SiteSurvey->value,
            ]))
            ->latest('assigned_at')
            ->get();

        return response()->streamDownload(function () use ($assignments): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Pipeline ID', 'Site Name', 'Tower', 'Pipeline Aging (Days)', 'Pipeline Status', 'Surveyor', 'Survey Date', 'Survey Status', 'Design Plan Assignee', 'Assigned At']);

            foreach ($assignments as $assignment) {
                fputcsv($handle, [
                    $assignment->pipeline?->pipeline_id,
                    $assignment->pipeline?->name,
                    $assignment->tower?->name,
                    $this->pipelineAgingInDays($assignment->pipeline),
                    $assignment->pipeline?->status->value,
                    $assignment->siteSurvey?->surveyor?->full_name ?? 'Unassigned',
                    $assignment->siteSurvey?->survey_date?->toDateString(),
                    $assignment->siteSurvey?->survey_completed_at !== null ? 'Survey Completed' : 'Survey Pending',
                    $assignment->designPlanAssignee?->full_name ?? 'Unassigned',
                    $assignment->assigned_at?->toDateTimeString(),
                ]);
            }

            fclose($handle);
        }, 'site-planning-site-survey-report-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
    }

    /**
     * Whole days elapsed from the pipeline's initiation (its endorsement date, falling back to
     * when it was created) up to today.
     */
    private function pipelineAgingInDays(?Pipeline $pipeline): ?int
    {
        $initiatedAt = $pipeline?->endorsement_date ?? $pipeline?->created_at;

        return $initiatedAt === null ? null : (int) $initiatedAt->copy()->startOfDay()->diffInDays(now()->startOfDay());
    }

    public function show(SitePlanningAssignment $sitePlanningAssignment): Response
    {
        $this->authorize('view', $sitePlanningAssignment);

        if ($sitePlanningAssignment->siteSurvey !== null) {
            SiteSurveyTaskSeeder::seedMissingFor($sitePlanningAssignment->siteSurvey);
        }

        $sitePlanningAssignment->load([
            'pipeline.siteProfile.address',
            'pipeline.siteProfile.contact',
            'pipeline.projectManager:id,full_name',
            'tower',
            'siteSurvey.surveyor:id,full_name',
            'siteSurvey.providers',
            'siteSurvey.siteSurveyTasks.documents.uploadedBy:id,full_name',
            'documents.uploadedBy:id,full_name',
            'sitePlanningTasks.documents.uploadedBy:id,full_name',
            'designPlanDocuments.uploadedBy:id,full_name',
            'completedBy:id,full_name',
            'approvedBy:id,full_name',
            'designPlanAssignee:id,full_name',
        ]);

        $user = auth()->user();
        $requirements = app(StageDocumentRequirements::class);

        // The project matching THIS assignment's own tower (or the untowered default project if
        // the site has no towers) — not the pipeline's generic project() accessor, which only
        // ever resolves the untowered one.
        $matchingProject = Project::query()
            ->where('pipeline_id', $sitePlanningAssignment->pipeline_id)
            ->where('tower_id', $sitePlanningAssignment->tower_id)
            ->first(['id', 'pipeline_id']);

        // Every other tower's assignment under the same pipeline, so the UI can link between
        // towers on a multi-tower site.
        $siblingAssignments = SitePlanningAssignment::query()
            ->where('pipeline_id', $sitePlanningAssignment->pipeline_id)
            ->where('id', '!=', $sitePlanningAssignment->id)
            ->with('tower:id,name')
            ->get(['id', 'pipeline_id', 'tower_id']);

        $canDeclareTowers = $sitePlanningAssignment->tower_id === null
            && ! $sitePlanningAssignment->pipeline->siteProfile->towers()->exists()
            && $user->can('fillSurvey', $sitePlanningAssignment);

        return Inertia::render('SitePlanning/Show', [
            'assignment' => $sitePlanningAssignment,
            'matchingProject' => $matchingProject,
            'siblingTowerAssignments' => $siblingAssignments,
            'surveyors' => fn () => User::withActionPermission(SitePlanningActionPermission::FillSurvey->value)
                ->select('id', 'full_name')->orderBy('full_name')->get(),
            'designers' => fn () => $this->designPlanEligibleUsers()->select('id', 'full_name')->orderBy('full_name')->get(),
            'designPlanTypes' => self::DESIGN_PLAN_TYPES,
            'siteProfileTypes' => SiteProfileType::selectable(),
            'documentTypes' => fn () => $requirements->activeFor(PipelineStatus::SiteSurvey)->values(),
            'missingDocumentRequirements' => fn () => $this->missingSiteSurveyDocuments($sitePlanningAssignment),
            'activityLogs' => AuditLog::query()
                ->where('subject_type', SitePlanningAssignment::class)
                ->where('subject_id', $sitePlanningAssignment->id)
                ->with('user:id,full_name')
                ->latest()
                ->get(),
            'can' => [
                'manageAssignment' => $user->can('manageAssignment', $sitePlanningAssignment),
                'assignSiteSurveyor' => $user->can('assignSiteSurveyor', $sitePlanningAssignment),
                'fillSurvey' => $user->can('fillSurvey', $sitePlanningAssignment),
                'submitInspection' => $user->can('submitInspection', $sitePlanningAssignment),
                'manageDesignPlan' => $user->can('manageDesignPlan', $sitePlanningAssignment),
                'completeDesignPlan' => $user->can('completeDesignPlan', $sitePlanningAssignment),
                'approveDesignPlan' => $user->can('approveDesignPlan', $sitePlanningAssignment),
                'assignDesignPlan' => $user->can('assignDesignPlan', $sitePlanningAssignment),
                'updateProjectType' => $user->can('updateProjectType', $sitePlanningAssignment),
                'declareTowers' => $canDeclareTowers,
            ],
        ]);
    }

    protected function ensureTaskBelongsToAssignment(SitePlanningAssignment $sitePlanningAssignment, SitePlanningTask $sitePlanningTask): void
    {
        abort_if($sitePlanningTask->site_planning_assignment_id !== $sitePlanningAssignment->id, 403);
    }

    protected function ensureSurveyTaskBelongsToAssignment(SitePlanningAssignment $sitePlanningAssignment, SiteSurveyTask $siteSurveyTask): void
    {
        abort_if($siteSurveyTask->site_survey_id !== $sitePlanningAssignment->site_survey_id, 403);
    }

    /**
     * Users eligible to be assigned the design plan checklist as the designer doing the work.
     * Deliberately narrower than SitePlanningPolicy::manageDesignPlan() — Manage holders
     * (Infrastructure Manager/Specialist) operate this module and can run the assignment form,
     * but shouldn't appear as candidates to assign themselves the design work.
     */
    protected function designPlanEligibleUsers(): Builder
    {
        return User::withActionPermission(SitePlanningActionPermission::DesignPlan->value)
            ->where('status', 'ACTIVE');
    }

    protected function persistInspectionData(CompleteSurveyRequest $request, SitePlanningAssignment $sitePlanningAssignment): void
    {
        $validated = $request->validated();
        // This assignment's own SiteSurvey — not SiteSurveyProgression::ensureSiteSurvey($pipeline),
        // which always resolves the pipeline's single primary survey (Tower A's, on a multi-tower
        // site) regardless of which tower's assignment is actually being saved.
        $survey = $sitePlanningAssignment->siteSurvey;

        // telco_room_fee is SAQ-owned data (set via PipelineController::saveSurveyFindings during
        // Prospect/Prospecting, see PipelinePolicy::editSurveyFindings) — Infra's own inspection
        // form no longer exposes it, and this excludes it here too so a raw request can't set it.
        $survey->update(
            collect($validated)->except(['providers', 'files', 'document_category', 'telco_room_fee'])->all()
        );

        $survey->providers()->delete();

        if (! empty($validated['providers'])) {
            $survey->providers()->createMany(
                collect($validated['providers'])
                    ->map(fn (array $provider): array => ['provider_name' => $provider['provider_name']])
                    ->all()
            );
        }

        if (! empty($validated['files'])) {
            foreach ($validated['files'] as $file) {
                $document = $this->documents->store($file, 'site-planning-documents', auth()->user(), $validated['document_category'] ?? null);
                $sitePlanningAssignment->documents()->syncWithoutDetaching([$document->id => ['created_at' => now()]]);
            }
        }
    }

    /** @return Collection<int, string> */
    private function missingSiteSurveyDocuments(SitePlanningAssignment $sitePlanningAssignment): Collection
    {
        $required = app(StageDocumentRequirements::class)->requiredFor(PipelineStatus::SiteSurvey)->pluck('name');

        if ($required->isEmpty()) {
            return collect();
        }

        $attachedCategories = $sitePlanningAssignment->documents()->whereNotNull('category')->pluck('category');

        return $required->diff($attachedCategories)->values();
    }

    protected function ensureRequiredDocumentsAreAttached(SitePlanningAssignment $sitePlanningAssignment): void
    {
        $missing = $this->missingSiteSurveyDocuments($sitePlanningAssignment->fresh(['documents']));

        if ($missing->isNotEmpty()) {
            throw ValidationException::withMessages([
                'required_documents' => 'Missing required documents: '.$missing->implode(', '),
            ]);
        }
    }
}
