<?php

namespace App\Support;

use App\Enums\DocumentType;
use App\Models\Document;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use RuntimeException;

/**
 * Shared storage + versioning logic for every document upload across the app (Pipelines, Site
 * Planning, Network, Project Management) — consolidates what used to be five near-identical
 * private storeDocument()/documentsDisk() helpers, and adds the replace()/version-history
 * operation on top so it applies uniformly everywhere a document can be uploaded.
 */
class DocumentUploader
{
    /**
     * Shared with every `'file' => [..., 'max:'.self::MAX_UPLOAD_KB, ...]` validation rule across
     * the app's upload endpoints, so the limit only needs to change in one place.
     */
    public const MAX_UPLOAD_KB = 102400;

    /**
     * Every belongsToMany relation Document exposes to a parent — mirrors Document::isOrphaned(),
     * since replace() needs to know every place a document might currently be attached in order
     * to carry those attachments forward to the new version. Keyed by the relation name as it
     * exists on Document, valued by the matching relation name on the parent model — usually
     * both sides are named `documents()`, but a parent can expose more than one document
     * collection (e.g. SitePlanningAssignment's own `documents()` vs. its separate
     * `designPlanDocuments()`), so this can't just assume `$parent->documents()` everywhere.
     *
     * @var array<string, string>
     */
    private const RELATIONS = [
        'pipelines' => 'documents',
        'siteSurveys' => 'documents',
        'projectStages' => 'documents',
        'ispTasks' => 'documents',
        'stageTasks' => 'documents',
        'networkFeasibilities' => 'documents',
        'networkFeasibilityTasks' => 'documents',
        'networkSiteAssignments' => 'documents',
        'sitePlanningAssignments' => 'documents',
        'sitePlanningTasks' => 'documents',
        'siteSurveyTasks' => 'documents',
        'designPlanAssignments' => 'designPlanDocuments',
    ];

    public function disk(): string
    {
        return (string) config('filesystems.documents_disk', 'public');
    }

    public function store(UploadedFile $file, string $storageSubpath, ?User $uploader = null, ?string $category = null, ?string $uploadedStatus = null): Document
    {
        $extension = $file->getClientOriginalExtension();
        $isPhoto = in_array(strtolower($extension), ['jpg', 'jpeg', 'png', 'gif', 'webp']);
        $path = $file->store($storageSubpath, $this->disk());

        if ($path === false) {
            throw new RuntimeException('Unable to store uploaded document.');
        }

        return Document::create([
            'name' => $file->getClientOriginalName(),
            'type' => $isPhoto ? DocumentType::Photo : DocumentType::Document,
            'filename' => $file->getClientOriginalName(),
            'file_path' => $path,
            'uploaded_by' => $uploader?->id,
            'uploaded_status' => $uploadedStatus,
            'category' => $category,
        ]);
    }

    /**
     * Supersede $old with a newly uploaded file as the next version: the new file inherits $old's
     * category/status and every parent it's currently attached to, $old is detached everywhere
     * and marked superseded — but never deleted, which is what makes it recoverable history.
     * Deliberately does not carry a project stage document's review `status` pivot column
     * forward: a replacement file needs fresh review, same as a first-time upload starts unreviewed.
     */
    public function replace(Document $old, UploadedFile $file, User $uploader, string $storageSubpath): Document
    {
        $new = $this->store($file, $storageSubpath, $uploader, $old->category, $old->uploaded_status);

        $new->forceFill([
            'version' => $old->version + 1,
            'root_document_id' => $old->root_document_id,
        ])->save();

        foreach (self::RELATIONS as $documentRelation => $parentRelation) {
            $parents = $old->{$documentRelation}()->get();

            foreach ($parents as $parent) {
                $parent->{$parentRelation}()->syncWithoutDetaching([$new->id => ['created_at' => now()]]);
            }

            $old->{$documentRelation}()->detach();
        }

        $old->forceFill(['superseded_at' => now()])->save();

        return $new;
    }
}
