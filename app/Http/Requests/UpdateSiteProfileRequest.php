<?php

namespace App\Http\Requests;

use App\Enums\SiteProfileType;
use App\Models\SiteProfile;
use App\Support\LeadName;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateSiteProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'name' => [
                'required', 'string', 'max:255',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $normalized = LeadName::normalize($value);
                    $city = $this->input('city');

                    $duplicate = SiteProfile::query()
                        ->where('normalized_name', $normalized)
                        ->where('id', '!=', $this->route('siteProfile')->id)
                        ->whereHas('address', fn ($query) => $query->where('city', $city))
                        ->exists();

                    if ($duplicate) {
                        $fail('A site with this name already exists in the same city.');
                    }
                },
            ],
            'type' => ['required', new Enum(SiteProfileType::class)],
            'type_other' => ['nullable', 'required_if:type,'.SiteProfileType::Others->value, 'string', 'max:255'],
            'developer' => ['nullable', 'string', 'max:255'],
            'building_operation_date' => ['nullable', 'date'],
            'total_building_count' => ['nullable', 'integer', 'min:0'],
            'total_floors' => ['nullable', 'integer', 'min:0'],
            'total_floors_per_tower' => ['nullable', 'integer', 'min:0'],
            'total_unit_count' => ['nullable', 'integer', 'min:0', 'max:2147483647'],
            'total_parking_floors' => ['nullable', 'integer', 'min:0'],
            'residential_floor_starts_at' => ['nullable', 'integer', 'min:0'],
            'floors_not_in_plan' => ['nullable', 'string', 'max:255'],
            'amenities' => ['nullable', 'string'],
            'total_commercial_unit_count' => ['nullable', 'integer', 'min:0'],
            'residential_occupancy_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'total_units_per_floor' => ['nullable', 'integer', 'min:0'],
            'room_types' => ['nullable', 'string'],
            'turnover_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'occupancy_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'whz_location' => ['nullable', 'string', 'max:255'],
            'total_whz_allocation' => ['nullable', 'integer', 'min:0'],
            'wifi_area_location' => ['nullable', 'string', 'max:255'],
            'street' => ['nullable', 'string', 'max:255'],
            'barangay' => ['nullable', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:255'],
            'province' => ['nullable', 'string', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:255', 'regex:/^[\p{L}\s\-\'.]+$/u'],
            'contact_designation' => ['nullable', 'string', 'max:255'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_mobile_number' => ['nullable', 'string', 'regex:/^(09\d{9}|\+639\d{9})$/'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'contact_name.regex' => 'Contact name may only contain letters, spaces, hyphens, apostrophes, and periods.',
            'contact_mobile_number.regex' => 'Enter a valid mobile number (e.g. 09171234567 or +639171234567).',
        ];
    }
}
