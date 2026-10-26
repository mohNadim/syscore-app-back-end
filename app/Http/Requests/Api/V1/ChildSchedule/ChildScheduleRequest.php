<?php

namespace App\Http\Requests\Api\V1\ChildSchedule;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ChildScheduleRequest extends FormRequest
{
    /**
     * The longest range, in days, that can be requested at once.
     */
    public const MAX_DAYS = 62;

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
            'from' => ['bail', 'sometimes', 'date_format:Y-m-d'],
            'to' => ['bail', 'sometimes', 'date_format:Y-m-d', 'after_or_equal:from'],
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
            'from.date_format' => __('child_schedule.validation.date_invalid'),
            'to.date_format' => __('child_schedule.validation.date_invalid'),
            'to.after_or_equal' => __('child_schedule.validation.to_after_from'),
        ];
    }

    /**
     * Get the validator instance's after-validation callbacks.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty() || ! $this->filled('from') || ! $this->filled('to')) {
                    return;
                }

                $days = CarbonImmutable::parse($this->string('from')->toString())->diffInDays(CarbonImmutable::parse($this->string('to')->toString()));

                if ($days > self::MAX_DAYS) {
                    $validator->errors()->add('to', __('child_schedule.validation.range_too_long', ['days' => self::MAX_DAYS]));
                }
            },
        ];
    }
}
