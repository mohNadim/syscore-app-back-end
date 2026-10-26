<?php

namespace App\Http\Requests\Api\V1\Admin\Announcements;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAnnouncementRequest extends FormRequest
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
            'audience' => ['bail', 'required', Rule::in(['all', 'academy'])],
            'academy_id' => ['bail', Rule::requiredIf($this->input('audience') === 'academy'), 'nullable', 'integer', 'exists:academies,id'],
            'title_ar' => ['bail', 'required', 'string', 'max:200'],
            'title_en' => ['bail', 'nullable', 'string', 'max:200'],
            'body_ar' => ['bail', 'required', 'string', 'max:5000'],
            'body_en' => ['bail', 'nullable', 'string', 'max:5000'],
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
            'audience.required' => __('admin_announcements.validation.audience_required'),
            'audience.in' => __('admin_announcements.validation.audience_invalid'),
            'academy_id.required' => __('admin_announcements.validation.academy_id_required'),
            'academy_id.integer' => __('admin_announcements.validation.academy_id_invalid'),
            'academy_id.exists' => __('admin_announcements.validation.academy_id_invalid'),
            'title_ar.required' => __('admin_announcements.validation.title_ar_required'),
            'title_ar.string' => __('admin_announcements.validation.title_ar_invalid'),
            'title_ar.max' => __('admin_announcements.validation.title_ar_invalid'),
            'title_en.string' => __('admin_announcements.validation.title_en_invalid'),
            'title_en.max' => __('admin_announcements.validation.title_en_invalid'),
            'body_ar.required' => __('admin_announcements.validation.body_ar_required'),
            'body_ar.string' => __('admin_announcements.validation.body_ar_invalid'),
            'body_ar.max' => __('admin_announcements.validation.body_ar_invalid'),
            'body_en.string' => __('admin_announcements.validation.body_en_invalid'),
            'body_en.max' => __('admin_announcements.validation.body_en_invalid'),
        ];
    }

    /**
     * Get the announcement attributes; the academy is dropped for the all-users audience.
     *
     * @return array<string, mixed>
     */
    public function announcementAttributes(): array
    {
        $data = $this->validated();

        if ($data['audience'] === 'all') {
            $data['academy_id'] = null;
        }

        return $data;
    }
}
