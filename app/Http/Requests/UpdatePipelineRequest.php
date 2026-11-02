<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePipelineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<string>> */
    public function rules(): array
    {
        // Registered By (project_manager_id) and the Site Profile selection (site_profile_id)
        // are deliberately absent: the former is stamped with the creating user at store time
        // , and a pipeline's site is fixed at creation — re-pointing it would rewrite the
        // deal's identity.
        return [
            'name' => ['required', 'string', 'max:255'],
            'endorsement_date' => ['nullable', 'date', 'after_or_equal:2019-01-01', 'before_or_equal:today'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'endorsement_date.after_or_equal' => 'Endorsement date cannot be earlier than January 1, 2019.',
            'endorsement_date.before_or_equal' => 'Endorsement date cannot be later than today.',
        ];
    }
}
