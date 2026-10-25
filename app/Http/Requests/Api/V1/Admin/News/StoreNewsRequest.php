<?php

namespace App\Http\Requests\Api\V1\Admin\News;

use App\Services\DisplayTimezone;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreNewsRequest extends FormRequest
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
            'title_en' => ['bail', 'required', 'string', 'max:200'],
            'body_ar' => ['bail', 'required', 'string', 'max:20000'],
            'body_en' => ['bail', 'required', 'string', 'max:20000'],
            'cover_url' => ['bail', 'nullable', 'url:http,https', 'max:500'],
            'is_published' => ['bail', 'sometimes', 'boolean'],
            'published_at' => ['bail', 'nullable', 'date'],
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
            'title_ar.required' => __('admin_news.validation.title_ar_required'),
            'title_ar.string' => __('admin_news.validation.title_ar_invalid'),
            'title_ar.max' => __('admin_news.validation.title_ar_invalid'),
            'title_en.required' => __('admin_news.validation.title_en_required'),
            'title_en.string' => __('admin_news.validation.title_en_invalid'),
            'title_en.max' => __('admin_news.validation.title_en_invalid'),
            'body_ar.required' => __('admin_news.validation.body_ar_required'),
            'body_ar.string' => __('admin_news.validation.body_ar_invalid'),
            'body_ar.max' => __('admin_news.validation.body_ar_invalid'),
            'body_en.required' => __('admin_news.validation.body_en_required'),
            'body_en.string' => __('admin_news.validation.body_en_invalid'),
            'body_en.max' => __('admin_news.validation.body_en_invalid'),
            'cover_url.url' => __('admin_news.validation.cover_url_invalid'),
            'cover_url.max' => __('admin_news.validation.cover_url_invalid'),
            'is_published.boolean' => __('admin_news.validation.is_published_invalid'),
            'published_at.date' => __('admin_news.validation.published_at_invalid'),
        ];
    }

    /**
     * Get the validated article attributes with published_at converted to UTC; naive values use the display time zone.
     *
     * @return array<string, mixed>
     */
    public function newsAttributes(): array
    {
        $data = $this->validated();

        if (isset($data['published_at'])) {
            $data['published_at'] = CarbonImmutable::parse($data['published_at'], app(DisplayTimezone::class)->name())->utc();
        }

        return $data;
    }
}
