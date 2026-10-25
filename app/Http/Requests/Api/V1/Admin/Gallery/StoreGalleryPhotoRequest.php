<?php

namespace App\Http\Requests\Api\V1\Admin\Gallery;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreGalleryPhotoRequest extends FormRequest
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
            'image_url' => ['bail', 'required', 'string', 'url:http,https', 'max:500'],
            'caption_ar' => ['bail', 'nullable', 'string', 'max:255'],
            'caption_en' => ['bail', 'nullable', 'string', 'max:255'],
            'sort_order' => ['bail', 'sometimes', 'integer', 'between:0,65535'],
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
            'image_url.required' => __('admin_gallery.validation.image_url_required'),
            'image_url.string' => __('admin_gallery.validation.image_url_invalid'),
            'image_url.url' => __('admin_gallery.validation.image_url_invalid'),
            'image_url.max' => __('admin_gallery.validation.image_url_invalid'),
            'caption_ar.string' => __('admin_gallery.validation.caption_ar_invalid'),
            'caption_ar.max' => __('admin_gallery.validation.caption_ar_invalid'),
            'caption_en.string' => __('admin_gallery.validation.caption_en_invalid'),
            'caption_en.max' => __('admin_gallery.validation.caption_en_invalid'),
            'sort_order.integer' => __('admin_gallery.validation.sort_order_invalid'),
            'sort_order.between' => __('admin_gallery.validation.sort_order_invalid'),
        ];
    }
}
