<?php

namespace App\Helpers;

class MediaValidation
{
    /**
     * Validate media file type, size and extension
     * 
     * @param mixed $file The uploaded file
     * @param string $mediaType The type of media (image, video, document)
     * @return array Validation result with 'valid' boolean and optional 'error' message
     */
    public static function validateMediaFile($file, $mediaType): array
    {
        if (!$file) {
            return ['valid' => false, 'error' => 'No file uploaded'];
        }

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
     * Get allowed extensions for media type
     */
    public static function getAllowedExtensions($mediaType): array
    {
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
     * Get max file size for media type
     */
    public static function getMaxFileSize($mediaType): int
    {
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
}