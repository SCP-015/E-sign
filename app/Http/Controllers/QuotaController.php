<?php

namespace App\Http\Controllers;

use App\Models\QuotaSetting;
use App\Models\UserQuotaUsage;
use App\Models\TenantUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Helpers\ApiResponse;

class QuotaController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $tenantId = session('current_tenant_id') ?? $user->current_tenant_id;

        if (!$tenantId) {
            return ApiResponse::error('No organization selected', 400);
        }

        $quotaSetting = QuotaSetting::getOrCreateForTenant($tenantId);

        $members = TenantUser::where('tenant_id', $tenantId)
            ->with('user:id,name,email,avatar')
            ->get();

        $usageData = [];
        foreach ($members as $member) {
            $usage = UserQuotaUsage::getOrCreateForUser($member->user_id, $tenantId);
            $usageData[] = [
                'user_id' => $member->user_id,
                'user' => $member->user,
                'role' => $member->role,
                'documents_uploaded' => $usage->documents_uploaded,
                'signatures_created' => $usage->signatures_created,
                'storage_used_mb' => $usage->storage_used_mb,
                'documents_remaining' => max(0, $quotaSetting->max_documents_per_user - $usage->documents_uploaded),
                'signatures_remaining' => max(0, $quotaSetting->max_signatures_per_user - $usage->signatures_created),
            ];
        }

        return ApiResponse::success([
            'quota_settings' => $quotaSetting,
            'usage' => $usageData,
        ]);
    }

    public function update(Request $request)
    {
        $user = Auth::user();
        $tenantId = session('current_tenant_id') ?? $user->current_tenant_id;

        if (!$tenantId) {
            return ApiResponse::error('No organization selected', 400);
        }

        if (!$user->hasPermissionInTenant('quota.manage', $tenantId)) {
            return ApiResponse::error('You do not have permission to manage quota', 403);
        }

        $validated = $request->validate([
            'max_documents_per_user' => 'required|integer|min:1|max:10000',
            'max_signatures_per_user' => 'required|integer|min:1|max:10000',
            'max_document_size_mb' => 'required|integer|min:1|max:100',
            'max_total_storage_mb' => 'required|integer|min:100|max:100000',
        ]);

        $quotaSetting = QuotaSetting::getOrCreateForTenant($tenantId);
        $quotaSetting->update($validated);

        return ApiResponse::success($quotaSetting, 'Quota settings updated successfully');
    }
}
