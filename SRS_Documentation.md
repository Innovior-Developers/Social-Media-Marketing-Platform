# Social Media Marketing Platform

A comprehensive web application for managing multiple social media accounts, creating content, scheduling posts, and analyzing performance across Facebook, Instagram, Twitter/X, LinkedIn, and YouTube.

## 🚀 Project Overview

**Duration**: 30 Days (Aug 26 - Sep 26, 2025)  
**Status**: Ready for Development  
**Architecture**: Laravel 11 + Vue.js 3 + MongoDB  

### Key Features

- **Multi-Platform Management** - Centralized control for 5+ social media platforms
- **Team Collaboration** - Role-based access control with 4 permission levels
- **Content Scheduling** - Advanced calendar-based scheduling system
- **Analytics Dashboard** - Real-time performance metrics and reporting
- **OAuth Integration** - Secure social media account connections
- **Queue-Based Publishing** - Reliable background job processing

## 🛠️ Technology Stack

### Backend
- **Framework**: Laravel 11.x
- **Language**: PHP 8.2+
- **Database**: MongoDB 7.0+
- **Cache/Queue**: Redis 7.0+
- **Authentication**: Laravel Sanctum

### Frontend
- **Framework**: Vue.js 3.x
- **Router**: Inertia.js 1.x
- **Styling**: Tailwind CSS 3.x
- **Build Tool**: Vite 4.x
- **Charts**: Chart.js 4.x

### Infrastructure
- **Web Server**: Nginx/Apache
- **Cloud**: AWS/DigitalOcean/Heroku
- **CDN**: CloudFlare
- **SSL**: Let's Encrypt

## 📋 System Requirements

### Development Environment
```bash
PHP >= 8.2
MongoDB >= 7.0
Redis >= 7.0
Node.js >= 18.x
Composer >= 2.x
```

### Production Environment
- **Memory**: 2GB RAM minimum, 4GB recommended
- **Storage**: 20GB minimum, SSD recommended
- **Network**: HTTPS/TLS 1.3 support required

## 🏗️ Architecture

### System Architecture
```
┌─────────────────┐    ┌──────────────────┐    ┌─────────────────┐
│   Frontend      │    │    Backend       │    │   External APIs │
│   Vue.js 3      │◄──►│   Laravel 11     │◄──►│  Social Media   │
│   Inertia.js    │    │   PHP 8.2+       │    │   Platforms     │
└─────────────────┘    └──────────────────┘    └─────────────────┘
         │                       │                       │
         ▼                       ▼                       ▼
┌─────────────────┐    ┌──────────────────┐    ┌─────────────────┐
│    Browser      │    │    Database      │    │   File Storage  │
│   Modern Web    │    │   MongoDB        │    │   AWS S3/DO     │
│   Browsers      │    │   Redis Cache    │    │   Spaces        │
└─────────────────┘    └──────────────────┘    └─────────────────┘
```

### Provider Architecture
The system uses a pluggable provider pattern for easy integration of new social media platforms:

```php
interface SocialProviderInterface {
    public function authenticate(): string;
    public function publishPost(Post $post): PublishResult;
    public function getAnalytics(string $postId): Analytics;
    public function validateConnection(): bool;
}
```

## 🗄️ Database Schema

### Core Collections

#### Users Collection
```javascript
{
  _id: ObjectId,
  name: String,
  email: String (unique),
  password: String (hashed),
  profile: Object,
  notifications: Object,
  created_at: Date,
  updated_at: Date
}
```

#### Organizations Collection
```javascript
{
  _id: ObjectId,
  name: String,
  slug: String (unique),
  settings: Object,
  subscription: Object,
  owner_id: ObjectId,
  created_at: Date,
  updated_at: Date
}
```

#### Brands Collection
```javascript
{
  _id: ObjectId,
  organization_id: ObjectId,
  name: String,
  description: String,
  settings: Object,
  timezone: String,
  status: String,
  created_at: Date,
  updated_at: Date
}
```

#### Posts Collection (with Embedded Analytics)
```javascript
{
  _id: ObjectId,
  brand_id: ObjectId,
  title: String,
  content: String,
  media_attachments: Array,
  schedules: [{
    platform: String,
    scheduled_at: Date,
    status: String,
    published_at: Date
  }],
  analytics: [{
    platform: String,
    metrics: Object,
    recorded_at: Date
  }],
  created_at: Date,
  updated_at: Date
}
```

## 🚦 API Endpoints

### Authentication
```
POST   /api/v1/auth/login
POST   /api/v1/auth/logout
POST   /api/v1/auth/refresh
```

