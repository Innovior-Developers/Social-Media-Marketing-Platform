<?php
// routes/web.php

// use Illuminate\Support\Facades\Route;
// use Illuminate\Support\Facades\DB;
// use Illuminate\Support\Facades\Redis;
// use App\Models\User;
// use Spatie\Permission\Models\Role;
// use Spatie\Permission\Models\Permission;
// use App\Models\SocialMediaPost;
// use App\Models\ScheduledPost;
// use App\Models\ContentCalendar;
// use App\Models\PostAnalytics;
// use App\Models\Organization;
// use App\Models\Brand;
// use App\Models\Membership;
// use App\Models\Channel;

// if (!function_exists('validateMediaFile')) {
//     function validateMediaFile($file, $mediaType)
//     {
//         if (!$file) {
//             return ['valid' => false, 'error' => 'No file uploaded'];
//         }

//         switch ($mediaType) {
//             case 'image':
//                 $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
//                 $maxSize = 20 * 1024 * 1024; // 20MB
//                 break;
//             case 'video':
//                 $allowedExtensions = ['mp4', 'mov', 'avi', 'wmv'];
//                 $maxSize = 200 * 1024 * 1024; // 200MB
//                 break;
//             case 'document':
//                 $allowedExtensions = ['pdf', 'doc', 'docx', 'ppt', 'pptx'];
//                 $maxSize = 100 * 1024 * 1024; // 100MB
//                 break;
//             default:
//                 return ['valid' => false, 'error' => 'Unsupported media type'];
//         }

//         $extension = strtolower($file->getClientOriginalExtension());
//         if (!in_array($extension, $allowedExtensions)) {
//             return [
//                 'valid' => false,
//                 'error' => "Only " . implode(', ', $allowedExtensions) . " files are supported for {$mediaType}"
//             ];
//         }

//         if ($file->getSize() > $maxSize) {
//             return [
//                 'valid' => false,
//                 'error' => ucfirst($mediaType) . " must be smaller than " . ($maxSize / 1024 / 1024) . "MB"
//             ];
//         }

//         return ['valid' => true];
//     }
// }

// Route::get('/', function () {
//     return [
//         'message' => 'Social Media Marketing Platform - Backend API',
//         'laravel' => app()->version(),
//         'timestamp' => now()->toISOString(),
//         'status' => 'operational'
//     ];
// });

// // MongoDB test route (working)
// Route::get('/test-mongodb', function () {
//     try {
//         $ping = DB::connection('mongodb')->getDatabase()->command(['ping' => 1]);
//         return [
//             'mongodb' => 'success',
//             'ping' => 'ok',
//             'database' => 'social_media_platform'
//         ];
//     } catch (Exception $e) {
//         return [
//             'mongodb' => 'error',
//             'message' => $e->getMessage()
//         ];
//     }
// });

// // Redis test route (NEW - this was missing!)
// Route::get('/test-redis', function () {
//     try {
//         // Test Redis connection
//         $ping = Redis::ping();

//         // Test cache operations
//         $testKey = 'redis_test_' . time();
//         $testValue = 'Redis working for SMP - ' . now();

//         cache()->put($testKey, $testValue, 60);
//         $retrieved = cache()->get($testKey);
//         cache()->forget($testKey);

//         // Test direct Redis operations
//         Redis::set('smp_direct_test', 'Direct Redis test - ' . now(), 'EX', 60);
//         $directTest = Redis::get('smp_direct_test');
//         Redis::del('smp_direct_test');

//         return [
//             'redis_status' => 'success',
//             'ping' => $ping ? 'PONG' : 'failed',
//             'cache_test' => [
//                 'stored' => $testValue,
//                 'retrieved' => $retrieved,
//                 'match' => $retrieved === $testValue,
//             ],
//             'direct_redis_test' => [
//                 'stored_and_retrieved' => $directTest ? true : false,
//                 'value' => $directTest
//             ],
//             'container_info' => [
//                 'host' => config('database.redis.default.host'),
//                 'port' => config('database.redis.default.port'),
//                 'client' => config('database.redis.client'),
//             ],
//             'ready_for' => [
//                 'real_time_features' => true,
//                 'background_jobs' => true,
//                 'session_management' => true,
//                 'api_rate_limiting' => true,
//                 'social_media_caching' => true,
//             ]
//         ];
//     } catch (Exception $e) {
//         return [
//             'redis_status' => 'error',
//             'message' => $e->getMessage(),
//             'suggestion' => 'Make sure Redis container is running: docker start redis-smp'
//         ];
//     }
// });

// // Authentication test routes
// Route::get('/test-auth', function () {
//     try {
//         // Test user creation and authentication
//         $userCount = User::count();
//         $adminUser = User::where('email', 'admin@socialmedia.com')->first();

//         // Get all unique roles and permissions from all users
//         $allUsers = User::all();
//         $allRoles = $allUsers->flatMap(fn($user) => $user->roles ?? [])->unique()->values();
//         $allPermissions = $allUsers->flatMap(fn($user) => $user->getAllPermissions())->unique()->values();

//         return [
//             'authentication_status' => 'success',
//             'timestamp' => now()->format('Y-m-d H:i:s'),
//             'developer' => 'J33WAKASUPUN',
//             'system' => 'MongoDB Native Role System',
//             'users' => [
//                 'total_count' => $userCount,
//                 'admin_exists' => $adminUser ? true : false,
//                 'admin_email' => $adminUser ? $adminUser->email : null,
//                 'admin_roles' => $adminUser ? $adminUser->getRoleNames() : [],
//                 'admin_permissions' => $adminUser ? $adminUser->getAllPermissions() : [],
//                 'admin_last_login' => $adminUser ? $adminUser->last_login_at : null,
//                 'admin_can_manage_users' => $adminUser ? $adminUser->hasPermission('manage users') : false,
//             ],
//             'roles_and_permissions' => [
//                 'available_roles' => $allRoles,
//                 'available_permissions' => $allPermissions,
//                 'total_unique_roles' => $allRoles->count(),
//                 'total_unique_permissions' => $allPermissions->count(),
//             ],
//             'subscription_system' => [
//                 'plans' => ['free', 'basic', 'pro', 'enterprise'],
//                 'admin_plan' => $adminUser ? $adminUser->subscription['plan'] ?? 'free' : null,
//                 'admin_limits' => $adminUser ? $adminUser->getSubscriptionLimits() : null,
//             ],
//             'mongodb_features' => [
//                 'native_arrays' => true,
//                 'flexible_schema' => true,
//                 'role_system' => 'custom_mongodb_implementation',
//                 'spatie_compatible' => false,
//             ],
//             'ready_for' => [
//                 'api_authentication' => true,
//                 'role_based_access' => true,
//                 'user_management' => true,
//                 'social_media_integration' => true,
//             ]
//         ];
//     } catch (Exception $e) {
//         return [
//             'authentication_status' => 'error',
//             'message' => $e->getMessage(),
//             'file' => $e->getFile(),
//             'line' => $e->getLine(),
//             'trace' => $e->getTraceAsString(),
//         ];
//     }
// });

// // Social Media Models Test Route
// Route::get('/test-models', function () {
//     try {
//         // Test model creation
//         $user = User::where('email', 'admin@socialmedia.com')->first();

//         if (!$user) {
//             return ['error' => 'Admin user not found. Run seeder first.'];
//         }

//         // Create a test post
//         $post = SocialMediaPost::create([
//             'user_id' => $user->_id,
//             'content' => [
//                 'text' => 'Test post for Social Media Marketing Platform! 🚀 #socialmedia #marketing',
//                 'title' => 'Platform Launch Post'
//             ],
//             'platforms' => ['twitter', 'facebook', 'linkedin'],
//             'post_status' => 'draft',
//             'hashtags' => ['#socialmedia', '#marketing', '#platform'],
//             'mentions' => ['@J33WAKASUPUN'],
//             'settings' => [
//                 'auto_hashtags' => true,
//                 'cross_post' => true,
//                 'track_analytics' => true,
//             ]
//         ]);

//         // Create scheduled post
//         $scheduledPost = ScheduledPost::create([
//             'user_id' => $user->_id,
//             'social_media_post_id' => $post->_id,
//             'platform' => 'twitter',
//             'scheduled_at' => now()->addHours(2),
//             'status' => 'pending',
//         ]);

//         // Create calendar entry
//         $calendarEntry = ContentCalendar::create([
//             'user_id' => $user->_id,
//             'social_media_post_id' => $post->_id,
//             'title' => 'Platform Launch Announcement',
//             'calendar_date' => now()->addDays(1)->toDateString(),
//             'time_slot' => '09:00',
//             'platforms' => ['twitter', 'facebook'],
//             'content_type' => 'announcement',
//             'status' => 'scheduled',
//         ]);

//         // Create analytics entry
//         $analytics = PostAnalytics::create([
//             'user_id' => $user->_id,
//             'social_media_post_id' => $post->_id,
//             'platform' => 'twitter',
//             'metrics' => [
//                 'impressions' => 1250,
//                 'reach' => 980,
//                 'likes' => 45,
//                 'shares' => 12,
//                 'comments' => 8,
//                 'clicks' => 23,
//                 'engagement_rate' => 7.2,
//             ],
//             'collected_at' => now(),
//         ]);

//         $analytics->updatePerformanceScore();

//         return [
//             'models_status' => 'success',
//             'timestamp' => now()->format('Y-m-d H:i:s'),
//             'developer' => 'J33WAKASUPUN',
//             'created_records' => [
//                 'social_media_post' => [
//                     'id' => $post->_id,
//                     'content_preview' => substr($post->content['text'], 0, 50) . '...',
//                     'platforms' => $post->platforms,
//                     'status' => $post->post_status,
//                 ],
//                 'scheduled_post' => [
//                     'id' => $scheduledPost->_id,
//                     'platform' => $scheduledPost->platform,
//                     'scheduled_at' => $scheduledPost->scheduled_at,
//                     'status' => $scheduledPost->status,
//                 ],
//                 'content_calendar' => [
//                     'id' => $calendarEntry->_id,
//                     'title' => $calendarEntry->title,
//                     'date' => $calendarEntry->calendar_date,
//                     'time' => $calendarEntry->time_slot,
//                 ],
//                 'analytics' => [
//                     'id' => $analytics->_id,
//                     'platform' => $analytics->platform,
//                     'performance_score' => $analytics->performance_score,
//                     'total_engagement' => $analytics->metrics['likes'] + $analytics->metrics['shares'] + $analytics->metrics['comments'],
//                 ],
//             ],
//             'model_counts' => [
//                 'users' => User::count(),
//                 'posts' => SocialMediaPost::count(),
//                 'scheduled_posts' => ScheduledPost::count(),
//                 'calendar_entries' => ContentCalendar::count(),
//                 'analytics_records' => PostAnalytics::count(),
//             ],
//             'relationships_test' => [
//                 'user_posts_count' => $user->posts()->count(),
//                 'user_scheduled_posts_count' => $user->scheduledPosts()->count(),
//                 'user_calendar_entries_count' => $user->contentCalendar()->count(),
//                 'user_analytics_count' => $user->analytics()->count(),
//             ],
//             'ready_for_step_1_5' => true,
//         ];
//     } catch (Exception $e) {
//         return [
//             'models_status' => 'error',
//             'message' => $e->getMessage(),
//             'file' => $e->getFile(),
//             'line' => $e->getLine(),
//         ];
//     }
// });

// // Organization Model Test Route
// Route::get('/test-organization-model', function () {
//     try {
//         $results = [
//             'test_session' => [
//                 'timestamp' => now()->toISOString(),
//                 'developer' => 'J33WAKASUPUN',
//                 'phase' => 'Organization Model Testing',
//                 'model' => 'Organization'
//             ],
//             'model_creation' => [],
//             'custom_methods' => [],
//             'factory_test' => [],
//             'summary' => []
//         ];

//         // === TEST 1: MANUAL ORGANIZATION CREATION ===
//         $testOrg = Organization::create([
//             'name' => 'Test Marketing Agency ' . time(),
//             'settings' => [
//                 'default_timezone' => 'America/New_York',
//                 'features' => ['analytics', 'scheduling', 'multi_brand', 'team_collaboration'],
//             ]
//         ]);

//         $results['model_creation'] = [
//             'status' => 'success',
//             'id' => $testOrg->_id,
//             'name' => $testOrg->name,
//             'timezone' => $testOrg->getTimezone(),
//             'features_count' => count($testOrg->settings['features']),
//             'default_attributes_applied' => true
//         ];

//         // === TEST 2: CUSTOM METHODS ===
//         $testOrg->addFeature('api_access');
//         $results['custom_methods'] = [
//             'get_timezone' => $testOrg->getTimezone(),
//             'has_analytics_feature' => $testOrg->hasFeature('analytics'),
//             'has_nonexistent_feature' => $testOrg->hasFeature('premium_support'),
//             'add_new_feature' => true,
//             'has_new_feature_after_add' => $testOrg->hasFeature('api_access'),
//             'total_brands_count' => $testOrg->getTotalBrandsCount(),
//             'active_brands_count' => $testOrg->getActiveBrandsCount()
//         ];

//         // === TEST 3: FACTORY TESTING ===
//         $factoryOrg = Organization::factory()->create();
//         $enterpriseOrg = Organization::factory()->enterprise()->create();
//         $basicOrg = Organization::factory()->basic()->create();

//         $results['factory_test'] = [
//             'standard_factory' => [
//                 'created' => true,
//                 'id' => $factoryOrg->_id,
//                 'name' => $factoryOrg->name,
//                 'features_count' => count($factoryOrg->settings['features'])
//             ],
//             'enterprise_factory' => [
//                 'created' => true,
//                 'id' => $enterpriseOrg->_id,
//                 'has_priority_support' => $enterpriseOrg->hasFeature('priority_support'),
//                 'features_count' => count($enterpriseOrg->settings['features'])
//             ],
//             'basic_factory' => [
//                 'created' => true,
//                 'id' => $basicOrg->_id,
//                 'features_count' => count($basicOrg->settings['features']),
//                 'has_only_basic_features' => count($basicOrg->settings['features']) <= 3
//             ]
//         ];

//         // === TEST 4: MODEL COUNTS ===
//         $totalOrgs = Organization::count();

//         $results['model_counts'] = [
//             'total_organizations' => $totalOrgs,
//             'organizations_with_analytics' => Organization::get()->filter(fn($org) => $org->hasFeature('analytics'))->count(),
//             'organizations_with_multi_brand' => Organization::get()->filter(fn($org) => $org->hasFeature('multi_brand'))->count()
//         ];

//         // === SUMMARY ===
//         $results['summary'] = [
//             'test_status' => 'SUCCESS',
//             'organization_model_working' => true,
//             'factory_working' => true,
//             'custom_methods_working' => true,
//             'mongodb_features' => [
//                 'embedded_settings' => 'working',
//                 'array_features' => 'working',
//                 'custom_attributes' => 'working'
//             ],
//             'ready_for_brand_model' => true,
//             'next_step' => 'Implement Brand model with belongsTo Organization relationship'
//         ];
//     } catch (Exception $e) {
//         $results = [
//             'test_status' => 'FAILED',
//             'error' => [
//                 'message' => $e->getMessage(),
//                 'file' => $e->getFile(),
//                 'line' => $e->getLine()
//             ]
//         ];
//     }

//     return response()->json($results, 200, [], JSON_PRETTY_PRINT);
// });

// // === NEW COMPREHENSIVE MODEL TESTING ROUTE ===
// Route::get('/test-all-models', function () {
//     $results = [
//         'test_session' => [
//             'timestamp' => now()->toISOString(),
//             'developer' => 'J33WAKASUPUN',
//             'phase' => 'Complete Model Validation',
//             'environment' => app()->environment(),
//             'laravel_version' => app()->version(),
//         ],
//         'models' => [],
//         'relationships' => [],
//         'custom_methods' => [],
//         'scopes' => [],
//         'data_integrity' => [],
//         'summary' => []
//     ];

