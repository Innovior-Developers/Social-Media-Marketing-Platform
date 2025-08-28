<?php
// Create this file: app/Http/Middleware/VerifyCsrfToken.php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array<int, string>
     */
    protected $except = [
        // Exclude all test routes from CSRF protection
        'test/*',
        'oauth/callback/*',
        'api/*',
        
        // Specific routes for LinkedIn testing
        'test/linkedin/post/*',
        'test/linkedin/profile/*',
        'test/provider/*',
        'test/oauth/*',
        
        // Future social media testing routes
        'test/twitter/*',
        'test/facebook/*',
        'test/instagram/*',
    ];
}