<?php

namespace App\Http\Requests\Api\V1\Admin\Gallery;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class IndexGalleryAlbumRequest extends FormRequest
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
            'per_page.integer' => __('admin_gallery.validation.per_page_invalid'),
            'per_page.min' => __('admin_gallery.validation.per_page_invalid'),
            'per_page.max' => __('admin_gallery.validation.per_page_invalid'),
        ];
    }
}
