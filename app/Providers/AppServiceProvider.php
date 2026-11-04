<?php

namespace App\Providers;

use App\Services\Notifications\FcmPushSender;
use App\Services\Notifications\LogPushSender;
use App\Services\Notifications\PushSender;
use App\Services\Otp\LogOtpSender;
use App\Services\Otp\OtpSender;
use App\Services\Otp\PhoneNumber;
use App\Services\Payments\ShamCash\FakeShamCashGateway;
use App\Services\Payments\ShamCash\ShamCashGateway;
use App\Services\Pricing\DiscountSelectionStrategy;
use App\Services\Pricing\HighestDiscountStrategy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(OtpSender::class, LogOtpSender::class);
        $this->app->bind(ShamCashGateway::class, FakeShamCashGateway::class);
        $this->app->bind(PushSender::class, fn () => config('services.push.driver') === 'fcm' ? $this->app->make(FcmPushSender::class) : new LogPushSender);
        $this->app->bind(DiscountSelectionStrategy::class, HighestDiscountStrategy::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();
    }

    /**
     * Limit OTP requests per phone number and per IP (on top of the resend cooldown setting) and staff login attempts per username and IP.
     * All limits are lifted when auth.throttle_enabled is off (development only).
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('otp-request', function (Request $request): array {
            if (! config('auth.throttle_enabled')) {
                return [Limit::none()];
            }

            $phone = is_string($request->input('phone')) ? PhoneNumber::normalize($request->input('phone')) : '';

            return [
                Limit::perHour(5)->by('otp-request:phone:'.$phone),
                Limit::perHour(20)->by('otp-request:ip:'.$request->ip()),
            ];
        });

        RateLimiter::for('admin-login', function (Request $request): Limit {
            if (! config('auth.throttle_enabled')) {
                return Limit::none();
            }

            $username = is_string($request->input('username')) ? mb_strtolower($request->input('username')) : '';

            return Limit::perMinute(5)->by('admin-login:'.$username.'|'.$request->ip());
        });

        RateLimiter::for('otp-verify', fn (Request $request): Limit => config('auth.throttle_enabled')
            ? Limit::perMinute(10)->by('otp-verify:ip:'.$request->ip())
            : Limit::none());
    }
}
