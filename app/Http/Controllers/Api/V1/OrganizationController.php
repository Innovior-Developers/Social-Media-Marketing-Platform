<?php
// app/Http/Controllers/Api/V1/OrganizationController.php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class OrganizationController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    /**
     * Display a listing of organizations
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Organization::query();

            // Apply filters
            if ($request->has('search')) {
                $query->where('name', 'like', '%' . $request->search . '%');
            }

            if ($request->has('features')) {
                $features = explode(',', $request->features);
                $query->where(function ($q) use ($features) {
                    foreach ($features as $feature) {
                        $q->where('settings.features', $feature);
                    }
                });
            }

            // Pagination
            $perPage = min($request->get('per_page', 15), 100);
            $organizations = $query->with(['brands'])->paginate($perPage);

            return response()->json([
                'status' => 'success',
                'data' => $organizations->items(),
                'meta' => [
                    'current_page' => $organizations->currentPage(),
                    'last_page' => $organizations->lastPage(),
                    'per_page' => $organizations->perPage(),
                    'total' => $organizations->total(),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve organizations',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created organization
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255|unique:organizations,name',
                'settings' => 'array',
                'settings.default_timezone' => 'string|timezone',
                'settings.features' => 'array',
                'settings.features.*' => 'string|in:analytics,scheduling,multi_brand,team_collaboration,advanced_reporting,api_access,white_label,priority_support'
            ]);

            // Set default settings if not provided
            $validated['settings'] = array_merge([
                'default_timezone' => 'UTC',
                'features' => ['analytics', 'scheduling']
            ], $validated['settings'] ?? []);

            $organization = Organization::create($validated);

            return response()->json([
                'status' => 'success',
                'message' => 'Organization created successfully',
                'data' => $organization->load(['brands'])
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
                'message' => 'Failed to create organization',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified organization
     */
    public function show(string $id): JsonResponse
    {
        try {
            $organization = Organization::with(['brands.channels', 'brands.memberships.user'])
                ->findOrFail($id);

            return response()->json([
                'status' => 'success',
                'data' => [
                    'organization' => $organization,
                    'stats' => [
                        'total_brands' => $organization->getTotalBrandsCount(),
                        'active_brands' => $organization->getActiveBrandsCount(),
                        'total_channels' => $organization->brands->sum(fn($brand) => $brand->channels->count()),
                        'connected_channels' => $organization->brands->sum(fn($brand) => $brand->getConnectedChannelsCount()),
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Organization not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Update the specified organization
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $organization = Organization::findOrFail($id);

            $validated = $request->validate([
                'name' => 'string|max:255|unique:organizations,name,' . $organization->_id,
                'settings' => 'array',
                'settings.default_timezone' => 'string|timezone',
                'settings.features' => 'array',
                'settings.features.*' => 'string|in:analytics,scheduling,multi_brand,team_collaboration,advanced_reporting,api_access,white_label,priority_support'
            ]);

            $organization->update($validated);

            return response()->json([
                'status' => 'success',
                'message' => 'Organization updated successfully',
                'data' => $organization->fresh(['brands'])
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
                'message' => 'Failed to update organization',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified organization
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $organization = Organization::findOrFail($id);

            // Check if organization has brands
            if ($organization->brands()->count() > 0) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Cannot delete organization with existing brands'
                ], 409);
            }

            $organization->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Organization deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete organization',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Add feature to organization
     */
    public function addFeature(Request $request, string $id): JsonResponse
    {
        try {
            $organization = Organization::findOrFail($id);

            $validated = $request->validate([
                'feature' => 'required|string|in:analytics,scheduling,multi_brand,team_collaboration,advanced_reporting,api_access,white_label,priority_support'
            ]);

            $organization->addFeature($validated['feature']);

            return response()->json([
                'status' => 'success',
                'message' => 'Feature added successfully',
                'data' => $organization->fresh()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to add feature',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