### Brand Management
```
GET    /api/v1/brands
POST   /api/v1/brands
PUT    /api/v1/brands/{id}
DELETE /api/v1/brands/{id}
```

### Channel Management
```
POST   /api/v1/channels/connect/{provider}
GET    /api/v1/channels/{id}/test
PUT    /api/v1/channels/{id}
DELETE /api/v1/channels/{id}
```

### Post Management
```
GET    /api/v1/posts
POST   /api/v1/posts
PUT    /api/v1/posts/{id}
DELETE /api/v1/posts/{id}
POST   /api/v1/posts/{id}/publish
```

### Analytics
```
GET    /api/v1/analytics/dashboard
GET    /api/v1/analytics/reports
POST   /api/v1/analytics/sync
```

### Calendar
```
GET    /api/v1/calendar
PUT    /api/v1/calendar/reschedule
```

## 👥 User Roles & Permissions

| Role | Connect Channels | Create Posts | Publish Posts | View Analytics | Invite Users | Manage Brand |
|------|------------------|--------------|---------------|----------------|--------------|--------------|
| **Owner** | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| **Manager** | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ |
| **Editor** | ❌ | ✅ | ❌ | ✅ | ❌ | ❌ |
| **Viewer** | ❌ | ❌ | ❌ | ✅ | ❌ | ❌ |

## 🔌 Social Media Integrations

### Supported Platforms

#### Facebook
- **API Version**: Graph API v18.0
- **Permissions**: `pages_manage_posts`, `pages_read_engagement`
- **Rate Limits**: 200 calls/hour per user
- **Content Limits**: 63,206 characters, 10 media attachments

#### Instagram
- **API Version**: Instagram Basic Display API v16.0
- **Permissions**: `instagram_basic`, `instagram_content_publish`
- **Rate Limits**: 200 calls/hour per user
- **Content Limits**: 2,200 characters, 1 media attachment

#### Twitter/X
- **API Version**: Twitter API v2
- **Permissions**: `tweet.write`, `users.read`, `offline.access`
- **Rate Limits**: 300 tweets per 15 minutes
- **Content Limits**: 280 characters, 4 media attachments

#### LinkedIn
- **API Version**: LinkedIn Marketing API v2
- **Permissions**: `w_member_social`, `r_organization_social`
- **Rate Limits**: 500 calls/day per application
- **Content Limits**: 3,000 characters, 20 media attachments

#### YouTube
- **API Version**: YouTube Data API v3
- **Permissions**: `youtube.upload`, `youtube.readonly`
- **Rate Limits**: 10,000 quota units/day
- **Content Limits**: Videos up to 256GB

## 📊 Performance Targets

### Response Times
- **API Response**: < 500ms for 95% of requests
- **Page Load**: < 2 seconds for dashboard
- **Calendar View**: < 1.5 seconds for monthly view
- **Analytics**: < 3 seconds for 90-day reports

### Scalability
- **Concurrent Users**: 100 users
- **Publishing Rate**: 1,000 posts/hour
- **System Uptime**: 99.5% availability target

## 🔒 Security Features

### Authentication & Authorization
- JWT tokens with 2-hour expiration
- OAuth 2.0 compliant social media integrations
- Role-based access control (RBAC)
- Session management with Redis

### Data Protection
- AES-256 encryption for sensitive data
- TLS 1.3 for all communications
- GDPR compliance capabilities
- Comprehensive audit logging

### API Security
- Rate limiting per user and IP
- Input validation and sanitization
- CSRF protection
- XSS prevention

## 📅 Development Timeline

### Week 1: Foundation & Architecture (Days 1-7)
- [ ] Project setup and environment configuration
- [ ] Authentication system implementation
- [ ] Role-based access control development
- [ ] OAuth provider architecture
- [ ] Channel management system
- [ ] Basic UI framework setup
- [ ] Testing and integration

### Week 2: Core Features Development (Days 8-14)
- [ ] Post creation and management
- [ ] Rich text editor implementation
- [ ] Scheduling system development
- [ ] Queue-based publishing engine
- [ ] Facebook provider integration
- [ ] Instagram provider integration
- [ ] Basic analytics collection

### Week 3: Analytics & Advanced Features (Days 15-21)
- [ ] Analytics dashboard development
- [ ] Report generation system
- [ ] Notification system implementation
- [ ] Team collaboration features
- [ ] Calendar view development
- [ ] Performance optimization
- [ ] Security hardening

### Week 4: Testing, Polish & Deployment (Days 22-28)
- [ ] Comprehensive testing
- [ ] UI/UX polish and refinement
- [ ] Documentation completion
- [ ] Production environment setup
- [ ] Performance testing and optimization
- [ ] Bug fixes and final adjustments
- [ ] Final presentation and handover

