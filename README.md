# Social Media Marketing Platform

A comprehensive online Social Media Marketing Platform for planning, drafting, scheduling, publishing, and analyzing posts across multiple social channels (Twitter/X, Facebook, Instagram, LinkedIn). Built as an easy-to-manage and deploy online platform with role-based access control.

## 🚀 Project Overview

This platform enables brands and teams to collaborate on social content with role-based access control, manage multiple social channels, create and schedule posts, and track performance analytics. Built with Laravel 11, MongoDB, Redis, and Vue.js with a pluggable provider-connector architecture.

### Key Features

- **Multi-brand Management**: Organizations and brands with role-based access (OWNER/MANAGER/EDITOR/VIEWER)
- **Channel Integration**: Connect social accounts via OAuth with secure encrypted token storage
- **Content Planning**: Create drafts, attach media, schedule per-channel publishing
- **Publishing Engine**: Queue-based reliable publishing with retries, exponential backoff, and idempotency
- **Analytics Dashboard**: Store and display basic metrics (impressions, likes, comments, shares) with CSV exports
- **Calendar View**: Visual content planning and scheduling interface with timezone awareness
- **Activity Logging**: Comprehensive audit trail for all actions
- **Notification System**: Email alerts for publishing success/failure and daily summaries
- **Easy Deployment**: MongoDB-based architecture for simple cloud deployment

## 🛠 Technology Stack

### Backend
- **Framework**: Laravel 11
- **PHP Version**: 8.2+
- **Database**: MongoDB 7.0+ (Cloud-ready with Atlas)
- **Cache/Queue**: Redis 7.0+
- **Storage**: Local/S3 compatible via spatie/laravel-medialibrary
- **Mail**: SMTP for notifications

### Frontend
- **Framework**: Vue.js 3 with Inertia.js
- **Build Tool**: Vite
- **CSS Framework**: Tailwind CSS
- **UI Components**: Headless UI
- **Charts**: Chart.js

### Key Packages

```json
{
  "mongodb/laravel-mongodb": "^4.2",
  "spatie/laravel-permission": "^6.0",
  "spatie/laravel-activitylog": "^4.0", 
  "spatie/laravel-medialibrary": "^11.0",
  "laravel/breeze": "^2.0",
  "inertiajs/inertia-laravel": "^1.0",
  "league/oauth2-client": "^2.7"
}
```

## 📁 Project Structure

```
social-media-platform/
├── app/
│   ├── Console/
│   │   ├── Commands/
│   │   │   ├── ProcessScheduledPosts.php
│   │   │   ├── SyncAnalytics.php
│   │   │   ├── SendDailySummary.php
│   │   │   └── RefreshTokens.php
│   │   └── Kernel.php
│   ├── Events/
│   │   ├── PostPublished.php
│   │   ├── PostFailed.php
│   │   ├── ChannelConnected.php
│   │   └── UserInvited.php
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Api/
│   │   │   │   ├── BrandController.php
│   │   │   │   ├── ChannelController.php
│   │   │   │   ├── PostController.php
│   │   │   │   ├── ScheduleController.php
│   │   │   │   ├── MediaController.php
│   │   │   │   ├── AnalyticsController.php
│   │   │   │   └── ReportController.php
│   │   │   ├── Auth/
│   │   │   │   └── OAuthController.php
│   │   │   ├── DashboardController.php
│   │   │   ├── BrandController.php
│   │   │   ├── ChannelController.php
│   │   │   ├── PostController.php
│   │   │   ├── CalendarController.php
│   │   │   └── AnalyticsController.php
│   │   ├── Middleware/
│   │   │   ├── EnsureBrandAccess.php
│   │   │   └── CheckRole.php
│   │   ├── Requests/
│   │   │   ├── StoreBrandRequest.php
│   │   │   ├── StoreChannelRequest.php
│   │   │   ├── StorePostRequest.php
│   │   │   ├── SchedulePostRequest.php
│   │   │   └── InviteUserRequest.php
│   │   └── Resources/
│   │       ├── BrandResource.php
│   │       ├── ChannelResource.php
│   │       ├── PostResource.php
│   │       ├── ScheduleResource.php
│   │       └── AnalyticsResource.php
│   ├── Jobs/
│   │   ├── PublishPost.php
│   │   ├── SyncPostAnalytics.php
│   │   ├── RefreshChannelTokens.php
│   │   └── SendDailySummary.php
│   ├── Mail/
│   │   ├── PublishSuccessMail.php
│   │   ├── PublishFailureMail.php
│   │   └── DailySummaryMail.php
│   ├── Models/
│   │   ├── Organization.php
│   │   ├── Brand.php
│   │   ├── User.php
│   │   ├── Membership.php
│   │   ├── Channel.php
│   │   ├── Post.php
│   │   ├── Schedule.php
│   │   └── Analytics.php
│   ├── Policies/
│   │   ├── BrandPolicy.php
│   │   ├── ChannelPolicy.php
│   │   ├── PostPolicy.php
│   │   └── SchedulePolicy.php
│   └── Services/
│       ├── PublisherService.php
│       ├── AnalyticsService.php
│       ├── ProviderAdapterFactory.php
│       └── Providers/
│           ├── ProviderAdapterInterface.php
│           ├── AbstractProviderAdapter.php
│           ├── TwitterAdapter.php
│           ├── FacebookAdapter.php
│           ├── InstagramAdapter.php
│           ├── LinkedInAdapter.php
│           └── StubAdapter.php
└── resources/
    └── js/
        ├── Components/
        │   ├── Calendar.vue
        │   ├── PostEditor.vue
        │   ├── MediaUploader.vue
        │   └── AnalyticsChart.vue
        └── Pages/
            ├── Dashboard.vue
            ├── Brands/
            ├── Channels/
            ├── Posts/
            ├── Calendar/
            └── Analytics/
```

