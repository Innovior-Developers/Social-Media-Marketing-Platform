<?php
// app/Http/Controllers/Api/V1/BrandController.php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class BrandController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    /**
     * Display a listing of brands
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Brand::query();

            // Filter by organization
            if ($request->has('organization_id')) {
                $query->byOrganization($request->organization_id);
            }

            // Filter by active status
            if ($request->has('active')) {
                $active = filter_var($request->active, FILTER_VALIDATE_BOOLEAN);
                if ($active) {
                    $query->active();
                } else {
                    $query->where('active', false);
                }
            }

            // Search
            if ($request->has('search')) {
                $query->where('name', 'like', '%' . $request->search . '%');
            }

            $perPage = min($request->get('per_page', 15), 100);
            $brands = $query->with(['organization', 'channels', 'memberships.user'])
                ->paginate($perPage);

            return response()->json([
                'status' => 'success',
                'data' => $brands->items(),
                'meta' => [
                    'current_page' => $brands->currentPage(),
                    'last_page' => $brands->lastPage(),
                    'per_page' => $brands->perPage(),
                    'total' => $brands->total(),
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve brands',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created brand
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'organization_id' => 'required|exists:organizations,_id',
                'name' => 'required|string|max:255',
                'slug' => 'string|max:255|unique:brands,slug',
                'active' => 'boolean',
                'settings' => 'array',
                'settings.timezone' => 'string|timezone',
                'settings.default_publish_time' => 'string|regex:/^\d{2}:\d{2}$/',
                'settings.branding' => 'array',
                'settings.branding.logo_url' => 'string|url',
                'settings.branding.primary_color' => 'string|regex:/^#[0-9a-f]{6}$/i'
            ]);

            // Generate slug if not provided
            if (!isset($validated['slug'])) {
                $validated['slug'] = \Illuminate\Support\Str::slug($validated['name']);
            }

            // Ensure slug is unique
            $originalSlug = $validated['slug'];
            $counter = 1;
            while (Brand::where('slug', $validated['slug'])->exists()) {
                $validated['slug'] = $originalSlug . '-' . $counter;
                $counter++;
            }

            $brand = Brand::create($validated);

            return response()->json([
                'status' => 'success',
                'message' => 'Brand created successfully',
                'data' => $brand->load(['organization', 'channels', 'memberships'])
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
                'message' => 'Failed to create brand',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified brand
     */
    public function show(string $id): JsonResponse
    {
        try {
            $brand = Brand::with([
                'organization',
                'channels',
                'memberships.user',
                'posts' => function($query) {
                    $query->latest()->limit(10);
                }
            ])->findOrFail($id);

            return response()->json([
                'status' => 'success',
                'data' => [
                    'brand' => $brand,
                    'stats' => [
                        'total_channels' => $brand->channels->count(),
                        'connected_channels' => $brand->getConnectedChannelsCount(),
                        'total_posts' => $brand->getTotalPostsCount(),
                        'this_month_posts' => $brand->getThisMonthPostsCount(),
                        'team_members' => $brand->memberships->count(),
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Brand not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Update the specified brand
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $brand = Brand::findOrFail($id);

            $validated = $request->validate([
                'name' => 'string|max:255',
                'slug' => 'string|max:255|unique:brands,slug,' . $brand->_id,
                'active' => 'boolean',
                'settings' => 'array',
                'settings.timezone' => 'string|timezone',
                'settings.default_publish_time' => 'string|regex:/^\d{2}:\d{2}$/',
                'settings.branding' => 'array',
                'settings.branding.logo_url' => 'string|url',
                'settings.branding.primary_color' => 'string|regex:/^#[0-9a-f]{6}$/i'
            ]);

            $brand->update($validated);

            return response()->json([
                'status' => 'success',
                'message' => 'Brand updated successfully',
                'data' => $brand->fresh(['organization', 'channels', 'memberships'])
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
                'message' => 'Failed to update brand',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified brand
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $brand = Brand::findOrFail($id);

            // Soft delete to preserve relationships
            $brand->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Brand deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete brand',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update brand branding
     */
    public function updateBranding(Request $request, string $id): JsonResponse
    {
        try {
            $brand = Brand::findOrFail($id);

            $validated = $request->validate([
                'logo_url' => 'string|url',
                'primary_color' => 'string|regex:/^#[0-9a-f]{6}$/i'
            ]);

            $brand->updateBranding($validated);

            return response()->json([
                'status' => 'success',
                'message' => 'Branding updated successfully',
                'data' => $brand->fresh()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update branding',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}