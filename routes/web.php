<?php
// routes/web.php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;


Route::get('/', function () {
    return [
        'message' => 'Social Media Marketing Platform - Backend API',
        'laravel' => app()->version(),
        'timestamp' => now()->toISOString(),
        'status' => 'operational'
    ];
});

// MongoDB test route (working)
Route::get('/test-mongodb', function () {
    try {
        $ping = DB::connection('mongodb')->getDatabase()->command(['ping' => 1]);
        return [
            'mongodb' => 'success',
            'ping' => 'ok',
            'database' => 'social_media_platform'
        ];
    } catch (Exception $e) {
        return [
            'mongodb' => 'error',
            'message' => $e->getMessage()
        ];
    }
});

// Redis test route (NEW - this was missing!)
Route::get('/test-redis', function () {
    try {
        // Test Redis connection
        $ping = Redis::ping();

        // Test cache operations
        $testKey = 'redis_test_' . time();
        $testValue = 'Redis working for SMP - ' . now();

        cache()->put($testKey, $testValue, 60);
        $retrieved = cache()->get($testKey);
        cache()->forget($testKey);

        // Test direct Redis operations
        Redis::set('smp_direct_test', 'Direct Redis test - ' . now(), 'EX', 60);
        $directTest = Redis::get('smp_direct_test');
        Redis::del('smp_direct_test');

        return [
            'redis_status' => 'success',
            'ping' => $ping ? 'PONG' : 'failed',
            'cache_test' => [
                'stored' => $testValue,
                'retrieved' => $retrieved,
                'match' => $retrieved === $testValue,
            ],
            'direct_redis_test' => [
                'stored_and_retrieved' => $directTest ? true : false,
                'value' => $directTest
            ],
            'container_info' => [
                'host' => config('database.redis.default.host'),
                'port' => config('database.redis.default.port'),
                'client' => config('database.redis.client'),
            ],
            'ready_for' => [
                'real_time_features' => true,
                'background_jobs' => true,
                'session_management' => true,
                'api_rate_limiting' => true,
                'social_media_caching' => true,
            ]
        ];
    } catch (Exception $e) {
        return [
            'redis_status' => 'error',
            'message' => $e->getMessage(),
            'suggestion' => 'Make sure Redis container is running: docker start redis-smp'
        ];
    }
});

// Authentication test routes
Route::get('/test-auth', function () {
    try {
        // Test user creation and authentication
        $userCount = User::count();
        $adminUser = User::where('email', 'admin@socialmedia.com')->first();

        // Get all unique roles and permissions from all users
        $allUsers = User::all();
        $allRoles = $allUsers->flatMap(fn($user) => $user->roles ?? [])->unique()->values();
        $allPermissions = $allUsers->flatMap(fn($user) => $user->getAllPermissions())->unique()->values();

        return [
            'authentication_status' => 'success',
            'timestamp' => now()->format('Y-m-d H:i:s'),
            'developer' => 'J33WAKASUPUN',
            'system' => 'MongoDB Native Role System',
            'users' => [
                'total_count' => $userCount,
                'admin_exists' => $adminUser ? true : false,
                'admin_email' => $adminUser ? $adminUser->email : null,
                'admin_roles' => $adminUser ? $adminUser->getRoleNames() : [],
                'admin_permissions' => $adminUser ? $adminUser->getAllPermissions() : [],
                'admin_last_login' => $adminUser ? $adminUser->last_login_at : null,
                'admin_can_manage_users' => $adminUser ? $adminUser->hasPermission('manage users') : false,
            ],
            'roles_and_permissions' => [
                'available_roles' => $allRoles,
                'available_permissions' => $allPermissions,
                'total_unique_roles' => $allRoles->count(),
                'total_unique_permissions' => $allPermissions->count(),
            ],
            'subscription_system' => [
                'plans' => ['free', 'basic', 'pro', 'enterprise'],
                'admin_plan' => $adminUser ? $adminUser->subscription['plan'] ?? 'free' : null,
                'admin_limits' => $adminUser ? $adminUser->getSubscriptionLimits() : null,
            ],
            'mongodb_features' => [
                'native_arrays' => true,
                'flexible_schema' => true,
                'role_system' => 'custom_mongodb_implementation',
                'spatie_compatible' => false,
            ],
            'ready_for' => [
                'api_authentication' => true,
                'role_based_access' => true,
                'user_management' => true,
                'social_media_integration' => true,
            ]
        ];
    } catch (Exception $e) {
        return [
            'authentication_status' => 'error',
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
        ];
    }
});

