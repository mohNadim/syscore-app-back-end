<?php

namespace App\Services\Otp;

use App\Models\OtpCode;
use App\Models\User;
use App\Services\SettingStore;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

class OtpService
{
    public function __construct(private readonly OtpSender $sender, private readonly SettingStore $settings) {}

    /**
     * Issue a new code for the phone and send it through the configured channel.
     *
     * @return array{expires_in: int, resend_after: int, debug_code?: string}
     *
     * @throws TooManyRequestsHttpException when the previous code was sent less than the cooldown ago
     */
    public function issue(string $phone): array
    {
        $cooldownSeconds = config('auth.throttle_enabled') ? (int) $this->settings->get('otp.resend_cooldown_seconds', 60) : 0;
        $ttlMinutes = (int) $this->settings->get('otp.ttl_minutes', 5);
        $channel = (string) $this->settings->get('otp.channel', 'sms');

        $latest = OtpCode::query()->where('phone', $phone)->latest('id')->first();

        if ($latest !== null) {
            $resendAt = $latest->created_at->copy()->addSeconds($cooldownSeconds);

            if ($resendAt->isFuture()) {
                throw new TooManyRequestsHttpException((int) ceil(now()->diffInSeconds($resendAt)));
            }
        }

        $code = (string) random_int(100000, 999999);

        OtpCode::create([
            'phone' => $phone,
            'code_hash' => Hash::make($code),
            'channel' => $channel,
            'attempts' => 0,
            'expires_at' => now()->addMinutes($ttlMinutes),
        ]);

        $this->sender->send($phone, $code, $channel);

        return [
            'expires_in' => $ttlMinutes * 60,
            'resend_after' => $cooldownSeconds,
            // TODO(before production): remove debug_code — temporary so the Flutter app can print the OTP during development.
            ...(config('app.debug') ? ['debug_code' => $code] : []),
        ];
    }

    /**
     * Check the latest code for the phone and return the matching user, creating one on first login.
     *
     * @return array{user: User, is_new: bool}
     *
     * @throws ValidationException when the code is wrong, expired, already used or out of attempts
     */
    public function verify(string $phone, string $code): array
    {
        $maxAttempts = (int) $this->settings->get('otp.max_attempts', 5);

        $rejection = DB::transaction(function () use ($phone, $code, $maxAttempts): ?string {
            $otp = OtpCode::query()->where('phone', $phone)->latest('id')->lockForUpdate()->first();

            $rejection = $this->rejectionReason($otp, $code, $maxAttempts);

            if ($rejection === null) {
                $otp->update(['consumed_at' => now()]);
            }

            return $rejection;
        });

        if ($rejection !== null) {
            throw ValidationException::withMessages(['code' => __("otp.{$rejection}")]);
        }

        $user = User::query()->firstOrCreate(['phone' => $phone], [
            'preferred_lang' => app()->getLocale(),
            'is_active' => true,
        ]);

        if (! $user->is_active) {
            abort(403, __('otp.account_disabled'));
        }

        $user->update(['last_login_at' => now()]);

        return ['user' => $user, 'is_new' => $user->wasRecentlyCreated];
    }

    /**
     * Decide why a code cannot be accepted, counting a failed attempt when the code is wrong.
     *
     * @return 'invalid'|'already_used'|'expired'|'too_many_attempts'|null
     */
    private function rejectionReason(?OtpCode $otp, string $code, int $maxAttempts): ?string
    {
        if ($otp === null) {
            return 'invalid';
        }

        if ($otp->consumed_at !== null) {
            return 'already_used';
        }

        if ($otp->expires_at->isPast()) {
            return 'expired';
        }

        if ($otp->attempts >= $maxAttempts) {
            return 'too_many_attempts';
        }

        if (! Hash::check($code, $otp->code_hash)) {
            $otp->increment('attempts');

            return $otp->attempts >= $maxAttempts ? 'too_many_attempts' : 'invalid';
        }

        return null;
    }
}
