<?php
// routes/posts.php - POST MANAGEMENT ROUTES

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Log;
use App\Helpers\LinkedInHelpers;

/*
|--------------------------------------------------------------------------
| Post Management Routes
|--------------------------------------------------------------------------
|
| These routes handle comprehensive post management functionality including:
| - Retrieving all posts with analytics
| - Updating posts with LinkedIn API limitations handling
| - Deleting posts from LinkedIn platform
| - Enhanced status checking and verification
| - Manual status confirmation
| - Post details with analytics breakdown
|
| Developer: J33WAKASUPUN
| Last Updated: 2025-09-08
| Middleware: CSRF-free for API compatibility
|
*/

Route::withoutMiddleware([
    'web',
    \App\Http\Middleware\VerifyCsrfToken::class,
    \Illuminate\Session\Middleware\StartSession::class
])->group(function () {

    Route::prefix('test/posts')->group(function () {
        
        // 📊 GET ALL POSTS WITH ANALYTICS
        Route::get('/all/{userId?}', function ($userId = 'system_test') {
            try {
                // Fetch posts for specified user
                $posts = \App\Models\SocialMediaPost::where('user_id', $userId)
                    ->orderBy('created_at', 'desc')
                    ->get();

                if ($posts->isEmpty()) {
                    return response()->json([
                        'test_type' => 'Posts Retrieval Test',
                        'posts_retrieval' => 'NO_POSTS_FOUND',
                        'message' => 'No posts found for the specified user',
                        'user_id' => $userId,
                        'suggestion' => 'Create posts using LinkedIn posting endpoints first',
                        'available_endpoints' => [
                            'text_post' => 'POST /test/linkedin/post/{sessionKey}',
                            'multi_image_post' => 'POST /test/linkedin/multi-image-post/{tokenFile}'
                        ],
                        'timestamp' => now()->toISOString(),
                        'developer' => 'J33WAKASUPUN'
                    ]);
                }

                // Fetch analytics for all posts
                $analytics = \App\Models\PostAnalytics::whereIn('social_media_post_id', $posts->pluck('_id'))
                    ->get()
                    ->groupBy('social_media_post_id');

                // Combine posts with their analytics
                $postsWithAnalytics = $posts->map(function ($post) use ($analytics) {
                    $postAnalytics = $analytics->get($post->_id, collect());
                    
                    // Calculate engagement totals
                    $totalEngagement = 0;
                    if (method_exists($post, 'getTotalEngagement')) {
                        $totalEngagement = $post->getTotalEngagement();
                    } elseif ($postAnalytics->isNotEmpty()) {
                        $latestAnalytics = $postAnalytics->sortByDesc('collected_at')->first();
                        if ($latestAnalytics && isset($latestAnalytics->metrics)) {
                            $metrics = $latestAnalytics->metrics;
                            $totalEngagement = ($metrics['likes'] ?? 0) + 
                                             ($metrics['shares'] ?? 0) + 
                                             ($metrics['comments'] ?? 0);
                        }
                    }

                    return [
                        'id' => $post->_id,
                        'content' => $post->content,
                        'hashtags' => $post->hashtags ?? [],
                        'mentions' => $post->mentions ?? [],
                        'platforms' => $post->platforms ?? [],
                        'post_status' => $post->post_status,
                        'created_at' => $post->created_at,
                        'published_at' => $post->published_at,
                        'platform_posts' => $post->platform_posts ?? [],
                        'engagement' => $post->engagement ?? [],
                        'settings' => $post->settings ?? [],
                        'media_count' => count($post->media ?? []),
                        'analytics_summary' => [
                            'records_count' => $postAnalytics->count(),
                            'latest_collection' => $postAnalytics->sortByDesc('collected_at')->first()?->collected_at,
                            'platforms_analyzed' => $postAnalytics->pluck('platform')->unique()->values(),
                            'average_performance' => $postAnalytics->avg('performance_score') ?? 0
                        ],
                        'total_engagement' => $totalEngagement,
                        'linkedin_info' => isset($post->platform_posts['linkedin']) ? [
                            'platform_id' => $post->platform_posts['linkedin']['platform_id'] ?? null,
                            'url' => $post->platform_posts['linkedin']['url'] ?? null,
                            'published_at' => $post->platform_posts['linkedin']['published_at'] ?? null,
                            'status_verified' => $post->linkedin_status_verified ?? false
                        ] : null
                    ];
                });

                // Calculate summary statistics
                $statusBreakdown = [
                    'published' => $posts->where('post_status', 'published')->count(),
                    'draft' => $posts->where('post_status', 'draft')->count(),
                    'scheduled' => $posts->where('post_status', 'scheduled')->count(),
                    'deleted' => $posts->where('post_status', 'deleted')->count(),
                    'deleted_on_platform' => $posts->where('post_status', 'deleted_on_platform')->count(),
                    'failed' => $posts->where('post_status', 'failed')->count()
                ];

                $platformBreakdown = [];
                foreach ($posts as $post) {
                    foreach ($post->platforms ?? [] as $platform) {
                        $platformBreakdown[$platform] = ($platformBreakdown[$platform] ?? 0) + 1;
                    }
                }

                return response()->json([
                    'test_type' => 'Posts Retrieval Test',
                    'posts_retrieval' => 'SUCCESS! 📊',
                    'user_id' => $userId,
                    'summary' => [
                        'total_posts' => $posts->count(),
                        'posts_with_analytics' => $analytics->count(),
                        'total_analytics_records' => $analytics->flatten()->count(),
                        'linkedin_posts' => $posts->filter(function($post) {
                            return isset($post->platform_posts['linkedin']);
                        })->count()
                    ],
                    'status_breakdown' => $statusBreakdown,
                    'platform_breakdown' => $platformBreakdown,
                    'posts' => $postsWithAnalytics,
                    'pagination_info' => [
                        'current_page' => 1,
                        'per_page' => $posts->count(),
                        'total_pages' => 1,
                        'has_more' => false
                    ],
                    'available_actions' => [
                        'update_post' => 'PUT /test/posts/update/{postId}',
                        'delete_from_linkedin' => 'DELETE /test/posts/delete-from-linkedin/{postId}',
                        'check_linkedin_status' => 'GET /test/posts/linkedin-status-enhanced/{postId}',
                        'view_post_details' => 'GET /test/posts/details/{postId}'
                    ],
                    'timestamp' => now()->toISOString(),
                    'developer' => 'J33WAKASUPUN'
                ]);

            } catch (\Exception $e) {
                Log::error('Posts Retrieval: Exception occurred', [
                    'user_id' => $userId,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);

                return response()->json([
                    'test_type' => 'Posts Retrieval Test',
                    'posts_retrieval' => 'ERROR',
                    'error' => $e->getMessage(),
                    'user_id' => $userId,
                    'exception_location' => $e->getFile() . ':' . $e->getLine()
                ], 500);
            }
        });

        // ✏️ UPDATE POST WITH LINKEDIN LIMITATIONS HANDLING
        Route::put('/update/{postId}', function ($postId, \Illuminate\Http\Request $request) {
            try {
                $post = \App\Models\SocialMediaPost::findOrFail($postId);

                // LinkedIn limitation detection logic
                $hasLinkedInPost = isset($post->platform_posts['linkedin']['platform_id']);
                $isPublishedStatus = in_array($post->post_status, ['published', 'deleted_on_platform']);
                $isPublishedToLinkedIn = $hasLinkedInPost && $isPublishedStatus;

                Log::info('Post Update: LinkedIn detection analysis', [
                    'post_id' => $postId,
                    'has_linkedin_post' => $hasLinkedInPost,
                    'post_status' => $post->post_status,
                    'is_published_to_linkedin' => $isPublishedToLinkedIn,
                    'linkedin_platform_id' => $hasLinkedInPost ? $post->platform_posts['linkedin']['platform_id'] : null
                ]);

                $linkedinAction = 'none';

                // Handle LinkedIn API limitations for published posts
                if ($isPublishedToLinkedIn) {
                    $actionType = $request->get('linkedin_action', 'warn');

                    switch ($actionType) {
                        case 'repost':
                            $linkedinAction = 'create_new_post';
                            break;
                        case 'ignore':
                            $linkedinAction = 'update_db_only';
                            break;
                        case 'warn':
                        default:
                            return response()->json([
                                'test_type' => 'Post Update Test',
                                'update_status' => 'LINKEDIN_LIMITATION',
                                'message' => 'LinkedIn does not allow editing published posts',
                                'post_id' => $postId,
                                'detection_info' => [
                                    'has_linkedin_post' => $hasLinkedInPost,
                                    'current_post_status' => $post->post_status,
                                    'linkedin_platform_id' => $post->platform_posts['linkedin']['platform_id'] ?? 'none',
                                    'linkedin_url' => $post->platform_posts['linkedin']['url'] ?? 'none'
                                ],
                                'available_options' => [
                                    'repost' => [
                                        'description' => 'Create new LinkedIn post with updated content',
                                        'usage' => "PUT " . $request->url() . "?linkedin_action=repost",
                                        'note' => 'Original post will remain unchanged, new post will be created'
                                    ],
                                    'ignore' => [
                                        'description' => 'Update database only (LinkedIn post unchanged)',
                                        'usage' => "PUT " . $request->url() . "?linkedin_action=ignore",
                                        'note' => 'LinkedIn post will not reflect changes'
                                    ],
                                    'warn' => [
                                        'description' => 'Show this warning (current behavior)',
                                        'usage' => "Default behavior when no linkedin_action specified",
                                        'note' => 'No changes will be made'
                                    ]
                                ],
                                'current_linkedin_post' => $post->platform_posts['linkedin'] ?? null,
                                'requested_update' => $request->only(['content', 'hashtags', 'mentions', 'media', 'settings', 'platforms']),
                                'linkedin_api_limitation' => 'LinkedIn API does not provide post editing functionality',
                                'developer' => 'J33WAKASUPUN'
                            ], 409);
                    }
                }

                // Process update data
                $updateData = $request->only([
                    'content',
                    'hashtags',
                    'mentions',
                    'media',
                    'settings',
                    'platforms'
                ]);

                // Handle content formatting with hashtags for LinkedIn
                if (isset($updateData['content']) && isset($updateData['hashtags'])) {
                    $content = is_array($updateData['content']) ? $updateData['content'] : ['text' => $updateData['content']];
                    $hashtags = $updateData['hashtags'];

                    // Add hashtags to content for LinkedIn compatibility
                    if (!empty($hashtags) && in_array('linkedin', $post->platforms ?? [])) {
                        $hashtagString = '';
                        foreach ($hashtags as $tag) {
                            $cleanTag = ltrim($tag, '#');
                            $hashtagString .= ' #' . $cleanTag;
                        }

                        if (!empty(trim($hashtagString))) {
                            $content['text'] = trim($content['text']) . "\n\n" . trim($hashtagString);
                        }
                    }

                    $updateData['content'] = $content;
                }

                // Add update metadata
                $updateData['last_updated_at'] = now();
                $updateData['update_count'] = ($post->update_count ?? 0) + 1;
                $updateData['updated_by'] = 'J33WAKASUPUN'; // Current user context

                // Filter out null/empty values
                $updateData = array_filter($updateData, function ($value) {
                    return $value !== null && $value !== '';
                });

                // Update post in database
                $post->update($updateData);

                $response = [
                    'test_type' => 'Post Update Test',
                    'update_status' => 'SUCCESS! ✏️',
                    'message' => 'Post updated successfully in database',
                    'post_id' => $postId,
                    'updated_fields' => array_keys($updateData),
                    'linkedin_action' => $linkedinAction,
                    'detection_results' => [
                        'has_linkedin_post' => $hasLinkedInPost,
                        'is_published_to_linkedin' => $isPublishedToLinkedIn,
                        'post_status' => $post->post_status,
                        'action_requested' => $request->get('linkedin_action', 'none')
                    ],
                    'post_data' => $post->fresh(),
                    'timestamp' => now()->toISOString(),
                    'developer' => 'J33WAKASUPUN'
                ];

                // Handle LinkedIn repost if requested
                if ($linkedinAction === 'create_new_post') {
                    try {
                        Log::info('Post Update: Starting LinkedIn repost process', [
                            'post_id' => $postId,
                            'original_platform_id' => $post->platform_posts['linkedin']['platform_id'] ?? 'none'
                        ]);

                        // Get latest LinkedIn token
                        $tokenHelper = LinkedInHelpers::getLatestTokenFile();
                        if (!$tokenHelper) {
                            throw new \Exception('No LinkedIn token files found');
                        }

                        $tokenData = $tokenHelper['token_data'];
                        if (!isset($tokenData['access_token'])) {
                            throw new \Exception('LinkedIn token not found in session file');
                        }

                        // Create temporary channel for API call
                        $channel = LinkedInHelpers::createTemporaryChannel($tokenData);
                        if (!$channel) {
                            throw new \Exception('Failed to create LinkedIn channel');
                        }

                        // Use LinkedIn provider to create new post
                        $provider = new \App\Services\SocialMedia\LinkedInProvider();
                        $publishResult = $provider->publishPost($post->fresh(), $channel);

                        Log::info('Post Update: LinkedIn repost result', [
                            'success' => $publishResult['success'],
                            'new_platform_id' => $publishResult['platform_id'] ?? null
                        ]);

                        if ($publishResult['success']) {
                            // Update platform_posts with new LinkedIn post data
                            $platformPosts = $post->platform_posts ?? [];

                            // Keep original LinkedIn post data for reference
                            $originalLinkedIn = $platformPosts['linkedin'] ?? null;

                            // Update main LinkedIn post data
                            $platformPosts['linkedin'] = [
                                'platform_id' => $publishResult['platform_id'],
                                'url' => $publishResult['url'],
                                'published_at' => $publishResult['published_at'],
                                'mode' => 'updated_repost',
                                'update_of_original' => true,
                                'updated_at' => now()->toISOString()
                            ];

                            // Store original post data for reference
                            if ($originalLinkedIn) {
                                $platformPosts['linkedin_original'] = array_merge($originalLinkedIn, [
                                    'replaced_at' => now()->toISOString(),
                                    'replaced_by' => $publishResult['platform_id']
                                ]);
                            }

                            $post->update([
                                'platform_posts' => $platformPosts,
                                'post_status' => 'published', // Update status back to published
                                'repost_count' => ($post->repost_count ?? 0) + 1
                            ]);

                            $response['linkedin_repost'] = [
                                'success' => true,
                                'message' => 'New LinkedIn post created successfully!',
                                'new_post_url' => $publishResult['url'],
                                'new_platform_id' => $publishResult['platform_id'],
                                'original_post_url' => $originalLinkedIn['url'] ?? 'unknown',
                                'both_posts_exist' => true,
                                'published_at' => $publishResult['published_at'],
                                'repost_count' => $post->repost_count
                            ];

                            $response['message'] = 'Post updated in database AND new LinkedIn post created!';
                            $response['update_status'] = 'SUCCESS_WITH_LINKEDIN_REPOST! 🔄✨';

                        } else {
                            $response['linkedin_repost'] = [
                                'success' => false,
                                'error' => $publishResult['error'] ?? 'Unknown publishing error',
                                'provider_result' => $publishResult,
                                'recommendation' => 'Check LinkedIn provider logs for details'
                            ];
                        }

                    } catch (\Exception $e) {
                        Log::error('Post Update: LinkedIn repost failed', [
                            'post_id' => $postId,
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString()
                        ]);

                        $response['linkedin_repost'] = [
                            'success' => false,
                            'error' => 'Repost failed: ' . $e->getMessage(),
                            'exception_location' => $e->getFile() . ':' . $e->getLine(),
                            'recommendation' => 'Check error logs and LinkedIn token validity'
                        ];
                    }
                }

                return response()->json($response);

            } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
                return response()->json([
                    'test_type' => 'Post Update Test',
                    'update_status' => 'POST_NOT_FOUND',
                    'error' => 'Post not found',
                    'post_id' => $postId,
                    'suggestion' => 'Check if the post ID is correct'
                ], 404);

            } catch (\Exception $e) {
                Log::error('Post Update: Exception occurred', [
                    'post_id' => $postId,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);

                return response()->json([
                    'test_type' => 'Post Update Test',
                    'update_status' => 'ERROR',
                    'error' => $e->getMessage(),
                    'post_id' => $postId,
                    'exception_location' => $e->getFile() . ':' . $e->getLine()
                ], 500);
            }
        });

        // 🗑️ DELETE POST FROM LINKEDIN PLATFORM
        Route::delete('/delete-from-linkedin/{postId}', function ($postId, \Illuminate\Http\Request $request) {
            try {
                $post = \App\Models\SocialMediaPost::findOrFail($postId);

                if (!isset($post->platform_posts['linkedin']['platform_id'])) {
                    return response()->json([
                        'test_type' => 'LinkedIn Post Deletion Test',
                        'linkedin_delete' => 'NOT_APPLICABLE',
                        'message' => 'Post was not published to LinkedIn',
                        'post_id' => $postId,
                        'available_platforms' => array_keys($post->platform_posts ?? []),
                        'suggestion' => 'This endpoint only works for posts published to LinkedIn'
                    ], 400);
                }

                // Use LinkedIn helper to get latest token
                $tokenHelper = LinkedInHelpers::getLatestTokenFile();
                if (!$tokenHelper) {
                    return response()->json([
                        'test_type' => 'LinkedIn Post Deletion Test',
                        'linkedin_delete' => 'NO_TOKEN',
                        'message' => 'No LinkedIn token available for deletion',
                        'post_id' => $postId,
                        'recommendation' => 'Complete LinkedIn OAuth flow first'
                    ], 400);
                }

                $tokenData = $tokenHelper['token_data'];
                if (!isset($tokenData['access_token'])) {
                    return response()->json([
                        'test_type' => 'LinkedIn Post Deletion Test',
                        'linkedin_delete' => 'INVALID_TOKEN',
                        'message' => 'LinkedIn token is invalid',
                        'post_id' => $postId,
                        'token_file' => $tokenHelper['file_name']
                    ], 400);
                }

                // Create channel and use provider
                $channel = LinkedInHelpers::createTemporaryChannel($tokenData);
                if (!$channel) {
                    return response()->json([
                        'test_type' => 'LinkedIn Post Deletion Test',
                        'linkedin_delete' => 'CHANNEL_ERROR',
                        'message' => 'Failed to create LinkedIn channel',
                        'post_id' => $postId
                    ], 500);
                }

                $provider = new \App\Services\SocialMedia\LinkedInProvider();

                // Check post deletion status first
                $statusCheck = $provider->getPostDeletionStatus(
                    $post->platform_posts['linkedin']['platform_id'],
                    $channel,
                    $post->platform_posts['linkedin']['url'] ?? null
                );

                Log::info('LinkedIn Post Deletion: Status check result', [
                    'post_id' => $postId,
                    'platform_id' => $post->platform_posts['linkedin']['platform_id'],
                    'status' => $statusCheck['status'] ?? 'unknown',
                    'message' => $statusCheck['message'] ?? 'no message'
                ]);

                // Update database based on LinkedIn status
                if ($statusCheck['status'] === 'DELETED') {
                    // Post already deleted on LinkedIn - update database
                    $post->update([
                        'post_status' => 'deleted_on_platform',
                        'deleted_from_linkedin_at' => now(),
                        'linkedin_status_verified' => true,
                        'linkedin_deletion_response' => [
                            'status' => 'verified_deleted',
                            'verified_at' => now()->toISOString(),
                            'method' => 'provider_check',
                            'deletion_detected_automatically' => true
                        ]
                    ]);

                    return response()->json([
                        'test_type' => 'LinkedIn Post Deletion Test',
                        'linkedin_delete' => 'SUCCESS! 🗑️',
                        'message' => 'Post was already deleted from LinkedIn - database updated',
                        'post_id' => $postId,
                        'platform_id' => $post->platform_posts['linkedin']['platform_id'],
                        'status_check' => $statusCheck,
                        'database_updated' => true,
                        'new_post_status' => 'deleted_on_platform',
                        'timestamp' => now()->toISOString(),
                        'developer' => 'J33WAKASUPUN'
                    ]);
                }

                // Post still exists - provide manual deletion guidance
                return response()->json([
                    'test_type' => 'LinkedIn Post Deletion Test',
                    'linkedin_delete' => 'MANUAL_DELETION_REQUIRED',
                    'message' => 'Post still exists on LinkedIn - manual deletion required',
                    'post_id' => $postId,
                    'platform_id' => $post->platform_posts['linkedin']['platform_id'],
                    'post_url' => $post->platform_posts['linkedin']['url'] ?? null,
                    'linkedin_limitation' => 'LinkedIn API does not provide reliable post deletion endpoints',
                    'manual_deletion_steps' => $statusCheck['manual_deletion_steps'] ?? [
                        '1. Visit the LinkedIn post URL directly',
                        '2. Click the "..." menu on your post',
                        '3. Select "Delete" from the dropdown menu',
                        '4. Confirm the deletion',
                        '5. Call this endpoint again to update the database'
                    ],
                    'status_check' => $statusCheck,
                    'note' => 'After manual deletion, call this endpoint again to update database',
                    'retry_endpoint' => $request->url(),
                    'timestamp' => now()->toISOString(),
                    'developer' => 'J33WAKASUPUN'
                ]);

            } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
                return response()->json([
                    'test_type' => 'LinkedIn Post Deletion Test',
                    'linkedin_delete' => 'POST_NOT_FOUND',
                    'error' => 'Post not found',
                    'post_id' => $postId
                ], 404);

            } catch (\Exception $e) {
                Log::error('LinkedIn Post Deletion: Exception occurred', [
                    'post_id' => $postId,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);

                return response()->json([
                    'test_type' => 'LinkedIn Post Deletion Test',
                    'linkedin_delete' => 'ERROR',
                    'error' => $e->getMessage(),
                    'post_id' => $postId,
                    'exception_location' => $e->getFile() . ':' . $e->getLine(),
                    'manual_verification_required' => true
                ], 500);
            }
        });

        // 🔥 ENHANCED DELETE FROM LINKEDIN WITH MULTI-METHOD VERIFICATION
        Route::delete('/delete-from-linkedin-enhanced/{postId}', function ($postId, \Illuminate\Http\Request $request) {
            try {
                $post = \App\Models\SocialMediaPost::findOrFail($postId);

                if (!isset($post->platform_posts['linkedin']['platform_id'])) {
                    return response()->json([
                        'test_type' => 'Enhanced LinkedIn Post Deletion Test',
                        'linkedin_delete' => 'NOT_APPLICABLE',
                        'message' => 'Post was not published to LinkedIn',
                        'post_id' => $postId,
                        'available_platforms' => array_keys($post->platform_posts ?? [])
                    ], 400);
                }

                // Get LinkedIn token with enhanced error handling
                $tokenHelper = LinkedInHelpers::getLatestTokenFile();
                if (!$tokenHelper) {
                    return response()->json([
                        'test_type' => 'Enhanced LinkedIn Post Deletion Test',
                        'linkedin_delete' => 'NO_TOKEN',
                        'message' => 'No LinkedIn token available',
                        'post_id' => $postId,
                        'manual_verification_required' => true,
                        'manual_verification_url' => $post->platform_posts['linkedin']['url'] ?? null
                    ], 400);
                }

                $tokenData = $tokenHelper['token_data'];
                if (!isset($tokenData['access_token'])) {
                    return response()->json([
                        'test_type' => 'Enhanced LinkedIn Post Deletion Test',
                        'linkedin_delete' => 'INVALID_TOKEN',
                        'message' => 'LinkedIn token is invalid',
                        'post_id' => $postId,
                        'token_file' => $tokenHelper['file_name'],
                        'manual_verification_required' => true
                    ], 400);
                }

                // Create channel and use enhanced provider methods
                $channel = LinkedInHelpers::createTemporaryChannel($tokenData);
                if (!$channel) {
                    return response()->json([
                        'test_type' => 'Enhanced LinkedIn Post Deletion Test',
                        'linkedin_delete' => 'CHANNEL_ERROR',
                        'message' => 'Failed to create LinkedIn channel',
                        'post_id' => $postId
                    ], 500);
                }

                $provider = new \App\Services\SocialMedia\LinkedInProvider();

                // Use enhanced multi-method status check
                $statusCheck = $provider->getPostDeletionStatusEnhanced(
                    $post->platform_posts['linkedin']['platform_id'],
                    $channel,
                    $post->platform_posts['linkedin']['url'] ?? null
                );

                Log::info('Enhanced LinkedIn Post Deletion: Multi-method status check', [
                    'post_id' => $postId,
                    'platform_id' => $post->platform_posts['linkedin']['platform_id'],
                    'status' => $statusCheck['status'] ?? 'unknown',
                    'confidence' => $statusCheck['confidence'] ?? 'unknown',
                    'methods_used' => $statusCheck['verification_methods'] ?? []
                ]);

                // Handle different status results with confidence levels
                switch ($statusCheck['status']) {
                    case 'DELETED':
                        if ($statusCheck['confidence'] === 'high') {
                            $post->update([
                                'post_status' => 'deleted_on_platform',
                                'deleted_from_linkedin_at' => now(),
                                'linkedin_status_verified' => true,
                                'linkedin_deletion_response' => [
                                    'status' => 'verified_deleted',
                                    'confidence' => $statusCheck['confidence'],
                                    'verified_at' => now()->toISOString(),
                                    'method' => 'enhanced_provider_check',
                                    'verification_methods' => $statusCheck['verification_methods'] ?? []
                                ]
                            ]);

                            return response()->json([
                                'test_type' => 'Enhanced LinkedIn Post Deletion Test',
                                'linkedin_delete' => 'SUCCESS! 🗑️✨',
                                'message' => 'Post deleted from LinkedIn (high confidence) - database updated',
                                'post_id' => $postId,
                                'platform_id' => $post->platform_posts['linkedin']['platform_id'],
                                'status_check' => $statusCheck,
                                'database_updated' => true,
                                'confidence' => $statusCheck['confidence'],
                                'verification_methods' => $statusCheck['verification_methods'] ?? [],
                                'timestamp' => now()->toISOString(),
                                'developer' => 'J33WAKASUPUN'
                            ]);
                        } else {
                            // Medium/low confidence deletion
                            return response()->json([
                                'test_type' => 'Enhanced LinkedIn Post Deletion Test',
                                'linkedin_delete' => 'UNCERTAIN_DELETION',
                                'message' => 'Post may be deleted but confidence is low - manual verification recommended',
                                'post_id' => $postId,
                                'platform_id' => $post->platform_posts['linkedin']['platform_id'],
                                'status_check' => $statusCheck,
                                'confidence' => $statusCheck['confidence'],
                                'requires_manual_verification' => true,
                                'manual_verification_url' => $post->platform_posts['linkedin']['url'] ?? null,
                                'confirmation_endpoint' => "POST /test/posts/confirm-linkedin-status/{$postId}",
                                'timestamp' => now()->toISOString(),
                                'developer' => 'J33WAKASUPUN'
                            ], 409);
                        }
                        break;

                    case 'EXISTS':
                        return response()->json([
                            'test_type' => 'Enhanced LinkedIn Post Deletion Test',
                            'linkedin_delete' => 'POST_STILL_EXISTS',
                            'message' => 'Post still exists on LinkedIn - manual deletion required',
                            'post_id' => $postId,
                            'platform_id' => $post->platform_posts['linkedin']['platform_id'],
                            'post_url' => $post->platform_posts['linkedin']['url'] ?? null,
                            'confidence' => $statusCheck['confidence'],
                            'manual_deletion_steps' => $statusCheck['manual_deletion_steps'] ?? [
                                '1. Visit: ' . ($post->platform_posts['linkedin']['url'] ?? 'LinkedIn post URL'),
                                '2. Click the "..." menu on your post',
                                '3. Select "Delete" from the dropdown',
                                '4. Confirm deletion',
                                '5. Call this endpoint again'
                            ],
                            'status_check' => $statusCheck,
                            'note' => 'Please delete manually and call this endpoint again',
                            'retry_after_manual_deletion' => $request->url(),
                            'timestamp' => now()->toISOString(),
                            'developer' => 'J33WAKASUPUN'
                        ]);

                    case 'UNCERTAIN':
                    default:
                        return response()->json([
                            'test_type' => 'Enhanced LinkedIn Post Deletion Test',
                            'linkedin_delete' => 'MANUAL_VERIFICATION_REQUIRED',
                            'message' => 'LinkedIn API results are inconsistent - manual verification needed',
                            'post_id' => $postId,
                            'platform_id' => $post->platform_posts['linkedin']['platform_id'],
                            'post_url' => $post->platform_posts['linkedin']['url'] ?? null,
                            'status_check' => $statusCheck,
                            'api_limitation_detected' => true,
                            'manual_verification_steps' => $statusCheck['manual_verification_steps'] ?? [
                                '1. Visit the LinkedIn post URL directly',
                                '2. Check if the post is visible',
                                '3. Use the manual confirmation endpoint below'
                            ],
                            'manual_confirmation_endpoint' => "POST /test/posts/confirm-linkedin-status/{$postId}",
                            'recommendation' => 'Visit the LinkedIn post URL directly to verify its status',
                            'linkedin_api_note' => 'LinkedIn API has limited post management capabilities',
                            'timestamp' => now()->toISOString(),
                            'developer' => 'J33WAKASUPUN'
                        ], 409);
                }

            } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
                return response()->json([
                    'test_type' => 'Enhanced LinkedIn Post Deletion Test',
                    'linkedin_delete' => 'POST_NOT_FOUND',
                    'error' => 'Post not found',
                    'post_id' => $postId
                ], 404);

            } catch (\Exception $e) {
                Log::error('Enhanced LinkedIn Post Deletion: Exception occurred', [
                    'post_id' => $postId,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);

                return response()->json([
                    'test_type' => 'Enhanced LinkedIn Post Deletion Test',
                    'linkedin_delete' => 'ERROR',
                    'error' => $e->getMessage(),
                    'post_id' => $postId,
                    'exception_location' => $e->getFile() . ':' . $e->getLine(),
                    'manual_verification_required' => true,
                    'fallback_action' => 'Use manual confirmation endpoint after verifying post status'
                ], 500);
            }
        });

        // 🔍 ENHANCED LINKEDIN STATUS CHECK ROUTE
        Route::get('/linkedin-status-enhanced/{postId}', function ($postId) {
            try {
                $post = \App\Models\SocialMediaPost::findOrFail($postId);

                if (!isset($post->platform_posts['linkedin']['platform_id'])) {
                    return response()->json([
                        'test_type' => 'Enhanced LinkedIn Status Check',
                        'status_check' => 'NOT_APPLICABLE',
                        'message' => 'Post was not published to LinkedIn',
                        'post_id' => $postId,
                        'available_platforms' => array_keys($post->platform_posts ?? [])
                    ], 400);
                }

                // Get LinkedIn token
                $tokenHelper = LinkedInHelpers::getLatestTokenFile();
                if (!$tokenHelper) {
                    return response()->json([
                        'test_type' => 'Enhanced LinkedIn Status Check',
                        'status_check' => 'NO_TOKEN',
                        'message' => 'No LinkedIn token available for status check',
                        'post_id' => $postId,
                        'linkedin_post_url' => $post->platform_posts['linkedin']['url'] ?? null
                    ], 400);
                }

                $tokenData = $tokenHelper['token_data'];
                if (!isset($tokenData['access_token'])) {
                    return response()->json([
                        'test_type' => 'Enhanced LinkedIn Status Check',
                        'status_check' => 'INVALID_TOKEN',
                        'message' => 'LinkedIn token is invalid',
                        'post_id' => $postId,
                        'token_file' => $tokenHelper['file_name']
                    ], 400);
                }

                // Create channel and use enhanced provider methods
                $channel = LinkedInHelpers::createTemporaryChannel($tokenData);
                if (!$channel) {
                    return response()->json([
                        'test_type' => 'Enhanced LinkedIn Status Check',
                        'status_check' => 'CHANNEL_ERROR',
                        'message' => 'Failed to create LinkedIn channel',
                        'post_id' => $postId
                    ], 500);
                }

                $provider = new \App\Services\SocialMedia\LinkedInProvider();

                // Use enhanced multi-method status check
                $enhancedCheck = $provider->checkPostExistsEnhanced(
                    $post->platform_posts['linkedin']['platform_id'],
                    $channel
                );

                $statusResult = $provider->getPostDeletionStatusEnhanced(
                    $post->platform_posts['linkedin']['platform_id'],
                    $channel,
                    $post->platform_posts['linkedin']['url'] ?? null
                );

                Log::info('Enhanced LinkedIn Status Check: Multi-method analysis completed', [
                    'post_id' => $postId,
                    'platform_id' => $post->platform_posts['linkedin']['platform_id'],
                    'methods_used' => $enhancedCheck['methods_used'] ?? 0,
                    'confidence' => $enhancedCheck['confidence'] ?? 'unknown',
                    'status' => $statusResult['status'] ?? 'unknown'
                ]);

                return response()->json([
                    'test_type' => 'Enhanced LinkedIn Status Check',
                    'status_check' => 'SUCCESS! 🔍✨',
                    'post_id' => $postId,
                    'platform_id' => $post->platform_posts['linkedin']['platform_id'],
                    'linkedin_post_url' => $post->platform_posts['linkedin']['url'] ?? null,
                    'enhanced_existence_check' => $enhancedCheck,
                    'deletion_status' => $statusResult,
                    'current_post_status' => $post->post_status,
                    'api_analysis' => [
                        'methods_used' => $enhancedCheck['methods_used'] ?? 0,
                        'methods_saying_exists' => $enhancedCheck['methods_saying_exists'] ?? 0,
                        'confidence_level' => $enhancedCheck['confidence'] ?? 'unknown',
                        'exists_percentage' => $enhancedCheck['exists_percentage'] ?? 0,
                        'linkedin_api_limitations' => 'Standard API has limited post management capabilities'
                    ],
                    'verification_methods' => $enhancedCheck['verification_methods'] ?? [],
                    'recommendation' => $enhancedCheck['recommendation'] ?? 'No recommendation available',
                    'suggested_actions' => [
                        'if_exists' => 'Use manual deletion steps provided',
                        'if_deleted' => 'Use confirmation endpoint to update database',
                        'if_uncertain' => 'Visit LinkedIn post URL directly for verification'
                    ],
                    'available_endpoints' => [
                        'manual_confirmation' => "POST /test/posts/confirm-linkedin-status/{$postId}",
                        'enhanced_deletion' => "DELETE /test/posts/delete-from-linkedin-enhanced/{$postId}"
                    ],
                    'timestamp' => now()->toISOString(),
                    'developer' => 'J33WAKASUPUN'
                ]);

            } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
                return response()->json([
                    'test_type' => 'Enhanced LinkedIn Status Check',
                    'status_check' => 'POST_NOT_FOUND',
                    'error' => 'Post not found',
                    'post_id' => $postId
                ], 404);

            } catch (\Exception $e) {
                Log::error('Enhanced LinkedIn Status Check: Exception occurred', [
                    'post_id' => $postId,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);

                return response()->json([
                    'test_type' => 'Enhanced LinkedIn Status Check',
                    'status_check' => 'ERROR',
                    'error' => $e->getMessage(),
                    'post_id' => $postId,
                    'exception_location' => $e->getFile() . ':' . $e->getLine()
                ], 500);
            }
        });

        // ✅ MANUAL STATUS CONFIRMATION ROUTE
        Route::post('/confirm-linkedin-status/{postId}', function ($postId, \Illuminate\Http\Request $request) {
            try {
                $post = \App\Models\SocialMediaPost::findOrFail($postId);

                $manualStatus = $request->get('status'); // 'deleted' or 'exists'
                $userConfirmation = $request->get('confirmed_by_user', false);
                $userNotes = $request->get('notes', '');

                if (!in_array($manualStatus, ['deleted', 'exists']) || !$userConfirmation) {
                    return response()->json([
                        'test_type' => 'Manual LinkedIn Status Confirmation',
                        'confirmation' => 'INVALID_INPUT',
                        'message' => 'Please provide valid status (deleted/exists) and confirmation',
                        'post_id' => $postId,
                        'required_fields' => [
                            'status' => 'Must be "deleted" or "exists"',
                            'confirmed_by_user' => 'Must be true',
                            'notes' => 'Optional user notes'
                        ],
                        'example_request' => [
                            'status' => 'deleted',
                            'confirmed_by_user' => true,
                            'notes' => 'Verified by visiting LinkedIn post URL directly'
                        ]
                    ], 400);
                }

                if ($manualStatus === 'deleted') {
                    $post->update([
                        'post_status' => 'deleted_on_platform',
                        'deleted_from_linkedin_at' => now(),
                        'linkedin_status_verified' => true,
                        'manual_verification' => true,
                        'linkedin_deletion_response' => [
                            'status' => 'manually_verified_deleted',
                            'verified_at' => now()->toISOString(),
                            'verified_by' => 'user_confirmation',
                            'method' => 'manual_verification',
                            'user_notes' => $userNotes,
                            'verified_by_user' => 'J33WAKASUPUN'
                        ]
                    ]);

                    Log::info('Manual LinkedIn Status Confirmation: Status updated to deleted', [
                        'post_id' => $postId,
                        'platform_id' => $post->platform_posts['linkedin']['platform_id'] ?? 'unknown',
                        'user_notes' => $userNotes,
                        'verified_by' => 'J33WAKASUPUN'
                    ]);

                    return response()->json([
                        'test_type' => 'Manual LinkedIn Status Confirmation',
                        'confirmation' => 'SUCCESS! ✅',
                        'message' => 'Post status updated to deleted based on manual verification',
                        'post_id' => $postId,
                        'new_status' => 'deleted_on_platform',
                        'verified_manually' => true,
                        'database_updated' => true,
                        'verification_details' => [
                            'verified_by' => 'J33WAKASUPUN',
                            'verified_at' => now()->toISOString(),
                            'user_notes' => $userNotes,
                            'method' => 'manual_confirmation_endpoint'
                        ],
                        'timestamp' => now()->toISOString(),
                        'developer' => 'J33WAKASUPUN'
                    ]);
                } else {
                    // Status is 'exists'
                    $post->update([
                        'linkedin_status_last_checked' => now(),
                        'manual_verification' => true,
                        'manual_verification_notes' => $userNotes
                    ]);

                    Log::info('Manual LinkedIn Status Confirmation: Status confirmed as existing', [
                        'post_id' => $postId,
                        'platform_id' => $post->platform_posts['linkedin']['platform_id'] ?? 'unknown',
                        'user_notes' => $userNotes,
                        'current_status' => $post->post_status
                    ]);

                    return response()->json([
                        'test_type' => 'Manual LinkedIn Status Confirmation',
                        'confirmation' => 'POST_EXISTS_CONFIRMED',
                        'message' => 'Post confirmed to still exist on LinkedIn',
                        'post_id' => $postId,
                        'current_status' => $post->post_status,
                        'linkedin_post_url' => $post->platform_posts['linkedin']['url'] ?? null,
                        'verification_details' => [
                            'verified_by' => 'J33WAKASUPUN',
                            'verified_at' => now()->toISOString(),
                            'user_notes' => $userNotes,
                            'status_confirmed' => 'exists'
                        ],
                        'recommendation' => 'Delete the post manually from LinkedIn first, then call the confirmation endpoint with status=deleted',
                        'manual_deletion_steps' => [
                            '1. Visit: ' . ($post->platform_posts['linkedin']['url'] ?? 'LinkedIn post URL'),
                            '2. Click the "..." menu on your post',
                            '3. Select "Delete" from the dropdown',
                            '4. Confirm deletion',
                            '5. Call this endpoint again with status=deleted'
                        ],
                        'timestamp' => now()->toISOString(),
                        'developer' => 'J33WAKASUPUN'
                    ]);
                }

            } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
                return response()->json([
                    'test_type' => 'Manual LinkedIn Status Confirmation',
                    'confirmation' => 'POST_NOT_FOUND',
                    'error' => 'Post not found',
                    'post_id' => $postId
                ], 404);

            } catch (\Exception $e) {
                Log::error('Manual LinkedIn Status Confirmation: Exception occurred', [
                    'post_id' => $postId,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);

                return response()->json([
                    'test_type' => 'Manual LinkedIn Status Confirmation',
                    'confirmation' => 'ERROR',
                    'error' => $e->getMessage(),
                    'post_id' => $postId,
                    'exception_location' => $e->getFile() . ':' . $e->getLine()
                ], 500);
            }
        });

        // 🔄 BASIC LINKEDIN POST STATUS CHECK
        Route::get('/check-linkedin-status/{postId}', function ($postId) {
            try {
                $post = \App\Models\SocialMediaPost::findOrFail($postId);

                if (!isset($post->platform_posts['linkedin'])) {
                    return response()->json([
                        'test_type' => 'Basic LinkedIn Status Check',
                        'status_check' => 'NOT_APPLICABLE',
                        'message' => 'Post was not published to LinkedIn',
                        'post_id' => $postId,
                        'post_status' => $post->post_status,
                        'available_platforms' => array_keys($post->platform_posts ?? [])
                    ], 400);
                }

                $linkedinData = $post->platform_posts['linkedin'];
                $platformId = $linkedinData['platform_id'];

                // Try to get LinkedIn token
                $tokenHelper = LinkedInHelpers::getLatestTokenFile();
                if (!$tokenHelper) {
                    return response()->json([
                        'test_type' => 'Basic LinkedIn Status Check',
                        'status_check' => 'NO_TOKEN',
                        'message' => 'No LinkedIn token available for status check',
                        'post_id' => $postId,
                        'linkedin_post_url' => $linkedinData['url'] ?? null
                    ], 400);
                }

                $tokenData = $tokenHelper['token_data'];
                if (!isset($tokenData['access_token'])) {
                    return response()->json([
                        'test_type' => 'Basic LinkedIn Status Check',
                        'status_check' => 'INVALID_TOKEN',
                        'message' => 'LinkedIn token is invalid',
                        'post_id' => $postId,
                        'token_file' => $tokenHelper['file_name']
                    ], 400);
                }

                // Check if post exists on LinkedIn using direct API call
                $response = \Illuminate\Support\Facades\Http::withToken($tokenData['access_token'])
                    ->withHeaders([
                        'X-Restli-Protocol-Version' => '2.0.0',
                        'Accept' => 'application/json'
                    ])
                    ->timeout(10)
                    ->get("https://api.linkedin.com/v2/shares/{$platformId}");

                $existsOnLinkedIn = $response->successful();
                $linkedinStatus = $existsOnLinkedIn ? 'ACTIVE' : 'DELETED_OR_UNAVAILABLE';

                // Update post status if needed
                $postStatusUpdated = false;
                if (!$existsOnLinkedIn && $post->post_status === 'published') {
                    $post->update([
                        'post_status' => 'deleted_on_platform',
                        'platform_status_checked_at' => now(),
                        'linkedin_status_detected_automatically' => true
                    ]);
                    $postStatusUpdated = true;
                }

                Log::info('Basic LinkedIn Status Check: Completed', [
                    'post_id' => $postId,
                    'platform_id' => $platformId,
                    'exists_on_linkedin' => $existsOnLinkedIn,
                    'post_status_updated' => $postStatusUpdated
                ]);

                return response()->json([
                    'test_type' => 'Basic LinkedIn Status Check',
                    'status_check' => 'SUCCESS! 🔄',
                    'post_id' => $postId,
                    'platform_id' => $platformId,
                    'linkedin_post_url' => $linkedinData['url'] ?? null,
                    'exists_on_linkedin' => $existsOnLinkedIn,
                    'linkedin_status' => $linkedinStatus,
                    'platform_response' => [
                        'status_code' => $response->status(),
                        'successful' => $response->successful(),
                        'api_endpoint' => "https://api.linkedin.com/v2/shares/{$platformId}"
                    ],
                    'database_update' => [
                        'post_status_updated' => $postStatusUpdated,
                        'current_post_status' => $post->fresh()->post_status,
                        'status_checked_at' => now()->toISOString()
                    ],
                    'recommendations' => $existsOnLinkedIn ? [
                        'post_is_active' => 'Post is still live on LinkedIn',
                        'available_actions' => ['update', 'manual_deletion']
                    ] : [
                        'post_not_found' => 'Post appears to be deleted from LinkedIn',
                        'database_updated' => $postStatusUpdated ? 'Post status updated automatically' : 'No database changes needed'
                    ],
                    'timestamp' => now()->toISOString(),
                    'developer' => 'J33WAKASUPUN'
                ]);

            } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
                return response()->json([
                    'test_type' => 'Basic LinkedIn Status Check',
                    'status_check' => 'POST_NOT_FOUND',
                    'error' => 'Post not found',
                    'post_id' => $postId
                ], 404);

            } catch (\Exception $e) {
                Log::error('Basic LinkedIn Status Check: Exception occurred', [
                    'post_id' => $postId,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);

                return response()->json([
                    'test_type' => 'Basic LinkedIn Status Check',
                    'status_check' => 'ERROR',
                    'error' => $e->getMessage(),
                    'post_id' => $postId,
                    'exception_location' => $e->getFile() . ':' . $e->getLine()
                ], 500);
            }
        });

        // 📊 GET POST DETAILS WITH COMPREHENSIVE ANALYTICS
        Route::get('/details/{postId}', function ($postId) {
            try {
                $post = \App\Models\SocialMediaPost::findOrFail($postId);
                
                // Fetch all analytics for this post
                $analytics = \App\Models\PostAnalytics::where('social_media_post_id', $postId)
                    ->orderBy('collected_at', 'desc')
                    ->get();

                // Group analytics by platform
                $analyticsByPlatform = $analytics->groupBy('platform');

                // Calculate engagement summary
                $engagementSummary = [
                    'total_engagement' => 0,
                    'platform_breakdown' => []
                ];

                foreach ($analyticsByPlatform as $platform => $platformAnalytics) {
                    $latestAnalytics = $platformAnalytics->first();
                    $platformEngagement = 0;
                    
                    if ($latestAnalytics && isset($latestAnalytics->metrics)) {
                        $metrics = $latestAnalytics->metrics;
                        $platformEngagement = ($metrics['likes'] ?? 0) + 
                                            ($metrics['shares'] ?? 0) + 
                                            ($metrics['comments'] ?? 0);
                    }

                    $engagementSummary['platform_breakdown'][$platform] = [
                        'records_count' => $platformAnalytics->count(),
                        'latest_metrics' => $latestAnalytics?->metrics ?? [],
                        'total_engagement' => $platformEngagement,
                        'avg_performance' => round($platformAnalytics->avg('performance_score'), 2),
                        'latest_collection' => $latestAnalytics?->collected_at,
                        'engagement_rate' => $latestAnalytics?->metrics['engagement_rate'] ?? 0
                    ];

                    $engagementSummary['total_engagement'] += $platformEngagement;
                }

                // Calculate method-based total engagement if available
                if (method_exists($post, 'getTotalEngagement')) {
                    $modelEngagement = $post->getTotalEngagement();
                    if ($modelEngagement > 0) {
                        $engagementSummary['total_engagement'] = $modelEngagement;
                    }
                }

                Log::info('Post Details: Retrieved comprehensive post information', [
                    'post_id' => $postId,
                    'analytics_records' => $analytics->count(),
                    'platforms_analyzed' => array_keys($analyticsByPlatform->toArray()),
                    'total_engagement' => $engagementSummary['total_engagement']
                ]);

                return response()->json([
                    'test_type' => 'Post Details with Analytics',
                    'post_details' => 'SUCCESS! 📊✨',
                    'post_id' => $postId,
                    'post_data' => [
                        'id' => $post->_id,
                        'content' => $post->content,
                        'hashtags' => $post->hashtags ?? [],
                        'mentions' => $post->mentions ?? [],
                        'platforms' => $post->platforms ?? [],
                        'post_status' => $post->post_status,
                        'created_at' => $post->created_at,
                        'published_at' => $post->published_at,
                        'last_updated_at' => $post->last_updated_at,
                        'update_count' => $post->update_count ?? 0,
                        'media_count' => count($post->media ?? []),
                        'settings' => $post->settings ?? []
                    ],
                    'platform_posts' => $post->platform_posts ?? [],
                    'linkedin_details' => isset($post->platform_posts['linkedin']) ? [
                        'platform_id' => $post->platform_posts['linkedin']['platform_id'] ?? null,
                        'url' => $post->platform_posts['linkedin']['url'] ?? null,
                        'published_at' => $post->platform_posts['linkedin']['published_at'] ?? null,
                        'mode' => $post->platform_posts['linkedin']['mode'] ?? 'unknown',
                        'status_verified' => $post->linkedin_status_verified ?? false,
                        'last_status_check' => $post->linkedin_status_last_checked ?? null
                    ] : null,
                    'analytics_summary' => [
                        'total_records' => $analytics->count(),
                        'platforms_tracked' => $analytics->pluck('platform')->unique()->values(),
                        'date_range' => [
                            'earliest_collection' => $analytics->min('collected_at'),
                            'latest_collection' => $analytics->max('collected_at')
                        ],
                        'performance_scores' => [
                            'average' => round($analytics->avg('performance_score'), 2),
                            'highest' => $analytics->max('performance_score'),
                            'lowest' => $analytics->min('performance_score')
                        ],
                        'recent_records' => $analytics->take(5)->map(function ($analytic) {
                            return [
                                'platform' => $analytic->platform,
                                'collected_at' => $analytic->collected_at,
                                'performance_score' => $analytic->performance_score,
                                'engagement_rate' => $analytic->metrics['engagement_rate'] ?? 0
                            ];
                        })
                    ],
                    'engagement_summary' => $engagementSummary,
                    'available_actions' => [
                        'update_post' => "PUT /test/posts/update/{$postId}",
                        'delete_from_linkedin' => "DELETE /test/posts/delete-from-linkedin/{$postId}",
                        'check_linkedin_status' => "GET /test/posts/linkedin-status-enhanced/{$postId}",
                        'get_all_posts' => "GET /test/posts/all/{$post->user_id}"
                    ],
                    'metadata' => [
                        'user_id' => $post->user_id,
                        'developer' => 'J33WAKASUPUN',
                        'retrieved_at' => now()->toISOString()
                    ]
                ]);

            } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
                return response()->json([
                    'test_type' => 'Post Details with Analytics',
                    'post_details' => 'POST_NOT_FOUND',
                    'error' => 'Post not found',
                    'post_id' => $postId,
                    'suggestion' => 'Check if the post ID is correct'
                ], 404);

            } catch (\Exception $e) {
                Log::error('Post Details: Exception occurred', [
                    'post_id' => $postId,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);

                return response()->json([
                    'test_type' => 'Post Details with Analytics',
                    'post_details' => 'ERROR',
                    'error' => $e->getMessage(),
                    'post_id' => $postId,
                    'exception_location' => $e->getFile() . ':' . $e->getLine()
                ], 500);
            }
        });
    });
});

/*
|--------------------------------------------------------------------------
| End of Post Management Routes Jeewaka@DESKTOP-91K8097
|--------------------------------------------------------------------------
*/