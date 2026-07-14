<?php

return [
    'enabled' => env('LICENSE_SERVICE_ENABLED', false),
    'plus_sku_code' => env('LICENSE_PLUS_SKU_CODE', 'GAME_PLUS'),
    'legacy_plus_goods_ids' => array_values(array_filter(array_map('intval', preg_split('/[\s,]+/', trim((string) env('LICENSE_LEGACY_PLUS_GOODS_IDS', '')))))),
    'lease_hours' => (int) env('LICENSE_LEASE_HOURS', 24),
    'otp_minutes' => (int) env('LICENSE_OTP_MINUTES', 10),
    'otp_max_attempts' => (int) env('LICENSE_OTP_MAX_ATTEMPTS', 5),
    'code_pepper' => env('LICENSE_CODE_PEPPER', env('APP_KEY')),
    'token_pepper' => env('LICENSE_TOKEN_PEPPER', env('APP_KEY')),
    'otp_pepper' => env('LICENSE_OTP_PEPPER', env('APP_KEY')),
    'privacy_pepper' => env('LICENSE_PRIVACY_PEPPER', env('APP_KEY')),
    'games' => [
        'magic_world' => '魔法世界',
        'streamer_simulator' => '主播模拟器',
        'hougong_fenghualu' => '后宫·风华录',
    ],
];
