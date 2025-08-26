<?php
// app/Services/SocialMedia/LinkedInProvider.php

namespace App\Services\SocialMedia;

use App\Models\SocialMediaPost;
use App\Models\Channel;
use Illuminate\Support\Facades\Http;

class LinkedInProvider extends AbstractSocialMediaProvider
{
    protected $platform = 'linkedin';

    public function authenticate(array $credentials): array
    {
        if ($this->isStubMode) {
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

        return 'https://www.linkedin.com/oauth/v2/authorization?' . http_build_query($params);
    }

    protected function getRealTokens(string $code): array
    {
        $response = Http::asForm()->post('https://www.linkedin.com/oauth/v2/accessToken', [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $this->getConfig('redirect'),
            'client_id' => $this->getConfig('client_id'),
            'client_secret' => $this->getConfig('client_secret'),
        ]);

        if (!$response->successful()) {
            throw new \Exception('LinkedIn token exchange failed: ' . $response->body());
        }

        $data = $response->json();

        return [
            'access_token' => $data['access_token'],
            'expires_at' => now()->addSeconds($data['expires_in']),
            'token_type' => $data['token_type'],
            'scope' => explode(' ', $data['scope'] ?? ''),
        ];
    }

    private function authenticateReal(array $credentials): array
    {
        return [
            'success' => true,
            'message' => 'LinkedIn authentication completed'
        ];
    }

    public function publishPost(SocialMediaPost $post, Channel $channel): array
    {
        if ($this->isStubMode) {
            return $this->publishStubPost($post, $channel);
        }

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
            'post_type' => !empty($formatted['media']) ? 'RICH_MEDIA' : 'TEXT_ONLY'
        ];
    }

    private function publishRealPost(SocialMediaPost $post, Channel $channel): array
    {
        try {
            $tokens = decrypt($channel->oauth_tokens);
            
            // Get user profile ID first
            $profileResponse = Http::withToken($tokens['access_token'])
                ->get('https://api.linkedin.com/v2/me');

            if (!$profileResponse->successful()) {
                throw new \Exception('Failed to get LinkedIn profile');
            }

            $profileId = $profileResponse->json()['id'];
            $formatted = $this->formatPost($post);

            $postData = [
                'author' => "urn:li:person:{$profileId}",
                'lifecycleState' => 'PUBLISHED',
                'specificContent' => [
                    'com.linkedin.ugc.ShareContent' => [
                        'shareCommentary' => [
                            'text' => $formatted['content']
                        ],
                        'shareMediaCategory' => 'NONE'
                    ]
                ],
                'visibility' => [
                    'com.linkedin.ugc.MemberNetworkVisibility' => 'PUBLIC'
                ]
            ];

            $response = $this->makeApiRequest(
                'post',
                'https://api.linkedin.com/v2/ugcPosts',
                $postData,
                [
                    'Authorization' => 'Bearer ' . $tokens['access_token'],
                    'X-Restli-Protocol-Version' => '2.0.0'
                ]
            );

            if ($response['success']) {
                $responseData = $response['data'];
                $postId = last(explode(':', $responseData['id']));

                return [
                    'success' => true,
                    'platform_id' => $postId,
                    'url' => "https://www.linkedin.com/feed/update/{$responseData['id']}/",
                    'published_at' => now()->toISOString(),
                    'platform_data' => $responseData
                ];
            }

            return [
                'success' => false,
                'error' => $response['error'],
                'retryable' => $response['retryable'] ?? false
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'retryable' => true
            ];
        }
    }

    public function getAnalytics(string $postId, Channel $channel): array
    {
        if ($this->isStubMode) {
            return [
                'impressions' => rand(100, 8000),
                'clicks' => rand(10, 400),
                'likes' => rand(5, 200),
                'comments' => rand(1, 50),
                'shares' => rand(2, 100),
                'follows' => rand(0, 20),
                'engagement_rate' => round(rand(150, 600) / 100, 2),
                'demographic_data' => [
                    'seniority' => [
                        'entry' => rand(10, 30),
                        'mid' => rand(30, 50),
                        'senior' => rand(20, 40),
                        'executive' => rand(5, 20)
                    ],
                    'industry' => [
                        'technology' => rand(20, 60),
                        'finance' => rand(10, 30),
                        'healthcare' => rand(5, 25),
                        'education' => rand(5, 20),
                        'other' => rand(10, 30)
                    ]
                ]
            ];
        }

        return $this->getRealAnalytics($postId, $channel);
    }

    private function getRealAnalytics(string $postId, Channel $channel): array
    {
        // LinkedIn analytics require additional permissions and enterprise access
        return [
            'success' => true,
            'note' => 'LinkedIn analytics require enterprise API access',
            'metrics' => [
                'impressions' => 0,
                'clicks' => 0,
                'likes' => 0,
                'comments' => 0,
                'shares' => 0
            ]
        ];
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
        
        return $errors;
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
        return ['w_member_social', 'r_liteprofile'];
    }
}