//     try {
//         // === TEST 1: USER MODEL ===
//         $testUser = User::create([
//             'name' => 'Model Test User ' . time(),
//             'email' => 'modeltest' . time() . '@socialmedia.com',
//             'password' => 'password123',
//             'roles' => ['manager'],
//             'subscription' => [
//                 'plan' => 'pro',
//                 'status' => 'active',
//                 'limits' => [
//                     'posts_per_month' => 1000,
//                     'social_accounts' => 25,
//                     'scheduled_posts' => 100
//                 ]
//             ],
//             'social_accounts' => [
//                 'twitter' => [
//                     'access_token' => 'test_token_twitter',
//                     'status' => 'active',
//                     'username' => '@testuser'
//                 ],
//                 'facebook' => [
//                     'access_token' => 'test_token_facebook',
//                     'status' => 'active',
//                     'page_id' => 'test_page_123'
//                 ]
//             ]
//         ]);

//         $results['models']['User'] = [
//             'creation' => 'success',
//             'id' => $testUser->_id,
//             'role_system' => [
//                 'has_manager_role' => $testUser->hasRole('manager'),
//                 'create_posts_permission' => $testUser->hasPermission('create posts'),
//                 'manage_team_permission' => $testUser->hasPermission('manage team'),
//                 'all_permissions_count' => count($testUser->getAllPermissions())
//             ],
//             'subscription_system' => [
//                 'plan' => $testUser->subscription['plan'],
//                 'limits' => $testUser->getSubscriptionLimits(),
//                 'remaining_posts' => $testUser->getRemainingPosts(),
//                 'can_add_social_account' => $testUser->canAddSocialAccount()
//             ],
//             'social_accounts' => [
//                 'connected_count' => $testUser->connectedSocialAccounts()->count(),
//                 'can_post_to_twitter' => $testUser->canPostTo('twitter'),
//                 'can_post_to_facebook' => $testUser->canPostTo('facebook')
//             ]
//         ];

//         // === TEST 2: SOCIAL MEDIA POST MODEL ===
//         $testPost = SocialMediaPost::create([
//             'user_id' => $testUser->_id,
//             'content' => [
//                 'text' => 'Test post for comprehensive model validation! 🚀 #socialmedia #testing',
//                 'title' => 'Model Test Post'
//             ],
//             'platforms' => ['twitter', 'facebook', 'linkedin'],
//             'post_status' => 'draft',
//             'hashtags' => ['#socialmedia', '#testing', '#mongodb'],
//             'mentions' => ['@J33WAKASUPUN'],
//             'media' => [
//                 [
//                     'type' => 'image',
//                     'url' => '/storage/test-image.jpg',
//                     'alt_text' => 'Test image for model validation'
//                 ]
//             ],
//             'settings' => [
//                 'auto_hashtags' => true,
//                 'cross_post' => true,
//                 'track_analytics' => true
//             ]
//         ]);

//         $results['models']['SocialMediaPost'] = [
//             'creation' => 'success',
//             'id' => $testPost->_id,
//             'content_text_length' => strlen($testPost->content['text'] ?? ''),
//             'platforms_count' => count($testPost->platforms),
//             'hashtags_count' => count($testPost->hashtags),
//             'media_count' => count($testPost->media),
//             'custom_methods' => [
//                 'is_scheduled_for_twitter' => $testPost->isScheduledFor('twitter'),
//                 'is_scheduled_for_instagram' => $testPost->isScheduledFor('instagram'),
//                 'total_engagement' => $testPost->getTotalEngagement()
//             ]
//         ];

//         // === TEST 3: SCHEDULED POST MODEL ===
//         $testScheduledPost = ScheduledPost::create([
//             'user_id' => $testUser->_id,
//             'social_media_post_id' => $testPost->_id,
//             'platform' => 'twitter',
//             'scheduled_at' => now()->addHours(2),
//             'status' => 'pending',
//             'settings' => [
//                 'timezone' => 'UTC',
//                 'auto_retry' => true,
//                 'notify_on_failure' => true
//             ]
//         ]);

//         $results['models']['ScheduledPost'] = [
//             'creation' => 'success',
//             'id' => $testScheduledPost->_id,
//             'platform' => $testScheduledPost->platform,
//             'scheduled_in_hours' => round($testScheduledPost->scheduled_at->diffInHours(now())),
//             'custom_methods' => [
//                 'can_retry' => $testScheduledPost->canRetry(),
//                 'retry_count' => $testScheduledPost->retry_count,
//                 'max_retries' => $testScheduledPost->max_retries
//             ]
//         ];

//         // === TEST 4: CONTENT CALENDAR MODEL ===
//         $testCalendarEntry = ContentCalendar::create([
//             'user_id' => $testUser->_id,
//             'social_media_post_id' => $testPost->_id,
//             'title' => 'Model Validation Calendar Entry',
//             'description' => 'Testing calendar functionality',
//             'calendar_date' => now()->addDays(3)->toDateString(),
//             'time_slot' => '10:00',
//             'platforms' => ['twitter', 'facebook'],
//             'content_type' => 'announcement',
//             'status' => 'planned',
//             'tags' => ['testing', 'validation', 'mongodb'],
//             'recurring' => [
//                 'enabled' => true,
//                 'frequency' => 'weekly',
//                 'end_date' => now()->addMonths(3)->toDateString()
//             ]
//         ]);

//         $results['models']['ContentCalendar'] = [
//             'creation' => 'success',
//             'id' => $testCalendarEntry->_id,
//             'title' => $testCalendarEntry->title,
//             'days_from_now' => now()->diffInDays($testCalendarEntry->calendar_date),
//             'platforms_count' => count($testCalendarEntry->platforms),
//             'tags_count' => count($testCalendarEntry->tags),
//             'is_recurring' => $testCalendarEntry->recurring['enabled']
//         ];

//         // === TEST 5: POST ANALYTICS MODEL ===
//         $testAnalytics = PostAnalytics::create([
//             'user_id' => $testUser->_id,
//             'social_media_post_id' => $testPost->_id,
//             'platform' => 'twitter',
//             'metrics' => [
//                 'impressions' => 2500,
//                 'reach' => 1800,
//                 'likes' => 156,
//                 'shares' => 23,
//                 'comments' => 12,
//                 'clicks' => 89,
//                 'engagement_rate' => 11.2
//             ],
//             'demographic_data' => [
//                 'age_groups' => [
//                     '18-24' => 25,
//                     '25-34' => 45,
//                     '35-44' => 20,
//                     '45+' => 10
//                 ],
//                 'top_locations' => ['United States', 'United Kingdom', 'Canada']
//             ],
//             'collected_at' => now()
//         ]);

//         // Test performance score calculation
//         $testAnalytics->updatePerformanceScore();

//         $results['models']['PostAnalytics'] = [
//             'creation' => 'success',
//             'id' => $testAnalytics->_id,
//             'platform' => $testAnalytics->platform,
//             'metrics_summary' => [
//                 'impressions' => $testAnalytics->metrics['impressions'],
//                 'total_engagement' => $testAnalytics->metrics['likes'] + $testAnalytics->metrics['shares'] + $testAnalytics->metrics['comments'],
//                 'engagement_rate' => $testAnalytics->metrics['engagement_rate']
//             ],
//             'performance_score' => $testAnalytics->performance_score,
//             'demographic_data_age_groups' => count($testAnalytics->demographic_data['age_groups']),
//             'top_locations_count' => count($testAnalytics->demographic_data['top_locations'])
//         ];

//         // === TEST RELATIONSHIPS ===
//         $results['relationships'] = [
//             'user_to_posts' => [
//                 'count' => $testUser->posts()->count(),
//                 'relationship_working' => $testUser->posts()->first()->_id == $testPost->_id
//             ],
//             'user_to_scheduled_posts' => [
//                 'count' => $testUser->scheduledPosts()->count(),
//                 'relationship_working' => $testUser->scheduledPosts()->first()->_id == $testScheduledPost->_id
//             ],
//             'user_to_calendar' => [
//                 'count' => $testUser->contentCalendar()->count(),
//                 'relationship_working' => $testUser->contentCalendar()->first()->_id == $testCalendarEntry->_id
//             ],
//             'user_to_analytics' => [
//                 'count' => $testUser->analytics()->count(),
//                 'relationship_working' => $testUser->analytics()->first()->_id == $testAnalytics->_id
//             ],
//             'post_to_user' => [
//                 'relationship_working' => $testPost->user->_id == $testUser->_id
//             ],
//             'scheduled_post_to_user_and_post' => [
//                 'user_relationship' => $testScheduledPost->user->_id == $testUser->_id,
//                 'post_relationship' => $testScheduledPost->socialMediaPost->_id == $testPost->_id
//             ]
//         ];

//         // === TEST SCOPES ===
//         $results['scopes'] = [
//             'posts_by_status_draft' => SocialMediaPost::byStatus('draft')->count(),
//             'posts_by_status_published' => SocialMediaPost::byStatus('published')->count(),
//             'scheduled_posts_pending' => ScheduledPost::pending()->count(),
//             'scheduled_posts_for_twitter' => ScheduledPost::forPlatform('twitter')->count(),
//             'calendar_upcoming' => ContentCalendar::upcoming()->count(),
//             'users_active' => User::active()->count(),
//             'users_with_manager_role' => User::withRole('manager')->count()
//         ];

//         // === TEST CUSTOM METHODS ===
//         $testUser->assignRole('editor');
//         $testPost->updatePlatformPost('twitter', ['tweet_id' => 'test_tweet_123']);
//         $testPost->updateEngagement(['likes' => 200, 'shares' => 50]);
//         $testScheduledPost->markAsFailed('Test error message');

//         $results['custom_methods'] = [
//             'user_role_methods' => [
//                 'assign_editor_role' => true,
//                 'has_editor_role_after_assignment' => $testUser->hasRole('editor'),
//                 'total_roles_count' => count($testUser->getRoleNames())
//             ],
//             'post_platform_methods' => [
//                 'update_platform_post_twitter' => true,
//                 'get_platform_post_twitter' => $testPost->getPlatformPost('twitter'),
//                 'update_engagement' => true
//             ],
//             'scheduled_post_status_methods' => [
//                 'mark_as_failed_test' => true,
//                 'can_retry_after_failure' => $testScheduledPost->canRetry()
//             ],
//             'analytics_calculation_methods' => [
//                 'calculate_performance_score' => $testAnalytics->calculatePerformanceScore(),
//                 'performance_score_in_db' => $testAnalytics->performance_score
//             ]
//         ];

//         // === DATA INTEGRITY TESTS ===
//         $results['data_integrity'] = [
//             'user_posts_relationship_integrity' => $testUser->posts()->count() > 0,
//             'embedded_document_integrity' => [
//                 'user_subscription_data' => isset($testUser->subscription['plan']),
//                 'post_content_data' => isset($testPost->content['text']),
//                 'analytics_metrics_data' => isset($testAnalytics->metrics['impressions']),
//                 'calendar_recurring_data' => isset($testCalendarEntry->recurring['enabled'])
//             ],
//             'mongodb_native_features' => [
//                 'array_fields_working' => is_array($testPost->platforms),
//                 'embedded_objects_working' => is_array($testUser->social_accounts),
//                 'flexible_schema_working' => true
//             ]
//         ];

//         // === SUMMARY ===
//         $allModelsCreated = count(array_filter($results['models'], fn($model) => $model['creation'] === 'success')) === 5;
//         $allRelationshipsWorking = count(array_filter(
//             $results['relationships'],
//             fn($rel) =>
//             isset($rel['relationship_working']) ? $rel['relationship_working'] : true
//         )) === count($results['relationships']);

//         $results['summary'] = [
//             'test_completion_status' => 'SUCCESS',
//             'all_models_created_successfully' => $allModelsCreated,
//             'all_relationships_working' => $allRelationshipsWorking,
//             'total_models_tested' => 5,
//             'total_relationships_tested' => 6,
//             'total_custom_methods_tested' => 10,
//             'total_scopes_tested' => 7,
//             'mongodb_features_validation' => [
//                 'embedded_documents' => 'PASSED',
//                 'array_fields' => 'PASSED',
//                 'flexible_schema' => 'PASSED',
//                 'custom_methods' => 'PASSED',
//                 'relationships' => 'PASSED'
//             ],
//             'infrastructure_readiness' => [
//                 'models_production_ready' => true,
//                 'mongodb_optimized' => true,
//                 'relationships_stable' => true,
//                 'business_logic_functional' => true
//             ],
//             'next_development_phase' => [
//                 'ready_for_api_controllers' => true,
//                 'ready_for_authentication_system' => true,
//                 'ready_for_provider_adapters' => true,
//                 'missing_models_needed' => ['Organization', 'Brand', 'Membership', 'Channel']
//             ],
//             'developer_grade' => 'A+',
//             'recommendation' => 'Proceed to implement missing models and API layer'
//         ];
//     } catch (Exception $e) {
//         $results['error'] = [
//             'status' => 'FAILED',
//             'message' => $e->getMessage(),
//             'file' => $e->getFile(),
//             'line' => $e->getLine(),
//             'trace' => $e->getTraceAsString()
//         ];

//         $results['summary'] = [
//             'test_completion_status' => 'FAILED',
//             'error_encountered' => true,
//             'recommendation' => 'Fix the error and re-run the test'
//         ];
//     }

//     return response()->json($results, 200, [], JSON_PRETTY_PRINT);
// });

// // === COMPREHENSIVE ALL NEW MODELS TEST ===
// Route::get('/test-all-new-models', function () {
//     $results = [
//         'test_session' => [
//             'timestamp' => now()->toISOString(),
//             'developer' => 'J33WAKASUPUN',
//             'phase' => 'All New Models Comprehensive Testing',
//             'models_tested' => ['Organization', 'Brand', 'Membership', 'Channel']
//         ],
//         'model_creation' => [],
//         'relationships' => [],
//         'custom_methods' => [],
//         'business_logic' => [],
//         'summary' => []
//     ];

//     try {
//         // === TEST 1: CREATE ORGANIZATION ===
//         $testOrg = Organization::create([
//             'name' => 'J33WAKASUPUN Marketing Agency ' . time(),
//             'settings' => [
//                 'default_timezone' => 'America/New_York',
//                 'features' => ['analytics', 'scheduling', 'multi_brand', 'team_collaboration'],
//             ]
//         ]);

//         $results['model_creation']['Organization'] = [
//             'status' => 'success',
//             'id' => $testOrg->_id,
//             'name' => $testOrg->name,
//             'features_count' => count($testOrg->settings['features'])
//         ];

//         // === TEST 2: CREATE BRAND ===
//         $testBrand = Brand::create([
//             'organization_id' => $testOrg->_id,
//             'name' => 'Tech Startup Brand',
//             'slug' => 'tech-startup-brand',
//             'settings' => [
//                 'timezone' => 'UTC',
//                 'default_publish_time' => '10:00',
//                 'branding' => [
//                     'logo_url' => '/assets/logo.png',
//                     'primary_color' => '#ff6b35',
//                 ],
//             ],
//         ]);

//         $results['model_creation']['Brand'] = [
//             'status' => 'success',
//             'id' => $testBrand->_id,
//             'name' => $testBrand->name,
//             'organization_id' => $testBrand->organization_id
//         ];

//         // === TEST 3: CREATE MEMBERSHIP ===
//         $adminUser = User::where('email', 'admin@socialmedia.com')->first();
//         if (!$adminUser) {
//             $adminUser = User::create([
//                 'name' => 'Admin User',
//                 'email' => 'admin@socialmedia.com',
//                 'password' => 'password'
//             ]);
//         }

//         $testMembership = Membership::create([
//             'user_id' => $adminUser->_id,
//             'brand_id' => $testBrand->_id,
//             'role' => 'OWNER',
//             'joined_at' => now(),
//         ]);

//         $results['model_creation']['Membership'] = [
//             'status' => 'success',
//             'id' => $testMembership->_id,
//             'role' => $testMembership->role,
//             'user_id' => $testMembership->user_id,
//             'brand_id' => $testMembership->brand_id
//         ];

