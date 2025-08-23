<?php
// app/Http/Controllers/Api/V1/ChannelController.php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Channel;
use App\Models\Brand;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class ChannelController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    /**
     * Display a listing of channels
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Channel::query();

            // Filter by brand
            if ($request->has('brand_id')) {
                $query->where('brand_id', $request->brand_id);
            }

            // Filter by provider
            if ($request->has('provider')) {
                $query->byProvider($request->provider);
            }

            // Filter by connection status
            if ($request->has('connected')) {
                $connected = filter_var($request->connected, FILTER_VALIDATE_BOOLEAN);
                if ($connected) {
                    $query->connected();
                } else {
                    $query->where('connection_status', '!=', 'connected');
                }
            }

            // Filter active channels
            if ($request->has('active')) {
                $active = filter_var($request->active, FILTER_VALIDATE_BOOLEAN);
                if ($active) {
                    $query->active();
                } else {
                    $query->where('active', false);
                }
            }

            $perPage = min($request->get('per_page', 15), 100);
            $channels = $query->with(['brand'])
                ->latest('created_at')
                ->paginate($perPage);

            return response()->json([
                'status' => 'success',
                'data' => $channels->items(),
                'meta' => [
                    'current_page' => $channels->currentPage(),
                    'last_page' => $channels->lastPage(),
                    'per_page' => $channels->perPage(),
                    'total' => $channels->total(),
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve channels',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created channel
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'brand_id' => 'required|exists:brands,_id',
                'provider' => 'required|string|in:twitter,facebook,instagram,linkedin,youtube,tiktok',
                'handle' => 'required|string|max:255',
                'display_name' => 'required|string|max:255',
                'avatar_url' => 'string|url',
                'oauth_tokens' => 'array',
                'oauth_tokens.access_token' => 'string',
                'oauth_tokens.refresh_token' => 'string',
                'oauth_tokens.expires_at' => 'date',
                'active' => 'boolean'
            ]);

            // Check if channel with same provider and handle already exists for this brand
            $existingChannel = Channel::where('brand_id', $validated['brand_id'])
                ->where('provider', $validated['provider'])
                ->where('handle', $validated['handle'])
                ->first();

            if ($existingChannel) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Channel with this provider and handle already exists for this brand'
                ], 409);
            }

            $channel = Channel::create($validated);

            return response()->json([
                'status' => 'success',
                'message' => 'Channel created successfully',
                'data' => $channel->load(['brand'])
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create channel',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified channel
     */
    public function show(string $id): JsonResponse
    {
        try {
            $channel = Channel::with(['brand.organization'])->findOrFail($id);

            return response()->json([
                'status' => 'success',
                'data' => [
                    'channel' => $channel,
                    'provider_info' => [
                        'display_name' => $channel->getProviderDisplayName(),
                        'max_characters' => $channel->getMaxCharacters(),
                        'max_media' => $channel->getMaxMedia(),
                        'supported_media_types' => $channel->getSupportedMediaTypes(),
                        'rate_limits' => $channel->getRateLimits(),
                    ],
                    'connection_status' => [
                        'is_connected' => $channel->isConnected(),
                        'is_expired' => $channel->isExpired(),
                        'last_sync_at' => $channel->last_sync_at,
                        'connection_status' => $channel->connection_status,
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Channel not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Update the specified channel
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $channel = Channel::findOrFail($id);

            $validated = $request->validate([
                'handle' => 'string|max:255',
                'display_name' => 'string|max:255',
                'avatar_url' => 'string|url',
                'active' => 'boolean'
            ]);

            $channel->update($validated);

            return response()->json([
                'status' => 'success',
                'message' => 'Channel updated successfully',
                'data' => $channel->fresh(['brand'])
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update channel',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified channel
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $channel = Channel::findOrFail($id);
            
            // Mark as disconnected first
            $channel->markAsDisconnected();
            
            // Then delete
            $channel->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Channel deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete channel',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Connect channel (OAuth callback simulation)
     */
    public function connect(Request $request, string $id): JsonResponse
    {
        try {
            $channel = Channel::findOrFail($id);

            $validated = $request->validate([
                'access_token' => 'required|string',
                'refresh_token' => 'string',
                'expires_at' => 'date'
            ]);

            $channel->updateTokens($validated);
            $channel->markAsConnected();

            return response()->json([
                'status' => 'success',
                'message' => 'Channel connected successfully',
                'data' => $channel->fresh(['brand'])
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to connect channel',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Disconnect channel
     */
    public function disconnect(string $id): JsonResponse
    {
        try {
            $channel = Channel::findOrFail($id);
            
            $channel->markAsDisconnected();

            return response()->json([
                'status' => 'success',
                'message' => 'Channel disconnected successfully',
                'data' => $channel->fresh(['brand'])
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to disconnect channel',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Sync channel data
     */
    public function sync(string $id): JsonResponse
    {
        try {
            $channel = Channel::findOrFail($id);

            if (!$channel->isConnected()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Channel is not connected'
                ], 400);
            }

            // Update last sync time
            $channel->update(['last_sync_at' => now()]);

            // Here you would implement actual sync logic with the social media provider
            // For now, we'll just simulate it

            return response()->json([
                'status' => 'success',
                'message' => 'Channel synced successfully',
                'data' => [
                    'channel' => $channel->fresh(['brand']),
                    'sync_info' => [
                        'last_sync_at' => $channel->last_sync_at,
                        'sync_status' => 'completed',
                        'items_synced' => rand(5, 50) // Simulated
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to sync channel',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get available providers
     */
    public function providers(): JsonResponse
    {
        $providers = [
            'twitter' => [
                'name' => 'Twitter/X',
                'max_characters' => 280,
                'max_media' => 4,
                'supported_media_types' => ['image', 'video', 'gif'],
                'oauth_required' => true,
            ],
            'facebook' => [
                'name' => 'Facebook',
                'max_characters' => 63206,
                'max_media' => 10,
                'supported_media_types' => ['image', 'video'],
                'oauth_required' => true,
            ],
            'instagram' => [
                'name' => 'Instagram',
                'max_characters' => 2200,
                'max_media' => 10,
                'supported_media_types' => ['image', 'video'],
                'oauth_required' => true,
            ],
            'linkedin' => [
                'name' => 'LinkedIn',
                'max_characters' => 3000,
                'max_media' => 9,
                'supported_media_types' => ['image', 'video', 'document'],
                'oauth_required' => true,
            ],
            'youtube' => [
                'name' => 'YouTube',
                'max_characters' => 5000,
                'max_media' => 1,
                'supported_media_types' => ['video'],
                'oauth_required' => true,
            ],
            'tiktok' => [
                'name' => 'TikTok',
                'max_characters' => 300,
                'max_media' => 1,
                'supported_media_types' => ['video'],
                'oauth_required' => true,
            ],
        ];

        return response()->json([
            'status' => 'success',
            'data' => $providers
        ]);
    }
}