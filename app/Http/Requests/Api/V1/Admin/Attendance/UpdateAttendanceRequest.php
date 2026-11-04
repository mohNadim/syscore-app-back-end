<?php

namespace App\Http\Requests\Api\V1\Admin\Attendance;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAttendanceRequest extends FormRequest
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
            'records' => ['bail', 'required', 'array', 'min:1', 'max:200'],
            'records.*.child_id' => ['bail', 'required', 'integer', 'distinct'],
            'records.*.status' => ['bail', 'required', 'in:present,absent,excused'],
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
            'records.required' => __('admin_attendance.validation.records_required'),
            'records.array' => __('admin_attendance.validation.records_invalid'),
            'records.min' => __('admin_attendance.validation.records_invalid'),
            'records.max' => __('admin_attendance.validation.records_invalid'),
            'records.*.child_id.required' => __('admin_attendance.validation.child_id_required'),
            'records.*.child_id.integer' => __('admin_attendance.validation.child_id_invalid'),
            'records.*.child_id.distinct' => __('admin_attendance.validation.child_id_duplicate'),
            'records.*.status.required' => __('admin_attendance.validation.status_required'),
            'records.*.status.in' => __('admin_attendance.validation.status_invalid'),
        ];
    }
}
