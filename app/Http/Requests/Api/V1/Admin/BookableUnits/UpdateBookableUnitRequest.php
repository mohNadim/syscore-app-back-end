<?php

namespace App\Http\Requests\Api\V1\Admin\BookableUnits;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class UpdateBookableUnitRequest extends StoreBookableUnitRequest
{
    /**
     * Get the validation rules that apply to the request; every field is optional on update.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = collect(parent::rules())
            ->map(fn (array $rules, string $field) => str_ends_with($field, '.*') ? $rules : ['bail', 'sometimes', ...array_values(array_filter($rules, fn ($rule) => ! in_array($rule, ['bail', 'sometimes'], true)))])
            ->all();

        $rules['code'] = ['bail', 'sometimes', 'required', 'string', 'max:20', 'regex:/^[A-Z0-9_]+$/', Rule::unique('bookable_units', 'code')->ignore($this->route('bookable_unit'))];
        $rules['space_ids'] = ['bail', 'required_with:facility_id', 'array', 'min:1'];

        return $rules;
    }

    /**
     * Get the facility id the submitted spaces must belong to: the new one if sent, otherwise the unit's current one.
     */
    protected function targetFacilityId(): ?int
    {
        return $this->filled('facility_id') ? (int) $this->input('facility_id') : $this->route('bookable_unit')->facility_id;
    }
}