## 🗄 Data Model (MongoDB Collections)

### Organizations
```javascript
{
  _id: ObjectId,
  name: string,
  settings: {
    default_timezone: "UTC",
    features: ["analytics", "scheduling", "multi_brand"]
  },
  created_at: Date,
  updated_at: Date
}
```

### Brands
```javascript
{
  _id: ObjectId,
  organization_id: ObjectId,
  name: string,
  slug: string,
  settings: {
    timezone: "UTC",
    default_publish_time: "09:00",
    branding: {
      logo_url: string,
      primary_color: "#1DA1F2"
    }
  },
  active: boolean,
  created_at: Date,
  updated_at: Date,
  deleted_at: Date // Soft delete
}
```

### Users & Memberships
```javascript
// Users Collection
{
  _id: ObjectId,
  name: string,
  email: string,
  email_verified_at: Date,
  password: string,
  profile: {
    avatar_url: string,
    timezone: "UTC",
    notification_preferences: {
      email: boolean,
      browser: boolean,
      daily_summary: boolean
    }
  },
  created_at: Date,
  updated_at: Date
}

// Memberships Collection
{
  _id: ObjectId,
  user_id: ObjectId,
  brand_id: ObjectId,
  role: "OWNER" | "MANAGER" | "EDITOR" | "VIEWER",
  permissions: [string],
  invited_by: ObjectId,
  joined_at: Date,
  created_at: Date,
  updated_at: Date
}
```

### Channels
```javascript
{
  _id: ObjectId,
  brand_id: ObjectId,
  provider: "TWITTER" | "FACEBOOK" | "INSTAGRAM" | "LINKEDIN" | "STUB",
  handle: string,
  display_name: string,
  avatar_url: string,
  oauth_tokens: {
    access_token: string,    // encrypted
    refresh_token: string,   // encrypted
    expires_at: Date,
    scope: [string],
    token_type: "Bearer"
  },
  provider_constraints: {
    max_characters: number,
    max_media: number,
    supported_media_types: [string],
    rate_limits: {
      posts_per_hour: number,
      posts_per_day: number
    }
  },
  connection_status: "CONNECTED" | "EXPIRED" | "ERROR" | "DISABLED",
  last_sync_at: Date,
  active: boolean,
  created_at: Date,
  updated_at: Date
}
```

### Posts
```javascript
{
  _id: ObjectId,
  brand_id: ObjectId,
  user_id: ObjectId,
  title: string,
  body: string,
  status: "DRAFT" | "SCHEDULED" | "PUBLISHED" | "FAILED",
  
  // Embedded media - no separate collection needed!
  media_attachments: [{
    path: string,
    mime_type: string,
    size_kb: number,
    alt_text: string,
    thumbnail_path: string
  }],
  
  // Embedded scheduling info
  schedules: [{
    channel_id: ObjectId,
    scheduled_for: Date,
    status: "PENDING" | "SUCCESS" | "FAILED",
    result_message: string,
    external_post_id: string,
    published_at: Date,
    idempotency_key: string,
    retry_count: number
  }],
  
  // Provider-specific data
  provider_data: {
    twitter: {
      external_id: string,
      thread_data: object
    },
    facebook: {
      external_id: string,
      page_id: string
    }
  },
  
  // Embedded analytics - real-time updates!
  analytics: {
    impressions: number,
    likes: number,
    comments: number,
    shares: number,
    clicks: number,
    engagement_rate: number,
    last_synced_at: Date
  },
  
  published_at: Date,
  created_at: Date,
  updated_at: Date
}
```

