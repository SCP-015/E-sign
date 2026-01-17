<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use App\Helpers\ApiResponse;

class PortalSettingsController extends Controller
{
    public function show(Request $request)
    {
        $user = Auth::user();
        $tenantId = session('current_tenant_id') ?? $user->current_tenant_id;

        if (!$tenantId) {
            return ApiResponse::error('No organization selected', 400);
        }

        $tenant = Tenant::find($tenantId);
        if (!$tenant) {
            return ApiResponse::error('Organization not found', 404);
        }

        return ApiResponse::success([
            'id' => $tenant->id,
            'name' => $tenant->name,
            'code' => $tenant->code,
            'slug' => $tenant->slug,
            'description' => $tenant->description,
            'logo' => $tenant->logo,
            'banner' => $tenant->banner,
            'website' => $tenant->website,
            'phone' => $tenant->phone,
            'address' => $tenant->address,
            'facebook' => $tenant->facebook,
            'twitter' => $tenant->twitter,
            'instagram' => $tenant->instagram,
            'linkedin' => $tenant->linkedin,
        ]);
    }

    public function update(Request $request)
    {
        $user = Auth::user();
        $tenantId = session('current_tenant_id') ?? $user->current_tenant_id;

        if (!$tenantId) {
            return ApiResponse::error('No organization selected', 400);
        }

        if (!$user->hasPermissionInTenant('portal_settings.update', $tenantId)) {
            return ApiResponse::error('You do not have permission to update portal settings', 403);
        }

        $tenant = Tenant::find($tenantId);
        if (!$tenant) {
            return ApiResponse::error('Organization not found', 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string|max:1000',
            'website' => 'nullable|url|max:255',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:500',
            'facebook' => 'nullable|url|max:255',
            'twitter' => 'nullable|url|max:255',
            'instagram' => 'nullable|url|max:255',
            'linkedin' => 'nullable|url|max:255',
        ]);

        $tenant->update($validated);

        return ApiResponse::success($tenant, 'Portal settings updated successfully');
    }

    public function uploadLogo(Request $request)
    {
        $user = Auth::user();
        $tenantId = session('current_tenant_id') ?? $user->current_tenant_id;

        if (!$tenantId) {
            return ApiResponse::error('No organization selected', 400);
        }

        if (!$user->hasPermissionInTenant('portal_settings.update', $tenantId)) {
            return ApiResponse::error('You do not have permission to update portal settings', 403);
        }

        $request->validate([
            'logo' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
        ]);

        $tenant = Tenant::find($tenantId);
        if (!$tenant) {
            return ApiResponse::error('Organization not found', 404);
        }

        if ($tenant->logo) {
            Storage::disk('public')->delete($tenant->logo);
        }

        $path = $request->file('logo')->store('tenants/logos', 'public');
        $tenant->update(['logo' => $path]);

        return ApiResponse::success(['logo' => $path], 'Logo uploaded successfully');
    }

    public function uploadBanner(Request $request)
    {
        $user = Auth::user();
        $tenantId = session('current_tenant_id') ?? $user->current_tenant_id;

        if (!$tenantId) {
            return ApiResponse::error('No organization selected', 400);
        }

        if (!$user->hasPermissionInTenant('portal_settings.update', $tenantId)) {
            return ApiResponse::error('You do not have permission to update portal settings', 403);
        }

        $request->validate([
            'banner' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
        ]);

        $tenant = Tenant::find($tenantId);
        if (!$tenant) {
            return ApiResponse::error('Organization not found', 404);
        }

        if ($tenant->banner) {
            Storage::disk('public')->delete($tenant->banner);
        }

        $path = $request->file('banner')->store('tenants/banners', 'public');
        $tenant->update(['banner' => $path]);

        return ApiResponse::success(['banner' => $path], 'Banner uploaded successfully');
    }
}
