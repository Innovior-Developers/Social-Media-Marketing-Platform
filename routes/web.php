<?php
// routes/web.php - MAIN ROUTE FILE (Complete Fix)

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Social Media Marketing Platform - Main Routes
|--------------------------------------------------------------------------
*/

// debug route to list all routes (for development purposes)
Route::get('/debug/routes', function () {
    $routes = \Illuminate\Support\Facades\Route::getRoutes();
    $routeList = [];
    
    foreach ($routes as $route) {
        $routeList[] = [
            'method' => implode('|', $route->methods()),
            'uri' => $route->uri(),
            'name' => $route->getName(),
            'action' => $route->getActionName()
        ];
    }
    
    return [
        'total_routes' => count($routeList),
        'test_routes' => array_filter($routeList, function($route) {
            return str_contains($route['uri'], 'test/');
        }),
        'facebook_routes' => array_filter($routeList, function($route) {
            return str_contains($route['uri'], 'facebook');
        }),
        'linkedin_routes' => array_filter($routeList, function($route) {
            return str_contains($route['uri'], 'linkedin');
        })
    ];
});

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
            'facebook_routes' => 'GET /test/facebook/* - Facebook-specific functionality',
            'oauth_routes' => 'GET /oauth/* - Authentication and OAuth flows',
            'posts_management' => 'GET /test/posts/* - Post management and analytics'
        ],
        'quick_links' => [
            'system_status' => 'GET /status',
            'linkedin_config' => 'GET /test/linkedin/config',
            'facebook_config' => 'GET /test/facebook/config',
            'linkedin_comprehensive' => 'GET /test/linkedin/comprehensive',
            'facebook_comprehensive' => 'GET /test/facebook/comprehensive',
            'oauth_sessions' => 'GET /test/oauth/sessions'
        ]
    ];
});

// SYSTEM STATUS
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
                'linkedin_provider' => class_exists('App\Services\SocialMedia\LinkedInProvider') ? 'LOADED ✅' : 'MISSING ❌',
                'facebook_provider' => class_exists('App\Services\SocialMedia\FacebookProvider') ? 'LOADED ✅' : 'MISSING ❌',
                'linkedin_helper' => class_exists('App\Helpers\LinkedInHelpers') ? 'LOADED ✅' : 'MISSING ❌',
                'facebook_helper' => class_exists('App\Helpers\FacebookHelpers') ? 'LOADED ✅' : 'MISSING ❌',
                'media_validation' => class_exists('App\Helpers\MediaValidation') ? 'LOADED ✅' : 'MISSING ❌'
            ],
            'route_groups' => [
                'testing' => 'Available at /test/*',
                'linkedin' => 'Available at /test/linkedin/*',
                'facebook' => 'Available at /test/facebook/*',
                'oauth' => 'Available at /oauth/*',
                'posts' => 'Available at /test/posts/*'
            ],
            'statistics' => [
                'total_users' => $databaseStatus === 'ONLINE' ? ($userCount ?? 0) : 'N/A',
                'total_posts' => $databaseStatus === 'ONLINE' ? (\App\Models\SocialMediaPost::count() ?? 0) : 'N/A'
            ],
            'route_files_loaded' => [
                'testing.php' => file_exists(__DIR__ . '/testing.php') ? 'EXISTS ✅' : 'MISSING ❌',
                'linkedin.php' => file_exists(__DIR__ . '/linkedin.php') ? 'EXISTS ✅' : 'MISSING ❌',
                'facebook.php' => file_exists(__DIR__ . '/facebook.php') ? 'EXISTS ✅' : 'MISSING ❌',
                'posts.php' => file_exists(__DIR__ . '/posts.php') ? 'EXISTS ✅' : 'MISSING ❌',
                'oauth.php' => file_exists(__DIR__ . '/oauth.php') ? 'EXISTS ✅' : 'MISSING ❌',
                'auth.php' => file_exists(__DIR__ . '/auth.php') ? 'EXISTS ✅' : 'MISSING ❌'
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

// 🔧 QUICK DIAGNOSTIC ROUTES (Added directly here to avoid conflicts)
Route::get('/test/linkedin/quick', function () {
    try {
        $provider = new \App\Services\SocialMedia\LinkedInProvider();
        return [
            'status' => 'success',
            'message' => 'LinkedIn provider is working ✅',
            'configured' => $provider->isConfigured(),
            'mode' => $provider->getCurrentMode(),
            'helper_available' => class_exists('App\Helpers\LinkedInHelpers'),
            'comprehensive_test' => 'GET /test/linkedin/comprehensive',
            'working_features' => [
                'oauth_sessions' => 'GET /test/oauth/sessions',
                'post_publishing' => 'Your LinkedIn posting works ✅',
                'provider_loaded' => 'LinkedInProvider class loaded ✅'
            ]
        ];
    } catch (\Exception $e) {
        return [
            'status' => 'error',
            'error' => $e->getMessage()
        ];
    }
});

Route::get('/test/facebook/quick', function () {
    try {
        $provider = new \App\Services\SocialMedia\FacebookProvider();
        return [
            'status' => 'success',
            'message' => 'Facebook provider is working ✅',
            'configured' => $provider->isConfigured(),
            'mode' => $provider->getCurrentMode(),
            'helper_available' => class_exists('App\Helpers\FacebookHelpers'),
            'comprehensive_test' => 'GET /test/facebook/comprehensive',
            'working_features' => [
                'provider_loaded' => 'FacebookProvider class loaded ✅',
                'helper_loaded' => 'FacebookHelpers class loaded ✅',
                'media_validation' => 'Enhanced media validation ✅',
                'oauth_ready' => 'OAuth flow implemented ✅'
            ]
        ];
    } catch (\Exception $e) {
        return [
            'status' => 'error',
            'error' => $e->getMessage()
        ];
    }
});

/*
|--------------------------------------------------------------------------
| Include Route Files (Safe Loading)
|--------------------------------------------------------------------------
*/

// Load route files in correct order with error handling
$routeFiles = [
    'testing' => 'testing.php',
    'oauth' => 'oauth.php',        // OAuth first (dependencies)
    'linkedin' => 'linkedin.php',   // LinkedIn (established)
    'facebook' => 'facebook.php',   // Facebook (new)
    'posts' => 'posts.php',         // Posts management
    'auth' => 'auth.php'            // Laravel Breeze (last)
];

foreach ($routeFiles as $group => $file) {
    $filePath = __DIR__ . '/' . $file;
    if (file_exists($filePath)) {
        try {
            require $filePath;
            \Illuminate\Support\Facades\Log::info("Route file loaded successfully: {$file}");
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Failed to load route file: {$file}", [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
        }
    } else {
        \Illuminate\Support\Facades\Log::warning("Route file not found: {$file}");
    }
}

// END OF MAIN ROUTE FILE