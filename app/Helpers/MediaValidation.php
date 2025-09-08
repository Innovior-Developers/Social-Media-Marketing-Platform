<?php

namespace App\Helpers;

class MediaValidation
{
    /**
     * Validate media file type, size and extension
     * 
     * @param mixed $file The uploaded file
     * @param string $mediaType The type of media (image, video, document)
     * @param string $platform The target platform (optional)
     * @return array Validation result with 'valid' boolean and optional 'error' message
     */
    public static function validateMediaFile($file, $mediaType, $platform = null): array
    {
        if (!$file) {
            return ['valid' => false, 'error' => 'No file uploaded'];
        }

        // Platform-specific validation
        if ($platform === 'facebook') {
            return self::validateFacebookMedia($file, $mediaType);
        }

        // Default validation
        switch ($mediaType) {
            case 'image':
                $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
                $maxSize = 20 * 1024 * 1024; // 20MB
                break;
            case 'video':
                $allowedExtensions = ['mp4', 'mov', 'avi', 'wmv'];
                $maxSize = 200 * 1024 * 1024; // 200MB
                break;
            case 'document':
                $allowedExtensions = ['pdf', 'doc', 'docx', 'ppt', 'pptx'];
                $maxSize = 100 * 1024 * 1024; // 100MB
                break;
            default:
                return ['valid' => false, 'error' => 'Unsupported media type'];
        }

        $extension = strtolower($file->getClientOriginalExtension());
        if (!in_array($extension, $allowedExtensions)) {
            return [
                'valid' => false,
                'error' => "Only " . implode(', ', $allowedExtensions) . " files are supported for {$mediaType}"
            ];
        }

        if ($file->getSize() > $maxSize) {
            return [
                'valid' => false,
                'error' => ucfirst($mediaType) . " must be smaller than " . ($maxSize / 1024 / 1024) . "MB"
            ];
        }

        return ['valid' => true];
    }

    /**
     * Facebook-specific media validation
     */
    private static function validateFacebookMedia($file, $mediaType): array
    {
        $constraints = config('services.facebook.constraints', []);

        switch ($mediaType) {
            case 'image':
                $allowedExtensions = $constraints['supported_image_formats'] ?? ['jpg', 'jpeg', 'png', 'gif'];
                $maxSize = $constraints['image_max_size'] ?? (100 * 1024 * 1024); // 100MB
                break;
            case 'video':
                $allowedExtensions = $constraints['supported_video_formats'] ?? ['mp4', 'mov', 'avi'];
                $maxSize = $constraints['video_max_size'] ?? (10 * 1024 * 1024 * 1024); // 10GB
                break;
            default:
                return ['valid' => false, 'error' => 'Facebook only supports image and video uploads'];
        }

        $extension = strtolower($file->getClientOriginalExtension());
        if (!in_array($extension, $allowedExtensions)) {
            return [
                'valid' => false,
                'error' => "Facebook only supports " . implode(', ', $allowedExtensions) . " files for {$mediaType}"
            ];
        }

        if ($file->getSize() > $maxSize) {
            return [
                'valid' => false,
                'error' => "Facebook {$mediaType} must be smaller than " . self::formatFileSize($maxSize)
            ];
        }

        return ['valid' => true];
    }

    /**
     * Get allowed extensions for media type and platform
     */
    public static function getAllowedExtensions($mediaType, $platform = null): array
    {
        if ($platform === 'facebook') {
            $constraints = config('services.facebook.constraints', []);
            switch ($mediaType) {
                case 'image':
                    return $constraints['supported_image_formats'] ?? ['jpg', 'jpeg', 'png', 'gif'];
                case 'video':
                    return $constraints['supported_video_formats'] ?? ['mp4', 'mov', 'avi'];
                default:
                    return [];
            }
        }

        // Default extensions
        switch ($mediaType) {
            case 'image':
                return ['jpg', 'jpeg', 'png', 'gif'];
            case 'video':
                return ['mp4', 'mov', 'avi', 'wmv'];
            case 'document':
                return ['pdf', 'doc', 'docx', 'ppt', 'pptx'];
            default:
                return [];
        }
    }

    /**
     * Get max file size for media type and platform
     */
    public static function getMaxFileSize($mediaType, $platform = null): int
    {
        if ($platform === 'facebook') {
            $constraints = config('services.facebook.constraints', []);
            switch ($mediaType) {
                case 'image':
                    return $constraints['image_max_size'] ?? (100 * 1024 * 1024); // 100MB
                case 'video':
                    return $constraints['video_max_size'] ?? (10 * 1024 * 1024 * 1024); // 10GB
                default:
                    return 0;
            }
        }

        // Default sizes
        switch ($mediaType) {
            case 'image':
                return 20 * 1024 * 1024; // 20MB
            case 'video':
                return 200 * 1024 * 1024; // 200MB
            case 'document':
                return 100 * 1024 * 1024; // 100MB
            default:
                return 0;
        }
    }

    /**
     * Format file size for human reading
     */
    private static function formatFileSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $pow = floor(log($bytes) / log(1024));
        return round($bytes / (1024 ** $pow), 1) . ' ' . $units[$pow];
    }
}