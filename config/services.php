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
    | Social Media API Configurations - Enhanced with Mixed Mode Support
    |--------------------------------------------------------------------------
    */

    // LINKEDIN CONFIGURATION
    'linkedin' => [
        'client_id' => env('LINKEDIN_CLIENT_ID'),
        'client_secret' => env('LINKEDIN_CLIENT_SECRET'),
        'redirect' => env('LINKEDIN_REDIRECT_URI', env('OAUTH_REDIRECT_BASE_URL') . '/linkedin'),
        'enabled' => env('LINKEDIN_ENABLED', false),
        'use_real_api' => env('LINKEDIN_USE_REAL_API', false),
        'api_version' => 'v2',
        'base_url' => 'https://api.linkedin.com',
        'auth_url' => 'https://www.linkedin.com/oauth/v2/authorization',
        'token_url' => 'https://www.linkedin.com/oauth/v2/accessToken',
        'scopes' => [
            'openid',          // Required for OpenID Connect
            'profile',         // Access to profile data
            'email',           // Access to email (if needed)
            'w_member_social'  // Required for posting
        ],
    ],

    // TWITTER/X CONFIGURATION
    'twitter' => [
        'client_id' => env('TWITTER_CLIENT_ID'),
        'client_secret' => env('TWITTER_CLIENT_SECRET'),
        'bearer_token' => env('TWITTER_BEARER_TOKEN'),
        'redirect' => env('OAUTH_REDIRECT_BASE_URL') . '/twitter',
        'enabled' => env('TWITTER_ENABLED', false),
        'use_real_api' => env('TWITTER_USE_REAL_API', false),
        'api_version' => '2',
        'base_url' => 'https://api.twitter.com/2',
    ],

    // FACEBOOK CONFIGURATION
    'facebook' => [
        'app_id' => env('FACEBOOK_CLIENT_ID'),                    // Maps to your .env
        'app_secret' => env('FACEBOOK_CLIENT_SECRET'),             // Maps to your .env
        'redirect' => env('FACEBOOK_REDIRECT_URI', env('OAUTH_REDIRECT_BASE_URL') . '/facebook'),
        'enabled' => env('FACEBOOK_ENABLED', false),
        'use_real_api' => env('FACEBOOK_USE_REAL_API', false),    // Fixed your typo
        'graph_version' => env('FACEBOOK_GRAPH_VERSION', 'v18.0'),
        
        // Facebook API endpoints
        'endpoints' => [
            'auth_url' => 'https://www.facebook.com/v18.0/dialog/oauth',
            'token_url' => 'https://graph.facebook.com/v18.0/oauth/access_token',
            'graph_api' => 'https://graph.facebook.com/v18.0'
        ],
        
        // Facebook-specific permissions
        'default_permissions' => [
            'pages_manage_posts',      // Required for posting
            'pages_read_engagement',   // Required for analytics
            'pages_show_list'          // Required for page selection
        ],
        
        // Facebook constraints
        'constraints' => [
            'character_limit' => 63206,      // Very generous
            'media_limit' => 10,             // Carousel limit
            'video_max_size' => 10737418240, // 10GB in bytes
            'image_max_size' => 104857600,   // 100MB in bytes
            'supported_video_formats' => ['mp4', 'mov', 'avi'],
            'supported_image_formats' => ['jpg', 'jpeg', 'png', 'gif']
        ],
        
        // Facebook features
        'features' => [
            'page_posting' => true,
            'carousel_posts' => true,
            'video_upload' => true,
            'link_preview' => true,
            'insights_api' => true,
            'demographic_data' => true,
            'reaction_tracking' => true
        ]
    ],

    // INSTAGRAM CONFIGURATION
    'instagram' => [
        'client_id' => env('INSTAGRAM_CLIENT_ID'),
        'client_secret' => env('INSTAGRAM_CLIENT_SECRET'),
        'redirect' => env('OAUTH_REDIRECT_BASE_URL') . '/instagram',
        'enabled' => env('INSTAGRAM_ENABLED', false),
        'use_real_api' => env('INSTAGRAM_USE_REAL_API', false),
        'api_version' => 'v18.0',
        'base_url' => 'https://graph.instagram.com',
    ],

    // YOUTUBE CONFIGURATION
    'youtube' => [
        'client_id' => env('YOUTUBE_CLIENT_ID'),
        'client_secret' => env('YOUTUBE_CLIENT_SECRET'),
        'api_key' => env('YOUTUBE_API_KEY'),
        'redirect' => env('OAUTH_REDIRECT_BASE_URL') . '/youtube',
        'enabled' => env('YOUTUBE_ENABLED', false),
        'use_real_api' => env('YOUTUBE_USE_REAL_API', false),
        'api_version' => 'v3',
        'base_url' => 'https://www.googleapis.com/youtube',
    ],

    // tiktok CONFIGURATION
    'tiktok' => [
        'client_id' => env('TIKTOK_CLIENT_ID'),
        'client_secret' => env('TIKTOK_CLIENT_SECRET'),
        'redirect' => env('OAUTH_REDIRECT_BASE_URL') . '/tiktok',
        'enabled' => env('TIKTOK_ENABLED', false),
        'use_real_api' => env('TIKTOK_USE_REAL_API', false),
        'api_version' => 'v1',
        'base_url' => 'https://open-api.tiktok.com',
    ],

    /*
    |--------------------------------------------------------------------------
    | Social Media Global Settings - Enhanced Configuration
    |--------------------------------------------------------------------------
    */

    'social_media' => [
        'mode' => env('SOCIAL_PROVIDER_MODE', 'stub'), // 'stub', 'real', or 'mixed'
        'enable_posting' => env('ENABLE_SOCIAL_POSTING', true),
        'enable_analytics' => env('ENABLE_ANALYTICS_COLLECTION', true),
        'rate_limit_enabled' => env('API_RATE_LIMIT_ENABLED', true),
        'rate_limit_per_minute' => env('API_RATE_LIMIT_PER_MINUTE', 60),

        // Mixed mode configuration - determines which providers use real APIs
        'real_providers' => [
            'linkedin' => env('LINKEDIN_USE_REAL_API', false) && !empty(env('LINKEDIN_CLIENT_ID')),
            'twitter' => env('TWITTER_USE_REAL_API', false) && !empty(env('TWITTER_CLIENT_ID')),
            'facebook' => env('FACEBOOK_USE_REAL_API', false) && !empty(env('FACEBOOK_CLIENT_ID')),
            'instagram' => env('INSTAGRAM_USE_REAL_API', false) && !empty(env('INSTAGRAM_CLIENT_ID')),
            'youtube' => env('YOUTUBE_USE_REAL_API', false) && !empty(env('YOUTUBE_CLIENT_ID')),
            'tiktok' => env('TIKTOK_USE_REAL_API', false) && !empty(env('TIKTOK_CLIENT_ID')),
        ],
        
        // Platform-specific settings
        'platform_settings' => [
            'facebook' => [
                'auto_select_page' => true,         // Auto-select first available page
                'default_post_type' => 'feed',      // 'feed' or 'photos'
                'enable_analytics' => true,         // Enable Facebook Insights
                'analytics_delay' => 300,           // Wait 5 mins for analytics
                'max_retry_attempts' => 3,          // Retry failed posts
                'timeout' => 120,                   // API timeout in seconds
            ],
        ]
    ],

    /*
    |--------------------------------------------------------------------------
    | Email Notification Settings
    |--------------------------------------------------------------------------
    */

    'notifications' => [
        'email_enabled' => env('ENABLE_EMAIL_NOTIFICATIONS', true),
        'default_recipient' => env('NOTIFICATION_EMAIL', 'admin@socialmedia.local'),
        'channels' => [
            'post_published' => true,
            'post_failed' => true,
            'oauth_expired' => true,
            'analytics_collected' => false, // Don't spam with analytics
        ],
    ],

];