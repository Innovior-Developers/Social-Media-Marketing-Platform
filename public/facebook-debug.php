<?php
/**
 * FACEBOOK TOKEN DEBUG & RE-AUTHORIZATION TOOL
 * J33WAKASUPUN Social Media Marketing Platform
 * Purpose: Debug token issues and provide re-auth URLs
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../vendor/autoload.php';
$storagePath = __DIR__ . '/../storage/app/oauth_sessions';

function getFacebookToken($storagePath) {
    $facebookFiles = glob($storagePath . '/oauth_tokens_facebook_*.json');
    if (empty($facebookFiles)) return null;
    usort($facebookFiles, fn($a, $b) => filemtime($b) - filemtime($a));
    return json_decode(file_get_contents($facebookFiles[0]), true);
}

function makeHttpRequest($url, $data = null, $method = 'GET', $headers = []) {
    $context = stream_context_create([
        'http' => [
            'method' => $method,
            'header' => implode("\r\n", array_merge([
                'User-Agent: J33WAKASUPUN-Debug/1.0'
            ], $headers)),
            'content' => $method === 'POST' && $data ? $data : null,
            'ignore_errors' => true,
            'timeout' => 30
        ]
    ]);
    
    $response = file_get_contents($url, false, $context);
    $httpCode = 200;
    
    if (isset($http_response_header[0])) {
        preg_match('/HTTP\/\d\.\d\s+(\d+)/', $http_response_header[0], $matches);
        $httpCode = intval($matches[1] ?? 200);
    }
    
    return [
        'data' => json_decode($response, true),
        'http_code' => $httpCode,
        'success' => $httpCode >= 200 && $httpCode < 300,
        'raw_response' => $response
    ];
}

try {
    $token = getFacebookToken($storagePath);
    
    if (!$token) {
        echo json_encode([
            'status' => 'NO_TOKEN',
            'error' => 'No Facebook token found',
            'solution' => 'Complete Facebook OAuth first',
            'oauth_url' => 'Use your Laravel OAuth routes or create new auth'
        ]);
        exit;
    }
    
    // Test basic token validity
    $userInfoResponse = makeHttpRequest('https://graph.facebook.com/v18.0/me?' . http_build_query([
        'access_token' => $token['access_token'],
        'fields' => 'id,name,email'
    ]));
    
    $tokenValid = $userInfoResponse['success'];
    $userInfo = $userInfoResponse['data'] ?? null;
    
    // Test permissions
    $permissionsResponse = makeHttpRequest('https://graph.facebook.com/v18.0/me/permissions?' . http_build_query([
        'access_token' => $token['access_token']
    ]));
    
    $permissions = [];
    $grantedPermissions = [];
    $declinedPermissions = [];
    
    if ($permissionsResponse['success']) {
        $permissions = $permissionsResponse['data']['data'] ?? [];
        foreach ($permissions as $perm) {
            if ($perm['status'] === 'granted') {
                $grantedPermissions[] = $perm['permission'];
            } else {
                $declinedPermissions[] = $perm['permission'];
            }
        }
    }
    
    // Test pages access with detailed error
    $pagesResponse = makeHttpRequest('https://graph.facebook.com/v18.0/me/accounts?' . http_build_query([
        'access_token' => $token['access_token'],
        'fields' => 'id,name,category'
    ]));
    
    $pagesAccessible = $pagesResponse['success'];
    $pagesData = $pagesResponse['data'] ?? null;
    $pagesError = $pagesAccessible ? null : $pagesData;
    
    // Generate new OAuth URL with all required permissions
    $requiredScopes = [
        'pages_show_list',
        'pages_manage_posts', 
        'pages_read_engagement',
        'business_management',
        'public_profile',
        'email'
    ];
    
    $facebookAppId = env('FACEBOOK_CLIENT_ID', 'YOUR_FACEBOOK_APP_ID');
    $redirectUri = env('FACEBOOK_REDIRECT_URI', 'http://localhost:8000/oauth/facebook/callback');
    
    $newOAuthUrl = 'https://www.facebook.com/v18.0/dialog/oauth?' . http_build_query([
        'client_id' => $facebookAppId,
        'redirect_uri' => $redirectUri,
        'scope' => implode(',', $requiredScopes),
        'response_type' => 'code',
        'state' => 'reauth_' . time()
    ]);
    
    echo json_encode([
        'status' => 'FACEBOOK_TOKEN_DEBUG_COMPLETE',
        'developer' => 'J33WAKASUPUN',
        'timestamp' => date('Y-m-d H:i:s'),
        'debug_results' => [
            'token_exists' => true,
            'token_valid' => $tokenValid,
            'token_expires_at' => $token['expires_at'] ?? 'Unknown',
            'pages_accessible' => $pagesAccessible,
            'user_info_accessible' => !empty($userInfo)
        ],
        'user_info' => $userInfo,
        'permissions_analysis' => [
            'total_permissions' => count($permissions),
            'granted_permissions' => $grantedPermissions,
            'declined_permissions' => $declinedPermissions,
            'required_permissions' => $requiredScopes,
            'missing_permissions' => array_diff($requiredScopes, $grantedPermissions)
        ],
        'pages_debug' => [
            'access_successful' => $pagesAccessible,
            'pages_count' => $pagesAccessible ? count($pagesData['data'] ?? []) : 0,
            'error_details' => $pagesError,
            'facebook_error_message' => $pagesError['error']['message'] ?? 'Unknown error'
        ],
        'diagnosis' => [
            'primary_issue' => !$pagesAccessible ? 'MISSING_PAGES_PERMISSIONS' : 'UNKNOWN',
            'likely_cause' => 'Facebook token lacks pages_show_list or pages_manage_posts permissions',
            'solution_required' => 'Re-authorize Facebook app with extended permissions'
        ],
        'solutions' => [
            'immediate_action' => 'Click the new OAuth URL below to re-authorize',
            'new_oauth_url' => $newOAuthUrl,
            'manual_steps' => [
                '1. Click the OAuth URL above',
                '2. Accept all permissions (especially pages permissions)',
                '3. Complete authorization flow',
                '4. Test API again'
            ]
        ],
        'facebook_app_config' => [
            'app_id' => $facebookAppId !== 'YOUR_FACEBOOK_APP_ID' ? substr($facebookAppId, 0, 8) . '...' : 'NOT_CONFIGURED',
            'redirect_uri' => $redirectUri,
            'scopes_requested' => $requiredScopes
        ],
        'alternative_solutions' => [
            'check_facebook_page' => 'Ensure you have admin access to at least one Facebook page',
            'create_facebook_page' => 'Create a Facebook page at https://www.facebook.com/pages/create',
            'business_account' => 'Use Facebook Business account for better API access'
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'DEBUG_ERROR',
        'error' => $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine(),
        'developer' => 'J33WAKASUPUN'
    ]);
}
?>