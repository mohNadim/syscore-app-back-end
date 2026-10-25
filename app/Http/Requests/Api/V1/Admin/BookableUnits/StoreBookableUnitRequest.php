<?php

namespace App\Http\Requests\Api\V1\Admin\BookableUnits;

use App\Models\Space;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreBookableUnitRequest extends FormRequest
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
            'facility_id' => ['bail', 'required', 'integer', 'exists:facilities,id'],
            'code' => ['bail', 'required', 'string', 'max:20', 'regex:/^[A-Z0-9_]+$/', 'unique:bookable_units,code'],
            'name_ar' => ['bail', 'required', 'string', 'max:100'],
            'name_en' => ['bail', 'nullable', 'string', 'max:100'],
            'is_active' => ['bail', 'sometimes', 'boolean'],
            'sort_order' => ['bail', 'sometimes', 'integer', 'min:0'],
            'space_ids' => ['bail', 'required', 'array', 'min:1'],
            'space_ids.*' => ['bail', 'integer', 'distinct', 'exists:spaces,id'],
            'sport_ids' => ['bail', 'sometimes', 'array'],
            'sport_ids.*' => ['bail', 'integer', 'distinct', 'exists:sports,id'],
        ];
    }

    /**
     * Get the facility id the submitted spaces must belong to.
     */
    protected function targetFacilityId(): ?int
    {
        return $this->filled('facility_id') ? (int) $this->input('facility_id') : null;
    }

    /**
     * Get the validation callbacks that run after the rules pass.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $facilityId = $this->targetFacilityId();

                if ($facilityId === null || ! $this->has('space_ids') || $validator->errors()->hasAny(['facility_id', 'space_ids', 'space_ids.*'])) {
                    return;
                }

                $foreignSpaceExists = Space::query()
                    ->whereIn('id', $this->input('space_ids'))
                    ->where('facility_id', '!=', $facilityId)
                    ->exists();

                if ($foreignSpaceExists) {
                    $validator->errors()->add('space_ids', __('admin_facilities.validation.space_ids_facility'));
                }
            },
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
            'facility_id.required' => __('admin_facilities.validation.facility_id_invalid'),
            'facility_id.integer' => __('admin_facilities.validation.facility_id_invalid'),
            'facility_id.exists' => __('admin_facilities.validation.facility_id_invalid'),
            'code.required' => __('admin_facilities.validation.unit_code_invalid'),
            'code.string' => __('admin_facilities.validation.unit_code_invalid'),
            'code.max' => __('admin_facilities.validation.unit_code_invalid'),
            'code.regex' => __('admin_facilities.validation.unit_code_invalid'),
            'code.unique' => __('admin_facilities.validation.unit_code_taken'),
            'name_ar.required' => __('admin_facilities.validation.name_ar_required'),
            'name_ar.string' => __('admin_facilities.validation.name_ar_invalid'),
            'name_ar.max' => __('admin_facilities.validation.name_ar_invalid'),
            'name_en.string' => __('admin_facilities.validation.name_en_invalid'),
            'name_en.max' => __('admin_facilities.validation.name_en_invalid'),
            'is_active.boolean' => __('admin_facilities.validation.is_active_invalid'),
            'sort_order.integer' => __('admin_facilities.validation.sort_order_invalid'),
            'sort_order.min' => __('admin_facilities.validation.sort_order_invalid'),
            'space_ids.required' => __('admin_facilities.validation.space_ids_required'),
            'space_ids.required_with' => __('admin_facilities.validation.space_ids_required'),
            'space_ids.array' => __('admin_facilities.validation.space_ids_invalid'),
            'space_ids.min' => __('admin_facilities.validation.space_ids_required'),
            'space_ids.*.integer' => __('admin_facilities.validation.space_id_invalid'),
            'space_ids.*.distinct' => __('admin_facilities.validation.space_id_invalid'),
            'space_ids.*.exists' => __('admin_facilities.validation.space_id_invalid'),
            'sport_ids.array' => __('admin_facilities.validation.sport_ids_invalid'),
            'sport_ids.*.integer' => __('admin_facilities.validation.sport_id_invalid'),
            'sport_ids.*.distinct' => __('admin_facilities.validation.sport_id_invalid'),
            'sport_ids.*.exists' => __('admin_facilities.validation.sport_id_invalid'),
        ];
    }
}
