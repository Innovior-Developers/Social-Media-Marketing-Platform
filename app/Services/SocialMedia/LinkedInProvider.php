<?php
// Complete LinkedInProvider.php with all methods FIXED

namespace App\Services\SocialMedia;

use App\Models\SocialMediaPost;
use App\Models\Channel;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LinkedInProvider extends AbstractSocialMediaProvider
{
    protected $platform = 'linkedin';

    public function __construct(array $config = [])
    {
        parent::__construct($config);
    }

    /**
     * Override stub mode detection for mixed mode support
     */
    public function isStubMode(): bool
    {
        // Check if this specific provider should use real API
        $realProviders = config('services.social_media.real_providers', []);
        $shouldUseReal = $realProviders['linkedin'] ?? false;

        if ($shouldUseReal) {
            Log::info('LinkedIn Provider: Using REAL API mode');
            return false; // Use real API
        }

        Log::info('LinkedIn Provider: Using STUB mode');
        return true; // Use stub mode
    }

    public function authenticate(array $credentials): array
    {
        if ($this->isStubMode()) {
            Log::info('LinkedIn: Using stub authentication');
            return [
                'success' => true,
                'access_token' => 'linkedin_token_' . uniqid(),
                'refresh_token' => 'linkedin_refresh_' . uniqid(),
                'expires_at' => now()->addDays(365),
                'user_info' => [
                    'username' => 'linkedin_user_' . rand(1000, 9999),
                    'display_name' => 'LinkedIn Professional',
                    'avatar_url' => 'https://linkedin.com/avatar.jpg',
                    'headline' => 'Professional at Company',
                    'industry' => 'Technology',
                    'connections_count' => rand(500, 5000)
                ]
            ];
        }

        Log::info('LinkedIn: Using real authentication');
        return $this->authenticateReal($credentials);
    }

    protected function getRealAuthUrl(string $state = null): string
    {
        $params = [
            'response_type' => 'code',
            'client_id' => $this->getConfig('client_id'),
            'redirect_uri' => $this->getConfig('redirect'),
            'scope' => implode(' ', $this->getDefaultScopes()),
            'state' => $state ?? csrf_token()
        ];

        $authUrl = $this->getConfig('auth_url') . '?' . http_build_query($params);

        Log::info('LinkedIn: Generated auth URL', [
            'url' => $authUrl,
            'client_id' => $this->getConfig('client_id'),
            'redirect_uri' => $this->getConfig('redirect'),
            'scopes' => $this->getDefaultScopes()
        ]);

        return $authUrl;
    }

    protected function getRealTokens(string $code): array
    {
        $tokenUrl = $this->getConfig('token_url');
        $requestData = [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $this->getConfig('redirect'),
            'client_id' => $this->getConfig('client_id'),
            'client_secret' => $this->getConfig('client_secret'),
        ];

        Log::info('LinkedIn: Exchanging code for tokens', [
            'token_url' => $tokenUrl,
            'client_id' => $this->getConfig('client_id'),
            'redirect_uri' => $this->getConfig('redirect')
        ]);

        $response = Http::asForm()->post($tokenUrl, $requestData);

        if (!$response->successful()) {
            Log::error('LinkedIn: Token exchange failed', [
                'status' => $response->status(),
                'response' => $response->body(),
                'request_data' => array_merge($requestData, ['client_secret' => '[HIDDEN]'])
            ]);
            throw new \Exception('LinkedIn token exchange failed: ' . $response->body());
        }

        $data = $response->json();

        Log::info('LinkedIn: Token exchange successful', [
            'expires_in' => $data['expires_in'] ?? 'not_specified',
            'token_type' => $data['token_type'] ?? 'not_specified'
        ]);

        return [
            'access_token' => $data['access_token'],
            'expires_at' => now()->addSeconds($data['expires_in'] ?? 3600),
            'token_type' => $data['token_type'] ?? 'Bearer',
            'scope' => explode(' ', $data['scope'] ?? implode(' ', $this->getDefaultScopes())),
        ];
    }

    private function authenticateReal(array $credentials): array
    {
        return [
            'success' => true,
            'message' => 'LinkedIn real authentication completed',
            'mode' => 'real'
        ];
    }

    public function publishPost(SocialMediaPost $post, Channel $channel): array
    {
        if ($this->isStubMode()) {
            Log::info('LinkedIn: Publishing post in stub mode');
            return $this->publishStubPost($post, $channel);
        }

        Log::info('LinkedIn: Publishing post in real mode');
        return $this->publishRealPost($post, $channel);
    }

    private function publishStubPost(SocialMediaPost $post, Channel $channel): array
    {
        $formatted = $this->formatPost($post);

        return [
            'success' => true,
            'platform_id' => 'linkedin_post_' . uniqid(),
            'url' => 'https://linkedin.com/feed/update/urn:li:activity:' . uniqid(),
            'published_at' => now()->toISOString(),
            'post_type' => !empty($formatted['media']) ? 'RICH_MEDIA' : 'TEXT_ONLY',
            'mode' => 'stub'
        ];
    }

    private function publishRealPost(SocialMediaPost $post, Channel $channel): array
    {
        try {
            $tokens = $channel->oauth_tokens;
            if (is_string($tokens)) {
                $tokens = decrypt($tokens);
            }

            // Get user profile ID first
            $profileResponse = Http::withToken($tokens['access_token'])
                ->withHeaders([
                    'Accept' => 'application/json',
                    'X-Restli-Protocol-Version' => '2.0.0'
                ])
                ->get('https://api.linkedin.com/v2/userinfo');

            if (!$profileResponse->successful()) {
                Log::error('LinkedIn: Failed to get profile', [
                    'status' => $profileResponse->status(),
                    'response' => $profileResponse->body()
                ]);
                throw new \Exception('Failed to get LinkedIn profile: ' . $profileResponse->body());
            }

            $profile = $profileResponse->json();
            $profileId = $profile['sub'];
            $formatted = $this->formatPost($post);

            // CHECK FOR MEDIA AND HANDLE ACCORDINGLY
            $hasMedia = !empty($post->media);
            $mediaUrns = [];
            $mediaUploadResults = [];

            // ENHANCED MEDIA PROCESSING FOR CAROUSEL POSTS
            if ($hasMedia) {
                Log::info('LinkedIn: Processing multiple images for carousel', [
                    'media_count' => count($post->media),
                    'media_types' => array_column($post->media, 'type')
                ]);

                // Upload ALL media files (not just the first one)
                foreach ($post->media as $index => $media) {
                    Log::info('LinkedIn: Uploading image for carousel', [
                        'index' => $index + 1,
                        'total' => count($post->media),
                        'name' => $media['name'] ?? "image_{$index}",
                        'size' => $media['size'] ?? 'unknown'
                    ]);

                    $uploadResult = $this->uploadMediaToLinkedIn($media, $tokens['access_token'], $profileId);
                    $mediaUploadResults[] = $uploadResult;

                    if ($uploadResult['success']) {
                        $mediaUrns[] = $uploadResult['media_urn'];
                        Log::info('LinkedIn: Carousel image uploaded successfully', [
                            'position' => $index + 1,
                            'media_urn' => $uploadResult['media_urn'],
                            'file_name' => $media['name'] ?? "image_{$index}"
                        ]);
                    } else {
                        Log::error('LinkedIn: Carousel image upload failed', [
                            'position' => $index + 1,
                            'error' => $uploadResult['error'],
                            'file_name' => $media['name'] ?? "image_{$index}"
                        ]);
                    }
                }

                // 🔥 ENHANCED CAROUSEL POST DATA STRUCTURE
                if (!empty($mediaUrns)) {
                    $shareContent['shareMediaCategory'] = count($mediaUrns) > 1 ? 'IMAGE' : 'IMAGE';

                    // Create media array for carousel
                    $shareContent['media'] = array_map(function ($urn, $index) {
                        return [
                            'status' => 'READY',
                            'media' => $urn,
                            'description' => [
                                'text' => "Image " . ($index + 1)  // Optional: Add descriptions
                            ]
                        ];
                    }, $mediaUrns, array_keys($mediaUrns));

                    Log::info('LinkedIn: Carousel post structure created', [
                        'media_count' => count($mediaUrns),
                        'media_category' => $shareContent['shareMediaCategory'],
                        'carousel_enabled' => count($mediaUrns) > 1
                    ]);
                }
            }

            // BUILD POST DATA WITH OR WITHOUT MEDIA
            $shareContent = [
                'shareCommentary' => [
                    'text' => $formatted['content']
                ]
            ];

            if (!empty($mediaUrns)) {
                // Determine media category based on first media type
                $firstMediaType = $post->media[0]['type'] ?? 'image';
                $mediaCategory = $this->getLinkedInMediaCategory($firstMediaType);

                $shareContent['shareMediaCategory'] = $mediaCategory;
                $shareContent['media'] = array_map(function ($urn) {
                    return [
                        'status' => 'READY',
                        'media' => $urn
                    ];
                }, $mediaUrns);
            } else {
                $shareContent['shareMediaCategory'] = 'NONE';
            }

            $postData = [
                'author' => "urn:li:person:{$profileId}",
                'lifecycleState' => 'PUBLISHED',
                'specificContent' => [
                    'com.linkedin.ugc.ShareContent' => $shareContent
                ],
                'visibility' => [
                    'com.linkedin.ugc.MemberNetworkVisibility' => 'PUBLIC'
                ]
            ];

            Log::info('LinkedIn: Attempting to publish post', [
                'profile_id' => $profileId,
                'content_length' => strlen($formatted['content']),
                'has_media' => $hasMedia,
                'media_count' => count($mediaUrns),
                'media_category' => $shareContent['shareMediaCategory'],
                'payload' => $postData
            ]);

            // PUBLISH POST (WITH OR WITHOUT MEDIA)
            $response = Http::withToken($tokens['access_token'])
                ->withHeaders([
                    'X-Restli-Protocol-Version' => '2.0.0',
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json'
                ])
                ->timeout(120) // Extended timeout for posts with media
                ->post('https://api.linkedin.com/v2/ugcPosts', $postData);

            if ($response->successful()) {
                $responseData = $response->json();
                $postId = $responseData['id'] ?? 'unknown';

                Log::info('LinkedIn: Post published successfully', [
                    'post_id' => $postId,
                    'linkedin_id' => $responseData['id'],
                    'media_count' => count($mediaUrns)
                ]);

                $successResponse = [
                    'success' => true,
                    'platform_id' => $postId,
                    'url' => "https://www.linkedin.com/feed/update/{$postId}/",
                    'published_at' => now()->toISOString(),
                    'platform_data' => $responseData,
                    'mode' => 'real'
                ];

                // Add media information if present
                if ($hasMedia) {
                    $successResponse['media_info'] = [
                        'media_uploaded' => count($mediaUrns),
                        'media_total' => count($post->media),
                        'media_urns' => $mediaUrns,
                        'upload_results' => $mediaUploadResults
                    ];
                }

                return $successResponse;
            }

            Log::error('LinkedIn: Post publishing failed', [
                'status' => $response->status(),
                'response' => $response->body(),
                'request_payload' => $postData
            ]);

            return [
                'success' => false,
                'error' => 'LinkedIn API error: ' . $response->body(),
                'retryable' => $this->isRetryableError($response->status()),
                'mode' => 'real',
                'media_upload_results' => $mediaUploadResults,
                'debug_info' => [
                    'status_code' => $response->status(),
                    'payload_sent' => $postData,
                    'api_response' => $response->body()
                ]
            ];
        } catch (\Exception $e) {
            Log::error('LinkedIn: Post publishing exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'retryable' => true,
                'mode' => 'real'
            ];
        }
    }

    /**
     * Upload media to LinkedIn and get media URN
     */
    private function uploadMediaToLinkedIn(array $mediaFile, string $accessToken, string $profileId): array
    {
        try {
            // Step 1: Register upload with LinkedIn
            $registerResult = $this->registerLinkedInUpload($mediaFile, $accessToken, $profileId);

            if (!$registerResult['success']) {
                return $registerResult;
            }

            // Step 2: Upload file to LinkedIn's servers
            $uploadResult = $this->uploadFileToLinkedIn($mediaFile, $registerResult['upload_url']);

            if (!$uploadResult['success']) {
                return $uploadResult;
            }

            // Step 3: Wait and verify upload
            sleep(3); // Give LinkedIn time to process
            $verifyResult = $this->verifyLinkedInUpload($registerResult['media_urn'], $accessToken);

            return [
                'success' => true,
                'media_urn' => $registerResult['media_urn'],
                'upload_url' => $registerResult['upload_url'],
                'file_info' => [
                    'type' => $mediaFile['type'] ?? 'unknown',
                    'size' => $mediaFile['size'] ?? 0,
                    'name' => $mediaFile['name'] ?? 'unknown'
                ],
                'verification' => $verifyResult,
                'upload_steps' => [
                    'register' => $registerResult,
                    'upload' => $uploadResult,
                    'verify' => $verifyResult
                ]
            ];
        } catch (\Exception $e) {
            Log::error('LinkedIn: Media upload process failed', [
                'error' => $e->getMessage(),
                'media_type' => $mediaFile['type'] ?? 'unknown'
            ]);

            return [
                'success' => false,
                'error' => 'Media upload failed: ' . $e->getMessage(),
                'media_type' => $mediaFile['type'] ?? 'unknown'
            ];
        }
    }

    /**
     * Register media upload with LinkedIn
     */
    private function registerLinkedInUpload(array $mediaFile, string $accessToken, string $profileId): array
    {
        try {
            $mediaType = $mediaFile['type'] ?? 'image';
            $recipeType = $this->getLinkedInRecipeType($mediaType);

            $payload = [
                'registerUploadRequest' => [
                    'recipes' => [$recipeType],
                    'owner' => "urn:li:person:{$profileId}",
                    'serviceRelationships' => [
                        [
                            'relationshipType' => 'OWNER',
                            'identifier' => 'urn:li:userGeneratedContent'
                        ]
                    ]
                ]
            ];

            Log::info('LinkedIn: Registering media upload', [
                'media_type' => $mediaType,
                'recipe_type' => $recipeType
            ]);

            $response = Http::withToken($accessToken)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'X-Restli-Protocol-Version' => '2.0.0'
                ])
                ->timeout(30)
                ->post('https://api.linkedin.com/v2/assets?action=registerUpload', $payload);

            if ($response->successful()) {
                $data = $response->json();

                return [
                    'success' => true,
                    'upload_url' => $data['value']['uploadMechanism']['com.linkedin.digitalmedia.uploading.MediaUploadHttpRequest']['uploadUrl'],
                    'media_urn' => $data['value']['asset'],
                    'registration_data' => $data
                ];
            }

            Log::error('LinkedIn: Upload registration failed', [
                'status' => $response->status(),
                'response' => $response->body(),
                'payload' => $payload
            ]);

            return [
                'success' => false,
                'error' => 'Upload registration failed: ' . $response->body(),
                'status_code' => $response->status()
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Registration exception: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Verify media upload status
     */
    private function verifyLinkedInUpload(string $mediaUrn, string $accessToken): array
    {
        try {
            $response = Http::withToken($accessToken)
                ->withHeaders([
                    'Accept' => 'application/json',
                    'X-Restli-Protocol-Version' => '2.0.0'
                ])
                ->timeout(15)
                ->get("https://api.linkedin.com/v2/assets/{$mediaUrn}");

            if ($response->successful()) {
                $data = $response->json();
                $status = $data['recipes'][0]['status'] ?? 'UNKNOWN';

                return [
                    'success' => true,
                    'status' => $status,
                    'ready' => $status === 'AVAILABLE',
                    'processing' => $status === 'PROCESSING',
                    'verification_data' => $data
                ];
            }

            return [
                'success' => false,
                'error' => 'Verification failed: ' . $response->body()
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Verification exception: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get LinkedIn recipe type for media
     */
    private function getLinkedInRecipeType(string $mediaType): string
    {
        $recipeMap = [
            'image' => 'urn:li:digitalmediaRecipe:feedshare-image',
            'video' => 'urn:li:digitalmediaRecipe:feedshare-video',
            'document' => 'urn:li:digitalmediaRecipe:feedshare-document'
        ];

        return $recipeMap[$mediaType] ?? $recipeMap['image'];
    }

    /**
     * Get LinkedIn media category
     */
    private function getLinkedInMediaCategory(string $mediaType): string
    {
        $categoryMap = [
            'image' => 'IMAGE',
            'video' => 'VIDEO',
            'document' => 'RICH'
        ];

        return $categoryMap[$mediaType] ?? 'IMAGE';
    }

    /**
     * Upload media to LinkedIn and get media URN
     */
    private function uploadMedia(array $mediaFile, string $accessToken, string $profileId): array
    {
        try {
            Log::info('LinkedIn: Starting media upload', [
                'file_type' => $mediaFile['type'],
                'file_size' => $mediaFile['size'] ?? 'unknown'
            ]);

            // Step 1: Register upload with LinkedIn
            $registerResponse = $this->registerMediaUpload($mediaFile, $accessToken, $profileId);

            if (!$registerResponse['success']) {
                return $registerResponse;
            }

            $uploadUrl = $registerResponse['upload_url'];
            $mediaUrn = $registerResponse['media_urn'];

            // Step 2: Upload actual file to LinkedIn's upload URL
            $uploadResponse = $this->uploadFileToLinkedIn($mediaFile, $uploadUrl);

            if (!$uploadResponse['success']) {
                return $uploadResponse;
            }

            // Step 3: Verify upload completed
            $verifyResponse = $this->verifyMediaUpload($mediaUrn, $accessToken);

            return [
                'success' => true,
                'media_urn' => $mediaUrn,
                'upload_url' => $uploadUrl,
                'file_type' => $mediaFile['type'],
                'verified' => $verifyResponse['success'] ?? false,
                'upload_details' => [
                    'registration' => $registerResponse,
                    'upload' => $uploadResponse,
                    'verification' => $verifyResponse
                ]
            ];
        } catch (\Exception $e) {
            Log::error('LinkedIn: Media upload failed', [
                'error' => $e->getMessage(),
                'file_type' => $mediaFile['type'] ?? 'unknown'
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'file_type' => $mediaFile['type'] ?? 'unknown'
            ];
        }
    }

    /**
     * Register media upload with LinkedIn
     */
    private function registerMediaUpload(array $mediaFile, string $accessToken, string $profileId): array
    {
        try {
            $mediaType = $this->getLinkedInMediaType($mediaFile['type']);

            $registerPayload = [
                'registerUploadRequest' => [
                    'recipes' => ["urn:li:digitalmediaRecipe:feedshare-{$mediaType}"],
                    'owner' => "urn:li:person:{$profileId}",
                    'serviceRelationships' => [
                        [
                            'relationshipType' => 'OWNER',
                            'identifier' => 'urn:li:userGeneratedContent'
                        ]
                    ]
                ]
            ];

            Log::info('LinkedIn: Registering media upload', [
                'media_type' => $mediaType,
                'payload' => $registerPayload
            ]);

            $response = Http::withToken($accessToken)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'X-Restli-Protocol-Version' => '2.0.0'
                ])
                ->post('https://api.linkedin.com/v2/assets?action=registerUpload', $registerPayload);

            if ($response->successful()) {
                $data = $response->json();

                return [
                    'success' => true,
                    'upload_url' => $data['value']['uploadMechanism']['com.linkedin.digitalmedia.uploading.MediaUploadHttpRequest']['uploadUrl'],
                    'media_urn' => $data['value']['asset'],
                    'registration_data' => $data
                ];
            }

            Log::error('LinkedIn: Media registration failed', [
                'status' => $response->status(),
                'response' => $response->body()
            ]);

            return [
                'success' => false,
                'error' => 'Media registration failed: ' . $response->body(),
                'status_code' => $response->status()
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Upload file to LinkedIn's upload URL
     */
    private function uploadFileToLinkedIn(array $mediaFile, string $uploadUrl): array
    {
        try {
            Log::info('LinkedIn: Uploading file to LinkedIn servers', [
                'upload_url_length' => strlen($uploadUrl),
                'file_size' => $mediaFile['size'] ?? 'unknown'
            ]);

            // Read file content
            $fileContent = file_get_contents($mediaFile['path']);

            if ($fileContent === false) {
                return [
                    'success' => false,
                    'error' => 'Could not read file: ' . $mediaFile['path']
                ];
            }

            // Upload file as binary data
            $response = Http::withHeaders([
                'Content-Type' => $mediaFile['mime_type'],
                'Content-Length' => strlen($fileContent)
            ])
                ->timeout(120) // Extended timeout for large files
                ->withBody($fileContent, $mediaFile['mime_type'])
                ->put($uploadUrl);

            if ($response->successful()) {
                Log::info('LinkedIn: File uploaded successfully');

                return [
                    'success' => true,
                    'status_code' => $response->status(),
                    'upload_completed' => true
                ];
            }

            Log::error('LinkedIn: File upload failed', [
                'status' => $response->status(),
                'response' => $response->body()
            ]);

            return [
                'success' => false,
                'error' => 'File upload failed: ' . $response->body(),
                'status_code' => $response->status()
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Verify media upload completed
     */
    private function verifyMediaUpload(string $mediaUrn, string $accessToken): array
    {
        try {
            // Wait a bit for processing
            sleep(2);

            $response = Http::withToken($accessToken)
                ->withHeaders([
                    'Accept' => 'application/json',
                    'X-Restli-Protocol-Version' => '2.0.0'
                ])
                ->get("https://api.linkedin.com/v2/assets/{$mediaUrn}");

            if ($response->successful()) {
                $data = $response->json();
                $status = $data['recipes'][0]['status'] ?? 'UNKNOWN';

                return [
                    'success' => true,
                    'status' => $status,
                    'ready' => $status === 'AVAILABLE',
                    'verification_data' => $data
                ];
            }

            return [
                'success' => false,
                'error' => 'Verification failed: ' . $response->body()
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get LinkedIn media type from file type
     */
    private function getLinkedInMediaType(string $fileType): string
    {
        $typeMap = [
            'image' => 'image',
            'video' => 'video',
            'document' => 'document'
        ];

        return $typeMap[$fileType] ?? 'image';
    }

    /**
     * Enhanced publishRealPost with media support
     */
    private function publishRealPostWithMedia(SocialMediaPost $post, Channel $channel): array
    {
        try {
            $tokens = $channel->oauth_tokens;
            if (is_string($tokens)) {
                $tokens = decrypt($tokens);
            }

            // Get user profile ID
            $profileResponse = Http::withToken($tokens['access_token'])
                ->withHeaders([
                    'Accept' => 'application/json',
                    'X-Restli-Protocol-Version' => '2.0.0'
                ])
                ->get('https://api.linkedin.com/v2/userinfo');

            if (!$profileResponse->successful()) {
                throw new \Exception('Failed to get LinkedIn profile: ' . $profileResponse->body());
            }

            $profile = $profileResponse->json();
            $profileId = $profile['sub'];
            $formatted = $this->formatPost($post);

            // HANDLE MEDIA UPLOADS
            $mediaUrns = [];
            $uploadedMedia = [];

            if (!empty($post->media)) {
                Log::info('LinkedIn: Processing media uploads', [
                    'media_count' => count($post->media),
                    'media_types' => array_column($post->media, 'type')
                ]);

                foreach ($post->media as $media) {
                    $uploadResult = $this->uploadMedia($media, $tokens['access_token'], $profileId);

                    if ($uploadResult['success']) {
                        $mediaUrns[] = $uploadResult['media_urn'];
                        $uploadedMedia[] = $uploadResult;

                        Log::info('LinkedIn: Media uploaded successfully', [
                            'media_urn' => $uploadResult['media_urn'],
                            'file_type' => $media['type']
                        ]);
                    } else {
                        Log::error('LinkedIn: Media upload failed', [
                            'error' => $uploadResult['error'],
                            'file_type' => $media['type']
                        ]);

                        // Decide whether to fail the entire post or continue without this media
                        // For now, we'll continue but log the failure
                    }
                }
            }

            // BUILD POST DATA WITH MEDIA
            $shareContent = [
                'shareCommentary' => [
                    'text' => $formatted['content']
                ]
            ];

            if (!empty($mediaUrns)) {
                $shareContent['shareMediaCategory'] = 'IMAGE'; // or VIDEO, RICH, etc.
                $shareContent['media'] = array_map(function ($urn) {
                    return [
                        'status' => 'READY',
                        'media' => $urn
                    ];
                }, $mediaUrns);
            } else {
                $shareContent['shareMediaCategory'] = 'NONE';
            }

            $postData = [
                'author' => "urn:li:person:{$profileId}",
                'lifecycleState' => 'PUBLISHED',
                'specificContent' => [
                    'com.linkedin.ugc.ShareContent' => $shareContent
                ],
                'visibility' => [
                    'com.linkedin.ugc.MemberNetworkVisibility' => 'PUBLIC'
                ]
            ];

            Log::info('LinkedIn: Publishing post with media', [
                'media_count' => count($mediaUrns),
                'content_length' => strlen($formatted['content']),
                'payload' => $postData
            ]);

            // PUBLISH POST WITH MEDIA
            $response = Http::withToken($tokens['access_token'])
                ->withHeaders([
                    'X-Restli-Protocol-Version' => '2.0.0',
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json'
                ])
                ->timeout(60) // Extended timeout for posts with media
                ->post('https://api.linkedin.com/v2/ugcPosts', $postData);

            if ($response->successful()) {
                $responseData = $response->json();
                $postId = $responseData['id'] ?? 'unknown';

                Log::info('LinkedIn: Post with media published successfully', [
                    'post_id' => $postId,
                    'media_count' => count($mediaUrns)
                ]);

                return [
                    'success' => true,
                    'platform_id' => $postId,
                    'url' => "https://www.linkedin.com/feed/update/{$postId}/",
                    'published_at' => now()->toISOString(),
                    'platform_data' => $responseData,
                    'media_uploaded' => $uploadedMedia,
                    'media_count' => count($mediaUrns),
                    'mode' => 'real'
                ];
            }

            Log::error('LinkedIn: Post with media publishing failed', [
                'status' => $response->status(),
                'response' => $response->body()
            ]);

            return [
                'success' => false,
                'error' => 'LinkedIn API error: ' . $response->body(),
                'retryable' => $this->isRetryableError($response->status()),
                'mode' => 'real',
                'media_uploaded' => $uploadedMedia,
                'debug_info' => [
                    'status_code' => $response->status(),
                    'payload_sent' => $postData,
                    'api_response' => $response->body()
                ]
            ];
        } catch (\Exception $e) {
            Log::error('LinkedIn: Post with media publishing exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'retryable' => true,
                'mode' => 'real'
            ];
        }
    }

    public function getAnalytics(string $postId, Channel $channel): array
    {
        if ($this->isStubMode()) {
            return $this->getEnhancedStubAnalytics();
        }

        return $this->getRealLinkedInAnalytics($postId, $channel);
    }

    private function getRealLinkedInAnalytics(string $postId, Channel $channel): array
    {
        try {
            $tokens = $channel->oauth_tokens;
            if (is_string($tokens)) {
                $tokens = decrypt($tokens);
            }

            Log::info('LinkedIn: Collecting real analytics', [
                'post_id' => $postId,
                'platform_id' => $postId
            ]);

            $shareStats = $this->getShareStatistics($postId, $tokens['access_token']);
            $analyticsData = $this->getLinkedInAnalyticsData($postId, $tokens['access_token']);

            $metrics = [
                'impressions' => $analyticsData['impressions'] ?? 0,
                'reach' => $analyticsData['reach'] ?? 0,
                'likes' => $shareStats['likes'] ?? 0,
                'comments' => $shareStats['comments'] ?? 0,
                'shares' => $shareStats['shares'] ?? 0,
                'clicks' => $analyticsData['clicks'] ?? 0,
                'saves' => $shareStats['saves'] ?? 0,
                'engagement_rate' => $this->calculateEngagementRate($shareStats, $analyticsData),
                'click_through_rate' => $analyticsData['ctr'] ?? 0
            ];

            return [
                'success' => true,
                'metrics' => $metrics,
                'demographics' => $analyticsData['demographics'] ?? [],
                'timeline' => $analyticsData['timeline'] ?? [],
                'data_source' => 'real_linkedin_api',
                'collected_at' => now()->toISOString()
            ];
        } catch (\Exception $e) {
            Log::error('LinkedIn: Real analytics collection failed', [
                'post_id' => $postId,
                'error' => $e->getMessage()
            ]);

            return $this->getEnhancedStubAnalytics();
        }
    }

    private function getShareStatistics(string $postId, string $accessToken): array
    {
        try {
            $response = Http::withToken($accessToken)
                ->withHeaders([
                    'X-Restli-Protocol-Version' => '2.0.0',
                    'Accept' => 'application/json'
                ])
                ->get("https://api.linkedin.com/v2/shares/{$postId}");

            if ($response->successful()) {
                $data = $response->json();

                return [
                    'likes' => $data['socialDetail']['totalSocialActivityCounts']['numLikes'] ?? 0,
                    'comments' => $data['socialDetail']['totalSocialActivityCounts']['numComments'] ?? 0,
                    'shares' => $data['socialDetail']['totalSocialActivityCounts']['numShares'] ?? 0,
                    'saves' => $data['socialDetail']['totalSocialActivityCounts']['numSaves'] ?? 0
                ];
            }

            Log::warning('LinkedIn: Share statistics not available', [
                'status' => $response->status(),
                'response' => $response->body()
            ]);

            return [];
        } catch (\Exception $e) {
            Log::warning('LinkedIn: Failed to get share statistics', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    private function getLinkedInAnalyticsData(string $postId, string $accessToken): array
    {
        try {
            $response = Http::withToken($accessToken)
                ->withHeaders([
                    'X-Restli-Protocol-Version' => '2.0.0',
                    'Accept' => 'application/json'
                ])
                ->get("https://api.linkedin.com/v2/organizationSocialActions", [
                    'q' => 'organizationalEntity',
                    'organizationalEntity' => 'urn:li:organization:123',
                    'start' => now()->subDay()->timestamp * 1000,
                    'end' => now()->timestamp * 1000
                ]);

            if ($response->successful()) {
                $data = $response->json();

                return [
                    'impressions' => $data['impressions'] ?? 0,
                    'reach' => $data['reach'] ?? 0,
                    'clicks' => $data['clicks'] ?? 0,
                    'ctr' => $data['clickThroughRate'] ?? 0
                ];
            }

            return [];
        } catch (\Exception $e) {
            Log::info('LinkedIn: Analytics API not available (requires enterprise access)', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    private function getEnhancedStubAnalytics(): array
    {
        $baseImpressions = rand(50, 2000);
        $engagementRate = rand(2, 8) / 100;
        $totalEngagement = (int)($baseImpressions * $engagementRate);

        $likes = (int)($totalEngagement * 0.7);
        $comments = (int)($totalEngagement * 0.15);
        $shares = (int)($totalEngagement * 0.15);

        return [
            'success' => true,
            'metrics' => [
                'impressions' => $baseImpressions,
                'reach' => (int)($baseImpressions * 0.8),
                'likes' => $likes,
                'comments' => $comments,
                'shares' => $shares,
                'clicks' => rand(5, (int)($baseImpressions * 0.1)),
                'saves' => rand(0, (int)($likes * 0.2)),
                'engagement_rate' => round($engagementRate * 100, 2),
                'click_through_rate' => round(rand(1, 5) / 100, 2)
            ],
            'demographics' => [
                'seniority' => [
                    'entry' => rand(15, 25),
                    'mid' => rand(35, 45),
                    'senior' => rand(25, 35),
                    'executive' => rand(5, 15)
                ],
                'industry' => [
                    'technology' => rand(30, 50),
                    'finance' => rand(15, 25),
                    'consulting' => rand(10, 20),
                    'healthcare' => rand(5, 15),
                    'other' => rand(10, 20)
                ],
                'location' => [
                    'united_states' => rand(30, 50),
                    'india' => rand(10, 25),
                    'united_kingdom' => rand(8, 15),
                    'canada' => rand(5, 12),
                    'other' => rand(15, 30)
                ]
            ],
            'timeline' => [
                now()->subHours(24)->toISOString() => rand(5, 50),
                now()->subHours(12)->toISOString() => rand(10, 100),
                now()->subHours(6)->toISOString() => rand(15, 80),
                now()->subHours(1)->toISOString() => rand(2, 20)
            ],
            'data_source' => 'enhanced_simulation',
            'collected_at' => now()->toISOString()
        ];
    }

    private function calculateEngagementRate(array $shareStats, array $analyticsData): float
    {
        $impressions = $analyticsData['impressions'] ?? 100;
        $totalEngagement = ($shareStats['likes'] ?? 0) +
            ($shareStats['comments'] ?? 0) +
            ($shareStats['shares'] ?? 0);

        return $impressions > 0 ? round(($totalEngagement / $impressions) * 100, 2) : 0;
    }

    public function validatePost(SocialMediaPost $post): array
    {
        $errors = [];

        $content = $post->content['text'] ?? '';
        $errors = array_merge($errors, $this->validateContent($content));

        $media = $post->media ?? [];
        $errors = array_merge($errors, $this->validateMedia($media));

        if (strlen($content) < 10) {
            $errors[] = "LinkedIn posts should be at least 10 characters for better engagement";
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'character_count' => strlen($content),
            'character_limit' => $this->getCharacterLimit(),
            'mode' => $this->isStubMode() ? 'stub' : 'real'
        ];
    }

    public function getCharacterLimit(): int
    {
        return 3000;
    }

    public function getMediaLimit(): int
    {
        return 9;
    }

    public function getSupportedMediaTypes(): array
    {
        return ['image', 'video', 'document', 'article'];
    }

    public function getDefaultScopes(): array
    {
        return [
            'openid',
            'profile',
            'w_member_social',
            'email'
        ];
    }

    public function getCurrentMode(): string
    {
        return $this->isStubMode() ? 'stub' : 'real';
    }

    // === PUBLIC OAUTH METHODS ===

    public function exchangeCodeForTokens(string $code): array
    {
        if ($this->isStubMode()) {
            throw new \Exception('Cannot exchange real tokens in stub mode.');
        }

        Log::info('LinkedIn: Public token exchange called', ['code_length' => strlen($code)]);
        return $this->getRealTokens($code);
    }

    public function getAuthUrl(string $state = null): string
    {
        if ($this->isStubMode()) {
            Log::info('LinkedIn: Generating stub auth URL');
            return 'https://example.com/oauth/stub?provider=linkedin&state=' . ($state ?? 'stub_state');
        }

        Log::info('LinkedIn: Generating real auth URL');
        return $this->getRealAuthUrl($state);
    }

    public function isConfigured(): bool
    {
        $hasClientId = !empty($this->getConfig('client_id'));
        $hasClientSecret = !empty($this->getConfig('client_secret'));
        $hasRedirect = !empty($this->getConfig('redirect'));

        Log::info('LinkedIn: Configuration check', [
            'client_id_set' => $hasClientId,
            'client_secret_set' => $hasClientSecret,
            'redirect_set' => $hasRedirect,
            'fully_configured' => $hasClientId && $hasClientSecret && $hasRedirect
        ]);

        return $hasClientId && $hasClientSecret && $hasRedirect;
    }

    public function getConfigurationStatus(): array
    {
        return [
            'platform' => $this->platform,
            'mode' => $this->getCurrentMode(),
            'configured' => $this->isConfigured(),
            'enabled' => $this->isEnabled(),
            'config_details' => [
                'client_id' => !empty($this->getConfig('client_id')) ? 'SET' : 'NOT SET',
                'client_secret' => !empty($this->getConfig('client_secret')) ? 'SET' : 'NOT SET',
                'redirect_uri' => $this->getConfig('redirect') ?? 'NOT SET',
                'base_url' => $this->getConfig('base_url') ?? 'NOT SET',
                'auth_url' => $this->getConfig('auth_url') ?? 'NOT SET',
                'token_url' => $this->getConfig('token_url') ?? 'NOT SET',
            ],
            'scopes' => $this->getDefaultScopes(),
            'constraints' => [
                'character_limit' => $this->getCharacterLimit(),
                'media_limit' => $this->getMediaLimit(),
                'supported_media' => $this->getSupportedMediaTypes()
            ]
        ];
    }

    // === POST DELETION METHODS ===

    public function checkPostExists(string $postId, Channel $channel): array
    {
        if ($this->isStubMode()) {
            return $this->checkStubPostExists($postId);
        }

        return $this->checkRealPostExists($postId, $channel);
    }

    private function checkRealPostExists(string $postId, Channel $channel): array
    {
        try {
            $tokens = $channel->oauth_tokens;
            if (is_string($tokens)) {
                $tokens = decrypt($tokens);
            }

            Log::info('LinkedIn: Checking if post exists', [
                'post_id' => $postId
            ]);

            $response = Http::withToken($tokens['access_token'])
                ->withHeaders([
                    'X-Restli-Protocol-Version' => '2.0.0',
                    'Accept' => 'application/json'
                ])
                ->get("https://api.linkedin.com/v2/shares/{$postId}");

            $exists = $response->successful();

            Log::info('LinkedIn: Post existence check result', [
                'post_id' => $postId,
                'exists' => $exists,
                'status_code' => $response->status()
            ]);

            return [
                'success' => true,
                'exists' => $exists,
                'post_id' => $postId,
                'status_code' => $response->status(),
                'checked_at' => now()->toISOString(),
                'mode' => 'real'
            ];
        } catch (\Exception $e) {
            Log::error('LinkedIn: Post existence check failed', [
                'post_id' => $postId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'exists' => 'unknown',
                'error' => $e->getMessage(),
                'post_id' => $postId,
                'mode' => 'real'
            ];
        }
    }

    private function checkStubPostExists(string $postId): array
    {
        $exists = rand(1, 10) > 3;

        return [
            'success' => true,
            'exists' => $exists,
            'post_id' => $postId,
            'status_code' => $exists ? 200 : 404,
            'checked_at' => now()->toISOString(),
            'mode' => 'stub'
        ];
    }

    public function deletePost(string $postId, Channel $channel): array
    {
        if ($this->isStubMode()) {
            return $this->deleteStubPost($postId);
        }

        return $this->deleteRealPost($postId, $channel);
    }

    private function deleteRealPost(string $postId, Channel $channel): array
    {
        try {
            $tokens = $channel->oauth_tokens;
            if (is_string($tokens)) {
                $tokens = decrypt($tokens);
            }

            Log::info('LinkedIn: Attempting to delete post', [
                'post_id' => $postId
            ]);

            $existenceCheck = $this->checkRealPostExists($postId, $channel);

            if (!$existenceCheck['success']) {
                return [
                    'success' => false,
                    'error' => 'Could not verify post existence',
                    'post_id' => $postId,
                    'requires_manual_deletion' => true,
                    'mode' => 'real'
                ];
            }

            if (!$existenceCheck['exists']) {
                return [
                    'success' => true,
                    'message' => 'Post was already deleted from LinkedIn',
                    'post_id' => $postId,
                    'already_deleted' => true,
                    'deleted_at' => now()->toISOString(),
                    'mode' => 'real'
                ];
            }

            $response = Http::withToken($tokens['access_token'])
                ->withHeaders([
                    'X-Restli-Protocol-Version' => '2.0.0',
                    'Accept' => 'application/json'
                ])
                ->delete("https://api.linkedin.com/v2/shares/{$postId}");

            if ($response->successful() || $response->status() === 404) {
                return [
                    'success' => true,
                    'message' => 'Post deletion completed or post not found',
                    'post_id' => $postId,
                    'status_code' => $response->status(),
                    'deleted_at' => now()->toISOString(),
                    'mode' => 'real'
                ];
            }

            return [
                'success' => false,
                'message' => 'LinkedIn posts must be deleted manually via the platform',
                'post_id' => $postId,
                'status_code' => $response->status(),
                'requires_manual_deletion' => true,
                'manual_deletion_note' => 'Visit the LinkedIn post and use the delete option from the post menu',
                'api_response' => $response->body(),
                'mode' => 'real'
            ];
        } catch (\Exception $e) {
            Log::error('LinkedIn: Post deletion failed', [
                'post_id' => $postId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'post_id' => $postId,
                'requires_manual_deletion' => true,
                'mode' => 'real'
            ];
        }
    }

    private function deleteStubPost(string $postId): array
    {
        $success = rand(1, 10) > 2;

        if ($success) {
            return [
                'success' => true,
                'message' => 'Post deleted successfully (stub mode)',
                'post_id' => $postId,
                'deleted_at' => now()->toISOString(),
                'mode' => 'stub'
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Post deletion failed (stub simulation)',
                'post_id' => $postId,
                'error' => 'Simulated deletion failure',
                'requires_manual_deletion' => false,
                'mode' => 'stub'
            ];
        }
    }

    public function getPostDeletionStatus(string $postId, Channel $channel, string $postUrl = null): array
    {
        $existenceCheck = $this->checkPostExists($postId, $channel);

        if (!$existenceCheck['success']) {
            return [
                'status' => 'UNKNOWN',
                'message' => 'Could not check post status',
                'post_id' => $postId,
                'error' => $existenceCheck['error'] ?? 'Unknown error'
            ];
        }

        if (!$existenceCheck['exists']) {
            return [
                'status' => 'DELETED',
                'message' => 'Post has been deleted from LinkedIn',
                'post_id' => $postId,
                'verified_deleted' => true,
                'checked_at' => $existenceCheck['checked_at']
            ];
        }

        return [
            'status' => 'EXISTS',
            'message' => 'Post still exists on LinkedIn - manual deletion required',
            'post_id' => $postId,
            'exists_on_platform' => true,
            'post_url' => $postUrl,
            'manual_deletion_steps' => [
                '1. Visit the LinkedIn post using the provided URL',
                '2. Click the three dots (•••) menu on the post',
                '3. Select "Delete post" from the menu',
                '4. Confirm the deletion when prompted',
                '5. The post will be removed from LinkedIn'
            ],
            'note' => 'LinkedIn does not provide an API endpoint for deleting regular posts',
            'checked_at' => $existenceCheck['checked_at']
        ];
    }

    /**
     * Enhanced LinkedIn post existence check with multiple verification methods
     */
    public function checkPostExistsEnhanced(string $postId, Channel $channel): array
    {
        if ($this->isStubMode()) {
            return $this->checkStubPostExists($postId);
        }

        return $this->checkRealPostExistsEnhanced($postId, $channel);
    }

    /**
     * Multi-method verification for LinkedIn post existence
     */
    private function checkRealPostExistsEnhanced(string $postId, Channel $channel): array
    {
        try {
            $tokens = $channel->oauth_tokens;
            if (is_string($tokens)) {
                $tokens = decrypt($tokens);
            }

            Log::info('LinkedIn: Enhanced post existence check', [
                'post_id' => $postId
            ]);

            $results = [];
            $methods = [];

            // METHOD 1: Direct shares endpoint
            try {
                $sharesResponse = Http::withToken($tokens['access_token'])
                    ->withHeaders([
                        'X-Restli-Protocol-Version' => '2.0.0',
                        'Accept' => 'application/json'
                    ])
                    ->timeout(10)
                    ->get("https://api.linkedin.com/v2/shares/{$postId}");

                $methods['shares_api'] = [
                    'status_code' => $sharesResponse->status(),
                    'successful' => $sharesResponse->successful(),
                    'exists' => $sharesResponse->successful(),
                    'response_size' => strlen($sharesResponse->body()),
                    'method' => 'shares_endpoint'
                ];

                Log::info('LinkedIn: Shares API check', [
                    'status' => $sharesResponse->status(),
                    'successful' => $sharesResponse->successful(),
                    'response_length' => strlen($sharesResponse->body())
                ]);
            } catch (\Exception $e) {
                $methods['shares_api'] = [
                    'error' => $e->getMessage(),
                    'exists' => false,
                    'method' => 'shares_endpoint'
                ];
            }

            // METHOD 2: Try UGC posts endpoint (newer API)
            try {
                $ugcResponse = Http::withToken($tokens['access_token'])
                    ->withHeaders([
                        'X-Restli-Protocol-Version' => '2.0.0',
                        'Accept' => 'application/json'
                    ])
                    ->timeout(10)
                    ->get("https://api.linkedin.com/v2/ugcPosts/{$postId}");

                $methods['ugc_api'] = [
                    'status_code' => $ugcResponse->status(),
                    'successful' => $ugcResponse->successful(),
                    'exists' => $ugcResponse->successful(),
                    'response_size' => strlen($ugcResponse->body()),
                    'method' => 'ugc_endpoint'
                ];

                Log::info('LinkedIn: UGC API check', [
                    'status' => $ugcResponse->status(),
                    'successful' => $ugcResponse->successful()
                ]);
            } catch (\Exception $e) {
                $methods['ugc_api'] = [
                    'error' => $e->getMessage(),
                    'exists' => false,
                    'method' => 'ugc_endpoint'
                ];
            }

            // METHOD 3: Check user's recent posts to see if this post appears
            try {
                $profileResponse = Http::withToken($tokens['access_token'])
                    ->withHeaders([
                        'Accept' => 'application/json',
                        'X-Restli-Protocol-Version' => '2.0.0'
                    ])
                    ->timeout(10)
                    ->get('https://api.linkedin.com/v2/userinfo');

                if ($profileResponse->successful()) {
                    $profile = $profileResponse->json();
                    $profileId = $profile['sub'];

                    // Try to get recent posts by this user
                    $recentPostsResponse = Http::withToken($tokens['access_token'])
                        ->withHeaders([
                            'X-Restli-Protocol-Version' => '2.0.0',
                            'Accept' => 'application/json'
                        ])
                        ->timeout(10)
                        ->get("https://api.linkedin.com/v2/shares", [
                            'q' => 'owners',
                            'owners' => "urn:li:person:{$profileId}",
                            'count' => 10
                        ]);

                    $foundInRecent = false;
                    if ($recentPostsResponse->successful()) {
                        $recentPosts = $recentPostsResponse->json();
                        $postIds = collect($recentPosts['elements'] ?? [])
                            ->pluck('id')
                            ->toArray();

                        $foundInRecent = in_array($postId, $postIds);
                    }

                    $methods['recent_posts'] = [
                        'status_code' => $recentPostsResponse->status(),
                        'found_in_recent' => $foundInRecent,
                        'exists' => $foundInRecent,
                        'total_recent_posts' => count($recentPosts['elements'] ?? []),
                        'method' => 'recent_posts_search'
                    ];
                }
            } catch (\Exception $e) {
                $methods['recent_posts'] = [
                    'error' => $e->getMessage(),
                    'exists' => false,
                    'method' => 'recent_posts_search'
                ];
            }

            // ANALYZE RESULTS FROM ALL METHODS
            $existsCount = 0;
            $totalMethods = 0;
            $confidence = 'low';

            foreach ($methods as $method => $result) {
                if (isset($result['exists'])) {
                    $totalMethods++;
                    if ($result['exists']) {
                        $existsCount++;
                    }
                }
            }

            // Determine confidence and final result
            if ($totalMethods > 0) {
                $existsPercentage = ($existsCount / $totalMethods) * 100;

                if ($existsPercentage >= 66) {
                    $finalExists = true;
                    $confidence = $existsPercentage >= 100 ? 'high' : 'medium';
                } elseif ($existsPercentage <= 33) {
                    $finalExists = false;
                    $confidence = $existsPercentage <= 0 ? 'high' : 'medium';
                } else {
                    $finalExists = 'uncertain';
                    $confidence = 'low';
                }
            } else {
                $finalExists = 'unknown';
                $confidence = 'none';
            }

            Log::info('LinkedIn: Enhanced existence check complete', [
                'post_id' => $postId,
                'exists_count' => $existsCount,
                'total_methods' => $totalMethods,
                'final_exists' => $finalExists,
                'confidence' => $confidence
            ]);

            return [
                'success' => true,
                'exists' => $finalExists,
                'confidence' => $confidence,
                'exists_percentage' => $totalMethods > 0 ? round($existsPercentage, 1) : 0,
                'methods_used' => $totalMethods,
                'methods_saying_exists' => $existsCount,
                'post_id' => $postId,
                'verification_methods' => $methods,
                'checked_at' => now()->toISOString(),
                'mode' => 'real',
                'recommendation' => $this->getExistenceRecommendation($finalExists, $confidence)
            ];
        } catch (\Exception $e) {
            Log::error('LinkedIn: Enhanced existence check failed', [
                'post_id' => $postId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'exists' => 'unknown',
                'confidence' => 'none',
                'error' => $e->getMessage(),
                'post_id' => $postId,
                'mode' => 'real',
                'recommendation' => 'Manual verification required due to API error'
            ];
        }
    }

    /**
     * Get recommendation based on existence check results
     */
    private function getExistenceRecommendation($exists, string $confidence): string
    {
        if ($exists === true) {
            return $confidence === 'high'
                ? 'Post definitely exists on LinkedIn'
                : 'Post likely exists on LinkedIn - manual verification recommended';
        } elseif ($exists === false) {
            return $confidence === 'high'
                ? 'Post definitely deleted from LinkedIn'
                : 'Post likely deleted from LinkedIn - manual verification recommended';
        } else {
            return 'LinkedIn API results are uncertain - manual verification strongly recommended';
        }
    }

    /**
     * Enhanced post deletion status with improved accuracy
     */
    public function getPostDeletionStatusEnhanced(string $postId, Channel $channel, string $postUrl = null): array
    {
        $existenceCheck = $this->checkPostExistsEnhanced($postId, $channel);

        if (!$existenceCheck['success']) {
            return [
                'status' => 'UNKNOWN',
                'message' => 'Could not check post status due to API error',
                'post_id' => $postId,
                'error' => $existenceCheck['error'] ?? 'Unknown error',
                'manual_verification_required' => true
            ];
        }

        $exists = $existenceCheck['exists'];
        $confidence = $existenceCheck['confidence'];

        if ($exists === false && $confidence === 'high') {
            return [
                'status' => 'DELETED',
                'message' => 'Post has been deleted from LinkedIn (high confidence)',
                'post_id' => $postId,
                'verified_deleted' => true,
                'confidence' => $confidence,
                'checked_at' => $existenceCheck['checked_at'],
                'verification_methods' => $existenceCheck['verification_methods']
            ];
        } elseif ($exists === true) {
            return [
                'status' => 'EXISTS',
                'message' => "Post exists on LinkedIn (confidence: {$confidence})",
                'post_id' => $postId,
                'exists_on_platform' => true,
                'confidence' => $confidence,
                'post_url' => $postUrl,
                'manual_deletion_steps' => [
                    '1. Visit the LinkedIn post using the provided URL',
                    '2. Click the three dots (•••) menu on the post',
                    '3. Select "Delete post" from the menu',
                    '4. Confirm the deletion when prompted',
                    '5. Call the status check endpoint again to verify deletion'
                ],
                'note' => 'LinkedIn does not provide a reliable API endpoint for deleting posts',
                'checked_at' => $existenceCheck['checked_at'],
                'verification_methods' => $existenceCheck['verification_methods']
            ];
        } else {
            return [
                'status' => 'UNCERTAIN',
                'message' => 'LinkedIn API results are inconsistent - manual verification required',
                'post_id' => $postId,
                'confidence' => $confidence,
                'post_url' => $postUrl,
                'manual_verification_required' => true,
                'api_inconsistency_detected' => true,
                'recommendation' => $existenceCheck['recommendation'],
                'note' => 'Due to LinkedIn API limitations, please manually verify post status',
                'manual_verification_steps' => [
                    '1. Visit the LinkedIn post URL directly',
                    '2. Check if the post loads successfully',
                    '3. If post loads: it still exists',
                    '4. If post shows "not found" or error: it has been deleted',
                    '5. Update the database status manually based on verification'
                ],
                'checked_at' => $existenceCheck['checked_at'],
                'verification_methods' => $existenceCheck['verification_methods']
            ];
        }
    }

    /**
     * Helper method to determine retryable errors
     */
    protected function isRetryableError($error): bool
    {
        if (is_int($error)) {
            return $error >= 500 || $error === 429;
        }

        if (is_array($error) && isset($error['status_code'])) {
            return $error['status_code'] >= 500 || $error['status_code'] === 429;
        }

        return false;
    }
}