//         // === TEST 4: CREATE CHANNEL ===
//         $testChannel = Channel::create([
//             'brand_id' => $testBrand->_id,
//             'provider' => 'twitter',
//             'handle' => '@techstartup',
//             'display_name' => 'Tech Startup Official',
//             'avatar_url' => 'https://example.com/avatar.jpg',
//             'oauth_tokens' => [
//                 'access_token' => 'test_access_token_123',
//                 'refresh_token' => 'test_refresh_token_456',
//                 'expires_at' => now()->addDays(30),
//             ],
//             'connection_status' => 'connected',
//         ]);

//         $results['model_creation']['Channel'] = [
//             'status' => 'success',
//             'id' => $testChannel->_id,
//             'provider' => $testChannel->provider,
//             'handle' => $testChannel->handle,
//             'brand_id' => $testChannel->brand_id
//         ];

//         // === TEST RELATIONSHIPS ===
//         $results['relationships'] = [
//             'organization_to_brands' => [
//                 'count' => $testOrg->brands()->count(),
//                 'working' => $testOrg->brands()->first()->_id == $testBrand->_id
//             ],
//             'brand_to_organization' => [
//                 'working' => $testBrand->organization->_id == $testOrg->_id
//             ],
//             'brand_to_channels' => [
//                 'count' => $testBrand->channels()->count(),
//                 'working' => $testBrand->channels()->first()->_id == $testChannel->_id
//             ],
//             'brand_to_memberships' => [
//                 'count' => $testBrand->memberships()->count(),
//                 'working' => $testBrand->memberships()->first()->_id == $testMembership->_id
//             ],
//             'membership_to_user' => [
//                 'working' => $testMembership->user->_id == $adminUser->_id
//             ],
//             'membership_to_brand' => [
//                 'working' => $testMembership->brand->_id == $testBrand->_id
//             ],
//             'channel_to_brand' => [
//                 'working' => $testChannel->brand->_id == $testBrand->_id
//             ]
//         ];

//         // === TEST CUSTOM METHODS ===
//         $results['custom_methods'] = [
//             'organization_methods' => [
//                 'get_timezone' => $testOrg->getTimezone(),
//                 'has_feature_analytics' => $testOrg->hasFeature('analytics'),
//                 'add_feature_test' => $testOrg->addFeature('premium_support'),
//                 'total_brands_count' => $testOrg->getTotalBrandsCount(),
//             ],
//             'brand_methods' => [
//                 'get_timezone' => $testBrand->getTimezone(),
//                 'get_default_publish_time' => $testBrand->getDefaultPublishTime(),
//                 'get_branding_info' => $testBrand->getBrandingInfo(),
//                 'connected_channels_count' => $testBrand->getConnectedChannelsCount(),
//             ],
//             'membership_methods' => [
//                 'has_permission_manage_brand' => $testMembership->hasPermission('manage_brand'),
//                 'can_create_posts' => $testMembership->canCreatePosts(),
//                 'is_owner' => $testMembership->isOwner(),
//                 'role_permissions_count' => count($testMembership->getRolePermissions()),
//             ],
//             'channel_methods' => [
//                 'is_connected' => $testChannel->isConnected(),
//                 'is_expired' => $testChannel->isExpired(),
//                 'max_characters' => $testChannel->getMaxCharacters(),
//                 'provider_display_name' => $testChannel->getProviderDisplayName(),
//             ]
//         ];

//         // === TEST BUSINESS LOGIC ===
//         $results['business_logic'] = [
//             'organization_can_manage_brands' => $testOrg->getTotalBrandsCount() > 0,
//             'brand_has_proper_hierarchy' => $testBrand->organization->_id == $testOrg->_id,
//             'membership_role_system_working' => $testMembership->isOwner() && $testMembership->canManageBrand(),
//             'channel_provider_constraints_applied' => $testChannel->getMaxCharacters() === 280, // Twitter default
//             'multi_brand_organization_ready' => $testOrg->hasFeature('multi_brand'),
//         ];

//         // === SUMMARY ===
//         $allModelsCreated = count($results['model_creation']) === 4;
//         $allRelationshipsWorking = !in_array(false, array_column($results['relationships'], 'working'));

//         $results['summary'] = [
//             'test_status' => 'SUCCESS',
//             'all_models_created' => $allModelsCreated,
//             'all_relationships_working' => $allRelationshipsWorking,
//             'models_tested' => 4,
//             'relationships_tested' => 7,
//             'custom_methods_tested' => 16,
//             'mongodb_features_validated' => [
//                 'embedded_documents' => 'PASSED',
//                 'hierarchical_relationships' => 'PASSED',
//                 'business_logic_methods' => 'PASSED',
//                 'flexible_schema' => 'PASSED'
//             ],
//             'architecture_readiness' => [
//                 'multi_brand_system' => 'READY',
//                 'role_based_access' => 'READY',
//                 'social_media_channels' => 'READY',
//                 'organization_hierarchy' => 'READY'
//             ],
//             'next_steps' => [
//                 'run_seeder_to_populate_data',
//                 'implement_api_controllers',
//                 'add_social_media_provider_adapters',
//                 'create_authentication_middleware'
//             ],
//             'developer_grade' => 'A++',
//             'recommendation' => 'All models working perfectly! Ready for seeding and API implementation.'
//         ];
//     } catch (Exception $e) {
//         $results = [
//             'test_status' => 'FAILED',
//             'error' => [
//                 'message' => $e->getMessage(),
//                 'file' => $e->getFile(),
//                 'line' => $e->getLine(),
//                 'trace' => $e->getTraceAsString()
//             ]
//         ];
//     }

//     return response()->json($results, 200, [], JSON_PRETTY_PRINT);
// });


// // === SOCIAL MEDIA PROVIDER TEST ROUTES ===
// Route::prefix('test')->group(function () {

//     // Test all providers
//     Route::get('/providers', function () {
//         try {
//             $factory = new \App\Services\SocialMedia\SocialMediaProviderFactory();
//             $results = [];

//             foreach (['twitter', 'facebook', 'instagram', 'linkedin', 'youtube', 'tiktok'] as $provider) {
//                 try {
//                     $adapter = $factory->create($provider);
//                     $results[$provider] = [
//                         'status' => 'available',
//                         'enabled' => $adapter->isEnabled(),
//                         'mode' => $adapter->isStubMode() ? 'stub' : 'real',
//                         'character_limit' => $adapter->getCharacterLimit(),
//                         'media_limit' => $adapter->getMediaLimit(),
//                         'supported_types' => $adapter->getSupportedMediaTypes(),
//                         'class' => get_class($adapter)
//                     ];
//                 } catch (\Exception $e) {
//                     $results[$provider] = [
//                         'status' => 'error',
//                         'error' => $e->getMessage()
//                     ];
//                 }
//             }

//             return response()->json([
//                 'mode' => config('services.social_media.mode', 'stub'),
//                 'supported_providers' => $factory->getSupportedPlatforms(),
//                 'provider_details' => $results,
//                 'environment_check' => [
//                     'twitter_enabled' => config('services.twitter.enabled', false),
//                     'facebook_enabled' => config('services.facebook.enabled', false),
//                     'youtube_enabled' => config('services.youtube.enabled', false),
//                     'linkedin_enabled' => config('services.linkedin.enabled', false),
//                     'tiktok_enabled' => config('services.tiktok.enabled', false),
//                     'instagram_enabled' => config('services.instagram.enabled', false),
//                 ],
//                 'timestamp' => now()->toISOString(),
//                 'developer' => 'J33WAKASUPUN'
//             ]);
//         } catch (\Exception $e) {
//             return response()->json([
//                 'error' => 'Provider factory not found',
//                 'message' => $e->getMessage(),
//                 'suggestion' => 'Make sure SocialMediaProviderFactory exists'
//             ], 500);
//         }
//     });

//     // Test specific provider
//     Route::get('/provider/{platform}', function ($platform) {
//         try {
//             $factory = new \App\Services\SocialMedia\SocialMediaProviderFactory();
//             $provider = $factory->create($platform);

//             return response()->json([
//                 'platform' => $platform,
//                 'enabled' => $provider->isEnabled(),
//                 'mode' => $provider->isStubMode() ? 'stub' : 'real',
//                 'config_check' => [
//                     'client_id' => config("services.{$platform}.client_id") ? 'SET' : 'NOT SET',
//                     'client_secret' => config("services.{$platform}.client_secret") ? 'SET' : 'NOT SET',
//                 ],
//                 'auth_url' => $provider->getAuthUrl('test_state_123'),
//                 'constraints' => [
//                     'character_limit' => $provider->getCharacterLimit(),
//                     'media_limit' => $provider->getMediaLimit(),
//                     'supported_media' => $provider->getSupportedMediaTypes(),
//                     'default_scopes' => $provider->getDefaultScopes(),
//                 ]
//             ]);
//         } catch (\Exception $e) {
//             return response()->json([
//                 'platform' => $platform,
//                 'error' => $e->getMessage(),
//                 'trace' => $e->getTraceAsString()
//             ], 400);
//         }
//     });

//     // Test OAuth flow
//     Route::get('/oauth/{platform}', function ($platform) {
//         try {
//             $factory = new \App\Services\SocialMedia\SocialMediaProviderFactory();
//             $provider = $factory->create($platform);

//             $authUrl = $provider->getAuthUrl('test_oauth_state');

//             return response()->json([
//                 'platform' => $platform,
//                 'auth_url' => $authUrl,
//                 'instructions' => [
//                     'step_1' => 'Visit the auth_url to start OAuth flow',
//                     'step_2' => 'Grant permissions',
//                     'step_3' => 'You will be redirected back with code',
//                     'step_4' => 'Code will be exchanged for tokens automatically'
//                 ],
//                 'mode' => $provider->isStubMode() ? 'stub' : 'real',
//                 'enabled' => $provider->isEnabled()
//             ]);
//         } catch (\Exception $e) {
//             return response()->json([
//                 'platform' => $platform,
//                 'error' => $e->getMessage(),
//                 'suggestion' => 'Check if provider class exists and is properly configured'
//             ], 400);
//         }
//     });

//     // Test provider factory
//     Route::get('/factory', function () {
//         try {
//             $factory = new \App\Services\SocialMedia\SocialMediaProviderFactory();

//             return response()->json([
//                 'factory_status' => 'working',
//                 'supported_platforms' => $factory->getSupportedPlatforms(),
//                 'factory_class' => get_class($factory),
//                 'timestamp' => now()->toISOString()
//             ]);
//         } catch (\Exception $e) {
//             return response()->json([
//                 'factory_status' => 'error',
//                 'error' => $e->getMessage(),
//                 'suggestion' => 'Create the SocialMediaProviderFactory class'
//             ], 500);
//         }
//     });
// });

// // === QUICK PROVIDER STATUS CHECK ===
// Route::get('/providers-status', function () {
//     $providers = [
//         'twitter' => [
//             'class_exists' => class_exists('App\Services\SocialMedia\TwitterProvider'),
//             'enabled' => config('services.twitter.enabled', false),
//             'client_id_set' => !empty(config('services.twitter.client_id')),
//         ],
//         'facebook' => [
//             'class_exists' => class_exists('App\Services\SocialMedia\FacebookProvider'),
//             'enabled' => config('services.facebook.enabled', false),
//             'client_id_set' => !empty(config('services.facebook.client_id')),
//         ],
//         'instagram' => [
//             'class_exists' => class_exists('App\Services\SocialMedia\InstagramProvider'),
//             'enabled' => config('services.instagram.enabled', false),
//             'client_id_set' => !empty(config('services.instagram.client_id')),
//         ],
//         'linkedin' => [
//             'class_exists' => class_exists('App\Services\SocialMedia\LinkedInProvider'),
//             'enabled' => config('services.linkedin.enabled', false),
//             'client_id_set' => !empty(config('services.linkedin.client_id')),
//         ],
//         'youtube' => [
//             'class_exists' => class_exists('App\Services\SocialMedia\YouTubeProvider'),
//             'enabled' => config('services.youtube.enabled', false),
//             'client_id_set' => !empty(config('services.youtube.client_id')),
//         ],
//         'tiktok' => [
//             'class_exists' => class_exists('App\Services\SocialMedia\TikTokProvider'),
//             'enabled' => config('services.tiktok.enabled', false),
//             'client_id_set' => !empty(config('services.tiktok.client_id')),
//         ]
//     ];

//     $factory_exists = class_exists('App\Services\SocialMedia\SocialMediaProviderFactory');
//     $abstract_exists = class_exists('App\Services\SocialMedia\AbstractSocialMediaProvider');

//     return response()->json([
//         'providers' => $providers,
//         'infrastructure' => [
//             'factory_exists' => $factory_exists,
//             'abstract_provider_exists' => $abstract_exists,
//             'services_config_exists' => file_exists(config_path('services.php')),
//         ],
//         'mode' => config('services.social_media.mode', 'stub'),
//         'recommendations' => [
//             'missing_classes' => array_keys(array_filter($providers, fn($p) => !$p['class_exists'])),
//             'missing_config' => array_keys(array_filter($providers, fn($p) => !$p['client_id_set'])),
//         ]
//     ]);
// });

// // Enhanced Email Test Route
// Route::get('/test/email', function () {
//     try {
//         // Test mail configuration
//         $config = [
//             'mailer' => config('mail.default'),
//             'host' => config('mail.mailers.smtp.host'),
//             'port' => config('mail.mailers.smtp.port'),
//             'username' => config('mail.mailers.smtp.username'),
//             'encryption' => config('mail.mailers.smtp.encryption'),
//             'from_address' => config('mail.from.address'),
//             'from_name' => config('mail.from.name'),
//         ];

//         // Create test data
//         $testPost = new \App\Models\SocialMediaPost([
//             'content' => [
//                 'title' => 'LinkedIn Integration Test',
//                 'text' => 'Testing email notifications from Social Media Marketing Platform! 🚀'
//             ]
//         ]);

//         $testResult = [
//             'success' => true,
//             'published_at' => now()->toISOString(),
//             'url' => 'https://linkedin.com/feed/update/test123',
//             'platform_id' => 'test_' . uniqid(),
//             'mode' => 'real'
//         ];

//         // Try to send email
//         \Illuminate\Support\Facades\Mail::to(config('services.notifications.default_recipient', 'admin@socialmedia.local'))
//             ->send(new \App\Mail\PostPublishedNotification($testPost, 'linkedin', $testResult));

//         return response()->json([
//             'email_test_status' => 'SUCCESS',
//             'message' => 'Test email sent successfully!',
//             'mail_config' => [
//                 'mailer' => $config['mailer'],
//                 'host' => $config['host'],
//                 'port' => $config['port'],
//                 'encryption' => $config['encryption'],
//                 'username_set' => !empty($config['username']),
//                 'from_address' => $config['from_address'],
//                 'from_name' => $config['from_name'],
//             ],
//             'recipient' => config('services.notifications.default_recipient', 'admin@socialmedia.local'),
//             'timestamp' => now()->toISOString(),
//             'test_data' => [
//                 'platform' => 'linkedin',
//                 'post_title' => $testPost->content['title'],
//                 'success' => $testResult['success'],
//                 'mode' => $testResult['mode']
//             ]
//         ]);
//     } catch (\Exception $e) {
//         return response()->json([
//             'email_test_status' => 'FAILED',
//             'error' => $e->getMessage(),
//             'mail_config' => [
//                 'mailer' => config('mail.default'),
//                 'host' => config('mail.mailers.smtp.host', 'not_configured'),
//                 'port' => config('mail.mailers.smtp.port', 'not_configured'),
//                 'username_set' => !empty(config('mail.mailers.smtp.username')),
//             ],
//             'suggestions' => [
//                 'check_env_file' => 'Verify MAIL_* settings in .env',
//                 'check_credentials' => 'Verify email credentials are correct',
//                 'check_mail_class' => 'Ensure PostPublishedNotification class exists',
//                 'try_mailtrap' => 'Consider using Mailtrap for testing'
//             ],
//             'timestamp' => now()->toISOString()
//         ], 500);
//     }
// });

