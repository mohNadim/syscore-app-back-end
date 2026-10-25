<?php

namespace App\Services\Otp;

final class PhoneNumber
{
    /**
     * Syrian mobile number in E.164 form, e.g. +963912345678.
     */
    public const PATTERN = '/^\+9639\d{8}$/';

    /**
     * Normalize local and international spellings (09…, 9639…, 009639…) to +9639….
     */
    public static function normalize(string $phone): string
    {
        $phone = preg_replace('/[\s\-().]/', '', $phone) ?? $phone;

        return match (true) {
            str_starts_with($phone, '00963') => '+963'.substr($phone, 5),
            str_starts_with($phone, '963') => '+'.$phone,
            str_starts_with($phone, '0') => '+963'.substr($phone, 1),
            default => $phone,
        };
    }
}
