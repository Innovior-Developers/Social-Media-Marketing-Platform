<?php
// routes/web.php - MAIN ROUTE FILE (Minimal & Clean)

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Social Media Marketing Platform - Main Routes
|--------------------------------------------------------------------------
|
| This file contains only the essential routes. All specific functionality
| is organized into separate route files for better maintainability.
|
| Developer: J33WAKASUPUN
| Organization: Clean, scalable route architecture
|
*/

// HOME ROUTE
Route::get('/', function () {
    return [
        'message' => 'Social Media Marketing Platform - Backend API',
        'developer' => 'J33WAKASUPUN',
        'laravel' => app()->version(),
        'timestamp' => now()->toISOString(),
        'status' => 'operational',
        'environment' => app()->environment(),
        'available_platforms' => ['linkedin', 'facebook', 'twitter', 'instagram'],
        'documentation' => [
            'testing_routes' => 'GET /test/* - Development and testing endpoints',
            'linkedin_routes' => 'GET /test/linkedin/* - LinkedIn-specific functionality',
            'oauth_routes' => 'GET /oauth/* - Authentication and OAuth flows',
            'posts_management' => 'GET /test/posts/* - Post management and analytics'
        ],
        'quick_links' => [
            'system_status' => 'GET /status',
            'linkedin_config' => 'GET /test/linkedin/config',
            'oauth_sessions' => 'GET /test/oauth/sessions',
            'test_environment' => 'GET /test/environment-complete'
        ]
    ];
});

// QUICK SYSTEM STATUS
Route::get('/status', function () {
    try {
        // Test database connection
        $databaseStatus = 'OFFLINE';
        try {
            $userCount = \App\Models\User::count();
            $databaseStatus = 'ONLINE';
        } catch (\Exception $e) {
            $databaseStatus = 'ERROR: ' . $e->getMessage();
        }

        // Test Redis connection
        $redisStatus = 'OFFLINE';
        try {
            \Illuminate\Support\Facades\Redis::ping();
            $redisStatus = 'ONLINE';
        } catch (\Exception $e) {
            $redisStatus = 'ERROR: ' . $e->getMessage();
        }

        return [
            'system_status' => 'OPERATIONAL',
            'developer' => 'J33WAKASUPUN',
            'timestamp' => now()->toISOString(),
            'services' => [
                'database' => $databaseStatus,
                'redis' => $redisStatus,
                'linkedin_provider' => class_exists('App\Services\SocialMedia\LinkedInProvider') ? 'LOADED' : 'MISSING',
                'media_validation' => class_exists('App\Helpers\MediaValidation') ? 'LOADED' : 'MISSING'
            ],
            'route_groups' => [
                'testing' => 'Available at /test/*',
                'linkedin' => 'Available at /test/linkedin/*',
                'oauth' => 'Available at /oauth/*',
                'posts' => 'Available at /test/posts/*'
            ],
            'statistics' => [
                'total_users' => $databaseStatus === 'ONLINE' ? ($userCount ?? 0) : 'N/A',
                'total_posts' => $databaseStatus === 'ONLINE' ? \App\Models\SocialMediaPost::count() : 'N/A',
                'total_organizations' => $databaseStatus === 'ONLINE' ? \App\Models\Organization::count() : 'N/A'
            ]
        ];
    } catch (\Exception $e) {
        return [
            'system_status' => 'ERROR',
            'error' => $e->getMessage(),
            'timestamp' => now()->toISOString()
        ];
    }
});

// DEVELOPMENT INFO (Only in development)
if (app()->environment(['local', 'development'])) {
    Route::get('/dev-info', function () {
        return [
            'development_mode' => true,
            'developer' => 'J33WAKASUPUN',
            'laravel_version' => app()->version(),
            'php_version' => PHP_VERSION,
            'environment' => app()->environment(),
            'debug_mode' => config('app.debug'),
            'available_route_files' => [
                'routes/testing.php' => 'Development & testing routes',
                'routes/linkedin.php' => 'LinkedIn integration routes',
                'routes/posts.php' => 'Post management routes',
                'routes/oauth.php' => 'OAuth & authentication routes',
                'routes/auth.php' => 'Laravel Breeze authentication'
            ],
            'helper_classes' => [
                'App\Helpers\MediaValidation' => 'Media file validation',
                'App\Helpers\LinkedInHelpers' => 'LinkedIn-specific helpers'
            ]
        ];
    });
}

/*
|--------------------------------------------------------------------------
| Organized Route File Includes
|--------------------------------------------------------------------------
|
| Each route file handles a specific area of functionality:
| - testing.php: Development and testing routes
| - linkedin.php: LinkedIn-specific functionality
| - posts.php: Post management and analytics
| - oauth.php: OAuth and authentication flows
| - auth.php: Laravel Breeze authentication (existing)
|
*/

// NCLUDE ORGANIZED ROUTE FILES
require __DIR__ . '/testing.php';      // Development & testing routes
require __DIR__ . '/linkedin.php';     // LinkedIn-specific routes
require __DIR__ . '/posts.php';        // Post management routes
require __DIR__ . '/oauth.php';        // OAuth & authentication routes
require __DIR__ . '/auth.php';         // Laravel Breeze auth routes (existing)

// END OF MAIN ROUTE FILE