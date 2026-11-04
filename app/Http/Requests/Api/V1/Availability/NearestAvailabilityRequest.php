<?php

namespace App\Http\Requests\Api\V1\Availability;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class NearestAvailabilityRequest extends FormRequest
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
            'facility_id' => ['bail', 'nullable', 'integer', 'exists:facilities,id'],
            'sport_id' => ['bail', 'nullable', 'integer', 'exists:sports,id'],
            'limit' => ['bail', 'nullable', 'integer', 'min:1', 'max:50'],
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
            'facility_id.integer' => __('availability.validation.facility_id_invalid'),
            'facility_id.exists' => __('availability.validation.facility_id_invalid'),
            'sport_id.integer' => __('availability.validation.sport_id_invalid'),
            'sport_id.exists' => __('availability.validation.sport_id_invalid'),
            'limit.integer' => __('availability.validation.limit_invalid'),
            'limit.min' => __('availability.validation.limit_invalid'),
            'limit.max' => __('availability.validation.limit_invalid'),
        ];
    }

    /**
     * The number of units to return, defaulting to 10.
     */
    public function limit(): int
    {
        return $this->filled('limit') ? $this->integer('limit') : 10;
    }
}