## 🧪 Testing Strategy

### Testing Pyramid
- **Unit Tests (60%)**: Models, services, helpers, provider adapters
- **Integration Tests (30%)**: API endpoints, database operations, OAuth flows
- **End-to-End Tests (10%)**: Complete user workflows, browser automation

### Coverage Targets
| Component | Target Coverage | Testing Focus |
|-----------|----------------|---------------|
| Models | 90%+ | Data validation, relationships, scopes |
| Services | 85%+ | Business logic, error handling |
| Controllers | 80%+ | Request/response, validation, authorization |
| Providers | 95%+ | OAuth flow, API integration, error handling |
| Overall | 80%+ | Comprehensive application coverage |

## 🚀 Deployment Options

### Option 1: DigitalOcean App Platform (Recommended)
- **Cost**: $24-48 USD/month
- **Features**: Managed deployment, auto-scaling, SSL certificates
- **Advantages**: Cost-effective, easy management

### Option 2: AWS Elastic Beanstalk
- **Cost**: $50-150 USD/month
- **Features**: Enterprise infrastructure, CloudWatch integration
- **Advantages**: Advanced monitoring, scalability

### Option 3: Heroku
- **Cost**: $25-75 USD/month
- **Features**: Simple deployment, extensive add-ons
- **Advantages**: Extremely simple setup

## 📁 Project Structure

```
├── app/
│   ├── Console/Commands/     # Artisan commands
│   ├── Http/Controllers/     # API controllers
│   ├── Jobs/                 # Background jobs
│   ├── Models/               # Eloquent models
│   ├── Services/             # Business logic
│   └── Providers/            # Social media providers
├── database/
│   ├── migrations/           # Database migrations
│   └── seeders/              # Database seeders
├── resources/
│   ├── js/
│   │   ├── Components/       # Vue components
│   │   ├── Pages/            # Route pages
│   │   └── Stores/           # State management
│   └── css/                  # Stylesheets
├── routes/
│   ├── api.php               # API routes
│   └── web.php               # Web routes
└── tests/
    ├── Unit/                 # Unit tests
    ├── Feature/              # Feature tests
    └── Browser/              # Browser tests
```

## 🎯 Success Metrics

### Development KPIs
- **Code Coverage**: 80%+ test coverage
- **API Response Time**: < 500ms for 95% of requests
- **Deployment Time**: < 5 minutes automated deployment
- **Bug Rate**: < 1 critical bug per 1000 lines of code

### Business KPIs
- **User Onboarding**: < 5 minutes to first post
- **Platform Uptime**: 99.5% availability
- **Publishing Success Rate**: 99.9% successful post delivery
- **Analytics Processing**: Real-time data within 5 minutes

## 🆚 Competitive Analysis

| Feature | This Platform | Hootsuite | Buffer | Sprout Social |
|---------|---------------|-----------|---------|---------------|
| Multi-brand Management | ✅ | ✅ | ❌ | ✅ |
| 5+ Social Platforms | ✅ | ✅ | ✅ | ✅ |
| Real-time Analytics | ✅ | ✅ | ❌ | ✅ |
| Team Collaboration | ✅ | ✅ | ✅ | ✅ |
| Custom Deployment | ✅ | ❌ | ❌ | ❌ |
| Open Source Potential | ✅ | ❌ | ❌ | ❌ |
| MongoDB Flexibility | ✅ | ❌ | ❌ | ❌ |

## 🔧 Installation & Setup

### Prerequisites
```bash
# Install PHP 8.2+
sudo apt update
sudo apt install php8.2 php8.2-cli php8.2-common

# Install MongoDB
wget -qO - https://www.mongodb.org/static/pgp/server-7.0.asc | sudo apt-key add -
sudo apt install -y mongodb-org

# Install Redis
sudo apt install redis-server

# Install Node.js
curl -fsSL https://deb.nodesource.com/setup_18.x | sudo -E bash -
sudo apt install -y nodejs

# Install Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

### Development Setup
```bash
# Clone the repository
git clone https://github.com/your-org/social-media-platform.git
cd social-media-platform

# Install PHP dependencies
composer install

# Install Node.js dependencies
npm install

# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate

# Run database migrations
php artisan migrate

# Seed the database (optional)
php artisan db:seed

# Build frontend assets
npm run build

# Start the development server
php artisan serve
```

### Production Deployment
```bash
# Build for production
npm run build

# Optimize for production
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Run queue workers
php artisan queue:work

# Set up scheduled tasks (add to crontab)
* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
```

## 🔍 Environment Variables

```bash
# Application
APP_NAME="Social Media Marketing Platform"
APP_ENV=production
APP_KEY=base64:your-app-key
APP_DEBUG=false
APP_URL=https://your-domain.com