### Analytics (Historical Data)
```javascript
{
  _id: ObjectId,
  post_id: ObjectId,
  channel_id: ObjectId,
  provider: string,
  external_post_id: string,
  
  // Time-series metrics
  metrics: {
    impressions: number,
    likes: number,
    comments: number,
    shares: number,
    clicks: number,
    engagement_rate: number
  },
  
  // Demographic data (if available)
  demographics: {
    age_groups: object,
    locations: object,
    devices: object
  },
  
  recorded_at: Date, // When metrics were recorded
  as_of: Date,      // Metrics are for this date
  created_at: Date,
  updated_at: Date
}
```

## 👥 User Roles & Permissions

### Role Hierarchy (spatie/laravel-permission + MongoDB)
- **OWNER**: Full access to brand management, user roles, and all features
- **MANAGER**: Manage brand content, channels, approve schedules, view analytics
- **EDITOR**: Create/edit posts, upload media, propose schedules
- **VIEWER**: Read-only access to calendar and analytics

### Key Permissions
- `brands.manage`: Create/update/delete brands
- `channels.manage`: Connect/disconnect social channels
- `posts.create`: Create new posts and drafts
- `posts.publish`: Approve and publish scheduled posts
- `schedules.manage`: Create and modify schedules
- `analytics.view`: Access analytics and reports
- `users.invite`: Invite new users to brand

## 🔧 Installation & Setup

### Prerequisites
- PHP 8.2+
- Composer
- Node.js 18+
- MongoDB 7.0+ (or MongoDB Atlas)
- Redis 7.0+

### Step 1: Clone Repository
```bash
git clone https://github.com/your-org/social-media-platform.git
cd social-media-platform
```

### Step 2: Install Dependencies
```bash
# Install PHP dependencies
composer install

# Install Node.js dependencies
npm install
```

### Step 3: Environment Configuration
```bash
# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate
```

### Step 4: Configure Environment Variables
```ENV
APP_NAME="Social Media Marketing Platform"
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost:8000

# MongoDB Atlas Configuration
DB_CONNECTION=mongodb
DB_DSN=mongodb+srv://

# Redis Configuration
REDIS_CLIENT=predis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
REDIS_DB=0
REDIS_CACHE_DB=1
REDIS_SESSION_DB=2

# Cache and Session (Redis-powered)
CACHE_STORE=redis
CACHE_PREFIX=smp_cache

# Session Configuration (Redis)
SESSION_DRIVER=redis
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=null

# Queue Configuration (Redis)
QUEUE_CONNECTION=redis

# Broadcasting (Redis)
BROADCAST_CONNECTION=redis

# Mail Configuration
MAIL_MAILER=log
MAIL_FROM_ADDRESS="hello@socialmedia.local"
MAIL_FROM_NAME="${APP_NAME}"

# File Storage
FILESYSTEM_DISK=local

# Logging
LOG_CHANNEL=stack
LOG_LEVEL=debug

# Social Media API Keys (Phase 3)
TWITTER_CLIENT_ID=
TWITTER_CLIENT_SECRET=
FACEBOOK_CLIENT_ID=
FACEBOOK_CLIENT_SECRET=
INSTAGRAM_CLIENT_ID=
INSTAGRAM_CLIENT_SECRET=
LINKEDIN_CLIENT_ID=
LINKEDIN_CLIENT_SECRET=
```

### Step 5: Database Setup
```bash
# MongoDB will create database automatically
# Run migrations (MongoDB collections will be created)
php artisan migrate

# Seed database with sample data
php artisan db:seed
```

### Step 6: Storage Setup
```bash
# Create storage link
php artisan storage:link

# Set permissions (Linux/Mac)
chmod -R 755 storage
chmod -R 755 bootstrap/cache
```

### Step 7: Build Frontend Assets
```bash
# Development
npm run dev

# Production
npm run build
```

### Step 8: Start Services
```bash
# Start Laravel development server
php artisan serve

# Start queue worker (in separate terminal)
php artisan queue:work

# Start scheduler (in separate terminal)
php artisan schedule:work
```

## 🚀 Cloud Deployment Options

### **Option 1: MongoDB Atlas + Heroku**
```bash
# Easy 1-click deployment
git push heroku main

# MongoDB Atlas provides free tier (512MB)
# Redis via Heroku Redis add-on
# No server management needed!
```