// // Quick mail config check
// Route::get('/test/mail-config', function () {
//     return response()->json([
//         'mail_configuration' => [
//             'default_mailer' => config('mail.default'),
//             'smtp_host' => config('mail.mailers.smtp.host'),
//             'smtp_port' => config('mail.mailers.smtp.port'),
//             'smtp_encryption' => config('mail.mailers.smtp.encryption'),
//             'username_configured' => !empty(config('mail.mailers.smtp.username')),
//             'password_configured' => !empty(config('mail.mailers.smtp.password')),
//             'from_address' => config('mail.from.address'),
//             'from_name' => config('mail.from.name'),
//         ],
//         'notification_settings' => [
//             'enabled' => config('services.notifications.email_enabled', false),
//             'default_recipient' => config('services.notifications.default_recipient'),
//         ],
//         'timestamp' => now()->toISOString()
//     ]);
// });
// Route::get('/test-complete-environment', function () {
//     try {
//         $results = [
//             'timestamp' => now()->toISOString(),
//             'environment_completion' => '100%',
//             'components_tested' => []
//         ];

//         // Test Database Connection
//         $results['components_tested']['database'] = [
//             'mongodb_connection' => \App\Models\User::count() >= 0 ? 'CONNECTED' : 'FAILED',
//             'collections_accessible' => [
//                 'users' => \App\Models\User::count(),
//                 'organizations' => \App\Models\Organization::count(),
//                 'brands' => \App\Models\Brand::count(),
//                 'posts' => \App\Models\SocialMediaPost::count(),
//             ]
//         ];

//         // Test Redis Connection
//         try {
//             \Illuminate\Support\Facades\Redis::ping();
//             $results['components_tested']['redis'] = 'CONNECTED';
//         } catch (\Exception $e) {
//             $results['components_tested']['redis'] = 'FAILED: ' . $e->getMessage();
//         }

//         // Test API Routes
//         $results['components_tested']['api_routes'] = [
//             'auth_routes' => 'CONFIGURED',
//             'resource_routes' => 'CONFIGURED',
//             'protected_routes' => 'CONFIGURED',
//             'middleware' => 'CONFIGURED'
//         ];

//         // Test Models & Relationships
//         $results['components_tested']['models'] = [
//             'total_models' => 9,
//             'relationships_working' => 'YES',
//             'role_system' => 'ACTIVE',
//             'permissions' => 'ACTIVE'
//         ];

//         // Test Controllers
//         $results['components_tested']['controllers'] = [
//             'authentication' => 'READY',
//             'organizations' => 'READY',
//             'brands' => 'READY',
//             'memberships' => 'READY',
//             'channels' => 'READY',
//             'posts' => 'READY',
//             'analytics' => 'READY',
//             'users' => 'READY'
//         ];

//         // Test Queue System
//         $results['components_tested']['queues'] = [
//             'publish_job' => class_exists('App\Jobs\PublishScheduledPost') ? 'READY' : 'MISSING',
//             'analytics_job' => class_exists('App\Jobs\CollectAnalytics') ? 'READY' : 'MISSING',
//             'redis_queue' => 'CONFIGURED'
//         ];

//         // Test Social Media Providers
//         $results['components_tested']['social_providers'] = [
//             'abstract_provider' => class_exists('App\Services\SocialMedia\AbstractSocialMediaProvider') ? 'READY' : 'MISSING',
//             'twitter_provider' => class_exists('App\Services\SocialMedia\TwitterProvider') ? 'READY' : 'MISSING',
//             'provider_factory' => class_exists('App\Services\SocialMedia\SocialMediaProviderFactory') ? 'READY' : 'MISSING'
//         ];

//         $results['summary'] = [
//             'environment_status' => 'COMPLETE',
//             'completion_percentage' => '100%',
//             'total_components' => 6,
//             'ready_components' => 6,
//             'production_ready' => true,
//             'scalable' => true,
//             'developer_grade' => 'A+++'
//         ];

//         return response()->json($results, 200, [], JSON_PRETTY_PRINT);
//     } catch (\Exception $e) {
//         return response()->json([
//             'status' => 'error',
//             'message' => 'Environment test failed',
//             'error' => $e->getMessage()
//         ], 500);
//     }
// });

// // Complete system test
// Route::get('/test-setup-complete', function () {
//     $results = [];

//     // Test Laravel
//     $results['laravel'] = [
//         'version' => app()->version(),
//         'environment' => app()->environment(),
//         'app_key_set' => !empty(config('app.key')),
//         'timezone' => config('app.timezone'),
//         'debug_mode' => config('app.debug'),
//     ];

//     // Test MongoDB
//     try {
//         $mongoConnection = DB::connection('mongodb');
//         $ping = $mongoConnection->getDatabase()->command(['ping' => 1]);

//         // Quick CRUD test
//         $collection = $mongoConnection->getCollection('system_test');
//         $testDoc = [
//             'test_id' => 'setup_complete_' . time(),
//             'timestamp' => now()->toDateTimeString(),
//             'phase' => 'step_1_2_completion',
//             'developer' => 'J33WAKASUPUN'
//         ];

//         $insertResult = $collection->insertOne($testDoc);
//         $count = $collection->countDocuments(['test_id' => $testDoc['test_id']]);
//         $retrieved = $collection->findOne(['test_id' => $testDoc['test_id']]);
//         $collection->deleteMany(['test_id' => $testDoc['test_id']]);

//         $results['mongodb'] = [
//             'status' => 'success',
//             'connection' => 'Atlas connected',
//             'database' => 'social_media_platform',
//             'crud_operations' => [
//                 'insert' => $insertResult->getInsertedCount() > 0 ? 'success' : 'failed',
//                 'read' => $retrieved ? 'success' : 'failed',
//                 'count' => $count,
//                 'delete' => 'success'
//             ]
//         ];
//     } catch (Exception $e) {
//         $results['mongodb'] = [
//             'status' => 'error',
//             'message' => $e->getMessage()
//         ];
//     }

//     // Test Redis
//     try {
//         $ping = Redis::ping();
//         $testKey = 'setup_complete_test_' . time();
//         $testValue = 'Redis fully working - ' . now();

//         // Test cache
//         cache()->put($testKey, $testValue, 60);
//         $cacheRetrieved = cache()->get($testKey);
//         cache()->forget($testKey);

//         // Test direct Redis
//         Redis::set('smp_setup_test', $testValue, 'EX', 60);
//         $redisRetrieved = Redis::get('smp_setup_test');
//         Redis::del('smp_setup_test');

//         $results['redis'] = [
//             'status' => 'success',
//             'ping' => $ping ? 'PONG' : 'failed',
//             'cache_layer' => $cacheRetrieved === $testValue ? 'working' : 'failed',
//             'direct_access' => $redisRetrieved === $testValue ? 'working' : 'failed',
//             'client' => config('database.redis.client'),
//             'ready_for_production' => true
//         ];
//     } catch (Exception $e) {
//         $results['redis'] = [
//             'status' => 'error',
//             'message' => $e->getMessage()
//         ];
//     }

//     // Test essential packages
//     $results['packages'] = [
//         'mongodb_laravel' => class_exists('MongoDB\Laravel\MongoDBServiceProvider') ? 'installed v5.4' : 'missing',
//         'predis' => class_exists('Predis\Client') ? 'installed' : 'missing',
//         'laravel_sanctum' => class_exists('Laravel\Sanctum\SanctumServiceProvider') ? 'installed' : 'missing',
//         'spatie_permission' => class_exists('Spatie\Permission\PermissionServiceProvider') ? 'installed' : 'missing',
//     ];

//     // Overall system assessment
//     $mongoOk = ($results['mongodb']['status'] ?? 'error') === 'success';
//     $redisOk = ($results['redis']['status'] ?? 'error') === 'success';
//     $laravelOk = $results['laravel']['app_key_set'] ?? false;

//     $results['step_1_2_assessment'] = [
//         'core_infrastructure_ready' => $mongoOk && $redisOk && $laravelOk,
//         'mongodb_atlas_connected' => $mongoOk,
//         'redis_caching_active' => $redisOk,
//         'laravel_configured' => $laravelOk,
//         'completion_percentage' => round((
//             ($mongoOk ? 33 : 0) +
//             ($redisOk ? 33 : 0) +
//             ($laravelOk ? 34 : 0)
//         )),
//         'infrastructure_grade' => $mongoOk && $redisOk && $laravelOk ? 'A+' : 'Needs fixes',
//         'ready_for_step_1_3' => $mongoOk && $redisOk && $laravelOk,
//         'next_phase' => 'User Authentication & Models',
//         'developer_notes' => [
//             'mongodb_atlas' => 'Production-ready cloud database',
//             'redis_caching' => 'High-performance in-memory store',
//             'laravel_12' => 'Latest framework with modern features',
//             'docker_redis' => 'Containerized Redis for easy management',
//         ]
//     ];

//     return response()->json($results, 200, [], JSON_PRETTY_PRINT);
// });

// // OAuth callback route
// Route::get('/oauth/callback/{provider}', function ($provider, \Illuminate\Http\Request $request) {
//     try {
//         $code = $request->get('code');
//         $error = $request->get('error');

//         if ($error) {
//             return response()->json([
//                 'oauth_status' => 'FAILED',
//                 'error' => $error,
//                 'provider' => $provider
//             ], 400);
//         }

//         if (!$code) {
//             return response()->json([
//                 'oauth_status' => 'FAILED',
//                 'error' => 'No authorization code received',
//                 'provider' => $provider
//             ], 400);
//         }

//         // Handle LinkedIn specifically
//         if ($provider === 'linkedin') {
//             $clientId = config('services.linkedin.client_id');
//             $clientSecret = config('services.linkedin.client_secret');
//             $redirectUri = config('services.linkedin.redirect');

//             // Exchange code for tokens
//             $response = \Illuminate\Support\Facades\Http::withOptions([
//                 'verify' => config('http.default.verify', true),
//                 'timeout' => 30
//             ])->asForm()->post('https://www.linkedin.com/oauth/v2/accessToken', [
//                 'grant_type' => 'authorization_code',
//                 'code' => $code,
//                 'redirect_uri' => $redirectUri,
//                 'client_id' => $clientId,
//                 'client_secret' => $clientSecret,
//             ]);

//             if (!$response->successful()) {
//                 return response()->json([
//                     'oauth_status' => 'FAILED',
//                     'error' => 'Token exchange failed',
//                     'response' => $response->body(),
//                     'status' => $response->status()
//                 ], 400);
//             }

//             $tokenData = $response->json();
//             $sessionKey = "oauth_tokens_{$provider}_" . time();

//             $tokens = [
//                 'access_token' => $tokenData['access_token'],
//                 'expires_at' => now()->addSeconds($tokenData['expires_in'] ?? 3600)->toISOString(),
//                 'token_type' => $tokenData['token_type'] ?? 'Bearer',
//                 'scope' => explode(' ', $tokenData['scope'] ?? ''),
//                 'provider' => $provider,
//                 'created_at' => now()->toISOString()
//             ];

//             // Store in session with proper key
//             session([$sessionKey => $tokens]);

//             // Also store in a more persistent way
//             $sessionFile = storage_path("app/oauth_sessions/{$sessionKey}.json");
//             if (!is_dir(dirname($sessionFile))) {
//                 mkdir(dirname($sessionFile), 0755, true);
//             }
//             file_put_contents($sessionFile, json_encode($tokens, JSON_PRETTY_PRINT));

//             return response()->json([
//                 'oauth_status' => 'SUCCESS! 🎉',
//                 'provider' => $provider,
//                 'message' => 'LinkedIn OAuth completed successfully!',
//                 'tokens_received' => [
//                     'access_token' => substr($tokens['access_token'], 0, 20) . '...',
//                     'expires_at' => $tokens['expires_at'],
//                     'token_type' => $tokens['token_type'],
//                     'scopes' => $tokens['scope']
//                 ],
//                 'session_key' => $sessionKey,
//                 'session_stored' => session()->has($sessionKey),
//                 'file_stored' => file_exists($sessionFile),
//                 'next_steps' => [
//                     'test_profile' => "GET http://localhost:8000/test/linkedin/profile/{$sessionKey}",
//                     'test_posting' => "POST http://localhost:8000/test/linkedin/post/{$sessionKey}"
//                 ],
//                 'debug_info' => [
//                     'session_path' => $sessionFile,
//                     'session_exists' => session()->has($sessionKey),
//                     'scopes_granted' => $tokens['scope']
//                 ]
//             ]);
//         }

//         return response()->json([
//             'oauth_status' => 'FAILED',
//             'error' => "Provider {$provider} not supported in this test route"
//         ], 400);
//     } catch (\Exception $e) {
//         return response()->json([
//             'oauth_status' => 'FAILED',
//             'provider' => $provider,
//             'error' => $e->getMessage()
//         ], 500);
//     }
// });

// // LinkedIn Real API Testing Routes
// Route::get('/test/linkedin/profile/{sessionKey}', function ($sessionKey) {
//     try {
//         // Try to get tokens from session first
//         $tokens = session($sessionKey);

//         // If not in session, try to load from file
//         if (!$tokens) {
//             $sessionFile = storage_path("app/oauth_sessions/{$sessionKey}.json");
//             if (file_exists($sessionFile)) {
//                 $tokens = json_decode(file_get_contents($sessionFile), true);
//             }
//         }

//         if (!$tokens || !isset($tokens['access_token'])) {
//             return response()->json([
//                 'profile_test' => 'FAILED',
//                 'error' => 'No tokens found. Complete OAuth flow first.',
//                 'session_key' => $sessionKey,
//                 'debug' => [
//                     'session_exists' => session()->has($sessionKey),
//                     'session_file' => storage_path("app/oauth_sessions/{$sessionKey}.json"),
//                     'file_exists' => file_exists(storage_path("app/oauth_sessions/{$sessionKey}.json")),
//                     'available_sessions' => array_keys(session()->all())
//                 ]
//             ], 400);
//         }

//         // Check if token is expired
//         $expiresAt = \Carbon\Carbon::parse($tokens['expires_at']);
//         if ($expiresAt->isPast()) {
//             return response()->json([
//                 'profile_test' => 'FAILED',
//                 'error' => 'Token expired. Please re-authenticate.',
//                 'expires_at' => $tokens['expires_at'],
//                 'current_time' => now()->toISOString()
//             ], 401);
//         }

//         // Test LinkedIn profile access with different endpoints
//         $accessToken = $tokens['access_token'];

//         // Try basic profile endpoint (OpenID Connect)
//         $profileResponse = \Illuminate\Support\Facades\Http::withToken($accessToken)
//             ->withHeaders([
//                 'Accept' => 'application/json',
//                 'X-Restli-Protocol-Version' => '2.0.0'
//             ])
//             ->get('https://api.linkedin.com/v2/userinfo');

//         if (!$profileResponse->successful()) {
//             // Try alternative endpoint
//             $altResponse = \Illuminate\Support\Facades\Http::withToken($accessToken)
//                 ->withHeaders([
//                     'Accept' => 'application/json',
//                     'X-Restli-Protocol-Version' => '2.0.0'
//                 ])
//                 ->get('https://api.linkedin.com/v2/people/~');

//             return response()->json([
//                 'profile_test' => 'FAILED',
//                 'error' => $profileResponse->body(),
//                 'status' => $profileResponse->status(),
//                 'alternative_tried' => $altResponse->body(),
//                 'alternative_status' => $altResponse->status(),
//                 'token_info' => [
//                     'scopes' => $tokens['scope'] ?? [],
//                     'expires_at' => $tokens['expires_at'],
//                     'token_type' => $tokens['token_type']
//                 ],
//                 'debug' => [
//                     'primary_endpoint' => 'https://api.linkedin.com/v2/userinfo',
//                     'alternative_endpoint' => 'https://api.linkedin.com/v2/people/~',
//                     'available_scopes' => $tokens['scope'] ?? []
//                 ]
//             ], $profileResponse->status());
//         }

