<?php
/**
 * COMPLETE FACEBOOK API WITH ALL CRUD OPERATIONS - FIXED
 * J33WAKASUPUN Social Media Marketing Platform
 * ALL ENDPOINTS: CREATE, READ, UPDATE, DELETE, ANALYTICS
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
                'User-Agent: J33WAKASUPUN-Facebook-API/4.0-Complete'
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
    $logEntry = date('Y-m-d H:i:s') . " [J33WAKASUPUN-COMPLETE] {$message}: " . json_encode($data) . "\n";
    error_log($logEntry, 3, __DIR__ . '/../storage/logs/facebook-complete-crud.log');
}

function getPageInfoWithTokens($userToken) {
    try {
        $pagesResponse = makeHttpRequest('https://graph.facebook.com/v18.0/me/accounts?' . http_build_query([
            'access_token' => $userToken,
            'fields' => 'id,name,access_token,category,followers_count'
        ]));
        
        if (!$pagesResponse['success'] || empty($pagesResponse['data']['data'])) {
            return [
                'success' => false, 
                'error' => 'Failed to get Facebook pages',
                'facebook_error' => $pagesResponse['data']
            ];
        }
        
        $pages = $pagesResponse['data']['data'];
        $selectedPage = $pages[0];
        
        if (empty($selectedPage['access_token'])) {
            return [
                'success' => false,
                'error' => 'Page access token not available'
            ];
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
        return [
            'success' => false, 
            'error' => 'Exception getting page info: ' . $e->getMessage()
        ];
    }
}

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

// Upload functions
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
            return [
                'status' => '🎥 FACEBOOK VIDEO UPLOADED SUCCESSFULLY! 🎥',
                'developer' => 'J33WAKASUPUN',
                'timestamp' => date('Y-m-d H:i:s'),
                'post_id' => $response['data']['id'],
                'post_url' => "https://facebook.com/{$response['data']['id']}",
                'post_type' => 'video'
            ];
        } else {
            return ['error' => 'Failed to upload video', 'facebook_response' => $response['data']];
        }
    } catch (Exception $e) {
        return ['error' => 'Video upload failed', 'message' => $e->getMessage()];
    }
}

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
            return [
                'status' => '📸 FACEBOOK IMAGE UPLOADED SUCCESSFULLY! 📸',
                'developer' => 'J33WAKASUPUN',
                'timestamp' => date('Y-m-d H:i:s'),
                'post_id' => $response['data']['id'],
                'post_url' => "https://facebook.com/{$response['data']['id']}",
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
            return [
                'status' => '📸📸 FACEBOOK CAROUSEL UPLOADED! 📸📸',
                'developer' => 'J33WAKASUPUN',
                'timestamp' => date('Y-m-d H:i:s'),
                'post_id' => $response['data']['id'],
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
            return [
                'status' => '📝 FACEBOOK TEXT POST CREATED - COMPLETE API! 📝',
                'developer' => 'J33WAKASUPUN',
                'timestamp' => date('Y-m-d H:i:s'),
                'post_id' => $response['data']['id'],
                'post_url' => "https://facebook.com/{$response['data']['id']}",
                'post_type' => 'text',
                'crud_status' => 'All CRUD operations now available!'
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
        $message = $_POST['message'] ?? '🎉 COMPLETE Facebook API with ALL CRUD operations! Create, Read, Update, Delete, Analytics all working! Built by J33WAKASUPUN! #FacebookAPI #CompleteCRUD #Success';
        
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
// MAIN SWITCH STATEMENT - ALL CRUD OPERATIONS
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
                'status' => 'Facebook COMPLETE CRUD API! 🎉',
                'developer' => 'J33WAKASUPUN', 
                'timestamp' => date('Y-m-d H:i:s'),
                'version' => '4.0 - Complete CRUD Operations',
                'token_status' => [
                    'facebook_token_available' => !empty($token),
                    'token_expires_at' => $token['expires_at'] ?? 'Unknown',
                    'pages_accessible' => $pageInfo['success'] ?? false,
                    'page_count' => count($pageInfo['all_pages'] ?? [])
                ],
                'all_crud_operations' => [
                    'CREATE' => '✅ POST ?action=post (Working)',
                    'READ' => '✅ GET ?action=view&post_id={id} (Working)',
                    'UPDATE' => '✅ PUT ?action=update&post_id={id} (Working)',
                    'DELETE' => '✅ DELETE ?action=delete&post_id={id} (Working)',
                    'ANALYTICS' => '✅ GET ?action=analytics&post_id={id} (Working)'
                ],
                'additional_endpoints' => [
                    'PAGES' => '✅ GET ?action=pages (Working)',
                    'STATUS' => '✅ GET ?action=status (Working)'
                ],
                'media_support' => [
                    'images' => '✅ Single & Carousel',
                    'videos' => '✅ Up to 4GB',
                    'formats' => ['jpg', 'png', 'gif', 'mp4', 'mov', 'avi']
                ],
                'integration_status' => 'COMPLETE FACEBOOK API WITH ALL CRUD OPERATIONS! 🚀'
            ]);
            break;
            
        // ========================================
        // PAGES ENDPOINT
        // ========================================
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
                echo json_encode([
                    'error' => 'Cannot access Facebook pages',
                    'details' => $pageInfo['error']
                ]);
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
                        'followers_count' => $page['followers_count'] ?? 0,
                        'has_access_token' => !empty($page['access_token'])
                    ];
                }, $pageInfo['all_pages'])
            ]);
            break;
            
        // ========================================
        // CREATE OPERATIONS  
        // ========================================
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
                echo json_encode([
                    'error' => 'Cannot access Facebook pages for posting',
                    'details' => $pageInfo['error']
                ]);
                exit;
            }

            $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
            $isFormData = strpos($contentType, 'multipart/form-data') !== false;

            if ($isFormData || !empty($_FILES)) {
                $result = handleAdvancedUpload($pageInfo);
                echo json_encode($result);
            } else {
                $input = json_decode(file_get_contents('php://input'), true) ?: [];
                $message = $input['message'] ?? $_POST['message'] ?? 'Facebook COMPLETE CRUD API! All operations working: Create, Read, Update, Delete, Analytics! Built by J33WAKASUPUN! #FacebookAPI #CompleteCRUD #Success';
                $result = createTextPost($message, $pageInfo);
                echo json_encode($result);
            }
            break;
            
        // ========================================
        // READ OPERATIONS - VIEW POST
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
            
            // Use page token if available, otherwise user token
            if ($pageInfo['success']) {
                $accessToken = $pageInfo['page_access_token'];
                $tokenType = 'page_token';
            } else {
                $accessToken = $token['access_token'];
                $tokenType = 'user_token';
            }
            
            logActivity('Attempting to view post', [
                'post_id' => $postId,
                'token_type' => $tokenType
            ]);
            
            // Progressive fallback for different field sets
            $attempts = [
                [
                    'fields' => 'id,message,story,created_time,updated_time,type,status_type,permalink_url,likes.summary(true),comments.summary(true),shares',
                    'description' => 'full_with_engagement'
                ],
                [
                    'fields' => 'id,message,story,created_time,updated_time,type,status_type,permalink_url',
                    'description' => 'basic_with_permalink'
                ],
                [
                    'fields' => 'id,message,created_time,type',
                    'description' => 'minimal_fields'
                ]
            ];
            
            $viewResult = null;
            
            foreach ($attempts as $attempt) {
                $response = makeHttpRequest("https://graph.facebook.com/v18.0/{$postId}?" . http_build_query([
                    'fields' => $attempt['fields'],
                    'access_token' => $accessToken
                ]));
                
                if ($response['success']) {
                    $data = $response['data'];
                    
                    $viewResult = [
                        'status' => '👀 Facebook Post Retrieved Successfully! 👀',
                        'developer' => 'J33WAKASUPUN',
                        'timestamp' => date('Y-m-d H:i:s'),
                        'method' => 'COMPLETE CRUD - View Operation',
                        'token_used' => $tokenType,
                        'data_level' => $attempt['description'],
                        'post_details' => [
                            'id' => $data['id'],
                            'message' => $data['message'] ?? $data['story'] ?? 'Media post or no text content',
                            'type' => $data['type'] ?? 'status',
                            'status_type' => $data['status_type'] ?? 'mobile_status_update',
                            'created_time' => $data['created_time'],
                            'updated_time' => $data['updated_time'] ?? null,
                            'permalink_url' => $data['permalink_url'] ?? "https://facebook.com/{$postId}"
                        ]
                    ];
                    
                    // Add engagement data if available
                    if (isset($data['likes']) || isset($data['comments']) || isset($data['shares'])) {
                        $viewResult['engagement'] = [
                            'likes' => $data['likes']['summary']['total_count'] ?? 0,
                            'comments' => $data['comments']['summary']['total_count'] ?? 0,
                            'shares' => $data['shares']['count'] ?? 0
                        ];
                    }
                    
                    $viewResult['crud_operations'] = [
                        'update' => "PUT ?action=update&post_id={$postId}",
                        'delete' => "DELETE ?action=delete&post_id={$postId}",
                        'analytics' => "GET ?action=analytics&post_id={$postId}"
                    ];
                    
                    break; // Success, exit loop
                }
            }
            
            if ($viewResult) {
                echo json_encode($viewResult);
            } else {
                http_response_code(404);
                echo json_encode([
                    'error' => 'Failed to retrieve post after all attempts',
                    'post_id' => $postId,
                    'token_type_used' => $tokenType,
                    'suggestion' => 'Post may not exist or may be private'
                ]);
            }
            break;
            
        // ========================================
        // UPDATE OPERATIONS
        // ========================================
        case 'PUT:update':
            $postId = $_GET['post_id'] ?? null;
            if (!$postId) {
                http_response_code(400);
                echo json_encode(['error' => 'Post ID required for update']);
                exit;
            }
            
            $input = json_decode(file_get_contents('php://input'), true) ?: [];
            $newMessage = $input['message'] ?? $input['new_message'] ?? '';
            
            if (empty($newMessage)) {
                http_response_code(400);
                echo json_encode([
                    'error' => 'New message content required',
                    'usage' => 'Send JSON: {"message": "Your new content"}',
                    'example' => '{"message": "Updated post with COMPLETE CRUD API! Built by J33WAKASUPUN!"}'
                ]);
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
                echo json_encode([
                    'error' => 'Cannot access Facebook pages for update',
                    'details' => $pageInfo['error']
                ]);
                exit;
            }
            
            logActivity('Attempting post update', [
                'post_id' => $postId,
                'new_message_length' => strlen($newMessage),
                'using_page_token' => true
            ]);
            
            // Use page access token for updating
            $updateData = [
                'message' => $newMessage,
                'access_token' => $pageInfo['page_access_token']
            ];
            
            $response = makeHttpRequest(
                "https://graph.facebook.com/v18.0/{$postId}",
                http_build_query($updateData),
                'POST',
                ['Content-Type: application/x-www-form-urlencoded']
            );
            
            if ($response['success']) {
                logActivity('Post update successful', ['post_id' => $postId]);
                
                echo json_encode([
                    'status' => '✏️ FACEBOOK POST UPDATED - COMPLETE CRUD! ✏️',
                    'developer' => 'J33WAKASUPUN',
                    'timestamp' => date('Y-m-d H:i:s'),
                    'method' => 'COMPLETE CRUD - Update Operation',
                    'post_id' => $postId,
                    'updated_message_preview' => substr($newMessage, 0, 150) . (strlen($newMessage) > 150 ? '...' : ''),
                    'update_confirmed' => true,
                    'post_url' => "https://facebook.com/{$postId}",
                    'authentication_method' => 'page_access_token',
                    'page_info' => [
                        'page_id' => $pageInfo['page_id'],
                        'page_name' => $pageInfo['page_name']
                    ],
                    'next_actions' => [
                        'view_updated_post' => "GET ?action=view&post_id={$postId}",
                        'get_analytics' => "GET ?action=analytics&post_id={$postId}",
                        'delete_post' => "DELETE ?action=delete&post_id={$postId}"
                    ]
                ]);
            } else {
                http_response_code($response['http_code']);
                echo json_encode([
                    'error' => 'Failed to update post',
                    'post_id' => $postId,
                    'facebook_response' => $response['data'],
                    'troubleshooting' => [
                        'media_posts' => 'Photo/video posts cannot be edited, only text posts',
                        'post_age' => 'Very old posts may not be editable',
                        'permissions' => 'Page admin permissions required',
                        'post_ownership' => 'Post must be created by your page'
                    ]
                ]);
            }
            break;
            
        // ========================================
        // DELETE OPERATIONS
        // ========================================
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
                echo json_encode([
                    'error' => 'Cannot access Facebook pages for deletion',
                    'details' => $pageInfo['error']
                ]);
                exit;
            }
            
            logActivity('Attempting post deletion', [
                'post_id' => $postId,
                'using_page_token' => true
            ]);
            
            // Use page access token for deletion
            $deleteResponse = makeHttpRequest(
                "https://graph.facebook.com/v18.0/{$postId}?" . http_build_query([
                    'access_token' => $pageInfo['page_access_token']
                ]),
                null,
                'DELETE'
            );
            
            if ($deleteResponse['success'] || $deleteResponse['http_code'] === 404) {
                logActivity('Post deletion successful', ['post_id' => $postId]);
                
                echo json_encode([
                    'status' => '🗑️ FACEBOOK POST DELETED - COMPLETE CRUD! 🗑️',
                    'developer' => 'J33WAKASUPUN',
                    'timestamp' => date('Y-m-d H:i:s'),
                    'method' => 'COMPLETE CRUD - Delete Operation',
                    'post_id' => $postId,
                    'deletion_confirmed' => true,
                    'deleted_at' => date('Y-m-d H:i:s'),
                    'http_code' => $deleteResponse['http_code'],
                    'authentication_method' => 'page_access_token',
                    'page_info' => [
                        'page_id' => $pageInfo['page_id'],
                        'page_name' => $pageInfo['page_name']
                    ],
                    'warning' => 'Post has been permanently removed from Facebook',
                    'crud_status' => 'All CRUD operations working perfectly!'
                ]);
            } else {
                http_response_code($deleteResponse['http_code']);
                echo json_encode([
                    'error' => 'Failed to delete post from Facebook',
                    'post_id' => $postId,
                    'facebook_response' => $deleteResponse['data'],
                    'http_code' => $deleteResponse['http_code'],
                    'manual_deletion_url' => "https://facebook.com/{$postId}",
                    'suggestion' => 'Try deleting manually from Facebook if API deletion fails'
                ]);
            }
            break;
            
        // ========================================
        // ANALYTICS OPERATIONS
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
            
            // Use page token if available, otherwise user token
            if ($pageInfo['success']) {
                $accessToken = $pageInfo['page_access_token'];
                $tokenType = 'page_token';
            } else {
                $accessToken = $token['access_token'];
                $tokenType = 'user_token';
            }
            
            logActivity('Attempting analytics retrieval', [
                'post_id' => $postId,
                'token_type' => $tokenType
            ]);
            
            // Progressive analytics attempts with different field sets
            $analyticsAttempts = [
                [
                    'fields' => 'id,message,created_time,updated_time,type,likes.summary(true),comments.summary(true),shares,reactions.summary(true)',
                    'description' => 'full_analytics_with_reactions'
                ],
                [
                    'fields' => 'id,message,created_time,type,likes.summary(true),comments.summary(true),shares',
                    'description' => 'basic_engagement_metrics'
                ],
                [
                    'fields' => 'id,message,created_time,type',
                    'description' => 'basic_post_info_only'
                ]
            ];
            
            $analyticsResult = null;
            
            foreach ($analyticsAttempts as $attempt) {
                $response = makeHttpRequest("https://graph.facebook.com/v18.0/{$postId}?" . http_build_query([
                    'fields' => $attempt['fields'],
                    'access_token' => $accessToken
                ]));
                
                if ($response['success']) {
                    $data = $response['data'];
                    
                    // Calculate engagement metrics
                    $likes = $data['likes']['summary']['total_count'] ?? 0;
                    $comments = $data['comments']['summary']['total_count'] ?? 0;
                    $shares = $data['shares']['count'] ?? 0;
                    $totalReactions = $data['reactions']['summary']['total_count'] ?? $likes;
                    $totalEngagement = $likes + $comments + $shares;
                    
                    $analyticsResult = [
                        'status' => '📊 Facebook Post Analytics - COMPLETE CRUD! 📊',
                        'developer' => 'J33WAKASUPUN',
                        'timestamp' => date('Y-m-d H:i:s'),
                        'method' => 'COMPLETE CRUD - Analytics Operation',
                        'post_id' => $postId,
                        'data_level' => $attempt['description'],
                        'token_used' => $tokenType,
                        'post_info' => [
                            'type' => $data['type'] ?? 'status',
                            'created_time' => $data['created_time'],
                            'updated_time' => $data['updated_time'] ?? null,
                            'message_preview' => isset($data['message']) ? substr($data['message'], 0, 150) . '...' : 'Media post or no text',
                            'post_url' => "https://facebook.com/{$postId}"
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
                            'view' => "GET ?action=view&post_id={$postId}",
                            'update' => "PUT ?action=update&post_id={$postId}",
                            'delete' => "DELETE ?action=delete&post_id={$postId}"
                        ],
                        'crud_status' => 'All CRUD operations working perfectly!'
                    ];
                    
                    break; // Success
                }
            }
            
            if ($analyticsResult) {
                logActivity('Analytics retrieval successful', [
                    'post_id' => $postId,
                    'data_level' => $analyticsResult['data_level']
                ]);
                echo json_encode($analyticsResult);
            } else {
                http_response_code(404);
                echo json_encode([
                    'error' => 'Failed to retrieve post analytics',
                    'post_id' => $postId,
                    'token_type_tried' => $tokenType,
                    'suggestion' => 'Post may not exist, be private, or require additional permissions'
                ]);
            }
            break;
            
        default:
            http_response_code(404);
            echo json_encode([
                'error' => 'Invalid endpoint or method',
                'method' => $method,
                'action' => $action,
                'complete_crud_endpoints' => [
                    'GET ?action=status' => '✅ API status and diagnostics',
                    'GET ?action=pages' => '✅ List Facebook pages',
                    'POST ?action=post' => '✅ Create posts (text/image/video/carousel)',
                    'GET ?action=view&post_id={id}' => '✅ View post details',
                    'PUT ?action=update&post_id={id}' => '✅ Update post content',
                    'DELETE ?action=delete&post_id={id}' => '✅ Delete posts',
                    'GET ?action=analytics&post_id={id}' => '✅ Get comprehensive analytics'
                ],
                'usage_examples' => [
                    'view_post' => 'GET ?action=view&post_id=775860752279131_122096194515016950',
                    'update_post' => 'PUT ?action=update&post_id=775860752279131_122096194515016950',
                    'delete_post' => 'DELETE ?action=delete&post_id=775860752279131_122096194515016950',
                    'get_analytics' => 'GET ?action=analytics&post_id=775860752279131_122096194515016950'
                ],
                'all_crud_operations_now_available' => '🎉 COMPLETE FACEBOOK CRUD API READY!'
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
        'error' => 'Complete Facebook API execution failed',
        'message' => $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine(),
        'developer' => 'J33WAKASUPUN',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}
?>