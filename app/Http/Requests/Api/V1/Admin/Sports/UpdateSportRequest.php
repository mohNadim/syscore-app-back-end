<?php

namespace App\Http\Requests\Api\V1\Admin\Sports;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class UpdateSportRequest extends StoreSportRequest
{
    /**
     * Get the validation rules that apply to the request; every field is optional on update and the current sport is ignored by the unique check.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = collect(parent::rules())
            ->map(fn (array $rules) => ['bail', 'sometimes', ...array_values(array_filter($rules, fn ($rule) => ! in_array($rule, ['bail', 'sometimes'], true)))])
            ->all();

        $rules['name_ar'] = ['bail', 'sometimes', 'string', 'max:100', Rule::unique('sports', 'name_ar')->ignore($this->route('sport'))];

        return $rules;
    }
}
