<?php

namespace App\Http\Requests;

use App\Enums\SiteProfileType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePipelineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'endorsement_date' => ['nullable', 'date', 'after_or_equal:2019-01-01', 'before_or_equal:today'],

            // Re-attempt: link to an existing site profile
            'site_profile_id' => ['nullable', 'integer', 'exists:site_profiles,id'],

            // New site fields (required when not linking to existing)
            'site_name' => ['required_without:site_profile_id', 'nullable', 'string', 'max:255'],
            'site_type' => ['required_without:site_profile_id', 'nullable', Rule::in(SiteProfileType::selectable())],
            'site_type_other' => ['nullable', 'required_if:site_type,'.SiteProfileType::Others->value, 'string', 'max:255'],
            'city' => ['required_without:site_profile_id', 'nullable', 'string', 'max:255'],

            // Optional site fields
            'developer' => ['nullable', 'string', 'max:255'],
            'total_unit_count' => ['nullable', 'integer', 'min:0', 'max:2147483647'],
            'total_floors_per_tower' => ['nullable', 'integer', 'min:0'],
            'total_commercial_unit_count' => ['nullable', 'integer', 'min:0', 'max:2147483647'],
            'total_building_count' => ['nullable', 'integer', 'min:0', 'max:2147483647'],
            'residential_floor_starts_at' => ['nullable', 'integer', 'min:0', 'max:2147483647'],
            'floors_not_in_plan' => ['nullable', 'string', 'max:255'],
            'turnover_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'occupancy_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'whz_location' => ['nullable', 'string', 'max:255'],
            'total_whz_allocation' => ['nullable', 'integer', 'min:0'],
            'wifi_area_location' => ['nullable', 'string', 'max:255'],
            'barangay' => ['nullable', 'string', 'max:255'],
            'province' => ['nullable', 'string', 'max:255'],
            'street' => ['nullable', 'string', 'max:255'],

            // Optional contact fields
            'contact_id' => ['nullable', 'integer', 'exists:site_contacts,id'],
            'contact_name' => ['nullable', 'string', 'max:255', 'regex:/^[\p{L}\s\-\'.]+$/u'],
            'contact_designation' => ['nullable', 'string', 'max:255'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_mobile_number' => ['nullable', 'string', 'regex:/^(09\d{9}|\+639\d{9})$/'],

            // Optional initial survey marketing fields
            'lobby_booth_allowed' => ['nullable', 'boolean'],
            'lobby_standee_allowed' => ['nullable', 'boolean'],
            'lobby_flyers_stand_allowed' => ['nullable', 'boolean'],
            'roaming_sales_rep_allowed' => ['nullable', 'boolean'],
            'marketing_paraphernalia' => ['nullable', 'string', 'max:2000'],
            'brochure_posting_location' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'endorsement_date.after_or_equal' => 'Endorsement date cannot be earlier than January 1, 2019.',
            'endorsement_date.before_or_equal' => 'Endorsement date cannot be later than today.',
            'contact_name.regex' => 'Contact name may only contain letters, spaces, hyphens, apostrophes, and periods.',
            'contact_mobile_number.regex' => 'Enter a valid mobile number (e.g. 09171234567 or +639171234567).',
        ];
    }
}