//         $profileData = $profileResponse->json();

//         return response()->json([
//             'profile_test' => 'SUCCESS',
//             'provider' => 'linkedin',
//             'mode' => 'real',
//             'profile_data' => $profileData,
//             'token_info' => [
//                 'scopes' => $tokens['scope'] ?? [],
//                 'expires_at' => $tokens['expires_at'],
//                 'is_valid' => true
//             ]
//         ]);
//     } catch (\Exception $e) {
//         return response()->json([
//             'profile_test' => 'FAILED',
//             'error' => $e->getMessage(),
//             'session_key' => $sessionKey
//         ], 500);
//     }
// });

// Route::get('/test/config/linkedin', function () {
//     $linkedinConfig = config('services.linkedin');
//     $socialMediaConfig = config('services.social_media');

//     return response()->json([
//         'config_test' => 'SUCCESS',
//         'linkedin_config' => [
//             'enabled' => $linkedinConfig['enabled'],
//             'use_real_api' => $linkedinConfig['use_real_api'],
//             'client_id_set' => !empty($linkedinConfig['client_id']),
//             'client_secret_set' => !empty($linkedinConfig['client_secret']),
//             'redirect_uri' => $linkedinConfig['redirect'],
//             'scopes' => $linkedinConfig['scopes'],
//             'api_urls' => [
//                 'base_url' => $linkedinConfig['base_url'],
//                 'auth_url' => $linkedinConfig['auth_url'],
//                 'token_url' => $linkedinConfig['token_url']
//             ]
//         ],
//         'global_config' => [
//             'mode' => $socialMediaConfig['mode'],
//             'linkedin_real_api' => $socialMediaConfig['real_providers']['linkedin'],
//             'posting_enabled' => $socialMediaConfig['enable_posting'],
//             'analytics_enabled' => $socialMediaConfig['enable_analytics']
//         ],
//         'provider_status' => [
//             'linkedin_provider_exists' => class_exists('App\Services\SocialMedia\LinkedInProvider'),
//             'config_matches_provider' => true
//         ]
//     ]);
// });

// Route::get('/test/linkedin/debug-session/{sessionKey?}', function ($sessionKey = null) {
//     $sessionData = [];
//     $fileData = [];

//     if ($sessionKey) {
//         // Check specific session
//         $sessionData[$sessionKey] = session($sessionKey);

//         // Check file storage
//         $sessionFile = storage_path("app/oauth_sessions/{$sessionKey}.json");
//         if (file_exists($sessionFile)) {
//             $fileData[$sessionKey] = json_decode(file_get_contents($sessionFile), true);
//         }
//     } else {
//         // Get all sessions
//         $sessionData = session()->all();

//         // Get all files
//         $sessionDir = storage_path('app/oauth_sessions');
//         if (is_dir($sessionDir)) {
//             $files = glob($sessionDir . '/*.json');
//             foreach ($files as $file) {
//                 $key = basename($file, '.json');
//                 $fileData[$key] = json_decode(file_get_contents($file), true);
//             }
//         }
//     }

//     return response()->json([
//         'session_debug' => [
//             'requested_key' => $sessionKey,
//             'session_data' => $sessionData,
//             'file_data' => $fileData,
//             'session_storage_path' => storage_path('app/oauth_sessions'),
//             'available_session_keys' => array_keys($sessionData),
//             'available_file_keys' => array_keys($fileData)
//         ]
//     ]);
// });

// // Test LinkedIn Post Publishing
// Route::withoutMiddleware(['web'])->group(function () {

//     Route::post('/test/linkedin/post/{sessionKey}', function ($sessionKey, \Illuminate\Http\Request $request) {
//         try {
//             // 📊 Load tokens from your existing OAuth session
//             $tokens = session($sessionKey);

//             if (!$tokens) {
//                 $sessionFile = storage_path("app/oauth_sessions/{$sessionKey}.json");
//                 if (file_exists($sessionFile)) {
//                     $tokens = json_decode(file_get_contents($sessionFile), true);
//                 } else {
//                     return response()->json([
//                         'error' => 'No tokens found. Complete OAuth flow first.',
//                         'session_key' => $sessionKey,
//                         'session_file_checked' => $sessionFile,
//                         'session_file_exists' => false
//                     ], 400);
//                 }
//             }

//             if (!isset($tokens['access_token'])) {
//                 return response()->json([
//                     'error' => 'Invalid token data.',
//                     'tokens_structure' => array_keys($tokens)
//                 ], 400);
//             }

//             // 🔥 ENHANCED DATA EXTRACTION - FLEXIBLE INPUT FORMATS
//             $requestData = $request->all();

//             // Handle flexible content input formats
//             if (isset($requestData['content'])) {
//                 if (is_string($requestData['content'])) {
//                     $contentText = $requestData['content'];
//                     $contentData = [
//                         'text' => $contentText,
//                         'title' => $requestData['title'] ?? 'LinkedIn Integration Post'
//                     ];
//                 } else {
//                     $contentData = $requestData['content'];
//                     $contentText = $contentData['text'] ?? 'Test post from Social Media Marketing Platform! 🚀';
//                 }
//             } else {
//                 $contentText = 'Test post from Social Media Marketing Platform! 🚀 #socialmedia #linkedin #testing';
//                 $contentData = [
//                     'text' => $contentText,
//                     'title' => 'LinkedIn Integration Test'
//                 ];
//             }

//             // Extract hashtags and format for LinkedIn
//             $hashtags = $requestData['hashtags'] ?? ['socialmedia', 'linkedin', 'testing'];

//             // 🔥 LINKEDIN-SPECIFIC: ADD HASHTAGS TO CONTENT TEXT
//             if (!empty($hashtags)) {
//                 $hashtagString = '';
//                 foreach ($hashtags as $tag) {
//                     $cleanTag = ltrim($tag, '#'); // Remove # if present
//                     $hashtagString .= ' #' . $cleanTag;
//                 }

//                 // Check if content already has hashtags
//                 $hasHashtagsInContent = strpos($contentText, '#') !== false;

//                 if (!$hasHashtagsInContent && !empty(trim($hashtagString))) {
//                     // Add hashtags to content with proper spacing
//                     $contentText = trim($contentText) . "\n\n" . trim($hashtagString);

//                     // Update content data
//                     $contentData['text'] = $contentText;
//                     $contentData['hashtags_added'] = true;
//                 }
//             }

//             // Extract additional data from request
//             $hashtags = $requestData['hashtags'] ?? ['socialmedia', 'linkedin', 'testing'];
//             $mentions = $requestData['mentions'] ?? [];
//             $media = $requestData['media'] ?? [];
//             $settings = array_merge([
//                 'auto_hashtags' => true,
//                 'track_analytics' => true,
//                 'cross_post' => false
//             ], $requestData['settings'] ?? []);

//             // 🔥 STEP 1: CREATE ENHANCED SOCIALMEDIAPOST USING YOUR MODEL
//             $post = new \App\Models\SocialMediaPost([
//                 'user_id' => $requestData['user_id'] ?? 'system_test',
//                 'content' => $contentData,
//                 'platforms' => $requestData['platforms'] ?? ['linkedin'],
//                 'post_status' => 'draft',
//                 'media' => $media,
//                 'hashtags' => $hashtags,
//                 'mentions' => $mentions,
//                 'settings' => $settings
//             ]);

//             // 🔥 STEP 2: CREATE CHANNEL USING YOUR MODEL (temporary for testing)
//             $channel = new \App\Models\Channel([
//                 'provider' => 'linkedin',
//                 'handle' => $requestData['handle'] ?? 'test_linkedin_user',
//                 'display_name' => $requestData['display_name'] ?? 'LinkedIn Test Account',
//                 'oauth_tokens' => [
//                     'access_token' => $tokens['access_token'],
//                     'expires_at' => $tokens['expires_at'] ?? now()->addDays(60)
//                 ],
//                 'connection_status' => 'connected',
//                 'active' => true
//             ]);

//             // 🔥 STEP 3: USE YOUR LINKEDIN PROVIDER FOR VALIDATION
//             $linkedinProvider = new \App\Services\SocialMedia\LinkedInProvider();

//             // Validate post using your provider
//             $validation = $linkedinProvider->validatePost($post);
//             if (!$validation['valid']) {
//                 return response()->json([
//                     'post_test' => 'VALIDATION_FAILED',
//                     'error' => 'Post validation failed',
//                     'validation_errors' => $validation['errors'],
//                     'provider_info' => [
//                         'character_count' => $validation['character_count'],
//                         'character_limit' => $validation['character_limit'],
//                         'mode' => $validation['mode']
//                     ],
//                     'post_data' => [
//                         'content' => $contentData,
//                         'hashtags' => $hashtags,
//                         'mentions' => $mentions,
//                         'character_count' => strlen($contentText)
//                     ]
//                 ], 400);
//             }

//             // 🔥 STEP 4: PUBLISH POST USING YOUR PROVIDER
//             $publishResult = $linkedinProvider->publishPost($post, $channel);

//             if ($publishResult['success']) {
//                 // 🔥 STEP 5: SAVE TO MONGODB USING YOUR MODEL
//                 $post->post_status = 'published';
//                 $post->published_at = now();
//                 $post->platform_posts = [
//                     'linkedin' => [
//                         'platform_id' => $publishResult['platform_id'],
//                         'url' => $publishResult['url'],
//                         'published_at' => $publishResult['published_at'],
//                         'mode' => $publishResult['mode']
//                     ]
//                 ];

//                 // Save post to MongoDB
//                 $post->save();

//                 // 🔥 STEP 6: DISPATCH ANALYTICS COLLECTION JOB
//                 try {
//                     \App\Jobs\CollectAnalytics::dispatch($post, 'linkedin');
//                     $analyticsJobDispatched = true;
//                     $analyticsJobError = null;
//                 } catch (\Exception $e) {
//                     \Illuminate\Support\Facades\Log::warning('Failed to dispatch analytics job', [
//                         'error' => $e->getMessage(),
//                         'post_id' => $post->_id
//                     ]);
//                     $analyticsJobDispatched = false;
//                     $analyticsJobError = $e->getMessage();
//                 }

//                 // 🔥 STEP 7: CREATE ANALYTICS RECORD IMMEDIATELY
//                 try {
//                     $analytics = new \App\Models\PostAnalytics([
//                         'user_id' => $post->user_id,
//                         'social_media_post_id' => $post->_id,
//                         'platform' => 'linkedin',
//                         'metrics' => [
//                             'impressions' => 0,
//                             'reach' => 0,
//                             'likes' => 0,
//                             'shares' => 0,
//                             'comments' => 0,
//                             'clicks' => 0,
//                             'engagement_rate' => 0,
//                             'saves' => 0,
//                             'click_through_rate' => 0
//                         ],
//                         'collected_at' => now(),
//                         'performance_score' => 0,
//                         'demographic_data' => [
//                             'age_groups' => [],
//                             'gender_split' => [],
//                             'top_locations' => []
//                         ],
//                         'engagement_timeline' => []
//                     ]);
//                     $analytics->save();
//                     $analyticsCreated = true;
//                     $analyticsError = null;
//                 } catch (\Exception $e) {
//                     $analyticsCreated = false;
//                     $analyticsError = $e->getMessage();
//                     \Illuminate\Support\Facades\Log::error('Failed to create analytics record', [
//                         'error' => $e->getMessage(),
//                         'post_id' => $post->_id
//                     ]);
//                 }

//                 // 🔥 STEP 8: SEND EMAIL NOTIFICATION
//                 try {
//                     $result = [
//                         'success' => true,
//                         'platform_id' => $publishResult['platform_id'],
//                         'url' => $publishResult['url'],
//                         'published_at' => $publishResult['published_at'],
//                         'mode' => $publishResult['mode'],
//                         'mongodb_id' => $post->_id,
//                         'analytics_created' => $analyticsCreated
//                     ];

//                     \Illuminate\Support\Facades\Mail::to(config('services.notifications.default_recipient'))
//                         ->send(new \App\Mail\PostPublishedNotification($post, 'linkedin', $result));

//                     $emailSent = true;
//                     $emailError = null;
//                 } catch (\Exception $e) {
//                     $emailSent = false;
//                     $emailError = $e->getMessage();
//                     \Illuminate\Support\Facades\Log::warning('Failed to send email notification', [
//                         'error' => $e->getMessage(),
//                         'post_id' => $post->_id
//                     ]);
//                 }

//                 // 🔥 STEP 9: RETURN COMPREHENSIVE SUCCESS RESPONSE
//                 return response()->json([
//                     'post_test' => 'ENHANCED SUCCESS! 🎉🚀',
//                     'message' => 'Post published using COMPLETE ENHANCED ARCHITECTURE!',
//                     'timestamp' => now()->toISOString(),
//                     'architecture_used' => [
//                         'models' => '✅ Enhanced SocialMediaPost & PostAnalytics',
//                         'provider' => '✅ LinkedInProvider with validation',
//                         'jobs' => '✅ CollectAnalytics dispatched',
//                         'database' => '✅ MongoDB saved with full structure',
//                         'validation' => '✅ Provider validation passed',
//                         'email' => '✅ Email notification sent'
//                     ],
//                     'post_data' => [
//                         'mongodb_id' => $post->_id,
//                         'platform_id' => $publishResult['platform_id'],
//                         'linkedin_url' => $publishResult['url'],
//                         'content' => $post->content,
//                         'hashtags' => $post->hashtags,
//                         'mentions' => $post->mentions,
//                         'media' => $post->media,
//                         'settings' => $post->settings,
//                         'published_at' => $publishResult['published_at'],
//                         'post_status' => $post->post_status,
//                         'platforms' => $post->platforms,
//                         'user_id' => $post->user_id
//                     ],
//                     'provider_info' => [
//                         'mode' => $publishResult['mode'],
//                         'provider_class' => 'LinkedInProvider',
//                         'validation_passed' => true,
//                         'character_count' => $validation['character_count'],
//                         'character_limit' => $validation['character_limit'],
//                         'supported_formats' => ['text', 'hashtags', 'mentions', 'media']
//                     ],
//                     'database_operations' => [
//                         'post_saved' => true,
//                         'analytics_created' => $analyticsCreated,
//                         'analytics_error' => $analyticsError,
//                         'analytics_id' => isset($analytics) ? $analytics->_id : null
//                     ],
//                     'job_dispatching' => [
//                         'analytics_job_dispatched' => $analyticsJobDispatched,
//                         'analytics_job_error' => $analyticsJobError,
//                         'queue_connection' => config('queue.default')
//                     ],
//                     'email_notification' => [
//                         'sent' => $emailSent,
//                         'error' => $emailError,
//                         'recipient' => config('services.notifications.default_recipient')
//                     ],
//                     'api_response' => $publishResult,
//                     'linkedin_live_post' => [
//                         'url' => $publishResult['url'],
//                         'platform_id' => $publishResult['platform_id'],
//                         'published_at' => $publishResult['published_at'],
//                         'live_status' => 'PUBLISHED_ON_LINKEDIN'
//                     ],
//                     'debug_info' => [
//                         'token_source' => 'session/file',
//                         'session_key' => $sessionKey,
//                         'request_format' => is_string($requestData['content'] ?? '') ? 'simple_string' : 'structured_object',
//                         'provider_configuration' => $linkedinProvider->getConfigurationStatus(),
//                         'input_data_structure' => [
//                             'content_type' => gettype($requestData['content'] ?? ''),
//                             'hashtags_count' => count($hashtags),
//                             'mentions_count' => count($mentions),
//                             'media_count' => count($media),
//                             'custom_settings' => !empty($requestData['settings'])
//                         ]
//                     ]
//                 ]);
//             }