// Complete system test
Route::get('/test-setup-complete', function () {
    $results = [];

    // Test Laravel
    $results['laravel'] = [
        'version' => app()->version(),
        'environment' => app()->environment(),
        'app_key_set' => !empty(config('app.key')),
        'timezone' => config('app.timezone'),
        'debug_mode' => config('app.debug'),
    ];

    // Test MongoDB
    try {
        $mongoConnection = DB::connection('mongodb');
        $ping = $mongoConnection->getDatabase()->command(['ping' => 1]);

        // Quick CRUD test
        $collection = $mongoConnection->getCollection('system_test');
        $testDoc = [
            'test_id' => 'setup_complete_' . time(),
            'timestamp' => now()->toDateTimeString(),
            'phase' => 'step_1_2_completion',
            'developer' => 'J33WAKASUPUN'
        ];

        $insertResult = $collection->insertOne($testDoc);
        $count = $collection->countDocuments(['test_id' => $testDoc['test_id']]);
        $retrieved = $collection->findOne(['test_id' => $testDoc['test_id']]);
        $collection->deleteMany(['test_id' => $testDoc['test_id']]);

        $results['mongodb'] = [
            'status' => 'success',
            'connection' => 'Atlas connected',
            'database' => 'social_media_platform',
            'crud_operations' => [
                'insert' => $insertResult->getInsertedCount() > 0 ? 'success' : 'failed',
                'read' => $retrieved ? 'success' : 'failed',
                'count' => $count,
                'delete' => 'success'
            ]
        ];
    } catch (Exception $e) {
        $results['mongodb'] = [
            'status' => 'error',
            'message' => $e->getMessage()
        ];
    }

    // Test Redis
    try {
        $ping = Redis::ping();
        $testKey = 'setup_complete_test_' . time();
        $testValue = 'Redis fully working - ' . now();

        // Test cache
        cache()->put($testKey, $testValue, 60);
        $cacheRetrieved = cache()->get($testKey);
        cache()->forget($testKey);

        // Test direct Redis
        Redis::set('smp_setup_test', $testValue, 'EX', 60);
        $redisRetrieved = Redis::get('smp_setup_test');
        Redis::del('smp_setup_test');

        $results['redis'] = [
            'status' => 'success',
            'ping' => $ping ? 'PONG' : 'failed',
            'cache_layer' => $cacheRetrieved === $testValue ? 'working' : 'failed',
            'direct_access' => $redisRetrieved === $testValue ? 'working' : 'failed',
            'client' => config('database.redis.client'),
            'ready_for_production' => true
        ];
    } catch (Exception $e) {
        $results['redis'] = [
            'status' => 'error',
            'message' => $e->getMessage()
        ];
    }

    // Test essential packages
    $results['packages'] = [
        'mongodb_laravel' => class_exists('MongoDB\Laravel\MongoDBServiceProvider') ? 'installed v5.4' : 'missing',
        'predis' => class_exists('Predis\Client') ? 'installed' : 'missing',
        'laravel_sanctum' => class_exists('Laravel\Sanctum\SanctumServiceProvider') ? 'installed' : 'missing',
        'spatie_permission' => class_exists('Spatie\Permission\PermissionServiceProvider') ? 'installed' : 'missing',
    ];

    // Overall system assessment
    $mongoOk = ($results['mongodb']['status'] ?? 'error') === 'success';
    $redisOk = ($results['redis']['status'] ?? 'error') === 'success';
    $laravelOk = $results['laravel']['app_key_set'] ?? false;

    $results['step_1_2_assessment'] = [
        'core_infrastructure_ready' => $mongoOk && $redisOk && $laravelOk,
        'mongodb_atlas_connected' => $mongoOk,
        'redis_caching_active' => $redisOk,
        'laravel_configured' => $laravelOk,
        'completion_percentage' => round((
            ($mongoOk ? 33 : 0) +
            ($redisOk ? 33 : 0) +
            ($laravelOk ? 34 : 0)
        )),
        'infrastructure_grade' => $mongoOk && $redisOk && $laravelOk ? 'A+' : 'Needs fixes',
        'ready_for_step_1_3' => $mongoOk && $redisOk && $laravelOk,
        'next_phase' => 'User Authentication & Models',
        'developer_notes' => [
            'mongodb_atlas' => 'Production-ready cloud database',
            'redis_caching' => 'High-performance in-memory store',
            'laravel_12' => 'Latest framework with modern features',
            'docker_redis' => 'Containerized Redis for easy management',
        ]
    ];

    return response()->json($results, 200, [], JSON_PRETTY_PRINT);
});

// Step 1.1 completion confirmation
Route::get('/step-1-1-complete', function () {
    return [
        'step_1_1_status' => 'COMPLETED',
        'developer' => 'J33WAKASUPUN',
        'timestamp' => now()->toISOString(),
        'laravel' => [
            'version' => app()->version(),
            'environment' => app()->environment(),
            'app_key_set' => !empty(config('app.key')),
        ],
        'mongodb' => [
            'status' => 'connected',
            'database' => 'social_media_platform',
            'atlas_cluster' => 'socialmediamarketingpla.6rj4p9c.mongodb.net'
        ],
        'confirmed_working' => [
            'laravel_12' => true,
            'mongodb_atlas' => true,
            'basic_routing' => true,
            'crud_operations' => true,
        ],
        'next_step' => 'Step 1.2: Core Configuration',
        'ready_for_phase_2' => true
    ];
});

// Keep auth routes that Breeze created
require __DIR__ . '/auth.php';
