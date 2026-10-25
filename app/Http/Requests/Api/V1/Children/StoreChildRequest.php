<?php

namespace App\Http\Requests\Api\V1\Children;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreChildRequest extends FormRequest
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
            'full_name' => ['bail', 'required', 'string', 'min:2', 'max:150'],
            'birth_date' => ['bail', 'required', 'date_format:Y-m-d', 'before:today', 'after:'.now()->subYears(30)->toDateString()],
            'gender' => ['required', Rule::in(['male', 'female'])],
            'health_notes' => ['bail', 'nullable', 'string', 'max:1000'],
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
            'full_name.required' => __('children.validation.full_name_required'),
            'full_name.string' => __('children.validation.full_name_invalid'),
            'full_name.min' => __('children.validation.full_name_length'),
            'full_name.max' => __('children.validation.full_name_length'),
            'birth_date.required' => __('children.validation.birth_date_required'),
            'birth_date.date_format' => __('children.validation.birth_date_format'),
            'birth_date.before' => __('children.validation.birth_date_range'),
            'birth_date.after' => __('children.validation.birth_date_range'),
            'gender.required' => __('children.validation.gender_required'),
            'gender.in' => __('children.validation.gender_invalid'),
            'health_notes.string' => __('children.validation.health_notes_invalid'),
            'health_notes.max' => __('children.validation.health_notes_invalid'),
        ];
    }
}
