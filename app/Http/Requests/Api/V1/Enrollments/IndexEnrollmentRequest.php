<?php

namespace App\Http\Requests\Api\V1\Enrollments;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class IndexEnrollmentRequest extends FormRequest
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
            'child_id' => ['bail', 'sometimes', 'integer'],
            'status' => ['bail', 'sometimes', 'in:active,ended,cancelled'],
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
            'child_id.integer' => __('enrollments.validation.child_id_invalid'),
            'status.in' => __('enrollments.validation.status_invalid'),
        ];
    }
}
