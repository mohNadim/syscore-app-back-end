<?php

namespace App\Http\Requests\Api\V1\Admin\Gallery;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreGalleryAlbumRequest extends FormRequest
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
            'title_ar' => ['bail', 'required', 'string', 'max:200'],
            'title_en' => ['bail', 'nullable', 'string', 'max:200'],
            'sort_order' => ['bail', 'sometimes', 'integer', 'between:0,65535'],
            'is_published' => ['bail', 'sometimes', 'boolean'],
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
            'title_ar.required' => __('admin_gallery.validation.title_ar_required'),
            'title_ar.string' => __('admin_gallery.validation.title_ar_invalid'),
            'title_ar.max' => __('admin_gallery.validation.title_ar_invalid'),
            'title_en.string' => __('admin_gallery.validation.title_en_invalid'),
            'title_en.max' => __('admin_gallery.validation.title_en_invalid'),
            'sort_order.integer' => __('admin_gallery.validation.sort_order_invalid'),
            'sort_order.between' => __('admin_gallery.validation.sort_order_invalid'),
            'is_published.boolean' => __('admin_gallery.validation.is_published_invalid'),
        ];
    }
}
