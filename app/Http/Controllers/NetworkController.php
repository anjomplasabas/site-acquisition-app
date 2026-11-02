<?php

namespace App\Http\Controllers;

use App\Enums\IspTaskStatus;
use App\Enums\PipelineStatus;
use App\Enums\ProviderStatus;
use App\Http\Requests\StoreNetworkFeasibilityRequest;
use App\Models\AuditLog;
use App\Models\Document;
use App\Models\NetworkFeasibility;
use App\Models\NetworkFeasibilityTask;
use App\Models\NetworkSiteAssignment;
use App\Support\DocumentUploader;
use App\Support\PipelineNotifier;
use App\Support\SiteSurveyProgression;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class NetworkController extends Controller
{
    public function __construct(private readonly DocumentUploader $documents) {}

    /**
     * ISP entries may only be added, edited, or completed while the underlying pipeline is
     * still in one of these two stages — the assignment record itself persists indefinitely
     * as an audit trail, but the workflow it represents is over once the pipeline moves on.
     */
    private const EDITABLE_STATUSES = [PipelineStatus::SiteSurveyPending, PipelineStatus::SiteSurvey];

    public function index(): Response
    {
        $this->authorize('viewAny', NetworkSiteAssignment::class);

        $assignments = NetworkSiteAssignment::query()
            ->with(['pipeline.siteProfile', 'siteSurvey.networkFeasibilities.tasks'])
            ->latest('assigned_at')
            ->paginate(15);

        return Inertia::render('Network/Index', [
            'assignments' => $assignments,
        ]);
    }

    public function show(NetworkSiteAssignment $networkSiteAssignment): Response
    {
        $this->authorize('view', $networkSiteAssignment);

        $networkSiteAssignment->load([
            'pipeline.siteProfile.address',
            'pipeline.siteProfile.contact',
            'pipeline.projectManager:id,full_name',
            // Tower-aware: pipeline.project only resolves the untowered default,
            // which is null once towers are declared — the JO checklist gate needs any tower's
            // project to count.
            'pipeline.projects:id,pipeline_id,tower_id',
            'siteSurvey.surveyor:id,full_name',
            'siteSurvey.providers',
            'siteSurvey.networkFeasibilities.documents.uploadedBy:id,full_name',
            'siteSurvey.networkFeasibilities.tasks.documents.uploadedBy:id,full_name',
            'documents.uploadedBy:id,full_name',
        ]);

        $user = auth()->user();

        return Inertia::render('Network/Show', [
            'assignment' => $networkSiteAssignment,
            'providerStatuses' => array_column(ProviderStatus::cases(), 'value'),
            'activityLogs' => AuditLog::query()
                ->where('subject_type', NetworkSiteAssignment::class)
                ->where('subject_id', $networkSiteAssignment->id)
                ->with('user:id,full_name')
                ->latest()
                ->get(),
            'can' => [
                'manageFeasibility' => $user->can('manageFeasibility', $networkSiteAssignment),
                'completeFeasibility' => $user->can('completeFeasibility', $networkSiteAssignment),
            ],
        ]);
    }

    public function storeFeasibility(StoreNetworkFeasibilityRequest $request, NetworkSiteAssignment $networkSiteAssignment): RedirectResponse
    {
        $this->authorize('manageFeasibility', $networkSiteAssignment);
        $this->abortUnlessEditable($networkSiteAssignment);

        $validated = $request->validated();
        if ($validated['status'] === ProviderStatus::Feasible->value) {
            $validated['feasible_at'] = now();
        }

        $networkSiteAssignment->siteSurvey->networkFeasibilities()->create($validated);

        return redirect()->route('network.show', $networkSiteAssignment)
            ->with('success', 'Network feasibility added.');
    }

    public function updateFeasibility(StoreNetworkFeasibilityRequest $request, NetworkSiteAssignment $networkSiteAssignment, NetworkFeasibility $networkFeasibility): RedirectResponse
    {
        $this->authorize('manageFeasibility', $networkSiteAssignment);
        $this->abortUnlessEditable($networkSiteAssignment);
        abort_unless($networkSiteAssignment->site_survey_id === $networkFeasibility->site_survey_id, 403);

        $validated = $request->validated();
        if ($validated['status'] === ProviderStatus::Feasible->value && $networkFeasibility->status !== ProviderStatus::Feasible) {
            $validated['feasible_at'] = now();
        }

        $networkFeasibility->update($validated);

        return redirect()->route('network.show', $networkSiteAssignment)
            ->with('success', 'Network feasibility updated.');
    }

    public function destroyFeasibility(NetworkSiteAssignment $networkSiteAssignment, NetworkFeasibility $networkFeasibility): RedirectResponse
    {
        $this->authorize('manageFeasibility', $networkSiteAssignment);
        $this->abortUnlessEditable($networkSiteAssignment);
        abort_unless($networkSiteAssignment->site_survey_id === $networkFeasibility->site_survey_id, 403);

        $networkFeasibility->delete();

        return redirect()->route('network.show', $networkSiteAssignment)
            ->with('success', 'Network feasibility removed.');
    }

    public function uploadFeasibilityDocument(Request $request, NetworkSiteAssignment $networkSiteAssignment, NetworkFeasibility $networkFeasibility): RedirectResponse
    {
        $this->authorize('manageFeasibility', $networkSiteAssignment);
        $this->abortUnlessEditable($networkSiteAssignment);
        abort_unless($networkSiteAssignment->site_survey_id === $networkFeasibility->site_survey_id, 403);

        $validated = $request->validate([
            'files' => ['required', 'array', 'min:1'],
            'files.*' => ['file', 'max:'.DocumentUploader::MAX_UPLOAD_KB, 'mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,ppt,pptx,txt,csv'],
        ]);

        foreach ($validated['files'] as $file) {
            $document = $this->documents->store($file, 'network-feasibility-documents', auth()->user(), $networkFeasibility->isp_name);
            $networkFeasibility->documents()->syncWithoutDetaching([$document->id => ['created_at' => now()]]);
        }

        return redirect()->route('network.show', $networkSiteAssignment)
            ->with('success', 'Document uploaded.');
    }

    public function deleteFeasibilityDocument(NetworkSiteAssignment $networkSiteAssignment, NetworkFeasibility $networkFeasibility, Document $document): RedirectResponse
    {
        $this->authorize('manageFeasibility', $networkSiteAssignment);
        $this->abortUnlessEditable($networkSiteAssignment);
        abort_unless($networkSiteAssignment->site_survey_id === $networkFeasibility->site_survey_id, 403);
        abort_unless($networkFeasibility->documents()->where('document_id', $document->id)->exists(), 403);

        $networkFeasibility->documents()->detach($document->id);

        if ($document->isOrphaned()) {
            Storage::disk($this->documents->disk())->delete($document->file_path);
            $document->delete();
        }

        return redirect()->route('network.show', $networkSiteAssignment)
            ->with('success', 'Document removed.');
    }

    public function replaceFeasibilityDocument(Request $request, NetworkSiteAssignment $networkSiteAssignment, NetworkFeasibility $networkFeasibility, Document $document): RedirectResponse
    {
        $this->authorize('manageFeasibility', $networkSiteAssignment);
        $this->abortUnlessEditable($networkSiteAssignment);
        abort_unless($networkSiteAssignment->site_survey_id === $networkFeasibility->site_survey_id, 403);
        abort_unless($networkFeasibility->documents()->where('document_id', $document->id)->exists(), 403);

        $validated = $request->validate([
            'file' => ['required', 'file', 'max:'.DocumentUploader::MAX_UPLOAD_KB, 'mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,ppt,pptx,txt,csv'],
        ]);

        $this->documents->replace($document, $validated['file'], auth()->user(), 'network-feasibility-documents');

        return redirect()->route('network.show', $networkSiteAssignment)
            ->with('success', 'Document replaced.');
    }

    public function updateFeasibilityTaskStatus(Request $request, NetworkSiteAssignment $networkSiteAssignment, NetworkFeasibility $networkFeasibility, NetworkFeasibilityTask $task): RedirectResponse
    {
        $this->authorize('manageFeasibility', $networkSiteAssignment);
        $this->abortUnlessProjectExists($networkSiteAssignment);
        $this->ensureTaskBelongsToFeasibility($networkSiteAssignment, $networkFeasibility, $task);

        $validated = $request->validate([
            'status' => ['sometimes', 'required', 'string', 'in:Pending,In-Progress,Done'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ]);

        if (($validated['status'] ?? null) === 'Done' && $task->requires_document && $task->documents()->doesntExist()) {
            throw ValidationException::withMessages([
                'status' => 'Attach a document before marking this task done.',
            ]);
        }

        $attributes = [];
        if (array_key_exists('status', $validated) && $validated['status'] !== $task->status->value) {
            $attributes['status'] = $validated['status'];
            $attributes['status_changed_at'] = now();
        }
        if (array_key_exists('notes', $validated)) {
            $attributes['notes'] = $validated['notes'];
        }
        if ($attributes !== []) {
            $task->update($attributes);
        }

        return redirect()->route('network.show', $networkSiteAssignment)
            ->with('success', 'Task updated.');
    }

    public function uploadFeasibilityTaskDocument(Request $request, NetworkSiteAssignment $networkSiteAssignment, NetworkFeasibility $networkFeasibility, NetworkFeasibilityTask $task): RedirectResponse
    {
        $this->authorize('manageFeasibility', $networkSiteAssignment);
        $this->abortUnlessProjectExists($networkSiteAssignment);
        $this->ensureTaskBelongsToFeasibility($networkSiteAssignment, $networkFeasibility, $task);

        $validated = $request->validate([
            'files' => ['required', 'array', 'min:1'],
            'files.*' => ['file', 'max:'.DocumentUploader::MAX_UPLOAD_KB, 'mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,ppt,pptx,txt,csv'],
        ]);

        foreach ($validated['files'] as $file) {
            $document = $this->documents->store($file, 'network-feasibility-task-documents', auth()->user(), $task->name);
            $task->documents()->syncWithoutDetaching([$document->id => ['created_at' => now()]]);
        }

        if ($task->requires_document && $task->status !== IspTaskStatus::Done) {
            $task->update(['status' => IspTaskStatus::Done, 'status_changed_at' => now()]);
        }

        return redirect()->route('network.show', $networkSiteAssignment)
            ->with('success', 'Document uploaded.');
    }

    public function deleteFeasibilityTaskDocument(NetworkSiteAssignment $networkSiteAssignment, NetworkFeasibility $networkFeasibility, NetworkFeasibilityTask $task, Document $document): RedirectResponse
    {
        $this->authorize('manageFeasibility', $networkSiteAssignment);
        $this->abortUnlessProjectExists($networkSiteAssignment);
        $this->ensureTaskBelongsToFeasibility($networkSiteAssignment, $networkFeasibility, $task);
        abort_unless($task->documents()->where('document_id', $document->id)->exists(), 403);

        if ($task->requires_document && $task->status === IspTaskStatus::Done && $task->documents()->count() === 1) {
            throw ValidationException::withMessages([
                'document' => 'Cannot remove the only attachment for a completed task. Replace it instead.',
            ]);
        }

        $task->documents()->detach($document->id);

        if ($document->isOrphaned()) {
            Storage::disk($this->documents->disk())->delete($document->file_path);
            $document->delete();
        }

        return redirect()->route('network.show', $networkSiteAssignment)
            ->with('success', 'Document removed.');
    }

    public function replaceFeasibilityTaskDocument(Request $request, NetworkSiteAssignment $networkSiteAssignment, NetworkFeasibility $networkFeasibility, NetworkFeasibilityTask $task, Document $document): RedirectResponse
    {
        $this->authorize('manageFeasibility', $networkSiteAssignment);
        $this->abortUnlessProjectExists($networkSiteAssignment);
        $this->ensureTaskBelongsToFeasibility($networkSiteAssignment, $networkFeasibility, $task);
        abort_unless($task->documents()->where('document_id', $document->id)->exists(), 403);

        $validated = $request->validate([
            'file' => ['required', 'file', 'max:'.DocumentUploader::MAX_UPLOAD_KB, 'mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,ppt,pptx,txt,csv'],
        ]);

        $this->documents->replace($document, $validated['file'], auth()->user(), 'network-feasibility-task-documents');

        return redirect()->route('network.show', $networkSiteAssignment)
            ->with('success', 'Document replaced.');
    }

    public function completeFeasibility(NetworkSiteAssignment $networkSiteAssignment): RedirectResponse
    {
        $this->authorize('completeFeasibility', $networkSiteAssignment);
        abort_if($networkSiteAssignment->pipeline->status !== PipelineStatus::SiteSurvey, 422, 'Pipeline is not in the correct status.');

        $pipeline = $networkSiteAssignment->pipeline;
        $survey = SiteSurveyProgression::ensureSiteSurvey($pipeline);

        if ($survey->network_feasibility_completed_at !== null) {
            return redirect()->route('network.show', $networkSiteAssignment)
                ->with('error', 'Network Feasibility is already completed.');
        }

        if ($survey->networkFeasibilities()->doesntExist()) {
            return redirect()->route('network.show', $networkSiteAssignment)
                ->with('error', 'Add at least one ISP feasibility entry before completing Network Feasibility.');
        }

        $survey->update([
            'network_feasibility_completed_at' => now(),
            'network_feasibility_completed_by_id' => auth()->id(),
        ]);

        app(PipelineNotifier::class)->networkFeasibilityCompleted($networkSiteAssignment, auth()->user());

        SiteSurveyProgression::advanceFromSiteSurveyWhenReady($pipeline, auth()->user());

        return redirect()->route('network.show', $networkSiteAssignment)
            ->with('success', 'Network Feasibility completed.');
    }

    private function abortUnlessEditable(NetworkSiteAssignment $networkSiteAssignment): void
    {
        abort_unless(
            in_array($networkSiteAssignment->pipeline->status, self::EDITABLE_STATUSES, true),
            422,
            'Pipeline is not in the correct status.'
        );
    }

    /**
     * The JO checklist is only actionable once the pipeline has been approved and a Project
     * exists for it — independent of the pipeline's/project's own current stage, since the
     * Network module operates on its own timeline from here on.
     */
    private function abortUnlessProjectExists(NetworkSiteAssignment $networkSiteAssignment): void
    {
        abort_unless($networkSiteAssignment->pipeline->projects()->exists(), 422, 'Project has not been created yet.');
    }

    private function ensureTaskBelongsToFeasibility(NetworkSiteAssignment $networkSiteAssignment, NetworkFeasibility $networkFeasibility, NetworkFeasibilityTask $task): void
    {
        abort_unless($networkSiteAssignment->site_survey_id === $networkFeasibility->site_survey_id, 403);
        abort_if($task->network_feasibility_id !== $networkFeasibility->id, 403);
    }
}