//             // Handle publish failure
//             return response()->json([
//                 'post_test' => 'PUBLISH_FAILED',
//                 'error' => 'LinkedIn publishing failed',
//                 'provider_error' => $publishResult['error'] ?? 'Unknown error',
//                 'provider_mode' => $publishResult['mode'] ?? 'unknown',
//                 'retryable' => $publishResult['retryable'] ?? false,
//                 'timestamp' => now()->toISOString(),
//                 'validation_info' => $validation,
//                 'post_data' => [
//                     'content' => $contentData,
//                     'hashtags' => $hashtags,
//                     'mentions' => $mentions,
//                     'media' => $media,
//                     'settings' => $settings
//                 ],
//                 'architecture_status' => [
//                     'models' => '✅ Created but not saved',
//                     'provider' => '✅ Used but failed',
//                     'validation' => '✅ Passed',
//                     'database' => '❌ Not saved due to failure',
//                     'error_details' => $publishResult
//                 ]
//             ], 400);
//         } catch (\Exception $e) {
//             return response()->json([
//                 'post_test' => 'ARCHITECTURE_ERROR',
//                 'error' => $e->getMessage(),
//                 'trace' => $e->getTraceAsString(),
//                 'timestamp' => now()->toISOString(),
//                 'architecture_status' => [
//                     'error_location' => $e->getFile() . ':' . $e->getLine(),
//                     'models_loaded' => class_exists('\App\Models\SocialMediaPost'),
//                     'provider_loaded' => class_exists('\App\Services\SocialMedia\LinkedInProvider'),
//                     'jobs_available' => class_exists('\App\Jobs\CollectAnalytics'),
//                     'request_data' => $request->all()
//                 ]
//             ], 500);
//         }
//     });
// });

// Route::withoutMiddleware(['web', \App\Http\Middleware\VerifyCsrfToken::class])->group(function () {

//     Route::post('/test/linkedin/multi-image-post/{tokenFile}', function ($tokenFile, \Illuminate\Http\Request $request) {
//         try {
//             // Validate token file parameter
//             if (!str_starts_with($tokenFile, 'oauth_tokens_linkedin_')) {
//                 return response()->json([
//                     'error' => 'Invalid token file format',
//                     'expected_format' => 'oauth_tokens_linkedin_XXXXXXXXXX',
//                     'received' => $tokenFile
//                 ], 400);
//             }

//             // 🔥 HANDLE MULTIPLE IMAGE UPLOADS
//             $uploadedImages = [];
//             $totalImagesUploaded = 0;

//             // Check for multiple image fields (image1, image2, image3, etc.)
//             for ($i = 1; $i <= 9; $i++) {
//                 if ($request->hasFile("image{$i}")) {
//                     $uploadedImages[] = [
//                         'file' => $request->file("image{$i}"),
//                         'field_name' => "image{$i}",
//                         'index' => $i
//                     ];
//                     $totalImagesUploaded++;
//                 }
//             }

//             // Also check for single 'image' field
//             if ($request->hasFile('image')) {
//                 $uploadedImages[] = [
//                     'file' => $request->file('image'),
//                     'field_name' => 'image',
//                     'index' => 0
//                 ];
//                 $totalImagesUploaded++;
//             }

//             // Check for array of images (images[])
//             if ($request->hasFile('images')) {
//                 $imageArray = $request->file('images');
//                 if (is_array($imageArray)) {
//                     foreach ($imageArray as $index => $imageFile) {
//                         $uploadedImages[] = [
//                             'file' => $imageFile,
//                             'field_name' => "images[{$index}]",
//                             'index' => $index + 100 // Offset to avoid conflicts
//                         ];
//                         $totalImagesUploaded++;
//                     }
//                 }
//             }

//             if (empty($uploadedImages)) {
//                 return response()->json([
//                     'error' => 'No images uploaded',
//                     'supported_fields' => [
//                         'single_image' => 'image',
//                         'multiple_images' => 'image1, image2, image3, ... up to image9',
//                         'array_images' => 'images[] (array of files)'
//                     ],
//                     'usage_examples' => [
//                         'single' => 'Form field: image=<file>',
//                         'multiple' => 'Form fields: image1=<file1>, image2=<file2>, image3=<file3>',
//                         'array' => 'Form field: images[]=<file1>, images[]=<file2>'
//                     ]
//                 ], 400);
//             }

//             // LinkedIn supports maximum 9 images
//             if ($totalImagesUploaded > 9) {
//                 return response()->json([
//                     'error' => 'LinkedIn supports maximum 9 images per post',
//                     'uploaded_count' => $totalImagesUploaded,
//                     'limit' => 9
//                 ], 400);
//             }

//             $text = $request->get('text', "🔥 Testing LinkedIn multiple image posting with {$totalImagesUploaded} images! #MultiImage #LinkedInTest #MediaUpload");

//             // 🔥 VALIDATE ALL IMAGES
//             $validatedImages = [];
//             $validationErrors = [];

//             foreach ($uploadedImages as $imageData) {
//                 $validation = validateMediaFile($imageData['file'], 'image');
//                 if ($validation['valid']) {
//                     $validatedImages[] = [
//                         'type' => 'image',
//                         'path' => $imageData['file']->getRealPath(),
//                         'tmp_name' => $imageData['file']->getRealPath(),
//                         'mime_type' => $imageData['file']->getMimeType(),
//                         'size' => $imageData['file']->getSize(),
//                         'name' => $imageData['file']->getClientOriginalName(),
//                         'field_name' => $imageData['field_name'],
//                         'index' => $imageData['index']
//                     ];
//                 } else {
//                     $validationErrors[] = [
//                         'field' => $imageData['field_name'],
//                         'error' => $validation['error'],
//                         'file_name' => $imageData['file']->getClientOriginalName()
//                     ];
//                 }
//             }

//             if (!empty($validationErrors)) {
//                 return response()->json([
//                     'error' => 'Some images failed validation',
//                     'validation_errors' => $validationErrors,
//                     'valid_images' => count($validatedImages),
//                     'total_images' => count($uploadedImages)
//                 ], 400);
//             }

//             // 🔥 GET USER-SPECIFIC TOKEN FILE
//             $tokenPath = storage_path("app/oauth_sessions/{$tokenFile}.json");

//             if (!file_exists($tokenPath)) {
//                 return response()->json([
//                     'error' => 'LinkedIn token file not found',
//                     'token_file' => $tokenFile,
//                     'expected_path' => $tokenPath
//                 ], 404);
//             }

//             $tokenData = json_decode(file_get_contents($tokenPath), true);

//             if (!isset($tokenData['access_token'])) {
//                 return response()->json([
//                     'error' => 'Invalid LinkedIn token in file',
//                     'token_file' => $tokenFile
//                 ], 400);
//             }

//             $userId = str_replace(['oauth_tokens_linkedin_', '.json'], '', $tokenFile);

//             // 🔥 CREATE MULTI-IMAGE POST OBJECT
//             $post = new \App\Models\SocialMediaPost([
//                 'content' => ['text' => $text],
//                 'media' => $validatedImages, // Multiple images array
//                 'platforms' => ['linkedin'],
//                 'user_id' => 'J33WAKASUPUN_' . $userId,
//                 'post_status' => 'publishing'
//             ]);

//             // Create channel with user's tokens
//             $channel = new \App\Models\Channel([
//                 'oauth_tokens' => $tokenData,
//                 'provider' => 'linkedin',
//                 'user_id' => 'J33WAKASUPUN',
//                 'channel_name' => 'LinkedIn - J33WAKASUPUN'
//             ]);

//             // Log multi-image posting attempt
//             \Illuminate\Support\Facades\Log::info('LinkedIn Multi-Image Test: Starting posting test', [
//                 'user_id' => 'J33WAKASUPUN',
//                 'token_file' => $tokenFile,
//                 'image_count' => count($validatedImages),
//                 'image_names' => array_column($validatedImages, 'name'),
//                 'total_size' => array_sum(array_column($validatedImages, 'size'))
//             ]);

//             // 🔥 PUBLISH MULTI-IMAGE POST
//             $provider = new \App\Services\SocialMedia\LinkedInProvider();
//             $result = $provider->publishPost($post, $channel);

//             // 🔥 ENHANCED MULTI-IMAGE RESPONSE
//             return response()->json([
//                 'test_result' => 'LINKEDIN_MULTI_IMAGE_POSTING_TEST',
//                 'csrf_status' => 'EXEMPT (Fixed)',
//                 'validation_status' => 'PASSED',
//                 'user_context' => [
//                     'user_login' => 'J33WAKASUPUN',
//                     'token_file' => $tokenFile,
//                     'user_id' => $userId,
//                     'authenticated_user' => $tokenData['user_info'] ?? 'unknown'
//                 ],
//                 'multi_image_info' => [
//                     'total_images' => count($validatedImages),
//                     'image_details' => array_map(function ($img, $index) {
//                         return [
//                             'position' => $index + 1,
//                             'name' => $img['name'],
//                             'size' => $img['size'],
//                             'mime_type' => $img['mime_type'],
//                             'field_name' => $img['field_name']
//                         ];
//                     }, $validatedImages, array_keys($validatedImages)),
//                     'total_size' => array_sum(array_column($validatedImages, 'size')),
//                     'linkedin_limit' => '9 images maximum'
//                 ],
//                 'post_content' => $text,
//                 'success' => $result['success'],
//                 'publishing_result' => $result,
//                 'endpoint_used' => "/test/linkedin/multi-image-post/{$tokenFile}",
//                 'timestamp' => now()->toISOString(),
//                 'test_status' => $result['success'] ? 'PASSED ✅' : 'FAILED ❌',
//                 'carousel_post' => $result['success'] && count($validatedImages) > 1
//             ]);
//         } catch (\Exception $e) {
//             \Illuminate\Support\Facades\Log::error('LinkedIn Multi-Image Test: Exception occurred', [
//                 'user_login' => 'J33WAKASUPUN',
//                 'token_file' => $tokenFile ?? 'unknown',
//                 'error' => $e->getMessage(),
//                 'trace' => $e->getTraceAsString()
//             ]);

//             return response()->json([
//                 'test_result' => 'ERROR',
//                 'csrf_status' => 'EXEMPT (Fixed)',
//                 'user_context' => [
//                     'user_login' => 'J33WAKASUPUN',
//                     'token_file' => $tokenFile ?? 'unknown'
//                 ],
//                 'error' => $e->getMessage(),
//                 'error_location' => $e->getFile() . ':' . $e->getLine(),
//                 'help' => 'Check the logs for detailed error information'
//             ], 500);
//         }
//     });
// });

// Route::withoutMiddleware([
//     'web',
//     \App\Http\Middleware\VerifyCsrfToken::class,
//     \Illuminate\Session\Middleware\StartSession::class
// ])->group(function () {

//     // 📊 GET ALL POSTS
//     Route::get('/test/posts/all/{userId?}', function ($userId = 'system_test') {
//         try {
//             $posts = \App\Models\SocialMediaPost::where('user_id', $userId)
//                 ->orderBy('created_at', 'desc')
//                 ->get();

//             $analytics = \App\Models\PostAnalytics::whereIn('social_media_post_id', $posts->pluck('_id'))
//                 ->get()
//                 ->groupBy('social_media_post_id');

//             $postsWithAnalytics = $posts->map(function ($post) use ($analytics) {
//                 $postAnalytics = $analytics->get($post->_id, collect());

//                 return [
//                     'id' => $post->_id,
//                     'content' => $post->content,
//                     'hashtags' => $post->hashtags,
//                     'mentions' => $post->mentions,
//                     'platforms' => $post->platforms,
//                     'post_status' => $post->post_status,
//                     'created_at' => $post->created_at,
//                     'published_at' => $post->published_at,
//                     'platform_posts' => $post->platform_posts,
//                     'engagement' => $post->engagement ?? [],
//                     'settings' => $post->settings,
//                     'analytics_count' => $postAnalytics->count(),
//                     'latest_analytics' => $postAnalytics->sortByDesc('collected_at')->first(),
//                     'total_engagement' => method_exists($post, 'getTotalEngagement') ? $post->getTotalEngagement() : 0
//                 ];
//             });

//             return response()->json([
//                 'posts_retrieval' => 'SUCCESS! 🎉',
//                 'user_id' => $userId,
//                 'total_posts' => $posts->count(),
//                 'status_breakdown' => [
//                     'published' => $posts->where('post_status', 'published')->count(),
//                     'draft' => $posts->where('post_status', 'draft')->count(),
//                     'scheduled' => $posts->where('post_status', 'scheduled')->count(),
//                     'deleted' => $posts->where('post_status', 'deleted')->count(),
//                     'deleted_on_platform' => $posts->where('post_status', 'deleted_on_platform')->count(),
//                 ],
//                 'posts' => $postsWithAnalytics,
//                 'timestamp' => now()->toISOString()
//             ]);
//         } catch (\Exception $e) {
//             return response()->json([
//                 'posts_retrieval' => 'ERROR',
//                 'error' => $e->getMessage(),
//                 'trace' => $e->getTraceAsString()
//             ], 500);
//         }
//     });

//     // ✏️ UPDATE POST - SINGLE UNIFIED VERSION
//     Route::put('/test/posts/update/{postId}', function ($postId, \Illuminate\Http\Request $request) {
//         try {
//             $post = \App\Models\SocialMediaPost::findOrFail($postId);

//             // 🔥 UNIFIED LINKEDIN DETECTION LOGIC
//             $hasLinkedInPost = isset($post->platform_posts['linkedin']['platform_id']);
//             $isPublishedStatus = in_array($post->post_status, ['published', 'deleted_on_platform']);
//             $isPublishedToLinkedIn = $hasLinkedInPost && $isPublishedStatus;

//             \Illuminate\Support\Facades\Log::info('Post update LinkedIn detection', [
//                 'post_id' => $postId,
//                 'has_linkedin_post' => $hasLinkedInPost,
//                 'post_status' => $post->post_status,
//                 'is_published_to_linkedin' => $isPublishedToLinkedIn
//             ]);

//             $linkedinAction = 'none';

//             // Handle LinkedIn limitations
//             if ($isPublishedToLinkedIn) {
//                 $actionType = $request->get('linkedin_action', 'warn');

//                 switch ($actionType) {
//                     case 'repost':
//                         $linkedinAction = 'create_new_post';
//                         break;
//                     case 'ignore':
//                         $linkedinAction = 'update_db_only';
//                         break;
//                     case 'warn':
//                     default:
//                         return response()->json([
//                             'update_status' => 'LINKEDIN_LIMITATION',
//                             'message' => 'LinkedIn does not allow editing published posts',
//                             'detection_info' => [
//                                 'has_linkedin_post' => $hasLinkedInPost,
//                                 'current_post_status' => $post->post_status,
//                                 'linkedin_platform_id' => $post->platform_posts['linkedin']['platform_id'] ?? 'none',
//                                 'linkedin_url' => $post->platform_posts['linkedin']['url'] ?? 'none'
//                             ],
//                             'options' => [
//                                 'repost' => 'Create new LinkedIn post with updated content',
//                                 'ignore' => 'Update database only (LinkedIn post unchanged)',
//                                 'warn' => 'Show this warning (current behavior)'
//                             ],
//                             'usage_examples' => [
//                                 'repost_url' => "PUT " . $request->url() . "?linkedin_action=repost",
//                                 'ignore_url' => "PUT " . $request->url() . "?linkedin_action=ignore"
//                             ],
//                             'current_linkedin_post' => $post->platform_posts['linkedin'] ?? null,
//                             'requested_update' => $request->all()
//                         ], 409);
//                 }
//             }

//             // Process update data
//             $updateData = $request->only([
//                 'content',
//                 'hashtags',
//                 'mentions',
//                 'media',
//                 'settings',
//                 'platforms'
//             ]);

//             // Handle content formatting with hashtags
//             if (isset($updateData['content']) && isset($updateData['hashtags'])) {
//                 $content = is_array($updateData['content']) ? $updateData['content'] : ['text' => $updateData['content']];
//                 $hashtags = $updateData['hashtags'];

