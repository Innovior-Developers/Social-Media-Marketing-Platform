# QUICK SETUP GUIDE: Laravel 11 + MongoDB + Redis + Docker
## J33WAKASUPUN's Social Media Platform - Fast Track Setup

---

## Table of Contents

1. [Project Overview](#project-overview)
2. [Prerequisites](#prerequisites)
3. [Step-by-Step Setup](#step-by-step-setup)
4. [Configuration Details](#configuration-details)
5. [Model Implementations](#model-implementations)
6. [Controller Implementations](#controller-implementations)
7. [Social Media Providers](#social-media-providers)
8. [Job Queue System](#job-queue-system)
9. [Middleware Setup](#middleware-setup)
10. [Testing & Verification](#testing--verification)
11. [Production Deployment](#production-deployment)
12. [Troubleshooting](#troubleshooting)

---

## Project Overview

This comprehensive guide walks you through creating a production-ready social media management platform using:

- **Laravel 11**: Modern PHP framework
- **MongoDB Atlas**: NoSQL cloud database
- **Redis**: In-memory data structure store for caching and queues
- **Docker**: Containerization for Redis
- **Laravel Sanctum**: API authentication
- **Queue Jobs**: Asynchronous task processing

**Estimated Setup Time**: 2-3 hours  
**Difficulty Level**: Intermediate  
**Target Audience**: PHP developers familiar with Laravel basics

---

## Prerequisites

Before starting, ensure you have:

- PHP 8.2 or higher
- Composer (latest version)
- Docker Desktop
- XAMPP (Windows) or Apache/Nginx (Linux)
- MongoDB Atlas account (free tier available)
- Basic Laravel knowledge

---

## Step-by-Step Setup

### ⚡ STEP 1: CREATE LARAVEL PROJECT

Create a new Laravel 11 project and verify installation:

```bash
composer create-project laravel/laravel Social-Media-Marketing-platform
cd Social-Media-Marketing-platform
php artisan --version
```

**Expected Output**: Laravel Framework 11.x.x

---

### ⚡ STEP 2: INSTALL MONGODB EXTENSION

#### For Windows (XAMPP):

1. Download the appropriate `php_mongodb.dll` for your PHP version from [PECL](https://pecl.php.net/package/mongodb)
2. Copy the file to `C:\xampp\php\ext\`
3. Edit `C:\xampp\php\php.ini` and add:
   ```ini
   extension=mongodb
   ```
4. Restart Apache service
5. Verify installation:
   ```bash
   php -m | grep mongodb
   ```

#### For Linux/Ubuntu:

```bash
sudo pecl install mongodb
echo "extension=mongodb" | sudo tee -a /etc/php/8.3/cli/php.ini
sudo systemctl restart apache2
php -m | grep mongodb
```

**Troubleshooting**: If installation fails, install development packages first:
```bash
sudo apt-get install php8.3-dev pkg-config libssl-dev
```

---

### ⚡ STEP 3: SETUP MONGODB ATLAS

1. Navigate to [MongoDB Atlas](https://www.mongodb.com/atlas)
2. Create a free account or sign in
3. Create a new cluster (select M0 Free tier)
4. Create a database user:
   - Username: `social_media_admin`
   - Password: Generate secure password
5. Configure Network Access:
   - Add IP: `0.0.0.0/0` (allow from anywhere)
   - **Note**: For production, use specific IP ranges
6. Get connection string:
   - Click "Connect" → "Connect your application"
   - Copy the connection string
   - Replace `<password>` with your database user password

**Sample Connection String**:
```
mongodb+srv://social_media_admin:YOUR_PASSWORD@cluster0.xxxxx.mongodb.net/social_media_db
```

---

### ⚡ STEP 4: SETUP REDIS DOCKER

Pull and run Redis container:

```bash
# Pull Redis Alpine image (lightweight)
docker pull redis:alpine

# Run Redis container
docker run -d --name social-media-redis -p 6379:6379 redis:alpine

# Verify container is running
docker ps

# Test Redis connection
docker exec -it social-media-redis redis-cli ping
```

**Expected Output**: `PONG`

---

### ⚡ STEP 5: INSTALL LARAVEL MONGODB

Install the MongoDB Laravel package:

```bash
composer require mongodb/laravel-mongodb
```

This package provides:
- Eloquent model integration
- Query builder for MongoDB
- Authentication support
- Schema builder

---

### ⚡ STEP 6: CONFIGURE ENVIRONMENT

#### Update `.env` file:

```env
# Application
APP_NAME="Social Media Platform"
APP_ENV=local
APP_KEY=base64:YOUR_APP_KEY
APP_DEBUG=true
APP_URL=http://localhost:8000

# Database Configuration
DB_CONNECTION=mongodb
MONGODB_DSN="mongodb+srv://social_media_admin:YOUR_PASSWORD@cluster0.xxxxx.mongodb.net/social_media_db"
DB_DATABASE=social_media_db

# Redis Configuration
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

# Cache & Session
CACHE_DRIVER=redis
SESSION_DRIVER=redis
SESSION_LIFETIME=120

# Queue Configuration
QUEUE_CONNECTION=redis

# Broadcasting
BROADCAST_DRIVER=redis

# Mail Configuration (for notifications)
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=your_username
MAIL_PASSWORD=your_password
MAIL_ENCRYPTION=tls
```

#### Update `config/database.php`:

```php
<?php

return [
    'default' => env('DB_CONNECTION', 'mongodb'),

    'connections' => [
        'mongodb' => [
            'driver' => 'mongodb',
            'dsn' => env('MONGODB_DSN'),
            'database' => env('DB_DATABASE', 'social_media_db'),
            'options' => [
                'retryWrites' => false,
            ],
        ],

        // Keep MySQL for potential future use
        'mysql' => [
            'driver' => 'mysql',
            'url' => env('DATABASE_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'forge'),
            'username' => env('DB_USERNAME', 'forge'),
            'password' => env('DB_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],
    ],
];
```

---

### ⚡ STEP 7: TEST CONNECTIONS

Create a test route to verify all connections work:

**Create `routes/test.php`:**

```php
<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

Route::get('/test-connections', function () {
    try {
        // Test MongoDB connection
        $mongoStatus = DB::connection('mongodb')->getMongoDB()->command(['ping' => 1]);
        
        // Test Redis connection
        $redisStatus = Redis::ping();
        
        return response()->json([
            'status' => 'success',
            'mongodb' => [
                'connected' => true,
                'response' => $mongoStatus,
                'database' => config('database.connections.mongodb.database')
            ],
            'redis' => [
                'connected' => true,
                'response' => $redisStatus,
                'host' => config('database.redis.default.host'),
                'port' => config('database.redis.default.port')
            ],
            'timestamp' => now()
        ]);
        
    } catch (\Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => $e->getMessage(),
            'timestamp' => now()
        ], 500);
    }
});
```

**Include test routes in `routes/web.php`:**

```php
<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Include test routes
require __DIR__.'/test.php';
```

**Test the connections:**

```bash
# Start Laravel development server
php artisan serve

# Test in new terminal
curl http://localhost:8000/test-connections
```

**Expected successful response:**
```json
{
    "status": "success",
    "mongodb": {
        "connected": true,
        "response": {"ok": 1},
        "database": "social_media_db"
    },
    "redis": {
        "connected": true,
        "response": "+PONG",
        "host": "127.0.0.1",
        "port": 6379
    },
    "timestamp": "2025-08-24T10:30:00.000000Z"
}
```

---

## Model Implementations

### ⚡ STEP 8: CREATE MODELS

Generate all required models:

```bash
php artisan make:model User
php artisan make:model Organization  
php artisan make:model Brand
php artisan make:model Membership
php artisan make:model Channel
php artisan make:model SocialMediaPost
php artisan make:model PostAnalytics
php artisan make:model ScheduledPost
php artisan make:model ContentCalendar
```

#### User Model (`app/Models/User.php`):

```php
<?php

namespace App\Models;

use MongoDB\Laravel\Auth\User as MongoUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Laravel\Sanctum\HasApiTokens;

class User extends MongoUser
{
    use HasApiTokens, HasFactory;

    protected $connection = 'mongodb';
    protected $collection = 'users';

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'profile_image',
        'phone',
        'timezone',
        'preferences',
        'last_active_at'
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_active_at' => 'datetime',
        'preferences' => 'array'
    ];

    // Relationships
    public function organizations()
    {
        return $this->belongsToMany(Organization::class, null, 'user_ids', 'organization_ids');
    }

    public function memberships()
    {
        return $this->hasMany(Membership::class);
    }

    public function posts()
    {
        return $this->hasMany(SocialMediaPost::class, 'created_by');
    }
}
```

#### Organization Model (`app/Models/Organization.php`):

```php
<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Organization extends Model
{
    use HasFactory;

    protected $connection = 'mongodb';
    protected $collection = 'organizations';

    protected $fillable = [
        'name',
        'description',
        'logo',
        'website',
        'industry',
        'size',
        'subscription_plan',
        'subscription_expires_at',
        'settings',
        'created_by'
    ];

    protected $casts = [
        'subscription_expires_at' => 'datetime',
        'settings' => 'array'
    ];

    // Relationships
    public function users()
    {
        return $this->belongsToMany(User::class, null, 'organization_ids', 'user_ids');
    }

    public function brands()
    {
        return $this->hasMany(Brand::class);
    }

    public function memberships()
    {
        return $this->hasMany(Membership::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
```

#### Brand Model (`app/Models/Brand.php`):

```php
<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Brand extends Model
{
    use HasFactory;

    protected $connection = 'mongodb';
    protected $collection = 'brands';

    protected $fillable = [
        'name',
        'description',
        'logo',
        'brand_colors',
        'brand_voice',
        'hashtags',
        'organization_id',
        'is_active'
    ];

    protected $casts = [
        'brand_colors' => 'array',
        'hashtags' => 'array',
        'is_active' => 'boolean'
    ];

    // Relationships
    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function channels()
    {
        return $this->hasMany(Channel::class);
    }

    public function posts()
    {
        return $this->hasMany(SocialMediaPost::class);
    }
}
```

#### Channel Model (`app/Models/Channel.php`):

```php
<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Channel extends Model
{
    use HasFactory;

    protected $connection = 'mongodb';
    protected $collection = 'channels';

    protected $fillable = [
        'platform',
        'account_name',
        'account_id',
        'access_token',
        'refresh_token',
        'token_expires_at',
        'account_data',
        'brand_id',
        'is_active',
        'last_sync_at'
    ];

    protected $hidden = [
        'access_token',
        'refresh_token'
    ];

    protected $casts = [
        'token_expires_at' => 'datetime',
        'last_sync_at' => 'datetime',
        'account_data' => 'array',
        'is_active' => 'boolean'
    ];

    // Relationships
    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }

    public function posts()
    {
        return $this->hasMany(SocialMediaPost::class);
    }
}
```

#### SocialMediaPost Model (`app/Models/SocialMediaPost.php`):

```php
<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SocialMediaPost extends Model
{
    use HasFactory;

    protected $connection = 'mongodb';
    protected $collection = 'social_media_posts';

    protected $fillable = [
        'content',
        'media',
        'hashtags',
        'scheduled_at',
        'published_at',
        'status',
        'platform_post_id',
        'brand_id',
        'channel_id',
        'created_by',
        'post_type',
        'engagement_data'
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'published_at' => 'datetime',
        'media' => 'array',
        'hashtags' => 'array',
        'engagement_data' => 'array'
    ];

    const STATUS_DRAFT = 'draft';
    const STATUS_SCHEDULED = 'scheduled';
    const STATUS_PUBLISHED = 'published';
    const STATUS_FAILED = 'failed';

    // Relationships
    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }

    public function channel()
    {
        return $this->belongsTo(Channel::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function analytics()
    {
        return $this->hasMany(PostAnalytics::class, 'post_id');
    }
}
```

#### PostAnalytics Model (`app/Models/PostAnalytics.php`):

```php
<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PostAnalytics extends Model
{
    use HasFactory;

    protected $connection = 'mongodb';
    protected $collection = 'post_analytics';

    protected $fillable = [
        'post_id',
        'platform',
        'metrics',
        'collected_at',
        'period'
    ];

    protected $casts = [
        'metrics' => 'array',
        'collected_at' => 'datetime'
    ];

    // Relationships
    public function post()
    {
        return $this->belongsTo(SocialMediaPost::class, 'post_id');
    }
}
```

#### Membership Model (`app/Models/Membership.php`):

```php
<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Membership extends Model
{
    use HasFactory;

    protected $connection = 'mongodb';
    protected $collection = 'memberships';

    protected $fillable = [
        'user_id',
        'organization_id',
        'role',
        'permissions',
        'joined_at',
        'is_active'
    ];

    protected $casts = [
        'permissions' => 'array',
        'joined_at' => 'datetime',
        'is_active' => 'boolean'
    ];

    const ROLE_OWNER = 'owner';
    const ROLE_ADMIN = 'admin';
    const ROLE_MANAGER = 'manager';
    const ROLE_EDITOR = 'editor';
    const ROLE_VIEWER = 'viewer';

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }
}
```

#### ScheduledPost Model (`app/Models/ScheduledPost.php`):

```php
<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ScheduledPost extends Model
{
    use HasFactory;

    protected $connection = 'mongodb';
    protected $collection = 'scheduled_posts';

    protected $fillable = [
        'post_id',
        'scheduled_at',
        'status',
        'attempts',
        'last_attempt_at',
        'error_message',
        'job_id'
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'last_attempt_at' => 'datetime'
    ];

    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';

    // Relationships
    public function post()
    {
        return $this->belongsTo(SocialMediaPost::class, 'post_id');
    }
}
```

#### ContentCalendar Model (`app/Models/ContentCalendar.php`):

```php
<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ContentCalendar extends Model
{
    use HasFactory;

    protected $connection = 'mongodb';
    protected $collection = 'content_calendars';

    protected $fillable = [
        'name',
        'description',
        'start_date',
        'end_date',
        'brand_id',
        'created_by',
        'posts',
        'templates',
        'is_active'
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'posts' => 'array',
        'templates' => 'array',
        'is_active' => 'boolean'
    ];

    // Relationships
    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
```

---

## Controller Implementations

### ⚡ STEP 9: CREATE CONTROLLERS

Generate all API controllers:

```bash
php artisan make:controller Api/V1/AuthController
php artisan make:controller Api/V1/UserController
php artisan make:controller Api/V1/OrganizationController
php artisan make:controller Api/V1/BrandController
php artisan make:controller Api/V1/MembershipController
php artisan make:controller Api/V1/ChannelController
php artisan make:controller Api/V1/SocialMediaPostController
php artisan make:controller Api/V1/AnalyticsController
```

#### AuthController (`app/Http/Controllers/Api/V1/AuthController.php`):

```php
<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'timezone' => 'nullable|string',
            'role' => 'nullable|string|in:admin,manager,user'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $request->role ?? 'user',
            'timezone' => $request->timezone ?? 'UTC',
            'preferences' => [
                'notifications' => true,
                'email_updates' => true,
                'theme' => 'light'
            ]
        ]);

        $token = $user->createToken('api-token', ['*'], now()->addDays(30))->plainTextToken;

        return response()->json([
            'status' => 'success',
            'message' => 'User registered successfully',
            'data' => [
                'user' => $user,
                'token' => $token
            ]
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        // Update last active timestamp
        $user->update(['last_active_at' => now()]);

        $token = $user->createToken('api-token', ['*'], now()->addDays(30))->plainTextToken;

        return response()->json([
            'status' => 'success',
            'message' => 'Login successful',
            'data' => [
                'user' => $user,
                'token' => $token
            ]
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Logged out successfully'
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data' => [
                'user' => $request->user()
            ]
        ]);
    }

    public function refreshToken(Request $request): JsonResponse
    {
        $user = $request->user();
        
        // Delete current token
        $request->user()->currentAccessToken()->delete();
        
        // Create new token
        $token = $user->createToken('api-token', ['*'], now()->addDays(30))->plainTextToken;

        return response()->json([
            'status' => 'success',
            'message' => 'Token refreshed successfully',
            'data' => [
                'token' => $token
            ]
        ]);
    }
}
```

#### OrganizationController (`app/Http/Controllers/Api/V1/OrganizationController.php`):

```php
<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\Membership;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class OrganizationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        
        $organizations = Organization::whereHas('memberships', function ($query) use ($user) {
            $query->where('user_id', $user->_id)->where('is_active', true);
        })->with(['brands', 'memberships.user'])->get();

        return response()->json([
            'status' => 'success',
            'data' => $organizations
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'website' => 'nullable|url',
            'industry' => 'nullable|string',
            'size' => 'nullable|in:startup,small,medium,large,enterprise'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        $organization = Organization::create([
            'name' => $request->name,
            'description' => $request->description,
            'website' => $request->website,
            'industry' => $request->industry,
            'size' => $request->size ?? 'startup',
            'subscription_plan' => 'free',
            'subscription_expires_at' => now()->addDays(30),
            'settings' => [
                'default_timezone' => 'UTC',
                'posting_limits' => [
                    'posts_per_day' => 10,
                    'brands_limit' => 3
                ]
            ],
            'created_by' => $request->user()->_id
        ]);

        // Create owner membership
        Membership::create([
            'user_id' => $request->user()->_id,
            'organization_id' => $organization->_id,
            'role' => Membership::ROLE_OWNER,
            'permissions' => ['*'],
            'joined_at' => now(),
            'is_active' => true
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Organization created successfully',
            'data' => $organization->load('memberships.user')
        ], 201);
    }

    public function show(Request $request, $id): JsonResponse
    {
        $organization = Organization::with(['brands', 'memberships.user'])
            ->findOrFail($id);

        // Check if user has access
        $this->authorizeOrganizationAccess($request->user(), $organization);

        return response()->json([
            'status' => 'success',
            'data' => $organization
        ]);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $organization = Organization::findOrFail($id);
        
        $this->authorizeOrganizationAccess($request->user(), $organization, ['owner', 'admin']);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'website' => 'nullable|url',
            'industry' => 'nullable|string',
            'size' => 'nullable|in:startup,small,medium,large,enterprise'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        $organization->update($request->only([
            'name', 'description', 'website', 'industry', 'size'
        ]));

        return response()->json([
            'status' => 'success',
            'message' => 'Organization updated successfully',
            'data' => $organization->load(['brands', 'memberships.user'])
        ]);
    }

    public function destroy(Request $request, $id): JsonResponse
    {
        $organization = Organization::findOrFail($id);
        
        $this->authorizeOrganizationAccess($request->user(), $organization, ['owner']);

        // Soft delete by marking as inactive
        $organization->update(['is_active' => false]);
        
        // Deactivate all memberships
        Membership::where('organization_id', $id)->update(['is_active' => false]);

        return response()->json([
            'status' => 'success',
            'message' => 'Organization deleted successfully'
        ]);
    }

    private function authorizeOrganizationAccess($user, $organization, $roles = null)
    {
        $membership = Membership::where('user_id', $user->_id)
            ->where('organization_id', $organization->_id)
            ->where('is_active', true)
            ->first();

        if (!$membership) {
            abort(403, 'Access denied to this organization');
        }

        if ($roles && !in_array($membership->role, $roles)) {
            abort(403, 'Insufficient permissions');
        }
    }
}
```

---

### ⚡ STEP 10: INSTALL SANCTUM

Install and configure Laravel Sanctum for API authentication:

```bash
composer require laravel/sanctum
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
php artisan sanctum:install
```

#### Configure Sanctum (`config/sanctum.php`):

```php
<?php

return [
    'stateful' => explode(',', env('SANCTUM_STATEFUL_DOMAINS', sprintf(
        '%s%s',
        'localhost,localhost:3000,127.0.0.1,127.0.0.1:8000,::1',
        \Laravel\Sanctum\Sanctum::currentApplicationUrlWithPort()
    ))),

    'guard' => ['web'],

    'expiration' => null,

    'token_prefix' => env('SANCTUM_TOKEN_PREFIX', ''),

    'middleware' => [
        'authenticate_session' => Laravel\Sanctum\Http\Middleware\AuthenticateSession::class,
        'encrypt_cookies' => App\Http\Middleware\EncryptCookies::class,
        'verify_csrf_token' => App\Http\Middleware\VerifyCsrfToken::class,
    ],
];
```

#### Update `app/Http/Kernel.php`:

```php
'api' => [
    \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
    'throttle:api',
    \Illuminate\Routing\Middleware\SubstituteBindings::class,
],
```

---

### ⚡ STEP 11: CREATE API ROUTES

#### Configure API routes (`routes/api.php`):

```php
<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\OrganizationController;
use App\Http\Controllers\Api\V1\BrandController;
use App\Http\Controllers\Api\V1\MembershipController;
use App\Http\Controllers\Api\V1\ChannelController;
use App\Http\Controllers\Api\V1\SocialMediaPostController;
use App\Http\Controllers\Api\V1\AnalyticsController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {
    // Public authentication routes
    Route::prefix('auth')->group(function () {
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
        Route::post('/reset-password', [AuthController::class, 'resetPassword']);
    });

    // Protected routes requiring authentication
    Route::middleware('auth:sanctum')->group(function () {
        
        // Auth management
        Route::prefix('auth')->group(function () {
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::get('/me', [AuthController::class, 'me']);
            Route::post('/refresh', [AuthController::class, 'refreshToken']);
        });

        // User management
        Route::apiResource('users', UserController::class);
        Route::put('/users/{id}/password', [UserController::class, 'updatePassword']);
        Route::post('/users/{id}/avatar', [UserController::class, 'uploadAvatar']);

        // Organization management
        Route::apiResource('organizations', OrganizationController::class);
        Route::post('/organizations/{id}/invite', [OrganizationController::class, 'inviteUser']);
        Route::put('/organizations/{id}/subscription', [OrganizationController::class, 'updateSubscription']);

        // Membership management
        Route::apiResource('memberships', MembershipController::class);
        Route::put('/memberships/{id}/role', [MembershipController::class, 'updateRole']);

        // Brand management
        Route::apiResource('brands', BrandController::class);
        Route::post('/brands/{id}/logo', [BrandController::class, 'uploadLogo']);

        // Channel management (Social media accounts)
        Route::apiResource('channels', ChannelController::class);
        Route::post('/channels/{id}/refresh-token', [ChannelController::class, 'refreshToken']);
        Route::post('/channels/{id}/sync', [ChannelController::class, 'syncAccount']);
        Route::get('/channels/{id}/insights', [ChannelController::class, 'getInsights']);

        // Social media post management
        Route::apiResource('posts', SocialMediaPostController::class);
        Route::post('/posts/{id}/schedule', [SocialMediaPostController::class, 'schedule']);
        Route::post('/posts/{id}/publish', [SocialMediaPostController::class, 'publishNow']);
        Route::post('/posts/{id}/duplicate', [SocialMediaPostController::class, 'duplicate']);
        Route::post('/posts/bulk-schedule', [SocialMediaPostController::class, 'bulkSchedule']);
        Route::delete('/posts/bulk-delete', [SocialMediaPostController::class, 'bulkDelete']);

        // Content calendar
        Route::prefix('calendar')->group(function () {
            Route::get('/', [SocialMediaPostController::class, 'calendar']);
            Route::get('/brand/{brandId}', [SocialMediaPostController::class, 'brandCalendar']);
            Route::get('/month/{year}/{month}', [SocialMediaPostController::class, 'monthlyCalendar']);
        });

        // Analytics and reporting
        Route::prefix('analytics')->group(function () {
            Route::get('/overview', [AnalyticsController::class, 'overview']);
            Route::get('/brand/{brandId}', [AnalyticsController::class, 'brandAnalytics']);
            Route::get('/post/{postId}', [AnalyticsController::class, 'postAnalytics']);
            Route::get('/channel/{channelId}', [AnalyticsController::class, 'channelAnalytics']);
            Route::get('/engagement-trends', [AnalyticsController::class, 'engagementTrends']);
            Route::get('/best-times', [AnalyticsController::class, 'bestPostingTimes']);
            Route::get('/hashtag-performance', [AnalyticsController::class, 'hashtagPerformance']);
            Route::get('/export/{type}', [AnalyticsController::class, 'exportReport']);
        });

        // Media upload and management
        Route::prefix('media')->group(function () {
            Route::post('/upload', [MediaController::class, 'upload']);
            Route::get('/', [MediaController::class, 'index']);
            Route::delete('/{id}', [MediaController::class, 'destroy']);
            Route::post('/optimize', [MediaController::class, 'optimize']);
        });

        // System health and monitoring
        Route::prefix('system')->group(function () {
            Route::get('/health', [SystemController::class, 'health']);
            Route::get('/queue-status', [SystemController::class, 'queueStatus']);
            Route::post('/queue/retry-failed', [SystemController::class, 'retryFailedJobs']);
        });
    });

    // Webhook endpoints (public but secured)
    Route::prefix('webhooks')->group(function () {
        Route::post('/facebook', [WebhookController::class, 'facebook']);
        Route::post('/twitter', [WebhookController::class, 'twitter']);
        Route::post('/instagram', [WebhookController::class, 'instagram']);
        Route::post('/linkedin', [WebhookController::class, 'linkedin']);
        Route::post('/youtube', [WebhookController::class, 'youtube']);
        Route::post('/tiktok', [WebhookController::class, 'tiktok']);
    });
});

// Health check route for monitoring
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now(),
        'version' => config('app.version', '1.0.0')
    ]);
});
```

---

## Social Media Providers

### ⚡ STEP 12: CREATE SOCIAL MEDIA PROVIDERS

#### Abstract Provider (`app/Services/SocialMedia/AbstractSocialMediaProvider.php`):

```php
<?php

namespace App\Services\SocialMedia;

use App\Models\Channel;
use App\Models\SocialMediaPost;

abstract class AbstractSocialMediaProvider
{
    protected string $platform;
    protected array $config;

    public function __construct()
    {
        $this->config = config("services.social_media.{$this->platform}", []);
    }

    // Authentication methods
    abstract public function authenticate(array $credentials): array;
    abstract public function refreshToken(Channel $channel): array;
    abstract public function validateCredentials(Channel $channel): bool;

    // Publishing methods
    abstract public function publishPost(SocialMediaPost $post, Channel $channel): array;
    abstract public function schedulePost(SocialMediaPost $post, Channel $channel): array;
    abstract public function deletePost(string $platformPostId, Channel $channel): bool;
    abstract public function updatePost(SocialMediaPost $post, Channel $channel): array;

    // Analytics methods
    abstract public function getPostAnalytics(string $platformPostId, Channel $channel): array;
    abstract public function getAccountAnalytics(Channel $channel, array $options = []): array;
    abstract public function getEngagementMetrics(Channel $channel, \DateTime $startDate, \DateTime $endDate): array;

    // Platform-specific constraints
    abstract public function getCharacterLimit(): int;
    abstract public function getMediaLimits(): array;
    abstract public function getSupportedMediaTypes(): array;
    abstract public function validatePostContent(SocialMediaPost $post): array;

    // Account information
    abstract public function getAccountInfo(Channel $channel): array;
    abstract public function getAccountInsights(Channel $channel): array;

    // Utility methods
    public function getPlatform(): string
    {
        return $this->platform;
    }

    public function formatHashtags(array $hashtags): string
    {
        return implode(' ', array_map(fn($tag) => "#{$tag}", $hashtags));
    }

    public function truncateContent(string $content, int $limit): string
    {
        return strlen($content) > $limit ? substr($content, 0, $limit - 3) . '...' : $content;
    }

    protected function makeApiRequest(string $method, string $url, array $data = [], array $headers = []): array
    {
        // Implementation of HTTP client request
        $defaultHeaders = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];

        $headers = array_merge($defaultHeaders, $headers);

        // Use Laravel's HTTP client
        $response = \Illuminate\Support\Facades\Http::withHeaders($headers)
            ->timeout(30);

        switch (strtoupper($method)) {
            case 'GET':
                $response = $response->get($url, $data);
                break;
            case 'POST':
                $response = $response->post($url, $data);
                break;
            case 'PUT':
                $response = $response->put($url, $data);
                break;
            case 'DELETE':
                $response = $response->delete($url, $data);
                break;
            default:
                throw new \InvalidArgumentException("Unsupported HTTP method: {$method}");
        }

        if (!$response->successful()) {
            throw new \Exception("API request failed: {$response->status()} - {$response->body()}");
        }

        return $response->json();
    }
}
```

#### Twitter/X Provider (`app/Services/SocialMedia/TwitterProvider.php`):

```php
<?php

namespace App\Services\SocialMedia;

use App\Models\Channel;
use App\Models\SocialMediaPost;

class TwitterProvider extends AbstractSocialMediaProvider
{
    protected string $platform = 'twitter';
    
    public function authenticate(array $credentials): array
    {
        // Twitter OAuth 2.0 implementation
        $response = $this->makeApiRequest('POST', 'https://api.twitter.com/2/oauth2/token', [
            'grant_type' => 'authorization_code',
            'client_id' => $this->config['client_id'],
            'client_secret' => $this->config['client_secret'],
            'code' => $credentials['code'],
            'redirect_uri' => $credentials['redirect_uri'],
        ]);

        return [
            'access_token' => $response['access_token'],
            'refresh_token' => $response['refresh_token'] ?? null,
            'expires_at' => now()->addSeconds($response['expires_in']),
            'account_data' => $this->getAccountInfo(['access_token' => $response['access_token']])
        ];
    }

    public function refreshToken(Channel $channel): array
    {
        if (!$channel->refresh_token) {
            throw new \Exception('No refresh token available');
        }

        $response = $this->makeApiRequest('POST', 'https://api.twitter.com/2/oauth2/token', [
            'grant_type' => 'refresh_token',
            'refresh_token' => $channel->refresh_token,
            'client_id' => $this->config['client_id'],
        ]);

        return [
            'access_token' => $response['access_token'],
            'refresh_token' => $response['refresh_token'] ?? $channel->refresh_token,
            'expires_at' => now()->addSeconds($response['expires_in']),
        ];
    }

    public function validateCredentials(Channel $channel): bool
    {
        try {
            $this->makeApiRequest('GET', 'https://api.twitter.com/2/users/me', [], [
                'Authorization' => "Bearer {$channel->access_token}"
            ]);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function publishPost(SocialMediaPost $post, Channel $channel): array
    {
        $tweetData = [
            'text' => $this->formatPostContent($post)
        ];

        // Add media if present
        if (!empty($post->media)) {
            $mediaIds = $this->uploadMedia($post->media, $channel);
            $tweetData['media'] = ['media_ids' => $mediaIds];
        }

        $response = $this->makeApiRequest('POST', 'https://api.twitter.com/2/tweets', $tweetData, [
            'Authorization' => "Bearer {$channel->access_token}"
        ]);

        return [
            'platform_post_id' => $response['data']['id'],
            'published_at' => now(),
            'platform_url' => "https://twitter.com/i/web/status/{$response['data']['id']}"
        ];
    }

    public function schedulePost(SocialMediaPost $post, Channel $channel): array
    {
        // Twitter doesn't support native scheduling, implement with job queue
        throw new \Exception('Twitter doesn\'t support native scheduling. Use Laravel queue for scheduling.');
    }

    public function deletePost(string $platformPostId, Channel $channel): bool
    {
        try {
            $this->makeApiRequest('DELETE', "https://api.twitter.com/2/tweets/{$platformPostId}", [], [
                'Authorization' => "Bearer {$channel->access_token}"
            ]);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function updatePost(SocialMediaPost $post, Channel $channel): array
    {
        throw new \Exception('Twitter doesn\'t support post editing');
    }

    public function getPostAnalytics(string $platformPostId, Channel $channel): array
    {
        $response = $this->makeApiRequest('GET', "https://api.twitter.com/2/tweets/{$platformPostId}", [
            'tweet.fields' => 'public_metrics,created_at',
            'expansions' => 'author_id'
        ], [
            'Authorization' => "Bearer {$channel->access_token}"
        ]);

        $metrics = $response['data']['public_metrics'];

        return [
            'impressions' => $metrics['impression_count'] ?? 0,
            'engagements' => ($metrics['like_count'] ?? 0) + ($metrics['retweet_count'] ?? 0) + ($metrics['reply_count'] ?? 0),
            'likes' => $metrics['like_count'] ?? 0,
            'shares' => $metrics['retweet_count'] ?? 0,
            'comments' => $metrics['reply_count'] ?? 0,
            'clicks' => 0, // Not available in basic metrics
            'reach' => $metrics['impression_count'] ?? 0,
        ];
    }

    public function getAccountAnalytics(Channel $channel, array $options = []): array
    {
        // Get user metrics
        $userResponse = $this->makeApiRequest('GET', 'https://api.twitter.com/2/users/me', [
            'user.fields' => 'public_metrics,created_at'
        ], [
            'Authorization' => "Bearer {$channel->access_token}"
        ]);

        return [
            'followers_count' => $userResponse['data']['public_metrics']['followers_count'],
            'following_count' => $userResponse['data']['public_metrics']['following_count'],
            'tweets_count' => $userResponse['data']['public_metrics']['tweet_count'],
            'listed_count' => $userResponse['data']['public_metrics']['listed_count'],
        ];
    }

    public function getEngagementMetrics(Channel $channel, \DateTime $startDate, \DateTime $endDate): array
    {
        // This would require Twitter Analytics API or premium access
        throw new \Exception('Engagement metrics require Twitter Analytics API access');
    }

    public function getCharacterLimit(): int
    {
        return 63206; // Facebook's character limit
    }

    public function getMediaLimits(): array
    {
        return [
            'images' => ['max_count' => 10, 'max_size' => 10 * 1024 * 1024], // 10MB
            'videos' => ['max_count' => 1, 'max_size' => 4 * 1024 * 1024 * 1024], // 4GB
        ];
    }

    public function getSupportedMediaTypes(): array
    {
        return ['image/jpeg', 'image/png', 'image/gif', 'video/mp4', 'video/mov', 'video/avi'];
    }

    public function validatePostContent(SocialMediaPost $post): array
    {
        $errors = [];
        
        if (strlen($post->content) > $this->getCharacterLimit()) {
            $errors[] = "Content exceeds {$this->getCharacterLimit()} character limit";
        }

        return $errors;
    }

    public function getAccountInfo(Channel $channel = null): array
    {
        $token = $channel ? $channel->access_token : null;
        
        $response = $this->makeApiRequest('GET', 'https://graph.facebook.com/v18.0/me/accounts', [
            'access_token' => $token
        ]);

        // Return first page (assuming single page management)
        $page = $response['data'][0] ?? null;
        
        if (!$page) {
            throw new \Exception('No Facebook pages found');
        }

        return [
            'id' => $page['id'],
            'name' => $page['name'],
            'category' => $page['category'] ?? 'Unknown',
            'access_token' => $page['access_token'], // Page access token
        ];
    }

    public function getAccountInsights(Channel $channel): array
    {
        return $this->getAccountAnalytics($channel);
    }

    private function formatPostContent(SocialMediaPost $post): string
    {
        $content = $post->content;
        
        if (!empty($post->hashtags)) {
            $content .= "\n\n" . $this->formatHashtags($post->hashtags);
        }

        return $content;
    }

    private function uploadMultipleMedia(array $media, Channel $channel): array
    {
        $attachedMedia = [];

        foreach ($media as $mediaItem) {
            // Upload each media item and get media ID
            $uploadResponse = $this->uploadSingleMedia($mediaItem, $channel);
            $attachedMedia[] = ['media_fbid' => $uploadResponse['id']];
        }

        return $attachedMedia;
    }

    private function uploadSingleMedia(array $mediaItem, Channel $channel): array
    {
        $pageId = $channel->account_data['id'];
        
        $uploadData = [
            'url' => $mediaItem['url'],
            'published' => false,
            'access_token' => $channel->access_token
        ];

        return $this->makeApiRequest('POST', "https://graph.facebook.com/v18.0/{$pageId}/photos", $uploadData);
    }
}
```

#### SocialMediaProviderFactory (`app/Services/SocialMedia/SocialMediaProviderFactory.php`):

```php
<?php

namespace App\Services\SocialMedia;

class SocialMediaProviderFactory
{
    private static array $providers = [
        'twitter' => TwitterProvider::class,
        'facebook' => FacebookProvider::class,
        'instagram' => InstagramProvider::class,
        'linkedin' => LinkedInProvider::class,
        'youtube' => YouTubeProvider::class,
        'tiktok' => TikTokProvider::class,
    ];

    public static function create(string $platform): AbstractSocialMediaProvider
    {
        if (!isset(self::$providers[$platform])) {
            throw new \InvalidArgumentException("Unsupported platform: {$platform}");
        }

        $providerClass = self::$providers[$platform];
        
        if (!class_exists($providerClass)) {
            throw new \Exception("Provider class not found: {$providerClass}");
        }

        return new $providerClass();
    }

    public static function getSupportedPlatforms(): array
    {
        return array_keys(self::$providers);
    }

    public static function isSupported(string $platform): bool
    {
        return isset(self::$providers[$platform]);
    }

    public static function getAllProviders(): array
    {
        $providers = [];
        
        foreach (self::$providers as $platform => $class) {
            $providers[$platform] = new $class();
        }

        return $providers;
    }

    public static function getProviderInfo(): array
    {
        $info = [];

        foreach (self::$providers as $platform => $class) {
            $provider = new $class();
            $info[$platform] = [
                'platform' => $platform,
                'character_limit' => $provider->getCharacterLimit(),
                'media_limits' => $provider->getMediaLimits(),
                'supported_media_types' => $provider->getSupportedMediaTypes(),
            ];
        }

        return $info;
    }
}
```

#### Service Configuration (`config/services.php` addition):

```php
// Add to config/services.php

'social_media' => [
    'twitter' => [
        'client_id' => env('TWITTER_CLIENT_ID'),
        'client_secret' => env('TWITTER_CLIENT_SECRET'),
        'redirect_uri' => env('TWITTER_REDIRECT_URI'),
        'api_version' => 'v2',
    ],
    'facebook' => [
        'app_id' => env('FACEBOOK_APP_ID'),
        'app_secret' => env('FACEBOOK_APP_SECRET'),
        'redirect_uri' => env('FACEBOOK_REDIRECT_URI'),
        'api_version' => 'v18.0',
    ],
    'instagram' => [
        'client_id' => env('INSTAGRAM_CLIENT_ID'),
        'client_secret' => env('INSTAGRAM_CLIENT_SECRET'),
        'redirect_uri' => env('INSTAGRAM_REDIRECT_URI'),
    ],
    'linkedin' => [
        'client_id' => env('LINKEDIN_CLIENT_ID'),
        'client_secret' => env('LINKEDIN_CLIENT_SECRET'),
        'redirect_uri' => env('LINKEDIN_REDIRECT_URI'),
    ],
    'youtube' => [
        'client_id' => env('YOUTUBE_CLIENT_ID'),
        'client_secret' => env('YOUTUBE_CLIENT_SECRET'),
        'redirect_uri' => env('YOUTUBE_REDIRECT_URI'),
    ],
    'tiktok' => [
        'client_key' => env('TIKTOK_CLIENT_KEY'),
        'client_secret' => env('TIKTOK_CLIENT_SECRET'),
        'redirect_uri' => env('TIKTOK_REDIRECT_URI'),
    ],
],
```

---

## Job Queue System

### ⚡ STEP 13: CREATE JOBS

Generate queue jobs for asynchronous processing:

```bash
php artisan make:job PublishScheduledPost
php artisan make:job CollectAnalytics
php artisan make:job RefreshSocialTokens
php artisan make:job ProcessMediaUpload
php artisan make:job SendNotification
php artisan make:job SyncAccountData
```

#### PublishScheduledPost Job (`app/Jobs/PublishScheduledPost.php`):

```php
<?php

namespace App\Jobs;

use App\Models\SocialMediaPost;
use App\Models\ScheduledPost;
use App\Services\SocialMedia\SocialMediaProviderFactory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Exception;

class PublishScheduledPost implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 300; // 5 minutes

    protected SocialMediaPost $post;
    protected ScheduledPost $scheduledPost;

    public function __construct(SocialMediaPost $post, ScheduledPost $scheduledPost)
    {
        $this->post = $post;
        $this->scheduledPost = $scheduledPost;
    }

    public function handle(): void
    {
        try {
            // Update scheduled post status
            $this->scheduledPost->update([
                'status' => ScheduledPost::STATUS_PROCESSING,
                'last_attempt_at' => now(),
                'attempts' => $this->scheduledPost->attempts + 1
            ]);

            // Load relationships
            $this->post->load(['brand', 'channel', 'creator']);
            
            if (!$this->post->channel) {
                throw new Exception('No channel associated with post');
            }

            // Get social media provider
            $provider = SocialMediaProviderFactory::create($this->post->channel->platform);

            // Validate credentials
            if (!$provider->validateCredentials($this->post->channel)) {
                throw new Exception('Invalid or expired credentials for channel');
            }

            // Validate post content
            $validationErrors = $provider->validatePostContent($this->post);
            if (!empty($validationErrors)) {
                throw new Exception('Post validation failed: ' . implode(', ', $validationErrors));
            }

            // Publish the post
            $result = $provider->publishPost($this->post, $this->post->channel);

            // Update post with platform data
            $this->post->update([
                'status' => SocialMediaPost::STATUS_PUBLISHED,
                'platform_post_id' => $result['platform_post_id'],
                'published_at' => $result['published_at'],
            ]);

            // Update scheduled post
            $this->scheduledPost->update([
                'status' => ScheduledPost::STATUS_COMPLETED,
            ]);

            // Schedule analytics collection
            CollectAnalytics::dispatch($this->post)->delay(now()->addHour());

            Log::info("Post published successfully", [
                'post_id' => $this->post->_id,
                'platform' => $this->post->channel->platform,
                'platform_post_id' => $result['platform_post_id']
            ]);

        } catch (Exception $e) {
            $this->handleFailure($e);
        }
    }

    public function failed(Exception $exception): void
    {
        $this->handleFailure($exception);
    }

    private function handleFailure(Exception $exception): void
    {
        Log::error("Failed to publish scheduled post", [
            'post_id' => $this->post->_id,
            'scheduled_post_id' => $this->scheduledPost->_id,
            'error' => $exception->getMessage(),
            'attempts' => $this->scheduledPost->attempts
        ]);

        // Update scheduled post with error
        $this->scheduledPost->update([
            'status' => ScheduledPost::STATUS_FAILED,
            'error_message' => $exception->getMessage(),
        ]);

        // Update main post status
        $this->post->update([
            'status' => SocialMediaPost::STATUS_FAILED,
        ]);

        // Send notification to user
        SendNotification::dispatch(
            $this->post->creator,
            'post_publish_failed',
            [
                'post_id' => $this->post->_id,
                'error' => $exception->getMessage(),
                'platform' => $this->post->channel->platform ?? 'unknown'
            ]
        );
    }
}
```

#### CollectAnalytics Job (`app/Jobs/CollectAnalytics.php`):

```php
<?php

namespace App\Jobs;

use App\Models\SocialMediaPost;
use App\Models\PostAnalytics;
use App\Services\SocialMedia\SocialMediaProviderFactory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Exception;

class CollectAnalytics implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    protected SocialMediaPost $post;

    public function __construct(SocialMediaPost $post)
    {
        $this->post = $post;
    }

    public function handle(): void
    {
        try {
            // Skip if post is not published or has no platform ID
            if ($this->post->status !== SocialMediaPost::STATUS_PUBLISHED || !$this->post->platform_post_id) {
                return;
            }

            // Load channel relationship
            $this->post->load('channel');
            
            if (!$this->post->channel) {
                throw new Exception('No channel associated with post');
            }

            // Get social media provider
            $provider = SocialMediaProviderFactory::create($this->post->channel->platform);

            // Validate credentials
            if (!$provider->validateCredentials($this->post->channel)) {
                Log::warning("Invalid credentials for analytics collection", [
                    'channel_id' => $this->post->channel->_id,
                    'platform' => $this->post->channel->platform
                ]);
                return;
            }

            // Collect analytics
            $analytics = $provider->getPostAnalytics($this->post->platform_post_id, $this->post->channel);

            // Store analytics data
            PostAnalytics::create([
                'post_id' => $this->post->_id,
                'platform' => $this->post->channel->platform,
                'metrics' => $analytics,
                'collected_at' => now(),
                'period' => 'current'
            ]);

            // Update post engagement data
            $this->post->update([
                'engagement_data' => array_merge($this->post->engagement_data ?? [], $analytics)
            ]);

            Log::info("Analytics collected successfully", [
                'post_id' => $this->post->_id,
                'platform' => $this->post->channel->platform,
                'metrics' => $analytics
            ]);

            // Schedule next collection (every 4 hours for first 24 hours, then daily)
            $nextCollection = $this->post->published_at->diffInHours(now()) < 24 
                ? now()->addHours(4) 
                : now()->addDay();

            self::dispatch($this->post)->delay($nextCollection);

        } catch (Exception $e) {
            Log::error("Failed to collect analytics", [
                'post_id' => $this->post->_id,
                'error' => $e->getMessage()
            ]);
        }
    }
}
```

#### RefreshSocialTokens Job (`app/Jobs/RefreshSocialTokens.php`):

```php
<?php

namespace App\Jobs;

use App\Models\Channel;
use App\Services\SocialMedia\SocialMediaProviderFactory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Exception;

class RefreshSocialTokens implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        // Find channels with tokens expiring in next 24 hours
        $expiringChannels = Channel::where('is_active', true)
            ->where('token_expires_at', '<=', now()->addDay())
            ->where('refresh_token', '!=', null)
            ->get();

        foreach ($expiringChannels as $channel) {
            $this->refreshChannelToken($channel);
        }

        Log::info("Token refresh job completed", [
            'channels_processed' => $expiringChannels->count()
        ]);
    }

    private function refreshChannelToken(Channel $channel): void
    {
        try {
            $provider = SocialMediaProviderFactory::create($channel->platform);
            
            $refreshResult = $provider->refreshToken($channel);
            
            $channel->update([
                'access_token' => $refreshResult['access_token'],
                'refresh_token' => $refreshResult['refresh_token'] ?? $channel->refresh_token,
                'token_expires_at' => $refreshResult['expires_at'],
                'last_sync_at' => now()
            ]);

            Log::info("Token refreshed successfully", [
                'channel_id' => $channel->_id,
                'platform' => $channel->platform
            ]);

        } catch (Exception $e) {
            Log::error("Failed to refresh token", [
                'channel_id' => $channel->_id,
                'platform' => $channel->platform,
                'error' => $e->getMessage()
            ]);

            // Mark channel as inactive if refresh fails multiple times
            if ($channel->token_expires_at < now()->subDays(7)) {
                $channel->update(['is_active' => false]);
                
                SendNotification::dispatch(
                    $channel->brand->organization->creator,
                    'channel_token_expired',
                    [
                        'channel_name' => $channel->account_name,
                        'platform' => $channel->platform,
                        'brand_name' => $channel->brand->name
                    ]
                );
            }
        }
    }
}
```

#### Configure Queue Worker (`config/queue.php` updates):

```php
// Update config/queue.php

'connections' => [
    'redis' => [
        'driver' => 'redis',
        'connection' => 'default',
        'queue' => env('REDIS_QUEUE', 'default'),
        'retry_after' => 90,
        'block_for' => null,
        'after_commit' => false,
    ],
],

// Add to the end of the file
'failed' => [
    'driver' => 'mongodb',
    'table' => 'failed_jobs',
],
```

#### Schedule Jobs (`app/Console/Kernel.php`):

```php
<?php

namespace App\Console;

use App\Jobs\RefreshSocialTokens;
use App\Jobs\CollectAnalytics;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        // Refresh social media tokens daily
        $schedule->job(new RefreshSocialTokens())
            ->dailyAt('02:00')
            ->withoutOverlapping()
            ->runInBackground();

        // Process scheduled posts every minute
        $schedule->command('queue:work --stop-when-empty --max-time=3600')
            ->everyMinute()
            ->withoutOverlapping()
            ->runInBackground();

        // Clean up old analytics data monthly
        $schedule->call(function () {
            \App\Models\PostAnalytics::where('collected_at', '<', now()->subMonths(6))->delete();
        })->monthly();

        // Generate daily reports
        $schedule->command('reports:generate daily')
            ->dailyAt('06:00');
    }

    protected $commands = [
        //
    ];
}
```

---

## Middleware Setup

### ⚡ STEP 14: CREATE MIDDLEWARE

Generate custom middleware for role and subscription management:

```bash
php artisan make:middleware CheckRole
php artisan make:middleware CheckSubscriptionLimits
php artisan make:middleware CheckOrganizationAccess
php artisan make:middleware LogApiRequests
```

#### CheckRole Middleware (`app/Http/Middleware/CheckRole.php`):

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Authentication required'
            ], 401);
        }

        // Check if user has any of the required roles
        if (!in_array($user->role, $roles)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Insufficient permissions'
            ], 403);
        }

        return $next($request);
    }
}
```

#### CheckSubscriptionLimits Middleware (`app/Http/Middleware/CheckSubscriptionLimits.php`):

```php
<?php

namespace App\Http\Middleware;

use App\Models\Organization;
use App\Models\Membership;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckSubscriptionLimits
{
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Authentication required'
            ], 401);
        }

        // Get organization from request or user's active organization
        $organizationId = $request->route('organization') ?? $request->input('organization_id');
        
        if (!$organizationId) {
            // Get user's first active membership
            $membership = Membership::where('user_id', $user->_id)
                ->where('is_active', true)
                ->first();
                
            if (!$membership) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No active organization membership found'
                ], 403);
            }
            
            $organizationId = $membership->organization_id;
        }

        $organization = Organization::find($organizationId);
        
        if (!$organization) {
            return response()->json([
                'status' => 'error',
                'message' => 'Organization not found'
            ], 404);
        }

        // Check subscription status
        if ($organization->subscription_expires_at < now()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Subscription expired',
                'code' => 'SUBSCRIPTION_EXPIRED'
            ], 402);
        }

        // Check feature-specific limits
        if (!$this->checkFeatureLimit($organization, $feature, $request)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Feature limit exceeded',
                'code' => 'LIMIT_EXCEEDED',
                'feature' => $feature
            ], 429);
        }

        return $next($request);
    }

    private function checkFeatureLimit(Organization $organization, string $feature, Request $request): bool
    {
        $limits = $this->getSubscriptionLimits($organization->subscription_plan);
        
        switch ($feature) {
            case 'posts':
                $todayPosts = $organization->brands()
                    ->with('posts')
                    ->get()
                    ->pluck('posts')
                    ->flatten()
                    ->where('created_at', '>=', now()->startOfDay())
                    ->count();
                    
                return $todayPosts < $limits['posts_per_day'];
                
            case 'brands':
                $brandsCount = $organization->brands()->count();
                return $brandsCount < $limits['brands_limit'];
                
            case 'channels':
                $channelsCount = $organization->brands()
                    ->with('channels')
                    ->get()
                    ->pluck('channels')
                    ->flatten()
                    ->count();
                    
                return $channelsCount < $limits['channels_limit'];
                
            case 'users':
                $usersCount = $organization->memberships()
                    ->where('is_active', true)
                    ->count();
                    
                return $usersCount < $limits['users_limit'];
                
            default:
                return true;
        }
    }

    private function getSubscriptionLimits(string $plan): array
    {
        $limits = [
            'free' => [
                'posts_per_day' => 10,
                'brands_limit' => 3,
                'channels_limit' => 5,
                'users_limit' => 2,
                'analytics_retention_days' => 30,
            ],
            'starter' => [
                'posts_per_day' => 50,
                'brands_limit' => 10,
                'channels_limit' => 20,
                'users_limit' => 5,
                'analytics_retention_days' => 90,
            ],
            'professional' => [
                'posts_per_day' => 200,
                'brands_limit' => 25,
                'channels_limit' => 50,
                'users_limit' => 15,
                'analytics_retention_days' => 365,
            ],
            'enterprise' => [
                'posts_per_day' => PHP_INT_MAX,
                'brands_limit' => PHP_INT_MAX,
                'channels_limit' => PHP_INT_MAX,
                'users_limit' => PHP_INT_MAX,
                'analytics_retention_days' => PHP_INT_MAX,
            ],
        ];

        return $limits[$plan] ?? $limits['free'];
    }
}
```

#### Register Middleware (`app/Http/Kernel.php`):

```php
<?php

namespace App\Http;

use Illuminate\Foundation\Http\Kernel as HttpKernel;

class Kernel extends HttpKernel
{
    protected $middleware = [
        \App\Http\Middleware\TrustHosts::class,
        \App\Http\Middleware\TrustProxies::class,
        \Illuminate\Http\Middleware\HandleCors::class,
        \App\Http\Middleware\PreventRequestsDuringMaintenance::class,
        \Illuminate\Foundation\Http\Middleware\ValidatePostSize::class,
        \App\Http\Middleware\TrimStrings::class,
        \Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull::class,
    ];

    protected $middlewareGroups = [
        'web' => [
            \App\Http\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \App\Http\Middleware\VerifyCsrfToken::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ],

        'api' => [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
            \Illuminate\Routing\Middleware\ThrottleRequests::class.':api',
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            \App\Http\Middleware\LogApiRequests::class,
        ],
    ];

    protected $middlewareAliases = [
        'auth' => \App\Http\Middleware\Authenticate::class,
        'auth.basic' => \Illuminate\Auth\Middleware\AuthenticateWithBasicAuth::class,
        'auth.session' => \Laravel\Sanctum\Http\Middleware\AuthenticateSession::class,
        'cache.headers' => \Illuminate\Http\Middleware\SetCacheHeaders::class,
        'can' => \Illuminate\Auth\Middleware\Authorize::class,
        'guest' => \App\Http\Middleware\RedirectIfAuthenticated::class,
        'password.confirm' => \Illuminate\Auth\Middleware\RequirePassword::class,
        'signed' => \App\Http\Middleware\ValidateSignature::class,
        'throttle' => \Illuminate\Routing\Middleware\ThrottleRequests::class,
        'verified' => \Illuminate\Auth\Middleware\EnsureEmailIsVerified::class,
        
        // Custom middleware
        'role' => \App\Http\Middleware\CheckRole::class,
        'subscription' => \App\Http\Middleware\CheckSubscriptionLimits::class,
        'organization' => \App\Http\Middleware\CheckOrganizationAccess::class,
    ];
}
```

---

## Testing & Verification

### ⚡ STEP 15: FINAL TEST

Create comprehensive test route to verify all components:

#### Complete Environment Test (`routes/test.php` update):

```php
<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use App\Models\User;
use App\Services\SocialMedia\SocialMediaProviderFactory;

Route::get('/test-complete-environment', function () {
    $results = [];
    
    try {
        // Test MongoDB connection
        $mongoStatus = DB::connection('mongodb')->getMongoDB()->command(['ping' => 1]);
        $results['mongodb'] = [
            'status' => 'connected',
            'response' => $mongoStatus,
            'database' => config('database.connections.mongodb.database')
        ];
    } catch (\Exception $e) {
        $results['mongodb'] = [
            'status' => 'error',
            'error' => $e->getMessage()
        ];
    }

    try {
        // Test Redis connection
        $redisStatus = Redis::ping();
        $results['redis'] = [
            'status' => 'connected',
            'response' => $redisStatus,
            'host' => config('database.redis.default.host'),
            'port' => config('database.redis.default.port')
        ];
    } catch (\Exception $e) {
        $results['redis'] = [
            'status' => 'error',
            'error' => $e->getMessage()
        ];
    }

    try {
        // Test Models
        $userCount = User::count();
        $results['models'] = [
            'status' => 'working',
            'user_count' => $userCount,
            'collections' => [
                'users' => User::count(),
                'organizations' => \App\Models\Organization::count(),
                'brands' => \App\Models\Brand::count(),
                'channels' => \App\Models\Channel::count(),
                'posts' => \App\Models\SocialMediaPost::count(),
            ]
        ];
    } catch (\Exception $e) {
        $results['models'] = [
            'status' => 'error',
            'error' => $e->getMessage()
        ];
    }

    try {
        // Test Social Media Providers
        $providers = SocialMediaProviderFactory::getSupportedPlatforms();
        $providerInfo = SocialMediaProviderFactory::getProviderInfo();
        $results['providers'] = [
            'status' => 'loaded',
            'supported_platforms' => $providers,
            'provider_details' => $providerInfo
        ];
    } catch (\Exception $e) {
        $results['providers'] = [
            'status' => 'error',
            'error' => $e->getMessage()
        ];
    }

    try {
        // Test Queue Connection
        $queueConnection = config('queue.default');
        $results['queue'] = [
            'status' => 'configured',
            'driver' => $queueConnection,
            'connection' => config("queue.connections.{$queueConnection}")
        ];
    } catch (\Exception $e) {
        $results['queue'] = [
            'status' => 'error',
            'error' => $e->getMessage()
        ];
    }

    try {
        // Test Sanctum Configuration
        $sanctumConfig = config('sanctum');
        $results['authentication'] = [
            'status' => 'configured',
            'sanctum_installed' => class_exists(\Laravel\Sanctum\Sanctum::class),
            'stateful_domains' => $sanctumConfig['stateful'] ?? []
        ];
    } catch (\Exception $e) {
        $results['authentication'] = [
            'status' => 'error',
            'error' => $e->getMessage()
        ];
    }

    // Overall system status
    $hasErrors = collect($results)->contains(function ($result) {
        return isset($result['status']) && $result['status'] === 'error';
    });

    $results['system'] = [
        'status' => $hasErrors ? 'PARTIAL' : 'COMPLETE',
        'timestamp' => now(),
        'laravel_version' => app()->version(),
        'php_version' => phpversion(),
        'environment' => config('app.env')
    ];

    return response()->json($results, $hasErrors ? 206 : 200);
});

// Health check for individual components
Route::get('/health/{component?}', function ($component = null) {
    $healthChecks = [
        'database' => function () {
            DB::connection('mongodb')->getMongoDB()->command(['ping' => 1]);
            return ['status' => 'healthy', 'response_time' => microtime(true)];
        },
        'redis' => function () {
            $start = microtime(true);
            Redis::ping();
            return ['status' => 'healthy', 'response_time' => (microtime(true) - $start) * 1000];
        },
        'queue' => function () {
            // Test job dispatching
            \App\Jobs\HealthCheckJob::dispatch();
            return ['status' => 'healthy', 'jobs_processed' => \Illuminate\Support\Facades\Queue::size()];
        },
        'storage' => function () {
            $testFile = 'health-check-' . now()->timestamp;
            \Illuminate\Support\Facades\Storage::put($testFile, 'test');
            $exists = \Illuminate\Support\Facades\Storage::exists($testFile);
            \Illuminate\Support\Facades\Storage::delete($testFile);
            return ['status' => $exists ? 'healthy' : 'unhealthy'];
        }
    ];

    if ($component && isset($healthChecks[$component])) {
        try {
            $result = $healthChecks[$component]();
            return response()->json([$component => $result]);
        } catch (\Exception $e) {
            return response()->json([
                $component => [
                    'status' => 'unhealthy',
                    'error' => $e->getMessage()
                ]
            ], 503);
        }
    }

    // Run all health checks
    $results = [];
    foreach ($healthChecks as $name => $check) {
        try {
            $results[$name] = $check();
        } catch (\Exception $e) {
            $results[$name] = [
                'status' => 'unhealthy',
                'error' => $e->getMessage()
            ];
        }
    }

    $overallHealthy = collect($results)->every(function ($result) {
        return $result['status'] === 'healthy';
    });

    return response()->json([
        'overall_status' => $overallHealthy ? 'healthy' : 'degraded',
        'components' => $results,
        'timestamp' => now()
    ], $overallHealthy ? 200 : 503);
});
```

#### Test Commands:

```bash
# Start Laravel development server
php artisan serve

# Test complete environment in new terminal
curl http://localhost:8000/test-complete-environment

# Test individual health checks
curl http://localhost:8000/health
curl http://localhost:8000/health/database
curl http://localhost:8000/health/redis
```

**Expected successful response:**
```json
{
    "mongodb": {
        "status": "connected",
        "response": {"ok": 1},
        "database": "social_media_db"
    },
    "redis": {
        "status": "connected",
        "response": "+PONG",
        "host": "127.0.0.1",
        "port": 6379
    },
    "models": {
        "status": "working",
        "user_count": 0,
        "collections": {
            "users": 0,
            "organizations": 0,
            "brands": 0,
            "channels": 0,
            "posts": 0
        }
    },
    "providers": {
        "status": "loaded",
        "supported_platforms": [
            "twitter", "facebook", "instagram", 
            "linkedin", "youtube", "tiktok"
        ]
    },
    "queue": {
        "status": "configured",
        "driver": "redis"
    },
    "authentication": {
        "status": "configured",
        "sanctum_installed": true
    },
    "system": {
        "status": "COMPLETE",
        "timestamp": "2025-08-24T10:30:00.000000Z",
        "laravel_version": "11.x.x",
        "php_version": "8.3.x",
        "environment": "local"
    }
}
```

---

## Production Deployment

### ⚡ PRODUCTION DEPLOYMENT GUIDE

#### Environment Optimization:

```bash
# 1. Install dependencies for production
composer install --optimize-autoloader --no-dev

# 2. Cache configuration
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 3. Generate optimized autoload files
composer dump-autoload --optimize

# 4. Set correct file permissions
chmod -R 755 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
```

#### Production Environment Variables (`.env.production`):

```env
APP_NAME="Social Media Platform"
APP_ENV=production
APP_KEY=base64:YOUR_PRODUCTION_APP_KEY
APP_DEBUG=false
APP_URL=https://your-domain.com

# Database
DB_CONNECTION=mongodb
MONGODB_DSN="mongodb+srv://prod_user:secure_password@prod-cluster.xxxxx.mongodb.net/social_media_prod"
DB_DATABASE=social_media_prod

# Redis (Production instance)
REDIS_HOST=your-redis-host.com
REDIS_PASSWORD=your_redis_password
REDIS_PORT=6379

# Cache & Session
CACHE_DRIVER=redis
SESSION_DRIVER=redis
SESSION_LIFETIME=120
SESSION_SECURE_COOKIE=true

# Queue
QUEUE_CONNECTION=redis

# Logging
LOG_CHANNEL=single
LOG_LEVEL=info

# Security
SESSION_SECURE_COOKIE=true
SANCTUM_STATEFUL_DOMAINS=your-frontend-domain.com

# Social Media API Keys (Production)
TWITTER_CLIENT_ID=your_prod_twitter_client_id
TWITTER_CLIENT_SECRET=your_prod_twitter_secret
FACEBOOK_APP_ID=your_prod_facebook_app_id
FACEBOOK_APP_SECRET=your_prod_facebook_secret
```

#### Nginx Configuration (`/etc/nginx/sites-available/social-media-platform`):

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name your-domain.com www.your-domain.com;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    
    server_name your-domain.com www.your-domain.com;
    root /var/www/social-media-platform/public;
    index index.php;

    # SSL Configuration
    ssl_certificate /etc/letsencrypt/live/your-domain.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/your-domain.com/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers ECDHE-RSA-AES256-GCM-SHA512:DHE-RSA-AES256-GCM-SHA512;
    ssl_prefer_server_ciphers off;

    # Security Headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "no-referrer-when-downgrade" always;
    add_header Content-Security-Policy "default-src 'self' http: https: data: blob: 'unsafe-inline'" always;

    # File Upload Limits
    client_max_body_size 100M;
    client_body_buffer_size 128k;

    # Gzip Compression
    gzip on;
    gzip_vary on;
    gzip_min_length 10240;
    gzip_proxied expired no-cache no-store private must-revalidate auth;
    gzip_types text/plain text/css text/xml text/javascript application/x-javascript application/xml+rss application/json;

    # Handle Laravel routes
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # PHP-FPM Configuration
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_read_timeout 300;
    }

    # Cache static assets
    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
        access_log off;
    }

    # Security: Hide sensitive files
    location ~ /\.(?!well-known).* {
        deny all;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }
}
```

#### Process Management (Supervisor Configuration):

Create `/etc/supervisor/conf.d/social-media-platform.conf`:

```ini
[program:social-media-platform-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/social-media-platform/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=4
redirect_stderr=true
stdout_logfile=/var/www/social-media-platform/storage/logs/worker.log
stopwaitsecs=3600

[program:social-media-platform-scheduler]
process_name=%(program_name)s
command=php /var/www/social-media-platform/artisan schedule:work
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/www/social-media-platform/storage/logs/scheduler.log
```

Start services:
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start all
```

#### Database Indexing (MongoDB):

```javascript
// Connect to MongoDB and create indexes for performance
use social_media_db

// Users collection indexes
db.users.createIndex({ "email": 1 }, { unique: true })
db.users.createIndex({ "last_active_at": 1 })

// Organizations collection indexes
db.organizations.createIndex({ "created_by": 1 })
db.organizations.createIndex({ "subscription_expires_at": 1 })

// Brands collection indexes
db.brands.createIndex({ "organization_id": 1 })
db.brands.createIndex({ "is_active": 1 })

// Channels collection indexes
db.channels.createIndex({ "brand_id": 1 })
db.channels.createIndex({ "platform": 1 })
db.channels.createIndex({ "token_expires_at": 1 })

// Posts collection indexes
db.social_media_posts.createIndex({ "brand_id": 1 })
db.social_media_posts.createIndex({ "channel_id": 1 })
db.social_media_posts.createIndex({ "status": 1 })
db.social_media_posts.createIndex({ "scheduled_at": 1 })
db.social_media_posts.createIndex({ "published_at": 1 })
db.social_media_posts.createIndex({ "created_by": 1 })

// Analytics collection indexes
db.post_analytics.createIndex({ "post_id": 1 })
db.post_analytics.createIndex({ "collected_at": 1 })
db.post_analytics.createIndex({ "platform": 1 })

// Scheduled posts collection indexes
db.scheduled_posts.createIndex({ "scheduled_at": 1 })
db.scheduled_posts.createIndex({ "status": 1 })

// Memberships collection indexes
db.memberships.createIndex({ "user_id": 1 })
db.memberships.createIndex({ "organization_id": 1 })
db.memberships.createIndex({ "is_active": 1 })
```

#### Monitoring & Logging Setup:

**Create logging configuration (`config/logging.php` update):**

```php
'channels' => [
    'stack' => [
        'driver' => 'stack',
        'channels' => ['single', 'slack'],
        'ignore_exceptions' => false,
    ],

    'single' => [
        'driver' => 'single',
        'path' => storage_path('logs/laravel.log'),
        'level' => env('LOG_LEVEL', 'debug'),
    ],

    'slack' => [
        'driver' => 'slack',
        'url' => env('LOG_SLACK_WEBHOOK_URL'),
        'username' => 'Social Media Platform',
        'emoji' => ':boom:',
        'level' => 'critical',
    ],

    'social_media' => [
        'driver' => 'single',
        'path' => storage_path('logs/social-media.log'),
        'level' => 'info',
    ],

    'analytics' => [
        'driver' => 'single',
        'path' => storage_path('logs/analytics.log'),
        'level' => 'info',
    ],
],
```

#### Performance Optimization:

**Enable OPcache (`/etc/php/8.3/fpm/conf.d/10-opcache.ini`):**

```ini
opcache.enable=1
opcache.enable_cli=1
opcache.memory_consumption=256
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=10000
opcache.max_wasted_percentage=10
opcache.use_cwd=1
opcache.validate_timestamps=1
opcache.revalidate_freq=2
opcache.save_comments=1
```

**PHP-FPM Optimization (`/etc/php/8.3/fpm/pool.d/www.conf`):**

```ini
pm = dynamic
pm.max_children = 50
pm.start_servers = 10
pm.min_spare_servers = 5
pm.max_spare_servers = 35
pm.max_requests = 500
```

---

## Troubleshooting

### Common Issues and Solutions:

#### 1. MongoDB Connection Issues:

**Problem:** `Connection timeout` or `Authentication failed`

**Solutions:**
```bash
# Check MongoDB extension
php -m | grep mongodb

# Verify connection string format
# Correct: mongodb+srv://user:pass@cluster.mongodb.net/db
# Check IP whitelist in MongoDB Atlas (0.0.0.0/0 for development)

# Test connection manually
php artisan tinker
>>> DB::connection('mongodb')->getMongoDB()->command(['ping' => 1]);
```

#### 2. Redis Connection Issues:

**Problem:** `Connection refused` or `Redis server went away`

**Solutions:**
```bash
# Check Redis container status
docker ps | grep redis

# Restart Redis container
docker restart social-media-redis

# Check Redis logs
docker logs social-media-redis

# Test Redis connection
redis-cli ping
```

#### 3. Queue Jobs Not Processing:

**Problem:** Jobs stuck in `waiting` status

**Solutions:**
```bash
# Start queue worker manually
php artisan queue:work redis --verbose

# Check failed jobs
php artisan queue:failed

# Retry failed jobs
php artisan queue:retry all

# Clear all jobs and restart
php artisan queue:flush
php artisan queue:restart
```

#### 4. Social Media API Errors:

**Problem:** `Invalid credentials` or `Rate limit exceeded`

**Solutions:**
- Verify API keys in `.env` file
- Check token expiration dates
- Implement rate limiting in providers
- Use exponential backoff for failed requests

#### 5. File Permission Issues:

**Problem:** `Permission denied` for storage or cache

**Solutions:**
```bash
# Set correct permissions
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache

# Clear all caches
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

---

## ✅ FINAL CHECKLIST

### Development Environment:
- ✅ Laravel 11 installed and running
- ✅ MongoDB extension installed and configured
- ✅ MongoDB Atlas connected successfully
- ✅ Redis Docker container running
- ✅ 9 Models created with proper relationships
- ✅ 8+ Controllers built with full CRUD operations
- ✅ Laravel Sanctum authentication configured
- ✅ 6 Social media providers implemented
- ✅ Queue jobs ready for background processing
- ✅ API routes properly organized and protected
- ✅ Middleware for role and subscription management
- ✅ Comprehensive testing routes working

### Production Readiness:
- ✅ Environment variables optimized
- ✅ Nginx configuration with SSL
- ✅ Database indexing implemented
- ✅ Queue workers supervised
- ✅ Logging and monitoring configured
- ✅ Security headers and HTTPS enabled
- ✅ Performance optimizations applied
- ✅ Error handling and notifications setup

### Key Features Implemented:
- ✅ Multi-tenant organization system
- ✅ Brand and channel management
- ✅ Post scheduling and publishing
- ✅ Real-time analytics collection
- ✅ Role-based permissions
- ✅ Subscription limit enforcement
- ✅ Social media platform integrations
- ✅ Queue-based job processing
- ✅ Comprehensive API with authentication

---

## 🎉 COMPLETION SUMMARY

**Total Setup Time:** 2-3 hours  
**Result:** Production-ready social media management platform  
**Architecture:** Modern, scalable, and maintainable  

### What You've Built:

1. **Robust Backend API** with Laravel 11
2. **NoSQL Database** with MongoDB Atlas
3. **High-Performance Caching** with Redis
4. **Social Media Integrations** for 6+ platforms
5. **Asynchronous Job Processing** for reliability
6. **Multi-Tenant Architecture** for scalability
7. **Comprehensive Analytics** system
8. **Production-Ready Deployment** configuration

### Next Steps:

1. **Frontend Development** (React/Vue.js/Angular)
2. **Mobile App** development
3. **Advanced Analytics** and reporting
4. **AI-Powered Content** suggestions
5. **Team Collaboration** features
6. **Advanced Scheduling** algorithms
7. **Social Listening** capabilities
8. **White-Label** solutions

### Support Resources:

- **Laravel Documentation:** https://laravel.com/docs
- **MongoDB Laravel Package:** https://github.com/mongodb/laravel-mongodb
- **Social Media APIs:** Platform-specific documentation
- **Redis Documentation:** https://redis.io/docs
- **Production Deployment:** DigitalOcean, AWS, or similar


**Built with ❤️ using Laravel 11, MongoDB, Redis, and Vue.js for easy deployment and scaling**