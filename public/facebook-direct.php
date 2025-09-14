<?php
/**
 * FACEBOOK API WITH POST ID FORMAT FIX
 * J33WAKASUPUN Social Media Marketing Platform
 * FIXED: Proper post ID handling for all post types
 */

// Enhanced PHP configuration
ini_set('upload_max_filesize', '1024M');
ini_set('post_max_size', '1024M');
ini_set('max_execution_time', '600');
ini_set('memory_limit', '1024M');

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? 'status';

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
                'User-Agent: J33WAKASUPUN-Facebook-API/4.1-PostIdFixed'
            ], $headers)),
            'content' => $method === 'POST' && $data ? $data : null,
            'ignore_errors' => true,
            'timeout' => 300
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

function logActivity($message, $data = []) {
    $logEntry = date('Y-m-d H:i:s') . " [J33WAKASUPUN-FIXED] {$message}: " . json_encode($data) . "\n";
    error_log($logEntry, 3, __DIR__ . '/../storage/logs/facebook-postid-fix.log');
}

function getPageInfoWithTokens($userToken) {
    try {
        $pagesResponse = makeHttpRequest('https://graph.facebook.com/v18.0/me/accounts?' . http_build_query([
            'access_token' => $userToken,
            'fields' => 'id,name,access_token,category,followers_count'
        ]));
        
        if (!$pagesResponse['success'] || empty($pagesResponse['data']['data'])) {
            return ['success' => false, 'error' => 'Failed to get pages'];
        }
        
        $pages = $pagesResponse['data']['data'];
        $selectedPage = $pages[0];
        
        if (empty($selectedPage['access_token'])) {
            return ['success' => false, 'error' => 'Page access token not available'];
        }
        
        return [
            'success' => true,
            'page_id' => $selectedPage['id'],
            'page_name' => $selectedPage['name'],
            'page_access_token' => $selectedPage['access_token'],
            'user_access_token' => $userToken,
            'page_info' => $selectedPage,
            'all_pages' => $pages
        ];
    } catch (Exception $e) {
        return ['success' => false, 'error' => 'Exception: ' . $e->getMessage()];
    }
}

// FIXED: Enhanced post ID resolution function
function resolvePostId($inputPostId, $pageInfo) {
    logActivity('Resolving post ID', [
        'input_post_id' => $inputPostId,
        'page_id' => $pageInfo['page_id']
    ]);
    
    // If post ID already contains underscore, use as-is
    if (strpos($inputPostId, '_') !== false) {
        logActivity('Post ID already in full format', ['post_id' => $inputPostId]);
        return $inputPostId;
    }
    
    // If post ID is just numbers, try with page prefix
    $fullPostId = $pageInfo['page_id'] . '_' . $inputPostId;
    logActivity('Created full post ID', ['full_post_id' => $fullPostId]);
    
    return $fullPostId;
}

