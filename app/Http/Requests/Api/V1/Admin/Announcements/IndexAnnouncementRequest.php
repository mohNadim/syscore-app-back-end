<?php

namespace App\Http\Requests\Api\V1\Admin\Announcements;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexAnnouncementRequest extends FormRequest
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
            'audience' => ['bail', 'sometimes', Rule::in(['all', 'academy', 'category', 'users'])],
            'academy_id' => ['bail', 'sometimes', 'integer', 'min:1'],
            'user_category_id' => ['bail', 'sometimes', 'integer', 'min:1'],
            'per_page' => ['bail', 'sometimes', 'integer', 'min:1', 'max:50'],
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
            'audience.in' => __('admin_announcements.validation.audience_invalid'),
            'academy_id.integer' => __('admin_announcements.validation.academy_id_filter_invalid'),
            'academy_id.min' => __('admin_announcements.validation.academy_id_filter_invalid'),
            'user_category_id.integer' => __('admin_announcements.validation.user_category_id_filter_invalid'),
            'user_category_id.min' => __('admin_announcements.validation.user_category_id_filter_invalid'),
            'per_page.integer' => __('admin_announcements.validation.per_page_invalid'),
            'per_page.min' => __('admin_announcements.validation.per_page_invalid'),
            'per_page.max' => __('admin_announcements.validation.per_page_invalid'),
        ];
    }
}