# Database
DB_CONNECTION=mongodb
DB_HOST=127.0.0.1
DB_PORT=27017
DB_DATABASE=social_media_platform
DB_USERNAME=
DB_PASSWORD=

# Redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

# Social Media APIs
FACEBOOK_CLIENT_ID=your-facebook-client-id
FACEBOOK_CLIENT_SECRET=your-facebook-client-secret
INSTAGRAM_CLIENT_ID=your-instagram-client-id
INSTAGRAM_CLIENT_SECRET=your-instagram-client-secret
TWITTER_CLIENT_ID=your-twitter-client-id
TWITTER_CLIENT_SECRET=your-twitter-client-secret
LINKEDIN_CLIENT_ID=your-linkedin-client-id
LINKEDIN_CLIENT_SECRET=your-linkedin-client-secret
YOUTUBE_CLIENT_ID=your-youtube-client-id
YOUTUBE_CLIENT_SECRET=your-youtube-client-secret

# File Storage
FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=your-aws-access-key
AWS_SECRET_ACCESS_KEY=your-aws-secret-key
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=your-s3-bucket

# Mail
MAIL_MAILER=smtp
MAIL_HOST=your-smtp-host
MAIL_PORT=587
MAIL_USERNAME=your-email@domain.com
MAIL_PASSWORD=your-email-password
```

## 📚 Documentation

### API Documentation
- Interactive API documentation available at `/api/documentation`
- Postman collection included in `/docs/postman/`
- OpenAPI/Swagger specification in `/docs/openapi.yml`

### User Guides
- [Administrator Setup Guide](docs/admin-setup.md)
- [Brand Manager Guide](docs/brand-manager-guide.md)
- [Content Editor Guide](docs/content-editor-guide.md)
- [Analytics Guide](docs/analytics-guide.md)

### Technical Documentation
- [Architecture Decision Records](docs/adr/)
- [Database Schema](docs/database-schema.md)
- [OAuth Integration Guide](docs/oauth-integration.md)
- [Provider Development Guide](docs/provider-development.md)

## 🤝 Contributing

### Development Workflow
1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Make your changes
4. Run tests (`php artisan test`)
5. Commit your changes (`git commit -m 'Add amazing feature'`)
6. Push to the branch (`git push origin feature/amazing-feature`)
7. Open a Pull Request

### Code Standards
- Follow PSR-12 coding standards for PHP
- Use ESLint configuration for JavaScript
- Write unit tests for new features
- Update documentation as needed

### Testing
```bash
# Run all tests
php artisan test

# Run with coverage
php artisan test --coverage

# Run specific test suite
php artisan test --testsuite=Feature

# Run frontend tests
npm run test
```

## 📈 Monitoring & Maintenance

### Performance Monitoring
- Application performance monitoring with built-in metrics
- Database query optimization and monitoring
- Redis cache hit rate monitoring
- External API response time tracking

### Log Management
- Structured logging with Laravel's logging system
- Error tracking and alerting
- Performance bottleneck identification
- Security event monitoring

### Maintenance Tasks
- **Daily**: System health checks, backup verification
- **Weekly**: Log analysis, performance review
- **Monthly**: Dependency updates, security patches
- **Quarterly**: Performance optimization, capacity planning

## 🆘 Support & Troubleshooting

### Common Issues

#### OAuth Connection Failures
```bash
# Check OAuth configuration
php artisan oauth:test facebook

# Clear cached configurations
php artisan config:clear
php artisan cache:clear
```

#### Queue Processing Issues
```bash
# Check queue status
php artisan queue:monitor

# Restart queue workers
php artisan queue:restart

# Process failed jobs
php artisan queue:retry all
```

#### Database Connection Issues
```bash
# Test MongoDB connection
php artisan mongodb:test

# Check Redis connection
php artisan redis:ping
```

### Getting Help
- Check the [documentation](docs/)
- Search existing [issues](https://github.com/your-org/social-media-platform/issues)
- Create a new issue with detailed information
- Contact the development team

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 👨‍💻 Author

**Jeewaka Supun**  
*Full Stack Developer*

- GitHub: [@jeewakasupon](https://github.com/jeewakasupon)
- Email: jeewaka@example.com

## 🙏 Acknowledgments

- Laravel community for excellent documentation and packages
- Vue.js team for the reactive framework
- MongoDB team for flexible database solutions
- Social media platform API teams for comprehensive documentation

---

**Development Status**: Ready for Implementation  
**Last Updated**: August 26, 2025  
**Version**: 1.0.0-dev