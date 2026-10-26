<?php

namespace App\Http\Requests\Api\V1\Admin\Closures;

use App\Services\DisplayTimezone;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreClosureRequest extends FormRequest
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
            'facility_id' => ['bail', 'required_without:space_id', 'prohibits:space_id', 'nullable', 'integer', 'exists:facilities,id'],
            'space_id' => ['bail', 'required_without:facility_id', 'nullable', 'integer', 'exists:spaces,id'],
            'starts_at' => ['bail', 'required', 'date_format:Y-m-d H:i'],
            'ends_at' => ['bail', 'required', 'date_format:Y-m-d H:i', 'after:starts_at'],
            'reason_ar' => ['bail', 'nullable', 'string', 'max:255'],
            'reason_en' => ['bail', 'nullable', 'string', 'max:255'],
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
            'facility_id.required_without' => __('admin_hours.validation.target_required'),
            'facility_id.prohibits' => __('admin_hours.validation.target_exclusive'),
            'facility_id.integer' => __('admin_hours.validation.facility_id_invalid'),
            'facility_id.exists' => __('admin_hours.validation.facility_id_invalid'),
            'space_id.required_without' => __('admin_hours.validation.target_required'),
            'space_id.integer' => __('admin_hours.validation.space_id_invalid'),
            'space_id.exists' => __('admin_hours.validation.space_id_invalid'),
            'starts_at.required' => __('admin_hours.validation.starts_at_invalid'),
            'starts_at.date_format' => __('admin_hours.validation.starts_at_invalid'),
            'ends_at.required' => __('admin_hours.validation.ends_at_invalid'),
            'ends_at.date_format' => __('admin_hours.validation.ends_at_invalid'),
            'ends_at.after' => __('admin_hours.validation.ends_after_starts'),
            'reason_ar.string' => __('admin_hours.validation.reason_ar_invalid'),
            'reason_ar.max' => __('admin_hours.validation.reason_ar_invalid'),
            'reason_en.string' => __('admin_hours.validation.reason_en_invalid'),
            'reason_en.max' => __('admin_hours.validation.reason_en_invalid'),
        ];
    }

    /**
     * Get the validated closure attributes with the local datetimes converted to UTC.
     *
     * @return array<string, mixed>
     */
    public function closureAttributes(): array
    {
        $timezone = app(DisplayTimezone::class);
        $data = $this->validated();

        foreach (['starts_at', 'ends_at'] as $field) {
            if (isset($data[$field])) {
                $data[$field] = $timezone->localToUtc($data[$field]);
            }
        }

        return $data;
    }
}
