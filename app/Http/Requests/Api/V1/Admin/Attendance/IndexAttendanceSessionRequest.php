<?php

namespace App\Http\Requests\Api\V1\Admin\Attendance;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class IndexAttendanceSessionRequest extends FormRequest
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
            'date' => ['bail', 'required', 'date_format:Y-m-d'],
            'group_id' => ['bail', 'sometimes', 'integer'],
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
            'date.required' => __('admin_attendance.validation.date_required'),
            'date.date_format' => __('admin_attendance.validation.date_invalid'),
            'group_id.integer' => __('admin_attendance.validation.group_id_invalid'),
        ];
    }
}
