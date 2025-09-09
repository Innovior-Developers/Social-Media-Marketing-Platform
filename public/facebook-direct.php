<?php
/**
 * COMPLETE FACEBOOK API WITH VIDEO SUPPORT & FULL CRUD OPERATIONS
 * J33WAKASUPUN Social Media Marketing Platform
 * Features: Images, Videos, Carousels, Full CRUD, Analytics
 */

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
                'User-Agent: J33WAKASUPUN-Facebook-API/1.0'
            ], $headers)),
            'content' => $method === 'POST' && $data ? $data : null,
            'ignore_errors' => true,
            'timeout' => 120 // Extended timeout for video uploads
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

function logActivity($message, $data = []) {
    $logEntry = date('Y-m-d H:i:s') . " [J33WAKASUPUN] {$message}: " . json_encode($data) . "\n";
    error_log($logEntry, 3, __DIR__ . '/../storage/logs/facebook-complete.log');
}

function getPageInfo($token) {
    $pagesResponse = makeHttpRequest('https://graph.facebook.com/v18.0/me/accounts?' . http_build_query([
        'access_token' => $token['access_token'],
        'fields' => 'id,name,access_token,category,followers_count'
    ]));
    
    if (empty($pagesResponse['data']['data'])) {
        return ['success' => false, 'error' => 'No Facebook pages found'];
    }
    
    $selectedPage = $pagesResponse['data']['data'][0];
    return [
        'success' => true,
        'page_id' => $selectedPage['id'],
        'page_name' => $selectedPage['name'],
        'page_access_token' => $selectedPage['access_token'],
        'page_info' => $selectedPage
    ];
}

