<?php

namespace App\Http\Controllers;

use App\Models\Document;
use Illuminate\Http\JsonResponse;

class DocumentController extends Controller
{
    /**
     * The version chain for a single document, newest first — powers the "History" popover
     * every document row across the app can show. Gated only by the surrounding `auth`
     * middleware, matching Document::url's existing exposure model: there's no per-record
     * DocumentPolicy since a single Document row can be shared across several parents at once.
     */
    public function versions(Document $document): JsonResponse
    {
        $versions = $document->versions()
            ->with('uploadedBy:id,full_name')
            ->get()
            ->map(fn (Document $version): array => [
                'id' => $version->id,
                'name' => $version->name,
                'version' => $version->version,
                'url' => $version->url,
                'uploaded_by' => $version->uploadedBy?->full_name,
                'created_at' => $version->created_at?->toIso8601String(),
                'superseded_at' => $version->superseded_at?->toIso8601String(),
                'is_current' => $version->isCurrentVersion(),
            ]);

        return response()->json(['versions' => $versions]);
    }
}
