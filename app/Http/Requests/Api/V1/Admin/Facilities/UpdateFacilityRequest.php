<?php

namespace App\Http\Requests\Api\V1\Admin\Facilities;

use Illuminate\Contracts\Validation\ValidationRule;

class UpdateFacilityRequest extends StoreFacilityRequest
{
    /**
     * Get the validation rules that apply to the request; every field is optional on update.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return collect(parent::rules())
            ->map(fn (array $rules) => ['bail', 'sometimes', ...array_values(array_filter($rules, fn ($rule) => ! in_array($rule, ['bail', 'sometimes'], true)))])
            ->all();
    }
}
