<?php
// app/Http/Controllers/Api/V1/MembershipController.php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Membership;
use App\Models\Brand;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class MembershipController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    /**
     * Display a listing of memberships
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Membership::query();

            // Filter by brand
            if ($request->has('brand_id')) {
                $query->where('brand_id', $request->brand_id);
            }

            // Filter by role
            if ($request->has('role')) {
                $query->byRole($request->role);
            }

            // Filter by user
            if ($request->has('user_id')) {
                $query->where('user_id', $request->user_id);
            }

            $perPage = min($request->get('per_page', 15), 100);
            $memberships = $query->with(['user', 'brand', 'invitedBy'])
                ->latest('joined_at')
                ->paginate($perPage);

            return response()->json([
                'status' => 'success',
                'data' => $memberships->items(),
                'meta' => [
                    'current_page' => $memberships->currentPage(),
                    'last_page' => $memberships->lastPage(),
                    'per_page' => $memberships->perPage(),
                    'total' => $memberships->total(),
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve memberships',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created membership (invite user)
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'user_id' => 'required|exists:users,_id',
                'brand_id' => 'required|exists:brands,_id',
                'role' => 'required|string|in:OWNER,MANAGER,EDITOR,VIEWER',
                'permissions' => 'array',
                'permissions.*' => 'string|in:manage_brand,manage_team,create_posts,edit_posts,delete_posts,schedule_posts,view_analytics,manage_channels'
            ]);

            // Check if membership already exists
            $existingMembership = Membership::where('user_id', $validated['user_id'])
                ->where('brand_id', $validated['brand_id'])
                ->first();

            if ($existingMembership) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'User is already a member of this brand'
                ], 409);
            }

            // Set invited_by to current user
            $validated['invited_by'] = $request->user()->_id;
            $validated['joined_at'] = now();

            $membership = Membership::create($validated);

            return response()->json([
                'status' => 'success',
                'message' => 'User invited successfully',
                'data' => $membership->load(['user', 'brand', 'invitedBy'])
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
                'message' => 'Failed to create membership',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified membership
     */
    public function show(string $id): JsonResponse
    {
        try {
            $membership = Membership::with(['user', 'brand.organization', 'invitedBy'])
                ->findOrFail($id);

            return response()->json([
                'status' => 'success',
                'data' => [
                    'membership' => $membership,
                    'permissions' => [
                        'role_permissions' => $membership->getRolePermissions(),
                        'custom_permissions' => $membership->permissions ?? [],
                        'all_permissions' => array_unique(array_merge(
                            $membership->getRolePermissions(),
                            $membership->permissions ?? []
                        )),
                        'can_manage_brand' => $membership->canManageBrand(),
                        'can_create_posts' => $membership->canCreatePosts(),
                        'can_manage_team' => $membership->canManageTeam(),
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Membership not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Update the specified membership
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $membership = Membership::findOrFail($id);

            $validated = $request->validate([
                'role' => 'string|in:OWNER,MANAGER,EDITOR,VIEWER',
                'permissions' => 'array',
                'permissions.*' => 'string|in:manage_brand,manage_team,create_posts,edit_posts,delete_posts,schedule_posts,view_analytics,manage_channels'
            ]);

            $membership->update($validated);

            return response()->json([
                'status' => 'success',
                'message' => 'Membership updated successfully',
                'data' => $membership->fresh(['user', 'brand', 'invitedBy'])
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
                'message' => 'Failed to update membership',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified membership
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $membership = Membership::findOrFail($id);

            // Prevent removing the last owner
            if ($membership->isOwner()) {
                $ownersCount = Membership::where('brand_id', $membership->brand_id)
                    ->where('role', 'OWNER')
                    ->count();

                if ($ownersCount <= 1) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Cannot remove the last owner of the brand'
                    ], 409);
                }
            }

            $membership->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Membership removed successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to remove membership',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get team members for a brand
     */
    public function teamMembers(Request $request, string $brandId): JsonResponse
    {
        try {
            $brand = Brand::findOrFail($brandId);
            
            $query = $brand->memberships()->with(['user', 'invitedBy']);

            // Filter by role
            if ($request->has('role')) {
                $query->byRole($request->role);
            }

            $memberships = $query->get();

            $teamStats = [
                'total_members' => $memberships->count(),
                'owners' => $memberships->where('role', 'OWNER')->count(),
                'managers' => $memberships->where('role', 'MANAGER')->count(),
                'editors' => $memberships->where('role', 'EDITOR')->count(),
                'viewers' => $memberships->where('role', 'VIEWER')->count(),
            ];

            return response()->json([
                'status' => 'success',
                'data' => [
                    'brand' => $brand,
                    'team_members' => $memberships,
                    'stats' => $teamStats
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve team members',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check user permissions for a brand
     */
    public function checkPermissions(Request $request, string $brandId): JsonResponse
    {
        try {
            $user = $request->user();
            $membership = Membership::where('user_id', $user->_id)
                ->where('brand_id', $brandId)
                ->first();

            if (!$membership) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'User is not a member of this brand'
                ], 403);
            }

            $permissions = [
                'manage_brand' => $membership->hasPermission('manage_brand'),
                'manage_team' => $membership->hasPermission('manage_team'),
                'create_posts' => $membership->hasPermission('create_posts'),
                'edit_posts' => $membership->hasPermission('edit_posts'),
                'delete_posts' => $membership->hasPermission('delete_posts'),
                'schedule_posts' => $membership->hasPermission('schedule_posts'),
                'view_analytics' => $membership->hasPermission('view_analytics'),
                'manage_channels' => $membership->hasPermission('manage_channels'),
            ];

            return response()->json([
                'status' => 'success',
                'data' => [
                    'membership' => $membership,
                    'permissions' => $permissions,
                    'role' => $membership->role,
                    'is_owner' => $membership->isOwner(),
                    'is_manager' => $membership->isManager(),
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to check permissions',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}