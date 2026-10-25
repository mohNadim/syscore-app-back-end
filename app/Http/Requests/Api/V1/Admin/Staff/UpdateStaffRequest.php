<?php

namespace App\Http\Requests\Api\V1\Admin\Staff;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class UpdateStaffRequest extends StoreStaffRequest
{
    /**
     * Get the validation rules that apply to the request; every field is optional on update.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = collect(parent::rules())
            ->map(fn (array $rules, string $field) => $field === 'is_active' || $field === 'phone' ? $rules : ['sometimes', ...$rules])
            ->all();

        $rules['username'] = ['bail', 'sometimes', 'required', 'string', 'min:3', 'max:100', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique('staff', 'username')->ignore($this->route('staff'))];

        return $rules;
    }
}
