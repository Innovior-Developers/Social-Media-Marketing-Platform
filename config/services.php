<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Social Media API Configurations
    |--------------------------------------------------------------------------
    */

    'twitter' => [
        'client_id' => env('TWITTER_CLIENT_ID'),
        'client_secret' => env('TWITTER_CLIENT_SECRET'),
        'bearer_token' => env('TWITTER_BEARER_TOKEN'),
        'redirect' => env('OAUTH_REDIRECT_BASE_URL') . '/twitter',
        'enabled' => env('TWITTER_ENABLED', false),
        'api_version' => '2',
        'base_url' => 'https://api.twitter.com/2',
    ],

    'facebook' => [
        'client_id' => env('FACEBOOK_CLIENT_ID'),
        'client_secret' => env('FACEBOOK_CLIENT_SECRET'),
        'redirect' => env('OAUTH_REDIRECT_BASE_URL') . '/facebook',
        'enabled' => env('FACEBOOK_ENABLED', false),
        'api_version' => 'v18.0',
        'base_url' => 'https://graph.facebook.com',
    ],

    'instagram' => [
        'client_id' => env('INSTAGRAM_CLIENT_ID'),
        'client_secret' => env('INSTAGRAM_CLIENT_SECRET'),
        'redirect' => env('OAUTH_REDIRECT_BASE_URL') . '/instagram',
        'enabled' => env('INSTAGRAM_ENABLED', false),
        'api_version' => 'v18.0',
        'base_url' => 'https://graph.instagram.com',
    ],

    'linkedin' => [
        'client_id' => env('LINKEDIN_CLIENT_ID'),
        'client_secret' => env('LINKEDIN_CLIENT_SECRET'),
        'redirect' => env('OAUTH_REDIRECT_BASE_URL') . '/linkedin',
        'enabled' => env('LINKEDIN_ENABLED', false),
        'api_version' => 'v2',
        'base_url' => 'https://api.linkedin.com',
    ],

    'youtube' => [
        'client_id' => env('YOUTUBE_CLIENT_ID'),
        'client_secret' => env('YOUTUBE_CLIENT_SECRET'),
        'api_key' => env('YOUTUBE_API_KEY'),
        'redirect' => env('OAUTH_REDIRECT_BASE_URL') . '/youtube',
        'enabled' => env('YOUTUBE_ENABLED', false),
        'api_version' => 'v3',
        'base_url' => 'https://www.googleapis.com/youtube',
    ],

    'tiktok' => [
        'client_id' => env('TIKTOK_CLIENT_ID'),
        'client_secret' => env('TIKTOK_CLIENT_SECRET'),
        'redirect' => env('OAUTH_REDIRECT_BASE_URL') . '/tiktok',
        'enabled' => env('TIKTOK_ENABLED', false),
        'api_version' => 'v1',
        'base_url' => 'https://open-api.tiktok.com',
    ],

    /*
    |--------------------------------------------------------------------------
    | Social Media Global Settings
    |--------------------------------------------------------------------------
    */

    'social_media' => [
        'mode' => env('SOCIAL_PROVIDER_MODE', 'stub'), // 'stub' or 'real'
        'enable_posting' => env('ENABLE_SOCIAL_POSTING', true),
        'enable_analytics' => env('ENABLE_ANALYTICS_COLLECTION', true),
        'rate_limit_enabled' => env('API_RATE_LIMIT_ENABLED', true),
        'rate_limit_per_minute' => env('API_RATE_LIMIT_PER_MINUTE', 60),
    ],

];