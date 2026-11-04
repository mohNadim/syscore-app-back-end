<?php

return [

    'updated' => 'Settings updated successfully.',

    'validation' => [
        'empty' => 'Send at least one setting to update.',
        'advance_window_days' => 'The advance booking window must be a whole number of days from 1 to 365.',
        'slot_minutes' => 'The slot length must be 30 or 60 minutes.',
        'deposit_percent' => 'The deposit percentage must be a whole number from 0 to 100.',
        'deposit_deadline_minutes' => 'The deposit deadline must be a whole number of minutes from 5 to 1440.',
        'cancellation_deadline_hours' => 'The cancellation deadline must be a whole number of hours from 0 to 168.',
        'reminder_minutes_before' => 'The booking reminder lead time must be a whole number of minutes from 1 to 4320.',
        'otp_channel' => 'The verification channel must be one of: sms, whatsapp, telegram.',
        'otp_ttl_minutes' => 'The code validity must be a whole number of minutes from 1 to 30.',
        'otp_max_attempts' => 'The maximum attempts must be a whole number from 1 to 10.',
        'otp_resend_cooldown_seconds' => 'The resend cooldown must be a whole number of seconds from 10 to 600.',
        'expiry_reminder_days' => 'The subscription expiry reminder must be a whole number of days from 1 to 60.',
        'session_generation_days' => 'The session generation window must be a whole number of days from 1 to 180.',
        'enrollment_payment_hours' => 'The enrollment payment window must be a whole number of hours from 1 to 720.',
        'waitlist_offer_hours' => 'The waitlist offer window must be a whole number of hours from 1 to 720.',
        'display_timezone' => 'The display timezone must be a valid timezone identifier.',
        'contact_invalid' => 'The contact details must be an object.',
        'contact_phone' => 'The phone number must be text of at most 20 characters.',
        'contact_whatsapp' => 'The WhatsApp number must be text of at most 20 characters.',
        'contact_address_ar' => 'The Arabic address must be text of at most 255 characters.',
        'contact_address_en' => 'The English address must be text of at most 255 characters.',
    ],

];