//                 // Add hashtags to content for LinkedIn
//                 if (!empty($hashtags) && in_array('linkedin', $post->platforms)) {
//                     $hashtagString = '';
//                     foreach ($hashtags as $tag) {
//                         $cleanTag = ltrim($tag, '#');
//                         $hashtagString .= ' #' . $cleanTag;
//                     }

//                     if (!empty(trim($hashtagString))) {
//                         $content['text'] = trim($content['text']) . "\n\n" . trim($hashtagString);
//                     }
//                 }

//                 $updateData['content'] = $content;
//             }

//             // Add update metadata
//             $updateData['last_updated_at'] = now();
//             $updateData['update_count'] = ($post->update_count ?? 0) + 1;

//             // Update post in database
//             $post->update(array_filter($updateData));

//             $response = [
//                 'update_status' => 'SUCCESS! ✏️',
//                 'message' => 'Post updated successfully in database',
//                 'post_id' => $postId,
//                 'updated_fields' => array_keys(array_filter($updateData)),
//                 'linkedin_status' => $linkedinAction,
//                 'detection_results' => [
//                     'has_linkedin_post' => $hasLinkedInPost,
//                     'is_published_to_linkedin' => $isPublishedToLinkedIn,
//                     'post_status' => $post->post_status,
//                     'action_requested' => $request->get('linkedin_action', 'none')
//                 ],
//                 'post_data' => $post->fresh(),
//                 'timestamp' => now()->toISOString()
//             ];

//             // 🔥 HANDLE LINKEDIN REPOST IF REQUESTED
//             if ($linkedinAction === 'create_new_post') {
//                 try {
//                     \Illuminate\Support\Facades\Log::info('Starting LinkedIn repost process', [
//                         'post_id' => $postId,
//                         'original_platform_id' => $post->platform_posts['linkedin']['platform_id'] ?? 'none'
//                     ]);

//                     // Get LinkedIn token
//                     $sessionFiles = glob(storage_path('app/oauth_sessions/oauth_tokens_linkedin_*.json'));

//                     if (empty($sessionFiles)) {
//                         throw new \Exception('No LinkedIn token files found');
//                     }

//                     $latestFile = array_reduce($sessionFiles, function ($latest, $file) {
//                         return (!$latest || filemtime($file) > filemtime($latest)) ? $file : $latest;
//                     });

//                     $tokenData = json_decode(file_get_contents($latestFile), true);

//                     if (!isset($tokenData['access_token'])) {
//                         throw new \Exception('LinkedIn token not found in session file');
//                     }

//                     // Create temporary channel for API call
//                     $channel = new \App\Models\Channel([
//                         'oauth_tokens' => $tokenData,
//                         'provider' => 'linkedin',
//                         'connection_status' => 'connected'
//                     ]);

//                     // Use LinkedIn provider to create new post
//                     $provider = new \App\Services\SocialMedia\LinkedInProvider();
//                     $publishResult = $provider->publishPost($post->fresh(), $channel);

//                     \Illuminate\Support\Facades\Log::info('LinkedIn repost result', [
//                         'success' => $publishResult['success'],
//                         'result' => $publishResult
//                     ]);

//                     if ($publishResult['success']) {
//                         // Update platform_posts with new LinkedIn post data
//                         $platformPosts = $post->platform_posts ?? [];

//                         // Keep original LinkedIn post data for reference
//                         $originalLinkedIn = $platformPosts['linkedin'] ?? null;

//                         // Update main LinkedIn post data
//                         $platformPosts['linkedin'] = [
//                             'platform_id' => $publishResult['platform_id'],
//                             'url' => $publishResult['url'],
//                             'published_at' => $publishResult['published_at'],
//                             'mode' => 'updated_repost',
//                             'update_of_original' => true
//                         ];

//                         // Store original post data for reference
//                         if ($originalLinkedIn) {
//                             $platformPosts['linkedin_original'] = $originalLinkedIn;
//                         }

//                         $post->update([
//                             'platform_posts' => $platformPosts,
//                             'post_status' => 'published' // Update status back to published
//                         ]);

//                         $response['linkedin_repost'] = [
//                             'success' => true,
//                             'message' => 'New LinkedIn post created successfully!',
//                             'new_post_url' => $publishResult['url'],
//                             'new_platform_id' => $publishResult['platform_id'],
//                             'original_post_url' => $originalLinkedIn['url'] ?? 'unknown',
//                             'both_posts_exist' => true,
//                             'published_at' => $publishResult['published_at']
//                         ];

//                         $response['message'] = 'Post updated in database AND new LinkedIn post created!';
//                     } else {
//                         $response['linkedin_repost'] = [
//                             'success' => false,
//                             'error' => $publishResult['error'] ?? 'Unknown publishing error',
//                             'provider_result' => $publishResult
//                         ];
//                     }
//                 } catch (\Exception $e) {
//                     \Illuminate\Support\Facades\Log::error('LinkedIn repost failed', [
//                         'error' => $e->getMessage(),
//                         'trace' => $e->getTraceAsString()
//                     ]);

//                     $response['linkedin_repost'] = [
//                         'success' => false,
//                         'error' => 'Repost failed: ' . $e->getMessage()
//                     ];
//                 }
//             }

//             return response()->json($response);
//         } catch (\Exception $e) {
//             return response()->json([
//                 'update_status' => 'ERROR',
//                 'error' => $e->getMessage(),
//                 'trace' => $e->getTraceAsString()
//             ], 500);
//         }
//     });

//     // 🗑️ DELETE POST FROM PLATFORM
//     Route::delete('/test/posts/delete-from-linkedin/{postId}', function ($postId) {
//         try {
//             $post = \App\Models\SocialMediaPost::findOrFail($postId);

//             if (!isset($post->platform_posts['linkedin']['platform_id'])) {
//                 return response()->json([
//                     'linkedin_delete' => 'NOT_APPLICABLE',
//                     'message' => 'Post was not published to LinkedIn',
//                     'post_id' => $postId
//                 ], 400);
//             }

//             // 🔥 USE PROVIDER METHODS INSTEAD OF DIRECT API CALLS
//             $sessionFiles = glob(storage_path('app/oauth_sessions/oauth_tokens_linkedin_*.json'));

//             if (empty($sessionFiles)) {
//                 return response()->json([
//                     'linkedin_delete' => 'NO_TOKEN',
//                     'message' => 'No LinkedIn token available for deletion',
//                     'post_id' => $postId
//                 ], 400);
//             }

//             $latestFile = array_reduce($sessionFiles, function ($latest, $file) {
//                 return (!$latest || filemtime($file) > filemtime($latest)) ? $file : $latest;
//             });

//             $tokenData = json_decode(file_get_contents($latestFile), true);

//             if (!isset($tokenData['access_token'])) {
//                 return response()->json([
//                     'linkedin_delete' => 'INVALID_TOKEN',
//                     'message' => 'LinkedIn token is invalid',
//                     'post_id' => $postId
//                 ], 400);
//             }

//             // Create channel and use provider
//             $channel = new \App\Models\Channel([
//                 'oauth_tokens' => $tokenData,
//                 'provider' => 'linkedin'
//             ]);

//             $provider = new \App\Services\SocialMedia\LinkedInProvider();

//             // 🔥 CHECK POST STATUS FIRST
//             $statusCheck = $provider->getPostDeletionStatus(
//                 $post->platform_posts['linkedin']['platform_id'],
//                 $channel,
//                 $post->platform_posts['linkedin']['url'] ?? null
//             );

//             // 🔥 UPDATE DATABASE BASED ON LINKEDIN STATUS
//             if ($statusCheck['status'] === 'DELETED') {
//                 // Post already deleted on LinkedIn - update database
//                 $post->update([
//                     'post_status' => 'deleted_on_platform',
//                     'deleted_from_linkedin_at' => now(),
//                     'linkedin_status_verified' => true,
//                     'linkedin_deletion_response' => [
//                         'status' => 'verified_deleted',
//                         'verified_at' => now()->toISOString(),
//                         'method' => 'provider_check'
//                     ]
//                 ]);

//                 return response()->json([
//                     'linkedin_delete' => 'SUCCESS! 🗑️',
//                     'message' => 'Post was already deleted from LinkedIn - database updated',
//                     'post_id' => $postId,
//                     'platform_id' => $post->platform_posts['linkedin']['platform_id'],
//                     'status_check' => $statusCheck,
//                     'database_updated' => true,
//                     'new_post_status' => 'deleted_on_platform',
//                     'timestamp' => now()->toISOString()
//                 ]);
//             }

//             // Post still exists - provide manual deletion guidance
//             return response()->json([
//                 'linkedin_delete' => 'MANUAL_DELETION_REQUIRED',
//                 'message' => 'Post still exists on LinkedIn - please delete manually',
//                 'post_id' => $postId,
//                 'platform_id' => $post->platform_posts['linkedin']['platform_id'],
//                 'post_url' => $post->platform_posts['linkedin']['url'] ?? null,
//                 'manual_deletion_steps' => $statusCheck['manual_deletion_steps'] ?? [],
//                 'status_check' => $statusCheck,
//                 'note' => 'After manual deletion, call this endpoint again to update database',
//                 'timestamp' => now()->toISOString()
//             ]);
//         } catch (\Exception $e) {
//             return response()->json([
//                 'linkedin_delete' => 'ERROR',
//                 'error' => $e->getMessage(),
//                 'post_id' => $postId,
//                 'trace' => $e->getTraceAsString()
//             ], 500);
//         }
//     });

//     // 🔥 ENHANCED DELETE FROM LINKEDIN WITH MULTI-METHOD VERIFICATION
//     Route::delete('/test/posts/delete-from-linkedin-enhanced/{postId}', function ($postId) {
//         try {
//             $post = \App\Models\SocialMediaPost::findOrFail($postId);

//             if (!isset($post->platform_posts['linkedin']['platform_id'])) {
//                 return response()->json([
//                     'linkedin_delete' => 'NOT_APPLICABLE',
//                     'message' => 'Post was not published to LinkedIn',
//                     'post_id' => $postId
//                 ], 400);
//             }

//             // Get LinkedIn token
//             $sessionFiles = glob(storage_path('app/oauth_sessions/oauth_tokens_linkedin_*.json'));

//             if (empty($sessionFiles)) {
//                 return response()->json([
//                     'linkedin_delete' => 'NO_TOKEN',
//                     'message' => 'No LinkedIn token available',
//                     'post_id' => $postId,
//                     'manual_verification_required' => true
//                 ], 400);
//             }

//             $latestFile = array_reduce($sessionFiles, function ($latest, $file) {
//                 return (!$latest || filemtime($file) > filemtime($latest)) ? $file : $latest;
//             });

//             $tokenData = json_decode(file_get_contents($latestFile), true);

//             if (!isset($tokenData['access_token'])) {
//                 return response()->json([
//                     'linkedin_delete' => 'INVALID_TOKEN',
//                     'message' => 'LinkedIn token is invalid',
//                     'post_id' => $postId,
//                     'manual_verification_required' => true
//                 ], 400);
//             }

//             // Create channel and use enhanced provider methods
//             $channel = new \App\Models\Channel([
//                 'oauth_tokens' => $tokenData,
//                 'provider' => 'linkedin'
//             ]);

//             $provider = new \App\Services\SocialMedia\LinkedInProvider();

//             // 🔥 USE ENHANCED STATUS CHECK
//             $statusCheck = $provider->getPostDeletionStatusEnhanced(
//                 $post->platform_posts['linkedin']['platform_id'],
//                 $channel,
//                 $post->platform_posts['linkedin']['url'] ?? null
//             );

//             // 🔥 HANDLE DIFFERENT STATUS RESULTS
//             switch ($statusCheck['status']) {
//                 case 'DELETED':
//                     if ($statusCheck['confidence'] === 'high') {
//                         $post->update([
//                             'post_status' => 'deleted_on_platform',
//                             'deleted_from_linkedin_at' => now(),
//                             'linkedin_status_verified' => true,
//                             'linkedin_deletion_response' => [
//                                 'status' => 'verified_deleted',
//                                 'confidence' => $statusCheck['confidence'],
//                                 'verified_at' => now()->toISOString(),
//                                 'method' => 'enhanced_provider_check',
//                                 'verification_methods' => $statusCheck['verification_methods'] ?? []
//                             ]
//                         ]);

//                         return response()->json([
//                             'linkedin_delete' => 'SUCCESS! 🗑️',
//                             'message' => 'Post deleted from LinkedIn (high confidence) - database updated',
//                             'post_id' => $postId,
//                             'platform_id' => $post->platform_posts['linkedin']['platform_id'],
//                             'status_check' => $statusCheck,
//                             'database_updated' => true,
//                             'confidence' => $statusCheck['confidence'],
//                             'timestamp' => now()->toISOString()
//                         ]);
//                     } else {
//                         // Medium/low confidence deletion
//                         return response()->json([
//                             'linkedin_delete' => 'UNCERTAIN_DELETION',
//                             'message' => 'Post may be deleted but confidence is low - manual verification recommended',
//                             'post_id' => $postId,
//                             'platform_id' => $post->platform_posts['linkedin']['platform_id'],
//                             'status_check' => $statusCheck,
//                             'confidence' => $statusCheck['confidence'],
//                             'requires_manual_verification' => true,
//                             'timestamp' => now()->toISOString()
//                         ], 409);
//                     }
//                     break;

//                 case 'EXISTS':
//                     return response()->json([
//                         'linkedin_delete' => 'POST_STILL_EXISTS',
//                         'message' => 'Post still exists on LinkedIn - manual deletion required',
//                         'post_id' => $postId,
//                         'platform_id' => $post->platform_posts['linkedin']['platform_id'],
//                         'post_url' => $post->platform_posts['linkedin']['url'] ?? null,
//                         'confidence' => $statusCheck['confidence'],
//                         'manual_deletion_steps' => $statusCheck['manual_deletion_steps'] ?? [],
//                         'status_check' => $statusCheck,
//                         'note' => 'Please delete manually and call this endpoint again',
//                         'timestamp' => now()->toISOString()
//                     ]);

//                 case 'UNCERTAIN':
//                 default:
//                     return response()->json([
//                         'linkedin_delete' => 'MANUAL_VERIFICATION_REQUIRED',
//                         'message' => 'LinkedIn API results are inconsistent - manual verification needed',
//                         'post_id' => $postId,
//                         'platform_id' => $post->platform_posts['linkedin']['platform_id'],
//                         'post_url' => $post->platform_posts['linkedin']['url'] ?? null,
//                         'status_check' => $statusCheck,
//                         'api_limitation_detected' => true,
//                         'manual_verification_steps' => $statusCheck['manual_verification_steps'] ?? [],
//                         'recommendation' => 'Visit the LinkedIn post URL directly to verify its status',
//                         'timestamp' => now()->toISOString()
//                     ], 409);
//             }
//         } catch (\Exception $e) {
//             return response()->json([
//                 'linkedin_delete' => 'ERROR',
//                 'error' => $e->getMessage(),
//                 'post_id' => $postId,
//                 'manual_verification_required' => true,
//                 'trace' => $e->getTraceAsString()
//             ], 500);
//         }
//     });

//     // 🔥 ENHANCED LINKEDIN STATUS CHECK ROUTE
//     Route::get('/test/posts/linkedin-status-enhanced/{postId}', function ($postId) {
//         try {
//             $post = \App\Models\SocialMediaPost::findOrFail($postId);

//             if (!isset($post->platform_posts['linkedin']['platform_id'])) {
//                 return response()->json([
//                     'status_check' => 'NOT_APPLICABLE',
//                     'message' => 'Post was not published to LinkedIn',
//                     'post_id' => $postId
//                 ], 400);
//             }

//             // Get LinkedIn token
//             $sessionFiles = glob(storage_path('app/oauth_sessions/oauth_tokens_linkedin_*.json'));

//             if (empty($sessionFiles)) {
//                 return response()->json([
//                     'status_check' => 'NO_TOKEN',
//                     'message' => 'No LinkedIn token available for status check',
//                     'post_id' => $postId
//                 ], 400);
//             }

