<?php

namespace App\Http\Requests\Api\V1\Admin\Closures;

use App\Models\Closure;
use App\Services\DisplayTimezone;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Validator;

class UpdateClosureRequest extends StoreClosureRequest
{
    /**
     * Get the validation rules that apply to the request; every field is optional on update.
     * The one-target rule and the order of the dates are checked against the merged values in `after()`.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'facility_id' => ['bail', 'sometimes', 'nullable', 'integer', 'exists:facilities,id'],
            'space_id' => ['bail', 'sometimes', 'nullable', 'integer', 'exists:spaces,id'],
            'starts_at' => ['bail', 'sometimes', 'required', 'date_format:Y-m-d H:i'],
            'ends_at' => ['bail', 'sometimes', 'required', 'date_format:Y-m-d H:i'],
            'reason_ar' => ['bail', 'sometimes', 'nullable', 'string', 'max:255'],
            'reason_en' => ['bail', 'sometimes', 'nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Get the validation callbacks that run once the base rules have passed.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            /** @var Closure $closure */
            $closure = $this->route('closure');
            $timezone = app(DisplayTimezone::class);

            $facilityId = $this->has('facility_id') ? $this->input('facility_id') : $closure->facility_id;
            $spaceId = $this->has('space_id') ? $this->input('space_id') : $closure->space_id;

            if (($facilityId === null) === ($spaceId === null)) {
                $validator->errors()->add(
                    $this->has('space_id') ? 'space_id' : 'facility_id',
                    $facilityId === null ? __('admin_hours.validation.target_required') : __('admin_hours.validation.target_exclusive'),
                );
            }

            $startsAt = $this->has('starts_at') ? $timezone->localToUtc($this->input('starts_at')) : $closure->starts_at;
            $endsAt = $this->has('ends_at') ? $timezone->localToUtc($this->input('ends_at')) : $closure->ends_at;

            if ($endsAt->lte($startsAt)) {
                $validator->errors()->add('ends_at', __('admin_hours.validation.ends_after_starts'));
            }
        }];
    }
}