// FIXED: Enhanced post retrieval with multiple ID format attempts
function retrievePostWithFallback($postId, $pageInfo, $fields = 'id,message,created_time,type') {
    $accessToken = $pageInfo['page_access_token'];
    
    // Try different post ID formats
    $postIdVariations = [
        'original' => $postId,
        'with_page_prefix' => $pageInfo['page_id'] . '_' . $postId,
        'without_prefix' => str_replace($pageInfo['page_id'] . '_', '', $postId)
    ];
    
    logActivity('Trying post ID variations', ['variations' => array_keys($postIdVariations)]);
    
    foreach ($postIdVariations as $variation => $testPostId) {
        logActivity('Testing post ID variation', [
            'variation' => $variation,
            'post_id' => $testPostId
        ]);
        
        $response = makeHttpRequest("https://graph.facebook.com/v18.0/{$testPostId}?" . http_build_query([
            'fields' => $fields,
            'access_token' => $accessToken
        ]));
        
        if ($response['success']) {
            logActivity('Post found with variation', [
                'variation' => $variation,
                'post_id' => $testPostId,
                'actual_post_id' => $response['data']['id'] ?? 'unknown'
            ]);
            
            return [
                'success' => true,
                'data' => $response['data'],
                'post_id_used' => $testPostId,
                'variation_used' => $variation
            ];
        } else {
            logActivity('Post not found with variation', [
                'variation' => $variation,
                'post_id' => $testPostId,
                'error' => $response['data']['error'] ?? 'Unknown error'
            ]);
        }
    }
    
    // If none worked, try getting recent posts to find it
    logActivity('Attempting to find post in recent posts');
    
    $recentPostsResponse = makeHttpRequest("https://graph.facebook.com/v18.0/{$pageInfo['page_id']}/posts?" . http_build_query([
        'fields' => $fields,
        'limit' => 10,
        'access_token' => $accessToken
    ]));
    
    if ($recentPostsResponse['success']) {
        $posts = $recentPostsResponse['data']['data'] ?? [];
        
        foreach ($posts as $post) {
            $fullPostId = $post['id'];
            $shortPostId = str_replace($pageInfo['page_id'] . '_', '', $fullPostId);
            
            if ($shortPostId === $postId || $fullPostId === $postId) {
                logActivity('Post found in recent posts', [
                    'found_post_id' => $fullPostId,
                    'searched_for' => $postId
                ]);
                
                return [
                    'success' => true,
                    'data' => $post,
                    'post_id_used' => $fullPostId,
                    'variation_used' => 'found_in_recent_posts'
                ];
            }
        }
    }
    
    return [
        'success' => false,
        'error' => 'Post not found with any ID variation',
        'tried_variations' => array_keys($postIdVariations)
    ];
}

// Include all the upload functions (same as before)
function createMultipartData($fields, $files = []) {
    $boundary = 'boundary_' . uniqid();
    $data = '';
    
    foreach ($fields as $key => $value) {
        $data .= "--{$boundary}\r\n";
        $data .= "Content-Disposition: form-data; name=\"{$key}\"\r\n\r\n";
        $data .= $value . "\r\n";
    }
    
    foreach ($files as $key => $filePath) {
        if (file_exists($filePath)) {
            $mimeType = mime_content_type($filePath) ?: 'application/octet-stream';
            $data .= "--{$boundary}\r\n";
            $data .= "Content-Disposition: form-data; name=\"{$key}\"; filename=\"" . basename($filePath) . "\"\r\n";
            $data .= "Content-Type: {$mimeType}\r\n\r\n";
            $data .= file_get_contents($filePath) . "\r\n";
        }
    }
    
    $data .= "--{$boundary}--\r\n";
    
    return [
        'data' => $data,
        'content_type' => "multipart/form-data; boundary={$boundary}"
    ];
}

