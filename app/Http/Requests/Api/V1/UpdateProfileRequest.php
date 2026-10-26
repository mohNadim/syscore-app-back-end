<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
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
            'full_name' => ['bail', 'sometimes', 'required', 'string', 'min:2', 'max:150'],
            'preferred_lang' => ['sometimes', 'required', Rule::in(['ar', 'en'])],
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
            'full_name.required' => __('profile.validation.full_name_required'),
            'full_name.string' => __('profile.validation.full_name_invalid'),
            'full_name.min' => __('profile.validation.full_name_length'),
            'full_name.max' => __('profile.validation.full_name_length'),
            'preferred_lang.required' => __('profile.validation.preferred_lang_invalid'),
            'preferred_lang.in' => __('profile.validation.preferred_lang_invalid'),
        ];
    }
}
