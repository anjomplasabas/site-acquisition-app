<?php

namespace App\Support;

use App\Enums\PipelineActionPermission;
use App\Enums\PipelineStatus;
use App\Enums\SiteProfileType;
use App\Models\Address;
use App\Models\Pipeline;
use App\Models\SiteContact;
use App\Models\SiteProfile;
use App\Models\SiteSurvey;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;

class PipelineCsvImport
{
    /** @return array<int, array<string, mixed>> */
    public function preview(array $rows): array
    {
        return array_map(
            fn (array $row): array => $this->previewRow($row),
            $rows
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{created_sites: int, reused_sites: int, created_pipelines: int, skipped_duplicates: int, invalid_rows: int}
     */
    public function confirm(array $rows): array
    {
        $summary = [
            'created_sites' => 0,
            'reused_sites' => 0,
            'created_pipelines' => 0,
            'skipped_duplicates' => 0,
            'invalid_rows' => 0,
        ];

        DB::transaction(function () use ($rows, &$summary): void {
            foreach ($rows as $row) {
                $data = $this->normalizeRow($row['data'] ?? []);
                $siteAction = $row['site_action'] ?? null;
                $pipelineAction = $row['pipeline_action'] ?? 'create';

                if ($siteAction === 'skip') {
                    $summary['skipped_duplicates']++;

                    continue;
                }

                $preview = $this->previewRow([
                    'row_number' => (int) ($row['row_number'] ?? 0),
                    'data' => $data,
                ]);

                if (! empty($preview['errors'])) {
                    $summary['invalid_rows']++;

                    continue;
                }

                // An explicit pipeline choice (skip/reattempt) is the user's own decision,
                // not evidence the record is a duplicate — only auto-skip-as-duplicate when
                // they left it on the default "create" action.
                if ($pipelineAction === 'create' && $preview['pipeline_duplicate']) {
                    $summary['skipped_duplicates']++;

                    continue;
                }

                $siteProfile = $this->resolveSiteProfile($data, $siteAction, $row['site_profile_id'] ?? null);
                $summary[$siteProfile->wasRecentlyCreated ? 'created_sites' : 'reused_sites']++;

                if ($pipelineAction === 'skip') {
                    continue;
                }

                $pipeline = Pipeline::create([
                    'name' => $data['pipeline_name'] ?: $data['name'] ?: $data['site_name'],
                    'pipeline_id' => $data['pipeline_id'] ?: $this->nextPipelineId(),
                    // Matches manual creation (PipelineController::store): the acting user is
                    // always "Registered By", and the Lead SAQ Specialist is whoever the CSV
                    // resolved via assigned_saq_email/assigned_saq_name (previously this was
                    // writing the resolved SAQ specialist into project_manager_id instead,
                    // which left the real "Assigned SAQ" field blank on every imported row).
                    'project_manager_id' => auth()->id(),
                    'assigned_saq_id' => $this->resolveLeadSaq($data)?->id,
                    'site_profile_id' => $siteProfile->id,
                    'endorsement_date' => $data['endorsement_date'] ?: now()->toDateString(),
                    'status' => PipelineStatus::Prospect,
                ]);

                $survey = SiteSurvey::create([
                    'site_profile_id' => $siteProfile->id,
                    ...$this->surveyPayload($data),
                ]);

                $pipeline->update(['site_survey_id' => $survey->id]);
                $summary['created_pipelines']++;
            }
        });

        return $summary;
    }

    /** @return array{row_number: int, data: array<string, mixed>, site_status: string, site_matches: array<int, array{id: int, name: string, city: ?string}>, pipeline_duplicate: bool, errors: array<int, string>} */
    private function previewRow(array $row): array
    {
        $data = $this->normalizeRow($row['data'] ?? []);
        $errors = [];

        if (blank($data['site_name']) && blank($data['site_profile_id'])) {
            $errors[] = 'Site name or site_profile_id is required.';
        }

        if (blank($data['site_type']) && blank($data['site_profile_id'])) {
            $errors[] = 'Site type is required for new sites.';
        }

        if (blank($data['city']) && blank($data['site_profile_id'])) {
            $errors[] = 'City is required for new sites.';
        }

        if (filled($data['contact_mobile_number']) && ! preg_match('/^(09\d{9}|\+639\d{9})$/', (string) $data['contact_mobile_number'])) {
            $errors[] = 'Contact mobile number must be a valid PH mobile number (e.g. 09171234567).';
        }

        if (filled($data['contact_email']) && ! filter_var($data['contact_email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Contact email is not a valid email address.';
        }

        if (filled($data['total_unit_count']) && (! preg_match('/^\d+$/', (string) $data['total_unit_count']) || (int) $data['total_unit_count'] > 2147483647)) {
            $errors[] = 'Total unit count must be a whole number between 0 and 2,147,483,647.';
        }

        $warnings = [];

        if ($this->resolveLeadSaq($data) === null && (filled($data['assigned_saq_email']) || filled($data['assigned_saq_name']))) {
            $warnings[] = 'Assigned SAQ Specialist was not found and will be set to null.';
        }

        $siteMatches = $this->siteMatches($data)
            ->map(fn (SiteProfile $site): array => [
                'id' => $site->id,
                'name' => $site->name,
                'city' => $site->address?->city,
            ])
            ->values()
            ->all();

        return [
            'row_number' => (int) ($row['row_number'] ?? 0),
            'data' => $data,
            'site_status' => match (count($siteMatches)) {
                0 => 'new',
                1 => 'matched',
                default => 'needs_review',
            },
            'site_matches' => $siteMatches,
            'pipeline_duplicate' => $this->pipelineDuplicateExists($data, $siteMatches),
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    /** @param array<string, mixed> $data */
    private function resolveLeadSaq(array $data): ?User
    {
        $email = $data['assigned_saq_email'] ?? null;
        $name = $data['assigned_saq_name'] ?? null;

        $query = User::query()
            ->whereHas('role.actionPermissions', fn ($query) => $query->whereIn('permission', [
                PipelineActionPermission::ViewAll->value,
                PipelineActionPermission::AssignSaq->value,
            ]));

        return filled($email)
            ? (clone $query)->where('email', $email)->first()
            : (filled($name) ? (clone $query)->where('full_name', $name)->first() : null);
    }

    /** @param array<string, mixed> $data */
    private function resolveSiteProfile(array $data, ?string $siteAction, ?int $siteProfileId): SiteProfile
    {
        if ($siteAction === 'use_existing' && $siteProfileId !== null) {
            return SiteProfile::findOrFail($siteProfileId);
        }

        $matches = $this->siteMatches($data);

        if ($siteAction !== 'create_new' && $matches->count() === 1) {
            return $matches->first();
        }

        $address = Address::create([
            'street' => $data['street'] ?: null,
            'barangay' => $data['barangay'] ?: null,
            'city' => $data['city'],
            'province' => $data['province'] ?: null,
        ]);

        $contact = null;

        if (filled($data['contact_name'])) {
            $contact = SiteContact::create([
                'name' => $data['contact_name'],
                'designation' => $data['contact_designation'] ?: null,
                'email' => $data['contact_email'] ?: null,
                'mobile_number' => $data['contact_mobile_number'] ?: null,
            ]);
        }

        return SiteProfile::create([
            'name' => $data['site_name'],
            'type' => $this->validSiteType($data['site_type']),
            'developer' => $data['developer'] ?: null,
            'total_unit_count' => $data['total_unit_count'] ?: null,
            'address_id' => $address->id,
            'contact_id' => $contact?->id,
        ]);
    }

    /** @param array<string, mixed> $data */
    private function siteMatches(array $data): EloquentCollection
    {
        if (filled($data['site_profile_id'])) {
            return SiteProfile::query()
                ->with('address')
                ->whereKey($data['site_profile_id'])
                ->get();
        }

        $siteName = $this->normalizeText($data['site_name'] ?? '');
        $city = $this->normalizeText($data['city'] ?? '');
        $street = $this->normalizeText($data['street'] ?? '');
        $barangay = $this->normalizeText($data['barangay'] ?? '');

        return SiteProfile::query()
            ->with('address')
            ->get()
            ->filter(function (SiteProfile $site) use ($siteName, $city, $street, $barangay): bool {
                if ($this->normalizeText($site->name) !== $siteName) {
                    return false;
                }

                $siteCity = $this->normalizeText($site->address?->city ?? '');
                $siteStreet = $this->normalizeText($site->address?->street ?? '');
                $siteBarangay = $this->normalizeText($site->address?->barangay ?? '');

                return ($city !== '' && $siteCity === $city)
                    || ($street !== '' && $barangay !== '' && $siteStreet === $street && $siteBarangay === $barangay && $siteCity === $city);
            });
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, array{id: int}>  $siteMatches
     */
    private function pipelineDuplicateExists(array $data, array $siteMatches): bool
    {
        if (filled($data['pipeline_id']) && Pipeline::where('pipeline_id', $data['pipeline_id'])->exists()) {
            return true;
        }

        $pipelineName = $data['pipeline_name'] ?: $data['name'] ?: $data['site_name'];
        $normalizedName = LeadName::normalize($pipelineName);

        if ($normalizedName === null) {
            return false;
        }

        return Pipeline::where('normalized_name', $normalizedName)
            ->whereNotIn('status', [PipelineStatus::Inactive->value, PipelineStatus::Completed->value])
            ->exists();
    }

    /** @param array<string, mixed> $data */
    private function surveyPayload(array $data): array
    {
        return collect($data)->only([
            'floors_surveyed',
            'actual_unit_count',
            'has_roof_access',
            'equipment_notes',
            'remarks',
            'building_network_system',
            'indoor_outlet_capacity',
            'isp_count_per_unit',
            'telco_room_location',
            'electrical_room_location',
            'lobby_booth_allowed',
            'lobby_standee_allowed',
            'lobby_flyers_stand_allowed',
            'roaming_sales_rep_allowed',
            'telco_room_fee',
            'marketing_paraphernalia',
            'brochure_posting_location',
        ])->filter(fn (mixed $value): bool => $value !== '')->all();
    }

    /** @param array<string, mixed> $row */
    private function normalizeRow(array $row): array
    {
        $data = [];

        foreach ($row as $key => $value) {
            $data[$this->normalizeKey((string) $key)] = is_string($value) ? trim($value) : $value;
        }

        foreach ([
            'pipeline_id', 'pipeline_name', 'name', 'endorsement_date', 'assigned_saq_email', 'assigned_saq_name',
            'site_profile_id', 'site_name', 'site_type', 'developer', 'total_unit_count', 'street', 'barangay',
            'city', 'province', 'contact_name', 'contact_designation', 'contact_email', 'contact_mobile_number',
            'floors_surveyed', 'actual_unit_count', 'has_roof_access', 'equipment_notes', 'remarks',
            'building_network_system', 'indoor_outlet_capacity', 'isp_count_per_unit', 'telco_room_location',
            'electrical_room_location', 'lobby_booth_allowed', 'lobby_standee_allowed', 'lobby_flyers_stand_allowed',
            'roaming_sales_rep_allowed', 'telco_room_fee', 'marketing_paraphernalia', 'brochure_posting_location',
        ] as $key) {
            $data[$key] ??= '';
        }

        return $data;
    }

    private function normalizeKey(string $key): string
    {
        return str($key)->lower()->replaceMatches('/[^a-z0-9]+/', '_')->trim('_')->toString();
    }

    private function normalizeText(string $value): string
    {
        return str($value)->lower()->replaceMatches('/[^a-z0-9]+/', ' ')->squish()->toString();
    }

    private function validSiteType(string $value): string
    {
        $values = array_column(SiteProfileType::cases(), 'value');

        return in_array($value, $values, true) ? $value : $values[0];
    }

    private function nextPipelineId(): string
    {
        $year = now()->year;
        $lastPipeline = Pipeline::where('pipeline_id', 'like', "PL-{$year}-%")
            ->lockForUpdate()
            ->orderByDesc('pipeline_id')
            ->first();

        $sequence = $lastPipeline
            ? ((int) substr($lastPipeline->pipeline_id, strrpos($lastPipeline->pipeline_id, '-') + 1)) + 1
            : 1;

        return sprintf('PL-%d-%04d', $year, $sequence);
    }
}
