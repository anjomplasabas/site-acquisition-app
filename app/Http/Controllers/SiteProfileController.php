<?php

namespace App\Http\Controllers;

use App\Enums\PipelineStatus;
use App\Enums\SiteProfileType;
use App\Http\Requests\StoreSiteProfileRequest;
use App\Http\Requests\UpdateSiteProfileRequest;
use App\Models\Address;
use App\Models\Pipeline;
use App\Models\SiteContact;
use App\Models\SiteProfile;
use App\Support\PipelineActivity;
use Illuminate\Http\RedirectResponse;

class SiteProfileController extends Controller
{
    public function store(StoreSiteProfileRequest $request): RedirectResponse
    {
        $address = Address::create($request->only('street', 'barangay', 'city', 'province'));

        $contactId = $request->contact_id ?? SiteContact::create([
            'name' => $request->contact_name,
            'designation' => $request->contact_designation,
            'email' => $request->contact_email,
            'mobile_number' => $request->contact_mobile_number,
        ])->id;

        $siteProfile = SiteProfile::create([
            'name' => $request->name,
            'type' => $request->type,
            'type_other' => $request->type === SiteProfileType::Others->value ? $request->type_other : null,
            'developer' => $request->developer,
            'building_operation_date' => $request->building_operation_date,
            'total_building_count' => $request->total_building_count,
            'total_floors' => $request->total_floors,
            'total_unit_count' => $request->total_unit_count,
            'total_parking_floors' => $request->total_parking_floors,
            'residential_floor_starts_at' => $request->residential_floor_starts_at,
            'floors_not_in_plan' => $request->floors_not_in_plan,
            'amenities' => $request->amenities,
            'total_commercial_unit_count' => $request->total_commercial_unit_count,
            'residential_occupancy_percentage' => $request->residential_occupancy_percentage,
            'total_units_per_floor' => $request->total_units_per_floor,
            'room_types' => $request->room_types,
            'turnover_rate' => $request->turnover_rate,
            'occupancy_rate' => $request->occupancy_rate,
            'whz_location' => $request->whz_location,
            'total_whz_allocation' => $request->total_whz_allocation,
            'wifi_area_location' => $request->wifi_area_location,
            'address_id' => $address->id,
            'contact_id' => $contactId,
        ]);

        if ($request->routeIs('site-profiles.store')) {
            return redirect()->route('site-management')
                ->with('success', 'Site profile created successfully.');
        }

        return redirect()->route('pipelines.create')
            ->with('newSiteProfileId', $siteProfile->id);
    }

    public function update(UpdateSiteProfileRequest $request, SiteProfile $siteProfile): RedirectResponse
    {
        // A site tied to a finalized deal is frozen — edits would silently rewrite the record
        // behind a Completed pipeline; a second pipeline on the
        // same site is not a real scenario).
        abort_if(
            $siteProfile->pipelines()->where('status', PipelineStatus::Completed->value)->exists(),
            422,
            'This site belongs to a completed pipeline and can no longer be edited.'
        );

        $siteProfile->address->update($request->only('street', 'barangay', 'city', 'province'));

        $contact = $siteProfile->contact;

        if ($contact === null) {
            // Contact Person is optional while a site profile's details are still being filled
            // in during Prospecting (e.g. imported profiles arrive with no contact at all) — only
            // create a contact record once a name is actually given.
            if ($request->filled('contact_name')) {
                $contact = SiteContact::create([
                    'name' => $request->contact_name,
                    'designation' => $request->contact_designation,
                    'email' => $request->contact_email,
                    'mobile_number' => $request->contact_mobile_number,
                ]);

                $siteProfile->update(['contact_id' => $contact->id]);
            }
        } else {
            // Leaving a contact field blank keeps the existing value rather than clearing it —
            // matches other optional site profile fields, which never wipe data on omission.
            $contact->update(array_filter([
                'name' => $request->contact_name,
                'designation' => $request->contact_designation,
                'email' => $request->contact_email,
                'mobile_number' => $request->contact_mobile_number,
            ], fn (?string $value): bool => $value !== null));
        }

        $siteProfile->update($request->only(
            'name', 'type', 'type_other', 'developer',
            'building_operation_date', 'total_building_count', 'total_floors',
            'total_unit_count', 'total_parking_floors', 'residential_floor_starts_at', 'floors_not_in_plan',
            'amenities', 'total_commercial_unit_count', 'residential_occupancy_percentage',
            'total_units_per_floor', 'room_types',
            'turnover_rate', 'occupancy_rate', 'whz_location', 'total_whz_allocation',
            'wifi_area_location',
        ));

        if ($siteProfile->type !== SiteProfileType::Others && $siteProfile->type_other !== null) {
            $siteProfile->update(['type_other' => null]);
        }

        $this->recordActivityForSitePipelines($siteProfile, 'updated site profile details');

        return redirect()->back()
            ->with('success', 'Site profile updated successfully.');
    }

    private function recordActivityForSitePipelines(SiteProfile $siteProfile, string $action): void
    {
        $siteProfile->pipelines()
            ->each(function (Pipeline $pipeline) use ($action): void {
                app(PipelineActivity::class)->record($pipeline, auth()->user(), $action);
            });
    }
}