### **Option 2: DigitalOcean App Platform**
```yaml
# app.yaml
name: social-media-platform
services:
- name: web
  source_dir: /
  github:
    repo: your-org/social-media-platform
    branch: main
  run_command: php artisan serve --host=0.0.0.0
  environment_slug: php
  instance_count: 1
  instance_size_slug: basic-xxs
  
databases:
- name: mongodb
  engine: MONGODB
  version: "7"
```

### **Option 3: Laravel Forge + MongoDB Atlas**
```bash
# Professional deployment with server management
# MongoDB Atlas for database (managed)
# Automatic deployments from Git
# SSL certificates included
```

## 🧪 Testing

### Run All Tests
```bash
# PHPUnit/Pest tests with MongoDB
php artisan test

# With coverage
php artisan test --coverage
```

### MongoDB-Specific Testing
```bash
# Test with in-memory MongoDB (faster)
php artisan test --env=testing

# Test provider adapters
php artisan test tests/Unit/Services/ProviderAdapterTest.php

# Test embedded documents
php artisan test tests/Feature/PostEmbeddedDataTest.php
```

### Code Quality Tools
```bash
# Laravel Pint (PSR-12 formatting)
./vendor/bin/pint

# PHPStan (static analysis)
./vendor/bin/phpstan analyse

# Run all quality checks
composer run quality
```

## 📊 Why MongoDB is Perfect for This Platform

### **1. Schema Flexibility**
```javascript
// Add new social platform without migrations
post.provider_data.tiktok = {
  external_id: "123456",
  video_id: "video_789"
}
```

### **2. Embedded Documents**
```javascript
// No JOINs needed - everything in one query
db.posts.find({
  "brand_id": ObjectId("..."),
  "schedules.status": "PENDING",
  "schedules.scheduled_for": { $lt: new Date() }
})
```

### **3. Real-time Analytics**
```javascript
// Update analytics directly in post document
db.posts.updateOne(
  { "_id": ObjectId("...") },
  { 
    $set: {
      "analytics.likes": 150,
      "analytics.last_synced_at": new Date()
    }
  }
)
```

### **4. Easy Scaling**
- **Horizontal scaling** with sharding
- **MongoDB Atlas** handles scaling automatically
- **Replica sets** for high availability
- **Cloud-native** architecture

## 🔌 Provider Adapters

### Supported Providers
- **Twitter/X**: Full implementation with v2 API
- **Facebook**: Basic post publishing via Graph API
- **Instagram**: Image/video posts via Graph API  
- **LinkedIn**: Company page posting via API
- **STUB**: Testing and development provider (fully functional)

### Provider Interface
```php
<?php

interface ProviderAdapterInterface
{
    public function authenticate(array $credentials): AuthResult;
    public function publishPost(string $content, array $media = []): PublishResult;
    public function getAnalytics(string $externalPostId): AnalyticsResult;
    public function getConstraints(): ProviderConstraints;
    public function refreshToken(string $refreshToken): TokenResult;
}
```

### MongoDB-Optimized Provider Implementation
```php
<?php

class TwitterAdapter extends AbstractProviderAdapter
{
    public function publishPost(string $content, array $media = []): PublishResult
    {
        // Publish to Twitter API
        $response = $this->twitter->post('tweets', [
            'text' => $content,
            'media' => $media
        ]);
        
        // Return result with external_post_id for MongoDB storage
        return new PublishResult(
            success: true,
            externalPostId: $response['data']['id'],
            publishedAt: now(),
            providerData: [
                'tweet_url' => "https://twitter.com/i/web/status/{$response['data']['id']}",
                'author_id' => $response['data']['author_id']
            ]
        );
    }
}
```

## 📚 API Endpoints

### Authentication
All API endpoints require authentication via Laravel Sanctum with MongoDB session storage.

### Core Endpoints

#### Brands
```http
GET    /api/brands              # List user's brands
POST   /api/brands              # Create new brand  
GET    /api/brands/{id}         # Get brand details
PUT    /api/brands/{id}         # Update brand
DELETE /api/brands/{id}         # Soft delete brand
```

#### Channels  
```http
GET    /api/brands/{id}/channels       # List brand channels
POST   /api/brands/{id}/channels       # Create channel
POST   /api/channels/{id}/connect      # Start OAuth flow
GET    /api/channels/{id}/test         # Test connection
PUT    /api/channels/{id}              # Update channel settings
DELETE /api/channels/{id}              # Delete channel
```