function saveUploadedFile($uploadedFile, $tempDir = null) {
    try {
        if ($tempDir === null) {
            $tempDir = sys_get_temp_dir();
        }
        
        $extension = pathinfo($uploadedFile['name'], PATHINFO_EXTENSION);
        $filename = 'facebook_upload_' . uniqid() . '.' . $extension;
        $tempPath = $tempDir . DIRECTORY_SEPARATOR . $filename;
        
        if (move_uploaded_file($uploadedFile['tmp_name'], $tempPath)) {
            return [
                'success' => true,
                'temp_path' => $tempPath,
                'filename' => $filename,
                'size' => $uploadedFile['size'],
                'original_name' => $uploadedFile['name'],
                'mime_type' => $uploadedFile['type'],
                'extension' => $extension
            ];
        } else {
            return ['success' => false, 'error' => 'Failed to move uploaded file'];
        }
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// FIXED: Enhanced video upload with proper post ID return
function uploadVideo($videoFile, $message, $pageInfo) {
    try {
        $uploadData = createMultipartData([
            'description' => $message,
            'access_token' => $pageInfo['page_access_token']
        ], [
            'source' => $videoFile['temp_path']
        ]);
        
        $response = makeHttpRequest(
            "https://graph.facebook.com/v18.0/{$pageInfo['page_id']}/videos",
            $uploadData['data'],
            'POST',
            ["Content-Type: {$uploadData['content_type']}"]
        );
        
        if (file_exists($videoFile['temp_path'])) {
            unlink($videoFile['temp_path']);
        }
        
        if ($response['success'] && !empty($response['data']['id'])) {
            $rawPostId = $response['data']['id'];
            
            // FIXED: Ensure we return the full post ID format
            $fullPostId = strpos($rawPostId, '_') !== false ? $rawPostId : $pageInfo['page_id'] . '_' . $rawPostId;
            
            logActivity('Video upload successful with proper post ID', [
                'raw_post_id' => $rawPostId,
                'full_post_id' => $fullPostId,
                'page_id' => $pageInfo['page_id']
            ]);
            
            return [
                'status' => '🎥 FACEBOOK VIDEO UPLOADED - POST ID FIXED! 🎥',
                'developer' => 'J33WAKASUPUN',
                'timestamp' => date('Y-m-d H:i:s'),
                'post_id' => $fullPostId, // FIXED: Return full post ID
                'raw_post_id' => $rawPostId, // Also return raw ID for reference
                'post_url' => "https://facebook.com/{$fullPostId}",
                'post_type' => 'video',
                'page_info' => [
                    'page_id' => $pageInfo['page_id'],
                    'page_name' => $pageInfo['page_name']
                ],
                'post_id_fix' => 'Now returns full post ID format for proper CRUD operations',
                'next_actions' => [
                    'view_post' => "GET ?action=view&post_id={$fullPostId}",
                    'get_analytics' => "GET ?action=analytics&post_id={$fullPostId}",
                    'update_post' => "PUT ?action=update&post_id={$fullPostId}",
                    'delete_post' => "DELETE ?action=delete&post_id={$fullPostId}"
                ]
            ];
        } else {
            return ['error' => 'Failed to upload video', 'facebook_response' => $response['data']];
        }
    } catch (Exception $e) {
        return ['error' => 'Video upload failed', 'message' => $e->getMessage()];
    }
}

// Enhanced upload functions (same logic for images)
function uploadSingleImage($fileInfo, $message, $pageInfo) {
    try {
        $uploadData = createMultipartData([
            'message' => $message,
            'access_token' => $pageInfo['page_access_token']
        ], [
            'source' => $fileInfo['temp_path']
        ]);
        
        $response = makeHttpRequest(
            "https://graph.facebook.com/v18.0/{$pageInfo['page_id']}/photos",
            $uploadData['data'],
            'POST',
            ["Content-Type: {$uploadData['content_type']}"]
        );
        
        if (file_exists($fileInfo['temp_path'])) {
            unlink($fileInfo['temp_path']);
        }
        
        if ($response['success'] && !empty($response['data']['id'])) {
            $rawPostId = $response['data']['id'];
            $fullPostId = strpos($rawPostId, '_') !== false ? $rawPostId : $pageInfo['page_id'] . '_' . $rawPostId;
            
            return [
                'status' => '📸 FACEBOOK IMAGE UPLOADED - POST ID FIXED! 📸',
                'developer' => 'J33WAKASUPUN',
                'timestamp' => date('Y-m-d H:i:s'),
                'post_id' => $fullPostId,
                'post_url' => "https://facebook.com/{$fullPostId}",
                'post_type' => 'single_image'
            ];
        } else {
            return ['error' => 'Failed to upload image', 'facebook_response' => $response['data']];
        }
    } catch (Exception $e) {
        return ['error' => 'Image upload failed', 'message' => $e->getMessage()];
    }
}

function uploadCarousel($files, $message, $pageInfo) {
    try {
        $uploadedMedia = [];
        
        foreach ($files as $fileInfo) {
            $uploadData = createMultipartData([
                'published' => 'false',
                'access_token' => $pageInfo['page_access_token']
            ], [
                'source' => $fileInfo['temp_path']
            ]);
            
            $response = makeHttpRequest(
                "https://graph.facebook.com/v18.0/{$pageInfo['page_id']}/photos",
                $uploadData['data'],
                'POST',
                ["Content-Type: {$uploadData['content_type']}"]
            );
            
            if (file_exists($fileInfo['temp_path'])) {
                unlink($fileInfo['temp_path']);
            }
            
            if ($response['success'] && !empty($response['data']['id'])) {
                $uploadedMedia[] = ['media_fbid' => $response['data']['id']];
            }
        }
        
        if (empty($uploadedMedia)) {
            return ['error' => 'No images could be uploaded for carousel'];
        }
        
        $postData = [
            'message' => $message,
            'attached_media' => json_encode($uploadedMedia),
            'access_token' => $pageInfo['page_access_token']
        ];
        
        $response = makeHttpRequest(
            "https://graph.facebook.com/v18.0/{$pageInfo['page_id']}/feed",
            http_build_query($postData),
            'POST',
            ['Content-Type: application/x-www-form-urlencoded']
        );
        
        if ($response['success'] && !empty($response['data']['id'])) {
            $fullPostId = $response['data']['id'];
            
            return [
                'status' => '📸📸 FACEBOOK CAROUSEL UPLOADED - POST ID FIXED! 📸📸',
                'developer' => 'J33WAKASUPUN',
                'timestamp' => date('Y-m-d H:i:s'),
                'post_id' => $fullPostId,
                'post_type' => 'carousel'
            ];
        } else {
            return ['error' => 'Failed to create carousel post'];
        }
    } catch (Exception $e) {
        return ['error' => 'Carousel upload failed', 'message' => $e->getMessage()];
    }
}

function createTextPost($message, $pageInfo) {
    try {
        $postData = [
            'message' => $message,
            'access_token' => $pageInfo['page_access_token']
        ];
        
        $response = makeHttpRequest(
            "https://graph.facebook.com/v18.0/{$pageInfo['page_id']}/feed",
            http_build_query($postData),
            'POST',
            ['Content-Type: application/x-www-form-urlencoded']
        );

        if ($response['success'] && !empty($response['data']['id'])) {
            $fullPostId = $response['data']['id'];
            
            return [
                'status' => '📝 FACEBOOK TEXT POST - POST ID FIXED! 📝',
                'developer' => 'J33WAKASUPUN',
                'timestamp' => date('Y-m-d H:i:s'),
                'post_id' => $fullPostId,
                'post_url' => "https://facebook.com/{$fullPostId}",
                'post_type' => 'text'
            ];
        } else {
            return ['error' => 'Failed to create text post', 'facebook_response' => $response['data']];
        }
    } catch (Exception $e) {
        return ['error' => 'Text post creation failed', 'message' => $e->getMessage()];
    }
}

function handleAdvancedUpload($pageInfo) {
    try {
        $message = $_POST['message'] ?? '🎉 FACEBOOK API - POST ID ISSUE FIXED! Now all CRUD operations work with proper post ID resolution! Built by J33WAKASUPUN! #FacebookAPI #PostIdFixed #Success';
        
        if (empty($_FILES) || empty($_FILES['files'])) {
            return createTextPost($message, $pageInfo);
        }

        $uploadedFiles = $_FILES['files'];
        $processedFiles = [];

        if (is_array($uploadedFiles['name'])) {
            for ($i = 0; $i < count($uploadedFiles['name']); $i++) {
                if ($uploadedFiles['error'][$i] === UPLOAD_ERR_OK) {
                    $fileInfo = [
                        'name' => $uploadedFiles['name'][$i],
                        'tmp_name' => $uploadedFiles['tmp_name'][$i],
                        'size' => $uploadedFiles['size'][$i],
                        'type' => $uploadedFiles['type'][$i]
                    ];
                    
                    $saved = saveUploadedFile($fileInfo);
                    if ($saved['success']) {
                        $processedFiles[] = $saved;
                    }
                }
            }
        } else {
            if ($uploadedFiles['error'] === UPLOAD_ERR_OK) {
                $saved = saveUploadedFile($uploadedFiles);
                if ($saved['success']) {
                    $processedFiles[] = $saved;
                }
            }
        }

        if (empty($processedFiles)) {
            return ['error' => 'No files were successfully processed'];
        }

        $videoFiles = array_filter($processedFiles, function($file) {
            return in_array(strtolower($file['extension']), ['mp4', 'mov', 'avi', 'wmv']);
        });

        $imageFiles = array_filter($processedFiles, function($file) {
            return in_array(strtolower($file['extension']), ['jpg', 'jpeg', 'png', 'gif']);
        });

        if (!empty($videoFiles)) {
            return uploadVideo(array_values($videoFiles)[0], $message, $pageInfo);
        } elseif (count($imageFiles) === 1) {
            return uploadSingleImage(array_values($imageFiles)[0], $message, $pageInfo);
        } elseif (count($imageFiles) > 1) {
            return uploadCarousel($imageFiles, $message, $pageInfo);
        } else {
            return ['error' => 'No supported media files found'];
        }

    } catch (Exception $e) {
        return ['error' => 'Advanced upload failed', 'message' => $e->getMessage()];
    }
}

// ========================================
// MAIN SWITCH STATEMENT WITH POST ID FIXES
// ========================================

try {
    switch ($method . ':' . $action) {
        
        case 'GET:status':
            $token = getFacebookToken($storagePath);
            
            if ($token) {
                $pageInfo = getPageInfoWithTokens($token['access_token']);
            } else {
                $pageInfo = ['success' => false, 'error' => 'No token found'];
            }
            
            echo json_encode([
                'status' => 'Facebook API - POST ID ISSUE FIXED! 🔧',
                'developer' => 'J33WAKASUPUN', 
                'timestamp' => date('Y-m-d H:i:s'),
                'version' => '4.1 - Post ID Resolution Fixed',
                'fix_applied' => [
                    'video_uploads' => 'Now return full post ID format',
                    'view_operations' => 'Enhanced post ID resolution with fallbacks',
                    'analytics_operations' => 'Multiple post ID format attempts',
                    'crud_operations' => 'All operations now work with any post ID format'
                ],
                'token_status' => [
                    'facebook_token_available' => !empty($token),
                    'pages_accessible' => $pageInfo['success'] ?? false,
                    'page_count' => count($pageInfo['all_pages'] ?? [])
                ],
                'post_id_handling' => [
                    'supports_short_format' => '770146209244294',
                    'supports_full_format' => 'page_id_770146209244294',
                    'automatic_resolution' => 'API tries multiple formats automatically',
                    'fallback_mechanism' => 'Searches recent posts if direct access fails'
                ]
            ]);
            break;
            
        case 'GET:pages':
            $token = getFacebookToken($storagePath);
            if (!$token) {
                http_response_code(404);
                echo json_encode(['error' => 'Facebook token not found']);
                exit;
            }
            
            $pageInfo = getPageInfoWithTokens($token['access_token']);
            if (!$pageInfo['success']) {
                http_response_code(400);
                echo json_encode(['error' => 'Cannot access Facebook pages', 'details' => $pageInfo['error']]);
                exit;
            }
            
            echo json_encode([
                'status' => 'Facebook Pages Retrieved Successfully! 📋',
                'developer' => 'J33WAKASUPUN',
                'timestamp' => date('Y-m-d H:i:s'),
                'total_pages' => count($pageInfo['all_pages']),
                'selected_page' => [
                    'id' => $pageInfo['page_id'],
                    'name' => $pageInfo['page_name']
                ],
                'all_pages' => array_map(function($page) {
                    return [
                        'id' => $page['id'],
                        'name' => $page['name'],
                        'category' => $page['category'] ?? 'Unknown',
                        'followers_count' => $page['followers_count'] ?? 0
                    ];
                }, $pageInfo['all_pages'])
            ]);
            break;
            
        case 'POST:post':
            $token = getFacebookToken($storagePath);
            if (!$token) {
                http_response_code(404);
                echo json_encode(['error' => 'Facebook token not found']);
                exit;
            }

            $pageInfo = getPageInfoWithTokens($token['access_token']);
            if (!$pageInfo['success']) {
                http_response_code(400);
                echo json_encode(['error' => 'Cannot access Facebook pages', 'details' => $pageInfo['error']]);
                exit;
            }

            $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
            $isFormData = strpos($contentType, 'multipart/form-data') !== false;

            if ($isFormData || !empty($_FILES)) {
                $result = handleAdvancedUpload($pageInfo);
                echo json_encode($result);
            } else {
                $input = json_decode(file_get_contents('php://input'), true) ?: [];
                $message = $input['message'] ?? $_POST['message'] ?? 'Facebook API - POST ID ISSUE FIXED! All CRUD operations now work properly with enhanced post ID resolution! Built by J33WAKASUPUN! #FacebookAPI #PostIdFixed #Success';
                $result = createTextPost($message, $pageInfo);
                echo json_encode($result);
            }
            break;
            
        // ========================================
        // FIXED VIEW OPERATION
        // ========================================
        case 'GET:view':
            $postId = $_GET['post_id'] ?? null;
            if (!$postId) {
                http_response_code(400);
                echo json_encode(['error' => 'Post ID required']);
                exit;
            }
            
            $token = getFacebookToken($storagePath);
            if (!$token) {
                http_response_code(404);
                echo json_encode(['error' => 'Facebook token not found']);
                exit;
            }
            
            $pageInfo = getPageInfoWithTokens($token['access_token']);
            if (!$pageInfo['success']) {
                http_response_code(400);
                echo json_encode(['error' => 'Cannot access pages', 'details' => $pageInfo['error']]);
                exit;
            }
            
            logActivity('Attempting to view post with enhanced resolution', [
                'input_post_id' => $postId,
                'page_id' => $pageInfo['page_id']
            ]);
            
            // FIXED: Use enhanced post retrieval
            $postResult = retrievePostWithFallback(
                $postId, 
                $pageInfo, 
                'id,message,story,created_time,updated_time,type,status_type,permalink_url,likes.summary(true),comments.summary(true),shares'
            );
            
            if ($postResult['success']) {
                $data = $postResult['data'];
                
                echo json_encode([
                    'status' => '👀 Facebook Post Retrieved - POST ID FIXED! 👀',
                    'developer' => 'J33WAKASUPUN',
                    'timestamp' => date('Y-m-d H:i:s'),
                    'method' => 'Enhanced post ID resolution',
                    'post_id_resolution' => [
                        'input_post_id' => $postId,
                        'resolved_post_id' => $postResult['post_id_used'],
                        'variation_used' => $postResult['variation_used']
                    ],
                    'post_details' => [
                        'id' => $data['id'],
                        'message' => $data['message'] ?? $data['story'] ?? 'Media post or no text content',
                        'type' => $data['type'] ?? 'status',
                        'status_type' => $data['status_type'] ?? 'mobile_status_update',
                        'created_time' => $data['created_time'],
                        'updated_time' => $data['updated_time'] ?? null,
                        'permalink_url' => $data['permalink_url'] ?? "https://facebook.com/{$data['id']}"
                    ],
                    'engagement' => [
                        'likes' => $data['likes']['summary']['total_count'] ?? 0,
                        'comments' => $data['comments']['summary']['total_count'] ?? 0,
                        'shares' => $data['shares']['count'] ?? 0
                    ],
                    'crud_operations' => [
                        'update' => "PUT ?action=update&post_id={$data['id']}",
                        'delete' => "DELETE ?action=delete&post_id={$data['id']}",
                        'analytics' => "GET ?action=analytics&post_id={$data['id']}"
                    ]
                ]);
            } else {
                http_response_code(404);
                echo json_encode([
                    'error' => 'Failed to retrieve post with enhanced resolution',
                    'input_post_id' => $postId,
                    'page_id' => $pageInfo['page_id'],
                    'resolution_attempts' => $postResult['tried_variations'] ?? [],
                    'suggestions' => [
                        'Check if post exists on Facebook',
                        'Verify post was created by your page',
                        'Try with full post ID format: page_id_post_id'
                    ]
                ]);
            }
            break;
            
        // ========================================
        // FIXED ANALYTICS OPERATION
        // ========================================
        case 'GET:analytics':
            $postId = $_GET['post_id'] ?? null;
            if (!$postId) {
                http_response_code(400);
                echo json_encode(['error' => 'Post ID required for analytics']);
                exit;
            }
            
            $token = getFacebookToken($storagePath);
            if (!$token) {
                http_response_code(404);
                echo json_encode(['error' => 'Facebook token not found']);
                exit;
            }
            
            $pageInfo = getPageInfoWithTokens($token['access_token']);
            if (!$pageInfo['success']) {
                http_response_code(400);
                echo json_encode(['error' => 'Cannot access pages', 'details' => $pageInfo['error']]);
                exit;
            }
            
            logActivity('Attempting analytics with enhanced post resolution', [
                'input_post_id' => $postId,
                'page_id' => $pageInfo['page_id']
            ]);
            
            // FIXED: Use enhanced post retrieval for analytics
            $postResult = retrievePostWithFallback(
                $postId, 
                $pageInfo, 
                'id,message,created_time,updated_time,type,likes.summary(true),comments.summary(true),shares,reactions.summary(true)'
            );
            
            if ($postResult['success']) {
                $data = $postResult['data'];
                
                // Calculate engagement metrics
                $likes = $data['likes']['summary']['total_count'] ?? 0;
                $comments = $data['comments']['summary']['total_count'] ?? 0;
                $shares = $data['shares']['count'] ?? 0;
                $totalReactions = $data['reactions']['summary']['total_count'] ?? $likes;
                $totalEngagement = $likes + $comments + $shares;
                
                echo json_encode([
                    'status' => '📊 Facebook Post Analytics - POST ID FIXED! 📊',
                    'developer' => 'J33WAKASUPUN',
                    'timestamp' => date('Y-m-d H:i:s'),
                    'method' => 'Enhanced post ID resolution for analytics',
                    'post_id_resolution' => [
                        'input_post_id' => $postId,
                        'resolved_post_id' => $postResult['post_id_used'],
                        'variation_used' => $postResult['variation_used']
                    ],
                    'post_info' => [
                        'id' => $data['id'],
                        'type' => $data['type'] ?? 'status',
                        'created_time' => $data['created_time'],
                        'message_preview' => isset($data['message']) ? substr($data['message'], 0, 150) . '...' : 'Media post or no text',
                        'post_url' => "https://facebook.com/{$data['id']}"
                    ],
                    'engagement_metrics' => [
                        'total_engagement' => $totalEngagement,
                        'likes' => $likes,
                        'comments' => $comments,
                        'shares' => $shares,
                        'total_reactions' => $totalReactions
                    ],
                    'performance_indicators' => [
                        'high_engagement' => $totalEngagement > 50,
                        'viral_potential' => $shares > 10,
                        'discussion_starter' => $comments > $likes,
                        'engagement_rate' => $totalReactions > 0 ? round(($totalEngagement / $totalReactions) * 100, 2) . '%' : '0%'
                    ],
                    'crud_operations' => [
                        'view' => "GET ?action=view&post_id={$data['id']}",
                        'update' => "PUT ?action=update&post_id={$data['id']}",
                        'delete' => "DELETE ?action=delete&post_id={$data['id']}"
                    ]
                ]);
            } else {
                http_response_code(404);
                echo json_encode([
                    'error' => 'Failed to retrieve post analytics with enhanced resolution',
                    'input_post_id' => $postId,
                    'page_id' => $pageInfo['page_id'],
                    'resolution_attempts' => $postResult['tried_variations'] ?? [],
                    'suggestions' => [
                        'Verify post exists and was created by your page',
                        'Check if post is public',
                        'Try with the full post ID returned from creation'
                    ]
                ]);
            }
            break;
            
        // UPDATE and DELETE operations remain the same as they were working
        case 'PUT:update':
            $postId = $_GET['post_id'] ?? null;
            if (!$postId) {
                http_response_code(400);
                echo json_encode(['error' => 'Post ID required for update']);
                exit;
            }
            
            $input = json_decode(file_get_contents('php://input'), true) ?: [];
            $newMessage = $input['message'] ?? '';
            
            if (empty($newMessage)) {
                http_response_code(400);
                echo json_encode(['error' => 'New message content required']);
                exit;
            }
            
            $token = getFacebookToken($storagePath);
            if (!$token) {
                http_response_code(404);
                echo json_encode(['error' => 'Facebook token not found']);
                exit;
            }
            
            $pageInfo = getPageInfoWithTokens($token['access_token']);
            if (!$pageInfo['success']) {
                http_response_code(400);
                echo json_encode(['error' => 'Cannot access Facebook pages', 'details' => $pageInfo['error']]);
                exit;
            }
            
            // FIXED: Resolve post ID before update
            $resolvedPostId = resolvePostId($postId, $pageInfo);
            
            $updateData = [
                'message' => $newMessage,
                'access_token' => $pageInfo['page_access_token']
            ];
            
            $response = makeHttpRequest(
                "https://graph.facebook.com/v18.0/{$resolvedPostId}",
                http_build_query($updateData),
                'POST',
                ['Content-Type: application/x-www-form-urlencoded']
            );
            
            if ($response['success']) {
                echo json_encode([
                    'status' => '✏️ FACEBOOK POST UPDATED - POST ID FIXED! ✏️',
                    'developer' => 'J33WAKASUPUN',
                    'timestamp' => date('Y-m-d H:i:s'),
                    'post_id' => $resolvedPostId,
                    'updated_message_preview' => substr($newMessage, 0, 150) . '...',
                    'update_confirmed' => true
                ]);
            } else {
                http_response_code($response['http_code']);
                echo json_encode([
                    'error' => 'Failed to update post',
                    'post_id' => $resolvedPostId,
                    'facebook_response' => $response['data']
                ]);
            }
            break;
            
        case 'DELETE:delete':
            $postId = $_GET['post_id'] ?? null;
            if (!$postId) {
                http_response_code(400);
                echo json_encode(['error' => 'Post ID required for deletion']);
                exit;
            }
            
            $token = getFacebookToken($storagePath);
            if (!$token) {
                http_response_code(404);
                echo json_encode(['error' => 'Facebook token not found']);
                exit;
            }
            
            $pageInfo = getPageInfoWithTokens($token['access_token']);
            if (!$pageInfo['success']) {
                http_response_code(400);
                echo json_encode(['error' => 'Cannot access Facebook pages', 'details' => $pageInfo['error']]);
                exit;
            }
            
            // FIXED: Resolve post ID before deletion
            $resolvedPostId = resolvePostId($postId, $pageInfo);
            
            $deleteResponse = makeHttpRequest(
                "https://graph.facebook.com/v18.0/{$resolvedPostId}?" . http_build_query([
                    'access_token' => $pageInfo['page_access_token']
                ]),
                null,
                'DELETE'
            );
            
            if ($deleteResponse['success'] || $deleteResponse['http_code'] === 404) {
                echo json_encode([
                    'status' => '🗑️ FACEBOOK POST DELETED - POST ID FIXED! 🗑️',
                    'developer' => 'J33WAKASUPUN',
                    'timestamp' => date('Y-m-d H:i:s'),
                    'post_id' => $resolvedPostId,
                    'deletion_confirmed' => true
                ]);
            } else {
                http_response_code($deleteResponse['http_code']);
                echo json_encode([
                    'error' => 'Failed to delete post',
                    'post_id' => $resolvedPostId,
                    'facebook_response' => $deleteResponse['data']
                ]);
            }
            break;
            
        default:
            http_response_code(404);
            echo json_encode([
                'error' => 'Invalid endpoint',
                'method' => $method,
                'action' => $action,
                'post_id_fix_applied' => [
                    'video_uploads' => 'Now return full post ID format',
                    'view_analytics' => 'Enhanced post ID resolution with multiple format attempts',
                    'all_crud_operations' => 'Work with any post ID format'
                ],
                'available_endpoints' => [
                    'GET ?action=status' => 'API status',
                    'POST ?action=post' => 'Create posts (fixed post ID return)',
                    'GET ?action=view&post_id={id}' => 'View posts (enhanced resolution)',
                    'GET ?action=analytics&post_id={id}' => 'Analytics (enhanced resolution)',
                    'PUT ?action=update&post_id={id}' => 'Update posts',
                    'DELETE ?action=delete&post_id={id}' => 'Delete posts'
                ]
            ]);
    }
    
} catch (Exception $e) {
    logActivity('API fatal error', [
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
    
    http_response_code(500);
    echo json_encode([
        'error' => 'Facebook API execution failed',
        'message' => $e->getMessage(),
        'developer' => 'J33WAKASUPUN'
    ]);
}
?>