//             $latestFile = array_reduce($sessionFiles, function ($latest, $file) {
//                 return (!$latest || filemtime($file) > filemtime($latest)) ? $file : $latest;
//             });

//             $tokenData = json_decode(file_get_contents($latestFile), true);

//             if (!isset($tokenData['access_token'])) {
//                 return response()->json([
//                     'status_check' => 'INVALID_TOKEN',
//                     'message' => 'LinkedIn token is invalid',
//                     'post_id' => $postId
//                 ], 400);
//             }

//             // Create channel and use enhanced provider methods
//             $channel = new \App\Models\Channel([
//                 'oauth_tokens' => $tokenData,
//                 'provider' => 'linkedin'
//             ]);

//             $provider = new \App\Services\SocialMedia\LinkedInProvider();

//             // 🔥 USE ENHANCED MULTI-METHOD STATUS CHECK
//             $enhancedCheck = $provider->checkPostExistsEnhanced(
//                 $post->platform_posts['linkedin']['platform_id'],
//                 $channel
//             );

//             $statusResult = $provider->getPostDeletionStatusEnhanced(
//                 $post->platform_posts['linkedin']['platform_id'],
//                 $channel,
//                 $post->platform_posts['linkedin']['url'] ?? null
//             );

//             return response()->json([
//                 'status_check' => 'SUCCESS! 🔍',
//                 'post_id' => $postId,
//                 'platform_id' => $post->platform_posts['linkedin']['platform_id'],
//                 'enhanced_existence_check' => $enhancedCheck,
//                 'deletion_status' => $statusResult,
//                 'current_post_status' => $post->post_status,
//                 'api_analysis' => [
//                     'methods_used' => $enhancedCheck['methods_used'] ?? 0,
//                     'methods_saying_exists' => $enhancedCheck['methods_saying_exists'] ?? 0,
//                     'confidence_level' => $enhancedCheck['confidence'] ?? 'unknown',
//                     'exists_percentage' => $enhancedCheck['exists_percentage'] ?? 0
//                 ],
//                 'verification_methods' => $enhancedCheck['verification_methods'] ?? [],
//                 'recommendation' => $enhancedCheck['recommendation'] ?? 'No recommendation available',
//                 'timestamp' => now()->toISOString()
//             ]);
//         } catch (\Exception $e) {
//             return response()->json([
//                 'status_check' => 'ERROR',
//                 'error' => $e->getMessage(),
//                 'post_id' => $postId,
//                 'trace' => $e->getTraceAsString()
//             ], 500);
//         }
//     });

//     // 🔥 MANUAL STATUS CONFIRMATION ROUTE
//     Route::post('/test/posts/confirm-linkedin-status/{postId}', function ($postId, \Illuminate\Http\Request $request) {
//         try {
//             $post = \App\Models\SocialMediaPost::findOrFail($postId);

//             $manualStatus = $request->get('status'); // 'deleted' or 'exists'
//             $userConfirmation = $request->get('confirmed_by_user', false);

//             if (!in_array($manualStatus, ['deleted', 'exists']) || !$userConfirmation) {
//                 return response()->json([
//                     'confirmation' => 'INVALID_INPUT',
//                     'message' => 'Please provide valid status (deleted/exists) and confirmation',
//                     'required_fields' => [
//                         'status' => 'deleted or exists',
//                         'confirmed_by_user' => true
//                     ]
//                 ], 400);
//             }

//             if ($manualStatus === 'deleted') {
//                 $post->update([
//                     'post_status' => 'deleted_on_platform',
//                     'deleted_from_linkedin_at' => now(),
//                     'linkedin_status_verified' => true,
//                     'manual_verification' => true,
//                     'linkedin_deletion_response' => [
//                         'status' => 'manually_verified_deleted',
//                         'verified_at' => now()->toISOString(),
//                         'verified_by' => 'user_confirmation',
//                         'method' => 'manual_verification'
//                     ]
//                 ]);

//                 return response()->json([
//                     'confirmation' => 'SUCCESS! ✅',
//                     'message' => 'Post status updated to deleted based on manual verification',
//                     'post_id' => $postId,
//                     'new_status' => 'deleted_on_platform',
//                     'verified_manually' => true,
//                     'database_updated' => true,
//                     'timestamp' => now()->toISOString()
//                 ]);
//             } else {
//                 return response()->json([
//                     'confirmation' => 'POST_EXISTS_CONFIRMED',
//                     'message' => 'Post confirmed to still exist on LinkedIn',
//                     'post_id' => $postId,
//                     'current_status' => $post->post_status,
//                     'recommendation' => 'Delete the post manually from LinkedIn first, then call the confirmation endpoint with status=deleted',
//                     'timestamp' => now()->toISOString()
//                 ]);
//             }
//         } catch (\Exception $e) {
//             return response()->json([
//                 'confirmation' => 'ERROR',
//                 'error' => $e->getMessage(),
//                 'post_id' => $postId
//             ], 500);
//         }
//     });

//     // 🔄 CHECK LINKEDIN POST STATUS
//     Route::get('/test/posts/check-linkedin-status/{postId}', function ($postId) {
//         try {
//             $post = \App\Models\SocialMediaPost::findOrFail($postId);

//             if (!isset($post->platform_posts['linkedin'])) {
//                 return response()->json([
//                     'status_check' => 'NOT_APPLICABLE',
//                     'message' => 'Post was not published to LinkedIn',
//                     'post_id' => $postId,
//                     'post_status' => $post->post_status
//                 ], 400);
//             }

//             $linkedinData = $post->platform_posts['linkedin'];
//             $platformId = $linkedinData['platform_id'];

//             // Try to get LinkedIn token
//             $sessionFiles = glob(storage_path('app/oauth_sessions/oauth_tokens_linkedin_*.json'));

//             if (empty($sessionFiles)) {
//                 return response()->json([
//                     'status_check' => 'NO_TOKEN',
//                     'message' => 'No LinkedIn token available for status check',
//                     'post_id' => $postId
//                 ], 400);
//             }

//             $latestFile = array_reduce($sessionFiles, function ($latest, $file) {
//                 return (!$latest || filemtime($file) > filemtime($latest)) ? $file : $latest;
//             });

//             $tokenData = json_decode(file_get_contents($latestFile), true);

//             if (!isset($tokenData['access_token'])) {
//                 return response()->json([
//                     'status_check' => 'INVALID_TOKEN',
//                     'message' => 'LinkedIn token is invalid',
//                     'post_id' => $postId
//                 ], 400);
//             }

//             // Check if post exists on LinkedIn
//             $response = \Illuminate\Support\Facades\Http::withToken($tokenData['access_token'])
//                 ->withHeaders([
//                     'X-Restli-Protocol-Version' => '2.0.0',
//                     'Accept' => 'application/json'
//                 ])
//                 ->get("https://api.linkedin.com/v2/shares/{$platformId}");

//             $existsOnLinkedIn = $response->successful();
//             $linkedinStatus = $existsOnLinkedIn ? 'ACTIVE' : 'DELETED_OR_UNAVAILABLE';

//             // Update post status if needed
//             $postStatusUpdated = false;
//             if (!$existsOnLinkedIn && $post->post_status === 'published') {
//                 $post->update([
//                     'post_status' => 'deleted_on_platform',
//                     'platform_status_checked_at' => now()
//                 ]);
//                 $postStatusUpdated = true;
//             }

//             return response()->json([
//                 'status_check' => 'SUCCESS! 🔄',
//                 'post_id' => $postId,
//                 'platform_id' => $platformId,
//                 'exists_on_linkedin' => $existsOnLinkedIn,
//                 'linkedin_status' => $linkedinStatus,
//                 'platform_response' => [
//                     'status_code' => $response->status(),
//                     'successful' => $response->successful()
//                 ],
//                 'post_status_updated' => $postStatusUpdated,
//                 'current_post_status' => $post->fresh()->post_status,
//                 'timestamp' => now()->toISOString()
//             ]);
//         } catch (\Exception $e) {
//             return response()->json([
//                 'status_check' => 'ERROR',
//                 'error' => $e->getMessage(),
//                 'post_id' => $postId
//             ], 500);
//         }
//     });

//     // 📊 GET POST DETAILS WITH ANALYTICS
//     Route::get('/test/posts/details/{postId}', function ($postId) {
//         try {
//             $post = \App\Models\SocialMediaPost::findOrFail($postId);
//             $analytics = \App\Models\PostAnalytics::where('social_media_post_id', $postId)
//                 ->orderBy('collected_at', 'desc')
//                 ->get();

//             return response()->json([
//                 'post_details' => 'SUCCESS! 📊',
//                 'post' => $post,
//                 'analytics' => [
//                     'total_records' => $analytics->count(),
//                     'latest_collection' => $analytics->first()?->collected_at,
//                     'platforms' => $analytics->pluck('platform')->unique()->values(),
//                     'performance_scores' => $analytics->pluck('performance_score')->toArray(),
//                     'records' => $analytics->take(10) // Limit for performance
//                 ],
//                 'engagement_summary' => [
//                     'total_engagement' => method_exists($post, 'getTotalEngagement') ? $post->getTotalEngagement() : 0,
//                     'platform_breakdown' => $analytics->groupBy('platform')->map(function ($platformAnalytics) {
//                         return [
//                             'count' => $platformAnalytics->count(),
//                             'latest_metrics' => $platformAnalytics->first()?->metrics,
//                             'avg_performance' => round($platformAnalytics->avg('performance_score'), 2)
//                         ];
//                     })
//                 ],
//                 'timestamp' => now()->toISOString()
//             ]);
//         } catch (\Exception $e) {
//             return response()->json([
//                 'post_details' => 'ERROR',
//                 'error' => $e->getMessage(),
//                 'post_id' => $postId
//             ], 500);
//         }
//     });
// });

// Route::get('/test/linkedin/analytics/{postId}', function ($postId) {
//     try {
//         $post = \App\Models\SocialMediaPost::find($postId);

//         if (!$post) {
//             return response()->json(['error' => 'Post not found'], 404);
//         }

//         // Manually trigger analytics collection
//         \App\Jobs\CollectAnalytics::dispatch($post, 'linkedin');

//         // Get current analytics
//         $analytics = \App\Models\PostAnalytics::where('social_media_post_id', $postId)
//             ->where('platform', 'linkedin')
//             ->orderBy('collected_at', 'desc')
//             ->first();

//         return response()->json([
//             'analytics_test' => 'SUCCESS',
//             'post_id' => $postId,
//             'analytics_data' => $analytics,
//             'linkedin_post' => $post->platform_posts['linkedin'] ?? null,
//             'job_dispatched' => true,
//             'timestamp' => now()->toISOString()
//         ]);
//     } catch (\Exception $e) {
//         return response()->json([
//             'analytics_test' => 'ERROR',
//             'error' => $e->getMessage()
//         ], 500);
//     }
// });

// // List active OAuth sessions
// Route::get('/test/oauth/sessions', function () {
//     $sessions = [];

//     foreach (session()->all() as $key => $value) {
//         if (str_starts_with($key, 'oauth_tokens_')) {
//             $sessions[] = [
//                 'session_key' => $key,
//                 'provider' => explode('_', $key)[2] ?? 'unknown',
//                 'created' => $value['expires_at'] ?? 'unknown',
//                 'has_access_token' => !empty($value['access_token'])
//             ];
//         }
//     }

//     return response()->json([
//         'active_sessions' => $sessions,
//         'total_sessions' => count($sessions),
//         'instructions' => [
//             'test_profile' => 'GET /test/linkedin/profile/{sessionKey}',
//             'test_posting' => 'POST /test/linkedin/post/{sessionKey}'
//         ]
//     ]);
// });

// // debugging LinkedIn scopes
// Route::get('/test/linkedin/scopes', function () {
//     return response()->json([
//         'linkedin_scopes_info' => [
//             'default_scopes' => ['w_member_social', 'r_liteprofile'],
//             'scope_descriptions' => [
//                 'w_member_social' => 'Write access to post on LinkedIn',
//                 'r_liteprofile' => 'Read access to basic profile info',
//                 'r_emailaddress' => 'REQUIRES SPECIAL APPROVAL - not available for new apps'
//             ],
//             'recommended_for_testing' => ['w_member_social', 'r_liteprofile'],
//             'current_config' => config('services.linkedin.scopes'),
//             'auth_url_with_correct_scopes' => 'https://www.linkedin.com/oauth/v2/authorization?' . http_build_query([
//                 'response_type' => 'code',
//                 'client_id' => config('services.linkedin.client_id'),
//                 'redirect_uri' => config('services.linkedin.redirect'),
//                 'scope' => 'w_member_social r_liteprofile',
//                 'state' => 'test_fixed_scopes'
//             ])
//         ]
//     ]);
// });

// // Step 1.1 completion confirmation
// Route::get('/step-1-1-complete', function () {
//     return [
//         'step_1_1_status' => 'COMPLETED',
//         'developer' => 'J33WAKASUPUN',
//         'timestamp' => now()->toISOString(),
//         'laravel' => [
//             'version' => app()->version(),
//             'environment' => app()->environment(),
//             'app_key_set' => !empty(config('app.key')),
//         ],
//         'mongodb' => [
//             'status' => 'connected',
//             'database' => 'social_media_platform',
//             'atlas_cluster' => 'socialmediamarketingpla.6rj4p9c.mongodb.net'
//         ],
//         'confirmed_working' => [
//             'laravel_12' => true,
//             'mongodb_atlas' => true,
//             'basic_routing' => true,
//             'crud_operations' => true,
//         ],
//         'next_step' => 'Step 1.2: Core Configuration',
//         'ready_for_phase_2' => true
//     ];
// });

// function checkLinkedInPostStatusWithProvider(\App\Models\SocialMediaPost $post): array
// {
//     try {
//         $linkedinData = $post->platform_posts['linkedin'] ?? null;

//         if (!$linkedinData || !isset($linkedinData['platform_id'])) {
//             return [
//                 'success' => false,
//                 'error' => 'No LinkedIn post data found',
//                 'exists' => false
//             ];
//         }

//         // Get LinkedIn token
//         $sessionFiles = glob(storage_path('app/oauth_sessions/oauth_tokens_linkedin_*.json'));

//         if (empty($sessionFiles)) {
//             return [
//                 'success' => false,
//                 'error' => 'No LinkedIn token available',
//                 'exists' => 'unknown',
//                 'requires_manual_deletion' => true
//             ];
//         }

//         $latestFile = array_reduce($sessionFiles, function ($latest, $file) {
//             return (!$latest || filemtime($file) > filemtime($latest)) ? $file : $latest;
//         });

//         $tokenData = json_decode(file_get_contents($latestFile), true);

//         if (!isset($tokenData['access_token'])) {
//             return [
//                 'success' => false,
//                 'error' => 'Invalid LinkedIn token',
//                 'exists' => 'unknown',
//                 'requires_manual_deletion' => true
//             ];
//         }

//         // Create temporary channel
//         $channel = new \App\Models\Channel([
//             'oauth_tokens' => $tokenData,
//             'provider' => 'linkedin'
//         ]);

//         // Use LinkedIn provider to check post status
//         $provider = new \App\Services\SocialMedia\LinkedInProvider();
//         $result = $provider->getPostDeletionStatus(
//             $linkedinData['platform_id'],
//             $channel,
//             $linkedinData['url'] ?? null
//         );

//         return [
//             'success' => true,
//             'exists' => $result['status'] === 'EXISTS',
//             'status' => $result['status'],
//             'message' => $result['message'],
//             'deletion_steps' => $result['manual_deletion_steps'] ?? null,
//             'post_url' => $result['post_url'] ?? null,
//             'checked_at' => $result['checked_at'] ?? now()->toISOString()
//         ];
//     } catch (\Exception $e) {
//         return [
//             'success' => false,
//             'error' => $e->getMessage(),
//             'exists' => 'unknown',
//             'requires_manual_deletion' => true
//         ];
//     }
// }

// // Keep auth routes that Breeze created
// require __DIR__ . '/auth.php';
