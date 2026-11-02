<?php

namespace App\Http\Requests;

use App\Enums\ProviderStatus;
use App\Models\NetworkSiteAssignment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreNetworkFeasibilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'isp_name' => [
                'required', 'string', 'max:255',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $networkSiteAssignment = $this->route('networkSiteAssignment');

                    if (! $networkSiteAssignment instanceof NetworkSiteAssignment) {
                        return;
                    }

                    $duplicate = $networkSiteAssignment->siteSurvey->networkFeasibilities()
                        ->whereRaw('LOWER(isp_name) = ?', [mb_strtolower(trim($value))])
                        ->when(
                            $this->route('networkFeasibility'),
                            fn ($query, $networkFeasibility) => $query->where('id', '!=', $networkFeasibility->id)
                        )
                        ->exists();

                    if ($duplicate) {
                        $fail('This ISP already exists for this network site assignment.');
                    }
                },
            ],
            'contact_name' => ['nullable', 'string', 'max:255', 'regex:/^[\p{L}\s\-\'.]+$/u'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'status' => ['required', new Enum(ProviderStatus::class)],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'contact_name.regex' => 'Contact name may only contain letters, spaces, hyphens, apostrophes, and periods.',
        ];
    }
}
