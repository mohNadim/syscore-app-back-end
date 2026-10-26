<?php

namespace App\Http\Requests\Api\V1\Admin\Spaces;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSpaceRequest extends FormRequest
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
            'code' => ['bail', 'required', 'string', 'max:20', 'regex:/^[A-Z0-9_]+$/', Rule::unique('spaces', 'code')->where('facility_id', $this->route('facility')->id)],
            'name_ar' => ['bail', 'required', 'string', 'max:100'],
            'name_en' => ['bail', 'nullable', 'string', 'max:100'],
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
            'code.required' => __('admin_facilities.validation.space_code_invalid'),
            'code.string' => __('admin_facilities.validation.space_code_invalid'),
            'code.max' => __('admin_facilities.validation.space_code_invalid'),
            'code.regex' => __('admin_facilities.validation.space_code_invalid'),
            'code.unique' => __('admin_facilities.validation.space_code_taken'),
            'name_ar.required' => __('admin_facilities.validation.name_ar_required'),
            'name_ar.string' => __('admin_facilities.validation.name_ar_invalid'),
            'name_ar.max' => __('admin_facilities.validation.name_ar_invalid'),
            'name_en.string' => __('admin_facilities.validation.name_en_invalid'),
            'name_en.max' => __('admin_facilities.validation.name_en_invalid'),
        ];
    }
}
