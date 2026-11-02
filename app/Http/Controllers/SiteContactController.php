<?php

namespace App\Http\Controllers;

use App\Enums\PipelineStatus;
use App\Events\AuditLogged;
use App\Http\Requests\StoreSiteContactRequest;
use App\Http\Requests\UpdateSiteContactRequest;
use App\Models\Pipeline;
use App\Models\SiteContact;
use App\Models\SiteProfile;
use App\Support\PipelineActivity;
use Illuminate\Http\RedirectResponse;

class SiteContactController extends Controller
{
    public function store(StoreSiteContactRequest $request): RedirectResponse
    {
        SiteContact::create($request->validated());

        return redirect()->back()
            ->with('success', 'Contact created successfully.');
    }

    public function update(UpdateSiteContactRequest $request, SiteContact $siteContact): RedirectResponse
    {
        $siteContact->update($request->validated());
        $this->recordActivityForContactPipelines($siteContact, 'updated site contact details');

        return redirect()->back()
            ->with('success', 'Contact updated successfully.');
    }

    public function destroy(SiteContact $siteContact): RedirectResponse
    {
        if ($this->hasActivePipeline($siteContact)) {
            AuditLogged::emit('delete_blocked', "Blocked deletion of site contact {$siteContact->name} (linked to an active pipeline)", $siteContact);

            return redirect()->back()
                ->with('error', "Cannot delete \"{$siteContact->name}\" — it is linked to an active pipeline.");
        }

        $this->recordActivityForContactPipelines($siteContact, 'deleted a site contact');
        $siteContact->delete();

        return redirect()->back()
            ->with('success', 'Contact deleted.');
    }

    private function hasActivePipeline(SiteContact $siteContact): bool
    {
        return $siteContact->siteProfiles()
            ->whereHas('pipelines', fn ($query) => $query->where('status', '!=', PipelineStatus::Inactive))
            ->exists();
    }

    private function recordActivityForContactPipelines(SiteContact $siteContact, string $action): void
    {
        $siteContact->siteProfiles()
            ->with('pipelines')
            ->get()
            ->each(function (SiteProfile $siteProfile) use ($action): void {
                $siteProfile->pipelines->each(function (Pipeline $pipeline) use ($action): void {
                    app(PipelineActivity::class)->record($pipeline, auth()->user(), $action);
                });
            });
    }
}
