<?php

namespace App\Providers;

use App\Services\Otp\LogOtpSender;
use App\Services\Otp\OtpSender;
use App\Services\Otp\PhoneNumber;
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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();
    }

    /**
     * Limit OTP requests per phone number and per IP, on top of the resend cooldown setting.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('otp-request', function (Request $request): array {
            $phone = is_string($request->input('phone')) ? PhoneNumber::normalize($request->input('phone')) : '';

            return [
                Limit::perHour(5)->by('otp-request:phone:'.$phone),
                Limit::perHour(20)->by('otp-request:ip:'.$request->ip()),
            ];
        });

        RateLimiter::for('otp-verify', fn (Request $request): Limit => Limit::perMinute(10)->by('otp-verify:ip:'.$request->ip()));
    }
}
