<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Services\TenantService;
use App\Services\TenantJoinService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class OrganizationController extends Controller
{
    protected TenantService $tenantService;
    protected TenantJoinService $joinService;

    public function __construct(TenantService $tenantService, TenantJoinService $joinService)
    {
        $this->tenantService = $tenantService;
        $this->joinService = $joinService;
    }

    /**
     * Get list of user's organizations.
     */
    public function index(): JsonResponse
    {
        $user = Auth::user();
        $tenants = $this->tenantService->getUserTenants($user);

        return response()->json([
            'success' => true,
            'data' => $tenants->map(function ($tenant) use ($user) {
                $aclRole = $user->getRoleInTenant($tenant->id);
                $roleName = $aclRole ? $aclRole->name : ($tenant->pivot->role ?? 'user');
                
                return [
                    'id' => $tenant->id,
                    'name' => $tenant->name,
                    'slug' => $tenant->slug,
                    'code' => $tenant->code,
                    'description' => $tenant->description,
                    'plan' => $tenant->plan,
                    'is_owner' => $tenant->owner_id === $user->id,
                    'role' => $roleName,
                ];
            }),
            'can_create' => $user->canCreateTenant(),
        ]);
    }

    /**
     * Create a new organization.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
        ]);

        try {
            $tenant = $this->tenantService->store(
                $request->only(['name', 'description']),
                Auth::user()
            );

            return response()->json([
                'success' => true,
                'message' => 'Organization berhasil dibuat.',
                'data' => [
                    'id' => $tenant->id,
                    'name' => $tenant->name,
                    'slug' => $tenant->slug,
                    'code' => $tenant->code,
                ],
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get organization details.
     */
    public function show(Tenant $organization): JsonResponse
    {
        $user = Auth::user();

        // Check if user is member
        $membership = $organization->tenantUsers()->where('user_id', $user->id)->first();
        if (!$membership) {
            return response()->json([
                'success' => false,
                'message' => 'Anda bukan anggota organization ini.',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $organization->id,
                'name' => $organization->name,
                'slug' => $organization->slug,
                'code' => $organization->code,
                'description' => $organization->description,
                'plan' => $organization->plan,
                'is_owner' => $organization->owner_id === $user->id,
                'role' => $membership->role,
                'owner' => [
                    'id' => $organization->owner->id,
                    'name' => $organization->owner->name,
                    'email' => $organization->owner->email,
                ],
                'member_count' => $organization->tenantUsers()->count(),
            ],
        ]);
    }

    /**
     * Update organization.
     */
    public function update(Request $request, Tenant $organization): JsonResponse
    {
        $user = Auth::user();

        // Check if user is admin
        $membership = $organization->tenantUsers()
            ->where('user_id', $user->id)
            ->whereIn('role', ['admin'])
            ->first();

        if (!$membership) {
            return response()->json([
                'success' => false,
                'message' => 'Hanya admin yang dapat mengupdate organization.',
            ], 403);
        }

        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string|max:1000',
        ]);

        $tenant = $this->tenantService->update(
            $request->only(['name', 'description']),
            $organization
        );

        return response()->json([
            'success' => true,
            'message' => 'Organization berhasil diupdate.',
            'data' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
            ],
        ]);
    }

    /**
     * Delete organization.
     */
    public function destroy(Tenant $organization): JsonResponse
    {
        try {
            $this->tenantService->destroy($organization, Auth::user());

            return response()->json([
                'success' => true,
                'message' => 'Organization berhasil dihapus.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 403);
        }
    }

    /**
     * Join organization by code.
     */
    public function join(Request $request): JsonResponse
    {
        $request->validate([
            'code' => 'required|string',
        ]);

        try {
            $tenant = $this->joinService->joinByCode(
                $request->input('code'),
                Auth::user()
            );

            return response()->json([
                'success' => true,
                'message' => 'Berhasil bergabung ke organization.',
                'data' => [
                    'id' => $tenant->id,
                    'name' => $tenant->name,
                    'slug' => $tenant->slug,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Switch current organization.
     */
    public function switch(Request $request)
    {
        $request->validate([
            'organization_id' => 'nullable|string',
        ]);

        $user = Auth::user();
        $tenantId = $request->input('organization_id');

        $shouldReturnJson = $request->is('api/*') || $request->expectsJson();

        // If null, switch to personal mode
        if (empty($tenantId)) {
            session()->forget('current_tenant_id');
            session()->save();

            $user->current_tenant_id = null;
            $user->save();

            if ($shouldReturnJson) {
                return response()->json([
                    'success' => true,
                    'message' => 'Berhasil beralih ke mode personal.',
                    'data' => null,
                    'mode' => 'personal',
                ]);
            }
            return back()->with('success', 'Berhasil beralih ke mode personal.');
        }

        // Check if user is member of the tenant
        $tenant = $user->tenants()->where('tenants.id', $tenantId)->first();
        if (!$tenant) {
            if ($shouldReturnJson) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda bukan anggota organization ini.',
                ], 403);
            }
            return back()->withErrors(['error' => 'Anda bukan anggota organization ini.']);
        }

        session(['current_tenant_id' => $tenantId]);
        session()->save();

        $user->current_tenant_id = $tenantId;
        $user->save();

        if ($shouldReturnJson) {
            return response()->json([
                'success' => true,
                'message' => 'Berhasil beralih ke ' . $tenant->name,
                'data' => [
                    'id' => $tenant->id,
                    'name' => $tenant->name,
                    'slug' => $tenant->slug,
                    'role' => $tenant->pivot->role,
                ],
                'mode' => 'organization',
            ]);
        }

        return back()->with('success', 'Berhasil beralih ke ' . $tenant->name);
    }

    /**
     * Get current organization context.
     */
    public function current(): JsonResponse
    {
        $user = Auth::user();
        $tenant = $user->getCurrentTenant();

        if (!$tenant) {
            return response()->json([
                'success' => true,
                'data' => null,
                'mode' => 'personal',
            ]);
        }

        $aclRole = $user->getRoleInTenant($tenant->id);
        $roleName = $aclRole ? $aclRole->name : ($tenant->pivot->role ?? 'user');

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'role' => $roleName,
            ],
            'mode' => 'organization',
        ]);
    }
}
