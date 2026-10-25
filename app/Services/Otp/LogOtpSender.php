<?php

namespace App\Services\Otp;

use Illuminate\Support\Facades\Log;

/**
 * Fake sender that writes the code to the log until a real SMS / WhatsApp / Telegram provider is wired in.
 */
class LogOtpSender implements OtpSender
{
    public function send(string $phone, string $code, string $channel): void
    {
        Log::info('OTP code issued', ['phone' => $phone, 'channel' => $channel, 'code' => $code]);
    }
}
