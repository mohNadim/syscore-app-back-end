<?php

namespace App\Http\Requests\Api\V1\Admin\Roles;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class UpdateRoleRequest extends StoreRoleRequest
{
    /**
     * Get the validation rules that apply to the request; every field is optional on update.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = collect(parent::rules())
            ->map(fn (array $rules, string $field) => str_ends_with($field, '.*') ? $rules : ['sometimes', ...array_filter($rules, fn ($rule) => $rule !== 'present')])
            ->all();

        $rules['name_ar'] = ['bail', 'sometimes', 'required', 'string', 'max:100', Rule::unique('roles', 'name_ar')->ignore($this->route('role'))];
        $rules['permission_ids'] = ['bail', 'sometimes', 'array'];

        return $rules;
    }
}
