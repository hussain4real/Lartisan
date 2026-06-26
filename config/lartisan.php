<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Waitlist Hosts
    |--------------------------------------------------------------------------
    |
    | Requests to these hosts only expose the public waitlist surface.
    |
    */

    'waitlist_hosts' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('LARTISAN_WAITLIST_HOSTS', 'lartisan.app,www.lartisan.app')),
    ))),

    /*
    |--------------------------------------------------------------------------
    | Admin Host
    |--------------------------------------------------------------------------
    |
    | When set, Filament operation panels are scoped to this host and other
    | application routes on the host redirect to the admin panel.
    |
    */

    'admin_host' => trim((string) env('LARTISAN_ADMIN_HOST', '')) ?: null,

    /*
    |--------------------------------------------------------------------------
    | Phase 8 Hardening
    |--------------------------------------------------------------------------
    |
    | Operational defaults used by staging and production hardening checks.
    |
    */

    'hardening' => [
        'queue_worker_command' => env(
            'LARTISAN_QUEUE_WORKER_COMMAND',
            'php artisan queue:work --queue=default --sleep=3 --tries=3 --timeout=60',
        ),
        'scheduler_command' => env('LARTISAN_SCHEDULER_COMMAND', 'php artisan schedule:run'),
        'scheduler_cache_store' => env('LARTISAN_SCHEDULER_CACHE_STORE', 'database'),
        'private_media_url_expiration_minutes' => (int) env('LARTISAN_PRIVATE_MEDIA_URL_EXPIRATION_MINUTES', 10),
    ],

    'rate_limits' => [
        'account_claims_per_minute' => (int) env('LARTISAN_ACCOUNT_CLAIMS_PER_MINUTE', 5),
        'booking_chat_messages_per_minute' => (int) env('LARTISAN_BOOKING_CHAT_MESSAGES_PER_MINUTE', 20),
        'booking_tracker_actions_per_minute' => (int) env('LARTISAN_BOOKING_TRACKER_ACTIONS_PER_MINUTE', 10),
        'marketplace_bookings_per_minute' => (int) env('LARTISAN_MARKETPLACE_BOOKINGS_PER_MINUTE', 6),
        'media_uploads_per_minute' => (int) env('LARTISAN_MEDIA_UPLOADS_PER_MINUTE', 12),
        'otp_requests_per_minute' => (int) env('LARTISAN_OTP_REQUESTS_PER_MINUTE', 4),
        'paystack_webhooks_per_minute' => (int) env('LARTISAN_PAYSTACK_WEBHOOKS_PER_MINUTE', 120),
        'waitlist_submissions_per_minute' => (int) env('LARTISAN_WAITLIST_SUBMISSIONS_PER_MINUTE', 6),
        'whatsapp_webhooks_per_minute' => (int) env('LARTISAN_WHATSAPP_WEBHOOKS_PER_MINUTE', 120),
    ],

    /*
    |--------------------------------------------------------------------------
    | Transactional Notifications
    |--------------------------------------------------------------------------
    |
    | Email and WhatsApp are the approved Phase 10 channels. WhatsApp remains
    | disabled until a provider URL, token, and webhook secret are configured.
    |
    */

    'notifications' => [
        'default_channels' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('LARTISAN_NOTIFICATION_CHANNELS', 'email,whatsapp')),
        ))),
        'channels' => [
            'email' => [
                'enabled' => (bool) env('LARTISAN_EMAIL_NOTIFICATIONS_ENABLED', true),
            ],
            'whatsapp' => [
                'base_url' => rtrim((string) env('LARTISAN_WHATSAPP_BASE_URL', ''), '/'),
                'connect_timeout' => (int) env('LARTISAN_WHATSAPP_CONNECT_TIMEOUT', 3),
                'enabled' => (bool) env('LARTISAN_WHATSAPP_NOTIFICATIONS_ENABLED', false),
                'retry_sleep_milliseconds' => (int) env('LARTISAN_WHATSAPP_RETRY_SLEEP_MILLISECONDS', 200),
                'retry_times' => (int) env('LARTISAN_WHATSAPP_RETRY_TIMES', 2),
                'timeout' => (int) env('LARTISAN_WHATSAPP_TIMEOUT', 5),
                'token' => (string) env('LARTISAN_WHATSAPP_TOKEN', ''),
                'webhook_secret' => (string) env('LARTISAN_WHATSAPP_WEBHOOK_SECRET', ''),
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Booking Payment Settlement
    |--------------------------------------------------------------------------
    |
    | Amounts are stored in minor currency units. Basis points are snapshotted
    | on each booking payment so future configuration changes do not rewrite
    | historical commission, provider fee, or net settlement calculations.
    |
    */

    'booking_payments' => [
        'commission_basis_points' => (int) env('LARTISAN_BOOKING_COMMISSION_BASIS_POINTS', 1000),
        'provider_fee_basis_points' => (int) env('LARTISAN_BOOKING_PROVIDER_FEE_BASIS_POINTS', 150),
        'provider_fee_flat_amount' => (int) env('LARTISAN_BOOKING_PROVIDER_FEE_FLAT_AMOUNT', 10000),
    ],
];
