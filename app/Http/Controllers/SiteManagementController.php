<?php

namespace App\Http\Controllers;

use App\Models\Pipeline;
use App\Models\SiteContact;
use App\Models\SiteProfile;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SiteManagementController extends Controller
{
    public function index(Request $request): Response
    {
        $sites = Pipeline::query()
            ->with(['projectManager:id,full_name', 'siteProfile.address'])
            ->when($request->search, fn ($q, $s) => $q->where('name', 'like', "%{$s}%"))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $contacts = SiteContact::query()
            ->withCount('siteProfiles')
            ->when($request->contact_search, fn ($q, $s) => $q->where('name', 'like', "%{$s}%"))
            ->latest()
            ->paginate(15, pageName: 'contacts_page')
            ->withQueryString();

        return Inertia::render('SiteManagement/Index', [
            'sites' => $sites,
            'contacts' => $contacts,
            'tab' => $request->tab ?? 'sites',
            'filters' => $request->only(['search', 'contact_search', 'tab', 'status']),
        ]);
    }

    public function show(SiteProfile $siteProfile): Response
    {
        $siteProfile->load([
            'address',
            'contact',
            'pipelines.projectManager:id,full_name',
            'pipelines.assignedSaq:id,full_name',
            'pipelines.siteSurvey.surveyor:id,full_name',
            'pipelines.siteSurvey.providers',
            'pipelines.siteSurvey.networkFeasibilities',
            'pipelines.siteSurvey.documents.uploadedBy:id,full_name',
            'pipelines.documents.uploadedBy:id,full_name',
            'pipelines.remarkLogs.addedBy:id,full_name',
            'pipelines.statusLogs.changedBy:id,full_name',
            'pipelines.approvals.role:id,name',
            'pipelines.approvals.decidedBy:id,full_name',
        ]);

        return Inertia::render('SiteManagement/Show', [
            'site' => $siteProfile,
        ]);
    }
}