#### Posts & Scheduling
```http
GET    /api/brands/{id}/posts          # List posts with embedded data
POST   /api/brands/{id}/posts          # Create post with media
GET    /api/posts/{id}                 # Get post with analytics
PUT    /api/posts/{id}                 # Update post
DELETE /api/posts/{id}                 # Delete post
POST   /api/posts/{id}/schedule        # Add schedule to post
PUT    /api/posts/{id}/schedules/{scheduleId} # Update schedule
```

#### Analytics (Real-time with MongoDB)
```http
GET    /api/posts/{id}/analytics       # Get embedded analytics
GET    /api/analytics/summary          # Aggregated analytics
POST   /api/analytics/sync             # Sync all provider analytics
GET    /api/analytics/export           # Export CSV with aggregation
```

### MongoDB Query Examples
```javascript
// Dashboard - get pending posts with analytics
db.posts.aggregate([
  {
    $match: {
      "brand_id": ObjectId("..."),
      "schedules.status": "PENDING"
    }
  },
  {
    $project: {
      title: 1,
      body: 1,
      "analytics.engagement_rate": 1,
      "schedules.$": 1
    }
  }
])
```

## 🔒 Security & Compliance

### MongoDB Security
- **Authentication** enabled by default
- **OAuth tokens encrypted** using Laravel encryption
- **Connection via TLS** in production
- **Role-based access** with MongoDB users
- **Audit logging** built into MongoDB

### Data Protection
- **Field-level encryption** for sensitive data
- **GDPR compliance** with document deletion
- **Backup strategies** with point-in-time recovery
- **Data retention policies** via TTL indexes

## 🎯 Performance Optimization

### MongoDB Indexes
```javascript
// Essential indexes for performance
db.posts.createIndex({ "brand_id": 1, "created_at": -1 })
db.posts.createIndex({ "schedules.scheduled_for": 1, "schedules.status": 1 })
db.posts.createIndex({ "status": 1, "published_at": -1 })
db.analytics.createIndex({ "post_id": 1, "as_of": -1 })
```

### Aggregation Pipeline Examples
```javascript
// Analytics dashboard - much faster than SQL JOINs
db.posts.aggregate([
  {
    $match: { 
      "brand_id": ObjectId("..."),
      "published_at": { $gte: new Date("2024-01-01") }
    }
  },
  {
    $group: {
      _id: null,
      total_posts: { $sum: 1 },
      total_likes: { $sum: "$analytics.likes" },
      avg_engagement: { $avg: "$analytics.engagement_rate" }
    }
  }
])
```

## 📈 Monitoring & Maintenance

### MongoDB Atlas Monitoring
- **Real-time performance** metrics
- **Automatic scaling** based on load
- **Backup and restore** automated
- **Security alerts** and monitoring
- **Query optimization** suggestions

### Application Monitoring
```bash
# Queue metrics
php artisan queue:monitor

# MongoDB connection health
php artisan mongodb:status

# Provider API health checks
php artisan providers:health-check
```

## 🤝 Contributing

### MongoDB Development Guidelines
- Use **Eloquent MongoDB models** for consistency
- **Embed related data** when it makes sense (1:few relationships)
- **Reference data** for 1:many relationships
- **Use aggregation pipelines** for complex queries
- **Index frequently queried fields**

### Testing with MongoDB
```php
<?php

class PostTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_post_with_embedded_media()
    {
        $post = Post::factory()->create([
            'media_attachments' => [
                [
                    'path' => '/storage/image.jpg',
                    'mime_type' => 'image/jpeg',
                    'size_kb' => 150
                ]
            ]
        ]);

        $this->assertCount(1, $post->media_attachments);
    }
}
```

## 📊 Deployment Comparison

| Feature | MongoDB | MySQL |
|---------|---------|-------|
| **Cloud Deployment** | ✅ Atlas (free 512MB) | ❌ Requires paid hosting |
| **Scaling** | ✅ Automatic horizontal | ❌ Manual vertical only |
| **Schema Changes** | ✅ No migrations needed | ❌ Complex migrations |
| **JSON Handling** | ✅ Native support | ⚠️ Limited JSON columns |
| **Development Speed** | ✅ Faster iterations | ❌ Slower schema changes |
| **Maintenance** | ✅ Minimal (Atlas managed) | ❌ More maintenance needed |

## 📞 Support & Contact

### Getting Help
- **MongoDB Community**: Extensive documentation and community support
- **Laravel MongoDB**: Well-maintained package with active community
- **Cloud Support**: MongoDB Atlas provides 24/7 support
- **GitHub Issues**: Create detailed bug reports with MongoDB queries

---

**Built with ❤️ using Laravel 11, MongoDB, Redis, and Vue.js for easy deployment and scaling**
