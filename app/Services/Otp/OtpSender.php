<?php

namespace App\Services\Otp;

interface OtpSender
{
    /**
     * Deliver a one-time code to the phone number through the given channel (sms, whatsapp or telegram).
     */
    public function send(string $phone, string $code, string $channel): void;
}
