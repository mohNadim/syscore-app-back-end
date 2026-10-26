<?php

namespace App\Http\Requests\Api\V1\Admin\Spaces;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class UpdateSpaceRequest extends StoreSpaceRequest
{
    /**
     * Get the validation rules that apply to the request; every field is optional on update.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $space = $this->route('space');

        return [
            'code' => ['bail', 'sometimes', 'required', 'string', 'max:20', 'regex:/^[A-Z0-9_]+$/', Rule::unique('spaces', 'code')->where('facility_id', $space->facility_id)->ignore($space->id)],
            'name_ar' => ['bail', 'sometimes', 'required', 'string', 'max:100'],
            'name_en' => ['bail', 'sometimes', 'nullable', 'string', 'max:100'],
        ];
    }
}
