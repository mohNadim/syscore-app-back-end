<?php

namespace App\Http\Requests\Api\V1\Admin\Discounts;

use App\Models\Discount;
use App\Services\DisplayTimezone;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreDiscountRequest extends FormRequest
{
    /**
     * The maximum value of a fixed discount in SYP.
     */
    public const MAX_FIXED_VALUE = 100000000;

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
     * @todo notify_users only stores the flag; sending the announcement notification is phase 13.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name_ar' => ['bail', 'required', 'string', 'max:150'],
            'name_en' => ['bail', 'nullable', 'string', 'max:150'],
            'type' => ['bail', 'required', 'in:percent,fixed'],
            'value' => ['bail', 'required', 'integer', 'min:1'],
            'target' => ['bail', 'sometimes', 'in:booking,subscription,all'],
            'starts_at' => ['bail', 'required', 'date_format:Y-m-d H:i'],
            'ends_at' => ['bail', 'required', 'date_format:Y-m-d H:i', 'after:starts_at'],
            'time_from' => ['bail', 'nullable', 'required_with:time_to', 'date_format:H:i'],
            'time_to' => ['bail', 'nullable', 'required_with:time_from', 'date_format:H:i', 'after:time_from'],
            'weekdays' => ['bail', 'nullable', 'array'],
            'weekdays.*' => ['bail', 'integer', 'between:0,6', 'distinct'],
            'is_active' => ['bail', 'sometimes', 'boolean'],
            'notify_users' => ['bail', 'sometimes', 'boolean'],
            'category_ids' => ['bail', 'nullable', 'array'],
            'category_ids.*' => ['bail', 'integer', 'distinct', 'exists:user_categories,id'],
            'sport_ids' => ['bail', 'nullable', 'array'],
            'sport_ids.*' => ['bail', 'integer', 'distinct', 'exists:sports,id'],
            'academy_ids' => ['bail', 'nullable', 'array'],
            'academy_ids.*' => ['bail', 'integer', 'distinct', 'exists:academies,id'],
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        $messages = [
            'name_ar.required' => __('admin_discounts.validation.name_ar_required'),
            'name_ar.string' => __('admin_discounts.validation.name_ar_invalid'),
            'name_ar.max' => __('admin_discounts.validation.name_ar_invalid'),
            'name_en.string' => __('admin_discounts.validation.name_en_invalid'),
            'name_en.max' => __('admin_discounts.validation.name_en_invalid'),
            'type.required' => __('admin_discounts.validation.type_invalid'),
            'type.in' => __('admin_discounts.validation.type_invalid'),
            'value.required' => __('admin_discounts.validation.value_invalid'),
            'value.integer' => __('admin_discounts.validation.value_invalid'),
            'value.min' => __('admin_discounts.validation.value_invalid'),
            'target.in' => __('admin_discounts.validation.target_invalid'),
            'starts_at.required' => __('admin_discounts.validation.starts_at_invalid'),
            'starts_at.date_format' => __('admin_discounts.validation.starts_at_invalid'),
            'ends_at.required' => __('admin_discounts.validation.ends_at_invalid'),
            'ends_at.date_format' => __('admin_discounts.validation.ends_at_invalid'),
            'ends_at.after' => __('admin_discounts.validation.ends_after_starts'),
            'time_from.required_with' => __('admin_discounts.validation.time_window_incomplete'),
            'time_from.date_format' => __('admin_discounts.validation.time_from_invalid'),
            'time_to.required_with' => __('admin_discounts.validation.time_window_incomplete'),
            'time_to.date_format' => __('admin_discounts.validation.time_to_invalid'),
            'time_to.after' => __('admin_discounts.validation.time_to_after_from'),
            'weekdays.array' => __('admin_discounts.validation.weekdays_invalid'),
            'weekdays.*.integer' => __('admin_discounts.validation.weekdays_invalid'),
            'weekdays.*.between' => __('admin_discounts.validation.weekdays_invalid'),
            'weekdays.*.distinct' => __('admin_discounts.validation.weekdays_invalid'),
            'is_active.boolean' => __('admin_discounts.validation.is_active_invalid'),
            'notify_users.boolean' => __('admin_discounts.validation.notify_users_invalid'),
        ];

        foreach (['category' => 'categories', 'sport' => 'sports', 'academy' => 'academies'] as $singular => $plural) {
            $messages["{$singular}_ids.array"] = __("admin_discounts.validation.{$singular}_ids_invalid");
            $messages["{$singular}_ids.*.integer"] = __("admin_discounts.validation.{$singular}_ids_invalid");
            $messages["{$singular}_ids.*.distinct"] = __("admin_discounts.validation.{$singular}_ids_invalid");
            $messages["{$singular}_ids.*.exists"] = __("admin_discounts.validation.{$singular}_ids_invalid");
        }

        return $messages;
    }

    /**
     * Get the validation callbacks that run once the base rules have run.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $errors = $validator->errors();

            if ($errors->has('value') || $errors->has('type')) {
                return;
            }

            $type = $this->effectiveType();
            $value = $this->has('value') ? $this->input('value') : $this->currentDiscount()?->value;

            if ($type === 'percent' && $value > 100) {
                $errors->add('value', __('admin_discounts.validation.value_percent_range'));
            } elseif ($type === 'fixed' && $value > self::MAX_FIXED_VALUE) {
                $errors->add('value', __('admin_discounts.validation.value_fixed_range'));
            }
        }];
    }

    /**
     * The discount being updated, or null when creating one.
     */
    protected function currentDiscount(): ?Discount
    {
        return null;
    }

    /**
     * The discount type after this request is applied: the sent value, else the stored one.
     */
    protected function effectiveType(): ?string
    {
        return $this->has('type') ? $this->input('type') : $this->currentDiscount()?->type;
    }

    /**
     * Get the validated discount columns with local datetimes converted to UTC and the weekdays folded into the bitmask.
     *
     * @return array<string, mixed>
     */
    public function discountAttributes(): array
    {
        $timezone = app(DisplayTimezone::class);
        $data = collect($this->validated())->except(['category_ids', 'sport_ids', 'academy_ids'])->all();

        foreach (['starts_at', 'ends_at'] as $field) {
            if (isset($data[$field])) {
                $data[$field] = $timezone->localToUtc($data[$field]);
            }
        }

        if (array_key_exists('weekdays', $data)) {
            $data['weekdays'] = Discount::weekdaysToMask($data['weekdays']);
        }

        return $data;
    }

    /**
     * The pivot id lists that were sent, keyed by relation name; a null list clears the relation.
     *
     * @return array<string, list<int>>
     */
    public function relationIds(): array
    {
        $ids = [];

        foreach (['category_ids' => 'categories', 'sport_ids' => 'sports', 'academy_ids' => 'academies'] as $key => $relation) {
            if ($this->has($key)) {
                $ids[$relation] = array_map('intval', $this->validated($key) ?? []);
            }
        }

        return $ids;
    }
}
