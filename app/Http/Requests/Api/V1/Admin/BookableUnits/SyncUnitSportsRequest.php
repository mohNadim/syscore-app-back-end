<?php

namespace App\Http\Requests\Api\V1\Admin\BookableUnits;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SyncUnitSportsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'sport_ids' => ['bail', 'present', 'array'],
            'sport_ids.*' => ['bail', 'integer', 'distinct', 'exists:sports,id'],
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'sport_ids.present' => __('admin_facilities.validation.sport_ids_required'),
            'sport_ids.array' => __('admin_facilities.validation.sport_ids_invalid'),
            'sport_ids.*.integer' => __('admin_facilities.validation.sport_id_invalid'),
            'sport_ids.*.distinct' => __('admin_facilities.validation.sport_id_invalid'),
            'sport_ids.*.exists' => __('admin_facilities.validation.sport_id_invalid'),
        ];
    }
}
