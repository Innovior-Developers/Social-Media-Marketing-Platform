<?php
// app/Services/SocialMedia/SocialMediaProviderFactory.php

namespace App\Services\SocialMedia;

class SocialMediaProviderFactory
{
    private static $providers = [
        'twitter' => TwitterProvider::class,
        'facebook' => FacebookProvider::class,
        'instagram' => InstagramProvider::class,
        'linkedin' => LinkedInProvider::class,
        'youtube' => YouTubeProvider::class,
        'tiktok' => TikTokProvider::class,
    ];

    public static function create(string $platform, array $config = []): AbstractSocialMediaProvider
    {
        if (!isset(self::$providers[$platform])) {
            throw new \InvalidArgumentException("Unsupported platform: {$platform}");
        }

        $providerClass = self::$providers[$platform];
        return new $providerClass($config);
    }

    public static function getSupportedPlatforms(): array
    {
        return array_keys(self::$providers);
    }

    public static function getAllProviders(): array
    {
        $providers = [];
        foreach (self::$providers as $platform => $class) {
            $providers[$platform] = new $class();
        }
        return $providers;
    }
}