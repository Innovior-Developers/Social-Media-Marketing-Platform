<?php
// Updated SocialMediaPostController.php with Facebook integration

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SocialMediaPost;
use App\Models\ScheduledPost;
use App\Models\ContentCalendar;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class SocialMediaPostController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    /**
     * Display a listing of posts
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $query = SocialMediaPost::where('user_id', $user->_id);

            // Filter by status
            if ($request->has('status')) {
                $query->byStatus($request->status);
            }

            // Filter by platform
            if ($request->has('platform')) {
                $query->forPlatform($request->platform);
            }

            // Filter by date range
            if ($request->has('from_date')) {
                $query->where('created_at', '>=', $request->from_date);
            }
            if ($request->has('to_date')) {
                $query->where('created_at', '<=', $request->to_date);
            }

            // Search in content
            if ($request->has('search')) {
                $query->where(function ($q) use ($request) {
                    $q->where('content.text', 'like', '%' . $request->search . '%')
                        ->orWhere('content.title', 'like', '%' . $request->search . '%');
                });
            }

            $perPage = min($request->get('per_page', 15), 100);
            $posts = $query->latest()->paginate($perPage);

            // Add engagement stats to each post
            $postsData = $posts->items();
            foreach ($postsData as $post) {
                $post->total_engagement = $post->getTotalEngagement();
                $post->platforms_count = count($post->platforms ?? []);
            }

            return response()->json([
                'status' => 'success',
                'data' => $postsData,
                'meta' => [
                    'current_page' => $posts->currentPage(),
                    'last_page' => $posts->lastPage(),
                    'per_page' => $posts->perPage(),
                    'total' => $posts->total(),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve posts',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created post
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            // Check posting limits
            if ($user->hasReachedPostingLimit()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'You have reached your monthly posting limit',
                    'remaining_posts' => 0
                ], 429);
            }

            $validated = $request->validate([
                'content' => 'required|array',
                'content.text' => 'required|string|max:5000',
                'content.title' => 'string|max:255',
                'media' => 'array',
                'media.*.type' => 'string|in:image,video,gif,document',
                'media.*.url' => 'required_with:media|string|url',
                'media.*.alt_text' => 'string|max:255',
                'platforms' => 'required|array|min:1',
                'platforms.*' => 'string|in:twitter,facebook,instagram,linkedin,youtube,tiktok',
                'hashtags' => 'array',
                'hashtags.*' => 'string|max:50',
                'mentions' => 'array',
                'mentions.*' => 'string|max:50',
                'scheduled_at' => 'date|after:now',
                'settings' => 'array',
                'settings.auto_hashtags' => 'boolean',
                'settings.cross_post' => 'boolean',
                'settings.track_analytics' => 'boolean'
            ]);

            // Validate platforms user can post to
            foreach ($validated['platforms'] as $platform) {
                if (!$user->canPostTo($platform)) {
                    return response()->json([
                        'status' => 'error',
                        'message' => "You cannot post to {$platform}. Please connect your account first."
                    ], 400);
                }
            }

            $validated['user_id'] = $user->_id;

            // Set status based on scheduling
            if (isset($validated['scheduled_at'])) {
                $validated['post_status'] = 'scheduled';
            } else {
                $validated['post_status'] = 'draft';
            }

            $post = SocialMediaPost::create($validated);

            // Create scheduled posts if needed
            if ($post->post_status === 'scheduled') {
                foreach ($post->platforms as $platform) {
                    ScheduledPost::create([
                        'user_id' => $user->_id,
                        'social_media_post_id' => $post->_id,
                        'platform' => $platform,
                        'scheduled_at' => $post->scheduled_at,
                        'status' => 'pending'
                    ]);
                }
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Post created successfully',
                'data' => [
                    'post' => $post,
                    'total_engagement' => $post->getTotalEngagement(),
                    'remaining_posts' => $user->getRemainingPosts()
                ]
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create post',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified post
     */
    public function show(Request $request, string $id): JsonResponse
    {
        try {
            $user = $request->user();
            $post = SocialMediaPost::where('user_id', $user->_id)->findOrFail($id);

            // Get scheduled posts for this post
            $scheduledPosts = ScheduledPost::where('social_media_post_id', $post->_id)->get();

            // Get analytics if available
            $analytics = \App\Models\PostAnalytics::where('social_media_post_id', $post->_id)->get();

            return response()->json([
                'status' => 'success',
                'data' => [
                    'post' => $post,
                    'total_engagement' => $post->getTotalEngagement(),
                    'platforms_count' => count($post->platforms ?? []),
                    'scheduled_posts' => $scheduledPosts,
                    'analytics' => $analytics,
                    'platform_posts' => $post->platform_posts ?? [],
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Post not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Update the specified post
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $user = $request->user();
            $post = SocialMediaPost::where('user_id', $user->_id)->findOrFail($id);

            // Prevent editing published posts
            if ($post->post_status === 'published') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Cannot edit published posts'
                ], 409);
            }

            $validated = $request->validate([
                'content' => 'array',
                'content.text' => 'string|max:5000',
                'content.title' => 'string|max:255',
                'media' => 'array',
                'platforms' => 'array|min:1',
                'platforms.*' => 'string|in:twitter,facebook,instagram,linkedin,youtube,tiktok',
                'hashtags' => 'array',
                'mentions' => 'array',
                'scheduled_at' => 'date|after:now',
                'settings' => 'array'
            ]);

            // Validate platforms if provided
            if (isset($validated['platforms'])) {
                foreach ($validated['platforms'] as $platform) {
                    if (!$user->canPostTo($platform)) {
                        return response()->json([
                            'status' => 'error',
                            'message' => "You cannot post to {$platform}. Please connect your account first."
                        ], 400);
                    }
                }
            }

            $post->update($validated);

            return response()->json([
                'status' => 'success',
                'message' => 'Post updated successfully',
                'data' => $post->fresh()
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update post',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified post
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        try {
            $user = $request->user();
            $post = SocialMediaPost::where('user_id', $user->_id)->findOrFail($id);

            // Cancel any scheduled posts
            ScheduledPost::where('social_media_post_id', $post->_id)
                ->where('status', 'pending')
                ->update(['status' => 'cancelled']);

            $post->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Post deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete post',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Publish post immediately - UPDATED WITH FACEBOOK SUPPORT
     */
    public function publish(Request $request, string $id): JsonResponse
    {
        try {
            $user = $request->user();
            $post = SocialMediaPost::where('user_id', $user->_id)->findOrFail($id);

            if ($post->post_status === 'published') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Post is already published'
                ], 409);
            }

            $results = [];
            $hasErrors = false;

            // PUBLISH TO EACH PLATFORM USING YOUR PROVIDERS
            foreach ($post->platforms as $platform) {
                if ($platform === 'linkedin') {
                    // Get user's LinkedIn channel
                    $channel = \App\Models\Channel::where('provider', 'linkedin')
                        ->where('connection_status', 'connected')
                        ->first();

                    if (!$channel) {
                        $results[$platform] = [
                            'success' => false,
                            'error' => 'LinkedIn channel not connected'
                        ];
                        $hasErrors = true;
                        continue;
                    }

                    // USE YOUR LINKEDIN PROVIDER
                    $provider = new \App\Services\SocialMedia\LinkedInProvider();
                    $result = $provider->publishPost($post, $channel);

                    $results[$platform] = $result;

                    if ($result['success']) {
                        // Update platform post data
                        $post->updatePlatformPost($platform, [
                            'platform_id' => $result['platform_id'],
                            'published_at' => now(),
                            'url' => $result['url']
                        ]);

                        // DISPATCH ANALYTICS COLLECTION
                        \App\Jobs\CollectAnalytics::dispatch($post, $platform);
                    } else {
                        $hasErrors = true;
                    }
                } 
                // NEW FACEBOOK INTEGRATION
                elseif ($platform === 'facebook') {
                    // Get user's Facebook channel
                    $channel = \App\Models\Channel::where('provider', 'facebook')
                        ->where('connection_status', 'connected')
                        ->first();

                    if (!$channel) {
                        $results[$platform] = [
                            'success' => false,
                            'error' => 'Facebook channel not connected'
                        ];
                        $hasErrors = true;
                        continue;
                    }

                    // USE YOUR FACEBOOK PROVIDER
                    $provider = new \App\Services\SocialMedia\FacebookProvider();
                    $result = $provider->publishPost($post, $channel);

                    $results[$platform] = $result;

                    if ($result['success']) {
                        // Update platform post data
                        $post->updatePlatformPost($platform, [
                            'platform_id' => $result['platform_id'],
                            'published_at' => now(),
                            'url' => $result['url'],
                            'post_type' => $result['post_type'] ?? 'TEXT'
                        ]);

                        // 🔥 DISPATCH ANALYTICS COLLECTION FOR FACEBOOK
                        \App\Jobs\CollectAnalytics::dispatch($post, $platform);
                    } else {
                        $hasErrors = true;
                    }
                }
                // Add other platforms here later (Twitter, Instagram, etc.)
            }

            // Update post status
            if (!$hasErrors) {
                $post->update([
                    'post_status' => 'published',
                    'published_at' => now()
                ]);
            }

            return response()->json([
                'status' => $hasErrors ? 'partial_success' : 'success',
                'message' => $hasErrors ? 'Some platforms failed' : 'Post published successfully',
                'results' => $results,
                'data' => $post->fresh()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to publish post',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Duplicate post
     */
    public function duplicate(Request $request, string $id): JsonResponse
    {
        try {
            $user = $request->user();
            $originalPost = SocialMediaPost::where('user_id', $user->_id)->findOrFail($id);

            if ($user->hasReachedPostingLimit()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'You have reached your monthly posting limit'
                ], 429);
            }

            $duplicateData = $originalPost->toArray();
            unset($duplicateData['_id']);
            $duplicateData['post_status'] = 'draft';
            $duplicateData['scheduled_at'] = null;
            $duplicateData['published_at'] = null;
            $duplicateData['platform_posts'] = [];
            $duplicateData['engagement'] = [
                'likes' => 0,
                'shares' => 0,
                'comments' => 0,
                'clicks' => 0,
                'impressions' => 0,
            ];

            // Add "Copy" to content title if exists
            if (isset($duplicateData['content']['title'])) {
                $duplicateData['content']['title'] = 'Copy of ' . $duplicateData['content']['title'];
            }

            $duplicatedPost = SocialMediaPost::create($duplicateData);

            return response()->json([
                'status' => 'success',
                'message' => 'Post duplicated successfully',
                'data' => $duplicatedPost
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to duplicate post',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get post analytics - ENHANCED WITH FACEBOOK SUPPORT
     */
    public function analytics(Request $request, string $id): JsonResponse
    {
        try {
            $user = $request->user();
            $post = SocialMediaPost::where('user_id', $user->_id)->findOrFail($id);

            $analytics = \App\Models\PostAnalytics::where('social_media_post_id', $post->_id)
                ->orderBy('collected_at', 'desc')
                ->get();

            // COLLECT REAL-TIME ANALYTICS FROM FACEBOOK IF AVAILABLE
            $platformAnalytics = [];
            
            if (in_array('facebook', $post->platforms ?? [])) {
                $facebookData = $post->platform_posts['facebook'] ?? null;
                if ($facebookData && isset($facebookData['platform_id'])) {
                    $facebookAnalytics = \App\Helpers\FacebookHelpers::getFacebookAnalyticsSummary($post);
                    if ($facebookAnalytics['success']) {
                        $platformAnalytics['facebook'] = $facebookAnalytics;
                    }
                }
            }

            if (in_array('linkedin', $post->platforms ?? [])) {
                $linkedinData = $post->platform_posts['linkedin'] ?? null;
                if ($linkedinData && isset($linkedinData['platform_id'])) {
                    $linkedinAnalytics = \App\Helpers\LinkedInHelpers::checkLinkedInPostStatusWithProvider($post);
                    $platformAnalytics['linkedin'] = $linkedinAnalytics;
                }
            }

            $summary = [
                'total_impressions' => $analytics->sum('metrics.impressions'),
                'total_engagement' => $post->getTotalEngagement(),
                'avg_engagement_rate' => $analytics->avg('metrics.engagement_rate'),
                'best_performing_platform' => $analytics->sortByDesc('performance_score')->first()?->platform,
                'latest_sync' => $analytics->first()?->collected_at,
                'real_time_data_available' => !empty($platformAnalytics)
            ];

            return response()->json([
                'status' => 'success',
                'data' => [
                    'post' => $post,
                    'analytics' => $analytics,
                    'platform_analytics' => $platformAnalytics,
                    'summary' => $summary
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve analytics',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}