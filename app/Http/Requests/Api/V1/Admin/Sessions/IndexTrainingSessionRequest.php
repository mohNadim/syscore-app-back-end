<?php

namespace App\Http\Requests\Api\V1\Admin\Sessions;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class IndexTrainingSessionRequest extends FormRequest
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
            'academy_id' => ['bail', 'sometimes', 'integer'],
            'group_id' => ['bail', 'sometimes', 'integer'],
            'unit_id' => ['bail', 'sometimes', 'integer'],
            'coach_id' => ['bail', 'sometimes', 'integer'],
            'status' => ['bail', 'sometimes', 'in:scheduled,changed,cancelled,done'],
            'from' => ['bail', 'sometimes', 'date_format:Y-m-d'],
            'to' => ['bail', 'sometimes', 'date_format:Y-m-d', 'after_or_equal:from'],
            'per_page' => ['bail', 'sometimes', 'integer', 'min:1', 'max:100'],
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
            'academy_id.integer' => __('admin_sessions.validation.academy_id_invalid'),
            'group_id.integer' => __('admin_sessions.validation.group_id_invalid'),
            'unit_id.integer' => __('admin_sessions.validation.unit_id_invalid'),
            'coach_id.integer' => __('admin_sessions.validation.coach_id_invalid'),
            'status.in' => __('admin_sessions.validation.status_invalid'),
            'from.date_format' => __('admin_sessions.validation.date_invalid'),
            'to.date_format' => __('admin_sessions.validation.date_invalid'),
            'to.after_or_equal' => __('admin_sessions.validation.to_after_from'),
            'per_page.integer' => __('admin_sessions.validation.per_page_invalid'),
            'per_page.min' => __('admin_sessions.validation.per_page_invalid'),
            'per_page.max' => __('admin_sessions.validation.per_page_invalid'),
        ];
    }
}
