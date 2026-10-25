<?php

namespace App\Http\Requests\Api\V1\Admin\Users;

use App\Http\Requests\Api\V1\Children\UpdateChildRequest;
use Illuminate\Contracts\Validation\ValidationRule;

class UpdateUserChildRequest extends UpdateChildRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules: the user-facing child rules plus the active flag.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'is_active' => ['bail', 'sometimes', 'required', 'boolean'],
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
            ...parent::messages(),
            'is_active.required' => __('admin_users.validation.is_active_invalid'),
            'is_active.boolean' => __('admin_users.validation.is_active_invalid'),
        ];
    }
}
