<?php

namespace App\Http\Requests\Api\V1\Admin\Settings;

use App\Services\SettingStore;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Validation\Validator;

class UpdateSettingsRequest extends FormRequest
{
    /**
     * Validation rules per known setting key (dot notation matches the nested request shape).
     *
     * @var array<string, list<string>>
     */
    public const RULES = [
        'booking.advance_window_days' => ['sometimes', 'integer', 'between:1,365'],
        'booking.slot_minutes' => ['sometimes', 'integer', 'in:30,60'],
        'booking.deposit_percent' => ['sometimes', 'integer', 'between:0,100'],
        'booking.deposit_deadline_minutes' => ['sometimes', 'integer', 'between:5,1440'],
        'booking.cancellation_deadline_hours' => ['sometimes', 'integer', 'between:0,168'],
        'booking.reminder_minutes_before' => ['sometimes', 'integer', 'between:1,4320'],
        'otp.channel' => ['sometimes', 'string', 'in:sms,whatsapp,telegram'],
        'otp.ttl_minutes' => ['sometimes', 'integer', 'between:1,30'],
        'otp.max_attempts' => ['sometimes', 'integer', 'between:1,10'],
        'otp.resend_cooldown_seconds' => ['sometimes', 'integer', 'between:10,600'],
        'subscription.expiry_reminder_days' => ['sometimes', 'integer', 'between:1,60'],
        'academy.session_generation_days' => ['sometimes', 'integer', 'between:1,180'],
        'academy.enrollment_payment_hours' => ['sometimes', 'integer', 'between:1,720'],
        'academy.waitlist_offer_hours' => ['sometimes', 'integer', 'between:1,720'],
        'app.display_timezone' => ['sometimes', 'string', 'timezone:all'],
        'app.contact' => ['sometimes', 'array'],
    ];

    /**
     * Validation rules for the sub-fields of the contact setting.
     *
     * @var array<string, list<string>>
     */
    public const CONTACT_RULES = [
        'app.contact.phone' => ['nullable', 'string', 'max:20'],
        'app.contact.whatsapp' => ['nullable', 'string', 'max:20'],
        'app.contact.address_ar' => ['nullable', 'string', 'max:255'],
        'app.contact.address_en' => ['nullable', 'string', 'max:255'],
    ];

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
        return [...self::RULES, ...self::CONTACT_RULES];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        $messages = [];

        foreach (self::RULES as $key => $rules) {
            $name = match ($key) {
                'otp.channel' => 'otp_channel',
                'otp.ttl_minutes' => 'otp_ttl_minutes',
                'otp.max_attempts' => 'otp_max_attempts',
                'otp.resend_cooldown_seconds' => 'otp_resend_cooldown_seconds',
                'app.contact' => 'contact_invalid',
                default => Arr::last(explode('.', $key)),
            };

            foreach ($rules as $rule) {
                $messages[$key.'.'.explode(':', $rule)[0]] = __('admin_settings.validation.'.$name);
            }
        }

        foreach (self::CONTACT_RULES as $key => $rules) {
            $name = 'contact_'.Arr::last(explode('.', $key));

            foreach ($rules as $rule) {
                $messages[$key.'.'.explode(':', $rule)[0]] = __('admin_settings.validation.'.$name);
            }
        }

        return $messages;
    }

    /**
     * Get the validator callbacks that run after the rules.
     *
     * @return list<callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $hasKnownSetting = collect(array_keys(SettingStore::DEFAULTS))
                    ->contains(fn (string $key): bool => Arr::has($this->all(), $key));

                if (! $hasKnownSetting) {
                    $validator->errors()->add('settings', __('admin_settings.validation.empty'));
                }
            },
        ];
    }
}
