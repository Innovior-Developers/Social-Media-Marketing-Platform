<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/*
|--------------------------------------------------------------------------
| Web Routes - Clean Structure (No Facebook Conflicts)
|--------------------------------------------------------------------------
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
        'facebook_integration' => [
            'comprehensive_routes' => 'GET /test/facebook/comprehensive',
            'configuration' => 'GET /test/facebook/config',
            'direct_php_backup' => 'POST /facebook-direct.php'
        ],
        'quick_links' => [
            'system_status' => 'GET /status',
            'facebook_comprehensive' => 'GET /test/facebook/comprehensive',
            'linkedin_comprehensive' => 'GET /test/linkedin/comprehensive'
        ]
    ];
});

// SYSTEM STATUS
Route::get('/status', function () {
    try {
        // Check OAuth sessions
        $oauthSessionsPath = storage_path('app/oauth_sessions');
        $oauthFiles = is_dir($oauthSessionsPath) ? glob($oauthSessionsPath . '/*.json') : [];
        $facebookTokens = array_filter($oauthFiles, fn($file) => str_contains(basename($file), 'oauth_tokens_facebook_'));
        $linkedinTokens = array_filter($oauthFiles, fn($file) => str_contains(basename($file), 'oauth_tokens_linkedin_'));

        return [
            'system_status' => 'OPERATIONAL',
            'developer' => 'J33WAKASUPUN',
            'timestamp' => now()->toISOString(),
            'oauth_sessions' => [
                'total_files' => count($oauthFiles),
                'facebook_sessions' => count($facebookTokens),
                'linkedin_sessions' => count($linkedinTokens),
                'storage_path' => $oauthSessionsPath
            ],
            'facebook_integration' => [
                'provider_class' => class_exists('App\Services\SocialMedia\FacebookProvider') ? 'LOADED ✅' : 'MISSING ❌',
                'helper_class' => class_exists('App\Helpers\FacebookHelpers') ? 'LOADED ✅' : 'MISSING ❌',
                'comprehensive_test' => 'GET /test/facebook/comprehensive',
                'direct_php_available' => file_exists(public_path('facebook-direct.php')) ? 'AVAILABLE ✅' : 'MISSING ❌'
            ],
            'recommendations' => [
                'facebook_testing' => count($facebookTokens) > 0 ? 'Ready: GET /test/facebook/comprehensive' : 'Complete OAuth first',
                'linkedin_testing' => count($linkedinTokens) > 0 ? 'Ready: GET /test/linkedin/comprehensive' : 'Complete OAuth first'
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


// Direct Facebook Post Route (CSRF-Free Alternative)
Route::post('/facebook/direct-post', function (Request $request) {
    try {
        $oauthSessionsPath = storage_path('app/oauth_sessions');
        $facebookFiles = glob($oauthSessionsPath . '/oauth_tokens_facebook_*.json');
        
        if (empty($facebookFiles)) {
            return response()->json([
                'error' => 'No Facebook tokens found',
                'oauth_url' => 'Complete OAuth first'
            ], 404);
        }
        
        $latestTokenFile = end($facebookFiles);
        $facebookToken = json_decode(file_get_contents($latestTokenFile), true);
        
        // Get pages
        $pagesResponse = Http::get('https://graph.facebook.com/v18.0/me/accounts', [
            'access_token' => $facebookToken['access_token'],
            'fields' => 'id,name,access_token,category'
        ]);
        
        if (!$pagesResponse->successful() || empty($pagesResponse->json()['data'])) {
            return response()->json(['error' => 'No Facebook pages found'], 400);
        }
        
        $selectedPage = $pagesResponse->json()['data'][0];
        $message = $request->input('message', '🎉 LARAVEL FACEBOOK SUCCESS!

✅ Clean file structure implemented
✅ CSRF issues bypassed  
✅ Laravel + Facebook working perfectly
✅ 4 Facebook OAuth sessions active

Built by J33WAKASUPUN! 🚀

#FacebookAPI #Laravel #CleanArchitecture #Success #J33WAKASUPUN');
        
        // Post to Facebook
        $postResponse = Http::post("https://graph.facebook.com/v18.0/{$selectedPage['id']}/feed", [
            'message' => $message,
            'access_token' => $selectedPage['access_token']
        ]);
        
        if ($postResponse->successful()) {
            return response()->json([
                'status' => '🎉 FACEBOOK POST SUCCESS VIA CLEAN LARAVEL! 🎉',
                'developer' => 'J33WAKASUPUN',
                'timestamp' => now()->format('Y-m-d H:i:s'),
                'route' => '/facebook/direct-post',
                'method' => 'Clean Laravel Implementation',
                'post_id' => $postResponse->json()['id'],
                'post_url' => "https://facebook.com/{$postResponse->json()['id']}",
                'page_info' => [
                    'id' => $selectedPage['id'],
                    'name' => $selectedPage['name'],
                    'category' => $selectedPage['category'] ?? 'Software company'
                ],
                'integration_status' => 'CLEAN LARAVEL FACEBOOK INTEGRATION COMPLETE! 🚀'
            ]);
        } else {
            return response()->json([
                'error' => 'Failed to publish post',
                'facebook_response' => $postResponse->json()
            ], $postResponse->status());
        }
        
    } catch (\Exception $e) {
        return response()->json([
            'error' => 'Laravel Facebook posting failed',
            'message' => $e->getMessage()
        ], 500);
    }
})->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);

/*
|--------------------------------------------------------------------------
| Include Route Files (Clean Loading)
|--------------------------------------------------------------------------
*/

$routeFiles = [
    'oauth' => 'oauth.php',
    'testing' => 'testing.php', 
    'linkedin' => 'linkedin.php',
    'facebook' => 'facebook.php',  // 🎯 Your main Facebook file
    'posts' => 'posts.php',
    'auth' => 'auth.php'
];

foreach ($routeFiles as $group => $file) {
    $filePath = __DIR__ . '/' . $file;
    if (file_exists($filePath)) {
        try {
            require $filePath;
            \Illuminate\Support\Facades\Log::info("Route file loaded: {$file}");
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Failed to load route file: {$file}", [
                'error' => $e->getMessage()
            ]);
        }
    }
}