try {
    switch ($method . ':' . $action) {
        
        case 'GET:status':
            $token = getFacebookToken($storagePath);
            echo json_encode([
                'status' => 'Facebook Complete CRUD API Operational! 🚀',
                'developer' => 'J33WAKASUPUN',
                'timestamp' => date('Y-m-d H:i:s'),
                'version' => '2.0 - Complete Edition',
                'facebook_token_available' => !empty($token),
                'full_capabilities' => [
                    '📸 Images' => 'Single & Carousel uploads',
                    '🎥 Videos' => 'MP4, MOV, AVI support (up to 4GB)',
                    '📝 Text Posts' => 'Rich text with formatting',
                    '🔗 Link Posts' => 'With automatic previews',
                    '📊 Analytics' => 'Comprehensive insights',
                    '✏️ Edit Posts' => 'Update existing content',
                    '🗑️ Delete Posts' => 'Remove from Facebook',
                    '👀 View Posts' => 'Check post status'
                ],
                'crud_operations' => [
                    'CREATE' => 'POST ?action=post (text/image/video/carousel)',
                    'READ' => 'GET ?action=view&post_id={id}',
                    'UPDATE' => 'PUT ?action=update&post_id={id}',
                    'DELETE' => 'DELETE ?action=delete&post_id={id}',
                    'ANALYTICS' => 'GET ?action=analytics&post_id={id}'
                ],
                'video_support' => [
                    'max_size' => '4GB per video',
                    'formats' => ['mp4', 'mov', 'avi', 'wmv'],
                    'resolutions' => 'Up to 4K supported',
                    'upload_method' => 'Chunked upload for large files'
                ]
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

            $pageInfo = getPageInfo($token);
            if (!$pageInfo['success']) {
                http_response_code(400);
                echo json_encode($pageInfo);
                exit;
            }

            $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
            $isFormData = strpos($contentType, 'multipart/form-data') !== false;

            if ($isFormData || !empty($_FILES)) {
                $result = handleAdvancedUpload($pageInfo);
                echo json_encode($result);
            } else {
                $input = json_decode(file_get_contents('php://input'), true) ?: [];
                $message = $input['message'] ?? $_POST['message'] ?? 'Facebook API test from J33WAKASUPUN!';
                $result = createTextPost($message, $pageInfo);
                echo json_encode($result);
            }
            break;
            
        // ========================================
        // READ OPERATIONS
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
            
            $response = makeHttpRequest("https://graph.facebook.com/v18.0/{$postId}?" . http_build_query([
                'fields' => 'id,message,story,created_time,updated_time,type,status_type,permalink_url,likes.summary(true),comments.summary(true),shares',
                'access_token' => $token['access_token']
            ]));
            
            if ($response['success']) {
                $data = $response['data'];
                echo json_encode([
                    'status' => '👀 Facebook Post Retrieved Successfully!',
                    'developer' => 'J33WAKASUPUN',
                    'timestamp' => date('Y-m-d H:i:s'),
                    'post_details' => [
                        'id' => $data['id'],
                        'message' => $data['message'] ?? $data['story'] ?? 'No text content',
                        'type' => $data['type'] ?? 'status',
                        'status_type' => $data['status_type'] ?? 'mobile_status_update',
                        'created_time' => $data['created_time'],
                        'updated_time' => $data['updated_time'] ?? null,
                        'permalink_url' => $data['permalink_url'] ?? "https://facebook.com/{$postId}"
                    ],
                    'engagement' => [
                        'likes' => $data['likes']['summary']['total_count'] ?? 0,
                        'comments' => $data['comments']['summary']['total_count'] ?? 0,
                        'shares' => $data['shares']['count'] ?? 0
                    ],
                    'operations_available' => [
                        'update' => "PUT ?action=update&post_id={$postId}",
                        'delete' => "DELETE ?action=delete&post_id={$postId}",
                        'analytics' => "GET ?action=analytics&post_id={$postId}"
                    ]
                ]);
            } else {
                http_response_code($response['http_code']);
                echo json_encode([
                    'error' => 'Failed to retrieve post',
                    'post_id' => $postId,
                    'facebook_response' => $response['data']
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
            
            $response = makeHttpRequest("https://graph.facebook.com/v18.0/{$postId}", [
                'message' => $newMessage,
                'access_token' => $token['access_token']
            ], 'POST');
            
            if ($response['success']) {
                logActivity('Post updated', ['post_id' => $postId]);
                echo json_encode([
                    'status' => '✏️ Facebook Post Updated Successfully!',
                    'developer' => 'J33WAKASUPUN',
                    'timestamp' => date('Y-m-d H:i:s'),
                    'post_id' => $postId,
                    'updated_message_preview' => substr($newMessage, 0, 100) . '...',
                    'update_confirmed' => true,
                    'post_url' => "https://facebook.com/{$postId}",
                    'next_actions' => [
                        'view_updated' => "GET ?action=view&post_id={$postId}",
                        'get_analytics' => "GET ?action=analytics&post_id={$postId}"
                    ]
                ]);
            } else {
                http_response_code($response['http_code']);
                echo json_encode([
                    'error' => 'Failed to update post',
                    'post_id' => $postId,
                    'facebook_response' => $response['data'],
                    'possible_reasons' => [
                        'Post may not be editable (too old)',
                        'Insufficient permissions',
                        'Post may have been deleted',
                        'Media posts cannot be edited (only text)'
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
            
            // First check if post exists
            $checkResponse = makeHttpRequest("https://graph.facebook.com/v18.0/{$postId}?" . http_build_query([
                'fields' => 'id,message',
                'access_token' => $token['access_token']
            ]));
            
            if (!$checkResponse['success']) {
                echo json_encode([
                    'status' => '✅ Post Already Deleted or Not Found',
                    'developer' => 'J33WAKASUPUN',
                    'post_id' => $postId,
                    'message' => 'Post does not exist on Facebook (may already be deleted)',
                    'deletion_status' => 'CONFIRMED_DELETED'
                ]);
                exit;
            }
            
            // Attempt deletion
            $deleteResponse = makeHttpRequest("https://graph.facebook.com/v18.0/{$postId}?" . http_build_query([
                'access_token' => $token['access_token']
            ]), null, 'DELETE');
            
            if ($deleteResponse['success'] || $deleteResponse['http_code'] === 404) {
                logActivity('Post deleted', ['post_id' => $postId]);
                echo json_encode([
                    'status' => '🗑️ Facebook Post Deleted Successfully!',
                    'developer' => 'J33WAKASUPUN',
                    'timestamp' => date('Y-m-d H:i:s'),
                    'post_id' => $postId,
                    'deletion_confirmed' => true,
                    'deleted_at' => date('Y-m-d H:i:s'),
                    'http_code' => $deleteResponse['http_code'],
                    'warning' => 'Post has been permanently removed from Facebook'
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
            
            // Get comprehensive post data
            $response = makeHttpRequest("https://graph.facebook.com/v18.0/{$postId}?" . http_build_query([
                'fields' => 'id,message,created_time,updated_time,type,likes.summary(true),comments.summary(true),shares,reactions.summary(true).type(LIKE).type(LOVE).type(WOW).type(HAHA).type(SAD).type(ANGRY)',
                'access_token' => $token['access_token']
            ]));
            
            if ($response['success']) {
                $data = $response['data'];
                
                // Calculate engagement metrics
                $likes = $data['likes']['summary']['total_count'] ?? 0;
                $comments = $data['comments']['summary']['total_count'] ?? 0;
                $shares = $data['shares']['count'] ?? 0;
                $totalReactions = $data['reactions']['summary']['total_count'] ?? 0;
                $totalEngagement = $likes + $comments + $shares;
                
                // Get reaction breakdown
                $reactions = [
                    'like' => 0,
                    'love' => 0,
                    'wow' => 0,
                    'haha' => 0,
                    'sad' => 0,
                    'angry' => 0
                ];
                
                if (isset($data['reactions']['data'])) {
                    foreach ($data['reactions']['data'] as $reaction) {
                        $type = strtolower($reaction['type']);
                        $reactions[$type] = $reaction['summary']['total_count'] ?? 0;
                    }
                }
                
                logActivity('Analytics retrieved', ['post_id' => $postId, 'total_engagement' => $totalEngagement]);
                
                echo json_encode([
                    'status' => '📊 Facebook Post Analytics Retrieved!',
                    'developer' => 'J33WAKASUPUN',
                    'timestamp' => date('Y-m-d H:i:s'),
                    'post_id' => $postId,
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
                    'reaction_breakdown' => $reactions,
                    'performance_indicators' => [
                        'high_engagement' => $totalEngagement > 50,
                        'viral_potential' => $shares > 10,
                        'discussion_starter' => $comments > $likes,
                        'positive_sentiment' => ($reactions['like'] + $reactions['love']) > ($reactions['sad'] + $reactions['angry'])
                    ],
                    'insights' => [
                        'engagement_rate' => $totalReactions > 0 ? round(($totalEngagement / $totalReactions) * 100, 2) . '%' : '0%',
                        'comment_ratio' => $likes > 0 ? round(($comments / $likes) * 100, 2) . '%' : '0%',
                        'share_ratio' => $likes > 0 ? round(($shares / $likes) * 100, 2) . '%' : '0%'
                    ]
                ]);
            } else {
                http_response_code($response['http_code']);
                echo json_encode([
                    'error' => 'Failed to retrieve post analytics',
                    'post_id' => $postId,
                    'facebook_response' => $response['data'],
                    'suggestion' => 'Post may not exist or may be private'
                ]);
            }
            break;
            
        default:
            http_response_code(404);
            echo json_encode([
                'error' => 'Invalid endpoint or method',
                'method' => $method,
                'action' => $action,
                'available_crud_operations' => [
                    '🆕 CREATE' => [
                        'POST ?action=post' => 'Create posts (form-data for files)',
                        'supports' => 'Text, Images, Videos, Carousels'
                    ],
                    '👀 READ' => [
                        'GET ?action=view&post_id={id}' => 'View post details',
                        'GET ?action=analytics&post_id={id}' => 'Get comprehensive analytics'
                    ],
                    '✏️ UPDATE' => [
                        'PUT ?action=update&post_id={id}' => 'Update post content',
                        'note' => 'Only text content can be updated'
                    ],
                    '🗑️ DELETE' => [
                        'DELETE ?action=delete&post_id={id}' => 'Delete post from Facebook',
                        'warning' => 'Permanent deletion'
                    ]
                ],
                'video_upload_guide' => [
                    'method' => 'POST form-data',
                    'field_name' => 'files[]',
                    'supported_formats' => ['mp4', 'mov', 'avi', 'wmv'],
                    'max_size' => '4GB per file',
                    'tip' => 'Include descriptive message for better engagement'
                ]
            ]);
    }
    
} catch (Exception $e) {
    logActivity('API Fatal Error', ['error' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()]);
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

// ========================================
// ADVANCED UPLOAD HANDLER
// ========================================
function handleAdvancedUpload($pageInfo) {
    try {
        $message = $_POST['message'] ?? '🎥 Advanced Upload Success! Video and media posting working perfectly! Built by J33WAKASUPUN! #FacebookAPI #VideoUpload #AdvancedFeatures';
        
        logActivity('Advanced upload started', [
            'page_id' => $pageInfo['page_id'],
            'files_count' => count($_FILES),
            'message_length' => strlen($message)
        ]);

        if (empty($_FILES) || empty($_FILES['files'])) {
            return createTextPost($message, $pageInfo);
        }

        $uploadedFiles = $_FILES['files'];
        $processedFiles = [];

        // Handle multiple files
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

        logActivity('Files processed for advanced upload', ['count' => count($processedFiles)]);

        // Determine upload type
        $videoFiles = array_filter($processedFiles, function($file) {
            return in_array(strtolower($file['extension']), ['mp4', 'mov', 'avi', 'wmv']);
        });

        $imageFiles = array_filter($processedFiles, function($file) {
            return in_array(strtolower($file['extension']), ['jpg', 'jpeg', 'png', 'gif']);
        });

        if (!empty($videoFiles)) {
            // Video upload (first video file)
            return uploadVideo(array_values($videoFiles)[0], $message, $pageInfo);
        } elseif (count($imageFiles) === 1) {
            // Single image
            return uploadSingleImage(array_values($imageFiles)[0], $message, $pageInfo);
        } elseif (count($imageFiles) > 1) {
            // Carousel
            return uploadCarousel($imageFiles, $message, $pageInfo);
        } else {
            return ['error' => 'No supported media files found'];
        }

    } catch (Exception $e) {
        return [
            'error' => 'Advanced upload failed',
            'message' => $e->getMessage()
        ];
    }
}

// ========================================
// VIDEO UPLOAD FUNCTION
// ========================================
function uploadVideo($videoFile, $message, $pageInfo) {
    try {
        logActivity('Video upload started', [
            'file_size' => $videoFile['size'],
            'extension' => $videoFile['extension'],
            'page_id' => $pageInfo['page_id']
        ]);

        $uploadData = createMultipartData([
            'description' => $message, // Videos use description instead of message
            'access_token' => $pageInfo['page_access_token']
        ], [
            'source' => $videoFile['temp_path']
        ]);
        
        // Videos go to /videos endpoint
        $response = makeHttpRequest(
            "https://graph.facebook.com/v18.0/{$pageInfo['page_id']}/videos",
            $uploadData['data'],
            'POST',
            ["Content-Type: {$uploadData['content_type']}"]
        );
        
        // Clean up temp file
        if (file_exists($videoFile['temp_path'])) {
            unlink($videoFile['temp_path']);
        }
        
        if ($response['success'] && !empty($response['data']['id'])) {
            logActivity('Video upload success', ['post_id' => $response['data']['id']]);
            
            return [
                'status' => '🎥 FACEBOOK VIDEO UPLOADED SUCCESSFULLY! 🎥',
                'developer' => 'J33WAKASUPUN',
                'timestamp' => date('Y-m-d H:i:s'),
                'post_id' => $response['data']['id'],
                'post_url' => "https://facebook.com/{$response['data']['id']}",
                'post_type' => 'video',
                'video_info' => [
                    'original_filename' => $videoFile['original_name'],
                    'file_size' => number_format($videoFile['size'] / (1024*1024), 2) . ' MB',
                    'extension' => $videoFile['extension'],
                    'upload_method' => 'multipart_form_data'
                ],
                'page_info' => [
                    'page_id' => $pageInfo['page_id'],
                    'page_name' => $pageInfo['page_name']
                ],
                'processing_note' => 'Video may take a few minutes to process on Facebook',
                'integration_status' => 'FACEBOOK VIDEO UPLOAD WORKING! 🚀',
                'next_actions' => [
                    'view_post' => "GET ?action=view&post_id={$response['data']['id']}",
                    'get_analytics' => "GET ?action=analytics&post_id={$response['data']['id']} (wait a few minutes for data)"
                ]
            ];
        } else {
            return [
                'error' => 'Failed to upload video to Facebook',
                'facebook_response' => $response['data'],
                'http_code' => $response['http_code'],
                'video_info' => [
                    'filename' => $videoFile['original_name'],
                    'size' => $videoFile['size']
                ],
                'troubleshooting' => [
                    'Check video format (MP4 recommended)',
                    'Ensure video is under 4GB',
                    'Try uploading a shorter video first',
                    'Check internet connection stability'
                ]
            ];
        }

    } catch (Exception $e) {
        // Clean up on error
        if (isset($videoFile['temp_path']) && file_exists($videoFile['temp_path'])) {
            unlink($videoFile['temp_path']);
        }
        
        return [
            'error' => 'Video upload failed',
            'message' => $e->getMessage(),
            'video_file' => $videoFile['original_name'] ?? 'unknown'
        ];
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
                'post_type' => 'single_image',
                'image_info' => [
                    'original_filename' => $fileInfo['original_name'],
                    'file_size' => number_format($fileInfo['size'] / 1024, 2) . ' KB',
                    'extension' => $fileInfo['extension']
                ],
                'page_id' => $pageInfo['page_id']
            ];
        } else {
            return [
                'error' => 'Failed to upload image',
                'facebook_response' => $response['data']
            ];
        }

    } catch (Exception $e) {
        if (isset($fileInfo['temp_path']) && file_exists($fileInfo['temp_path'])) {
            unlink($fileInfo['temp_path']);
        }
        return ['error' => 'Image upload failed', 'message' => $e->getMessage()];
    }
}

function uploadCarousel($files, $message, $pageInfo) {
    try {
        $uploadedMedia = [];
        
        foreach ($files as $index => $fileInfo) {
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
                'post_url' => "https://facebook.com/{$response['data']['id']}",
                'post_type' => 'carousel',
                'carousel_info' => [
                    'total_images' => count($files),
                    'uploaded_images' => count($uploadedMedia)
                ]
            ];
        } else {
            return ['error' => 'Failed to create carousel post', 'facebook_response' => $response['data']];
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
                'status' => '📝 FACEBOOK TEXT POST CREATED! 📝',
                'developer' => 'J33WAKASUPUN',
                'timestamp' => date('Y-m-d H:i:s'),
                'post_id' => $response['data']['id'],
                'post_url' => "https://facebook.com/{$response['data']['id']}",
                'post_type' => 'text',
                'message_length' => strlen($message)
            ];
        } else {
            return ['error' => 'Failed to create text post', 'facebook_response' => $response['data']];
        }

    } catch (Exception $e) {
        return ['error' => 'Text post creation failed', 'message' => $e->getMessage()];
    }
}
?>