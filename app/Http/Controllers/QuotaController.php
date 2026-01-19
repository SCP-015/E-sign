<?php

namespace App\Http\Controllers;

use App\Models\QuotaSetting;
use App\Models\UserQuotaUsage;
use App\Models\UserQuotaOverride;
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

        $overrides = UserQuotaOverride::where('tenant_id', $tenantId)
            ->get()
            ->keyBy('user_id');

        $usageData = [];
        foreach ($members as $member) {
            $usage = UserQuotaUsage::getOrCreateForUser($member->user_id, $tenantId);
            $override = $overrides->get($member->user_id);

            $effectiveMaxDocuments = $override?->max_documents_per_user ?? $quotaSetting->max_documents_per_user;
            $effectiveMaxSignatures = $override?->max_signatures_per_user ?? $quotaSetting->max_signatures_per_user;
            $effectiveMaxStorage = $override?->max_total_storage_mb ?? $quotaSetting->max_total_storage_mb;

            $usageData[] = [
                'userId' => $member->user_id,
                'user' => $member->user,
                'role' => $member->role,
                'documentsUploaded' => $usage->documents_uploaded,
                'signaturesCreated' => $usage->signatures_created,
                'storageUsedMb' => $usage->storage_used_mb,
                'override' => $override ? [
                    'maxDocumentsPerUser' => $override->max_documents_per_user,
                    'maxSignaturesPerUser' => $override->max_signatures_per_user,
                    'maxTotalStorageMb' => $override->max_total_storage_mb,
                ] : null,
                'effectiveLimits' => [
                    'maxDocumentsPerUser' => $effectiveMaxDocuments,
                    'maxSignaturesPerUser' => $effectiveMaxSignatures,
                    'maxTotalStorageMb' => $effectiveMaxStorage,
                ],
                'documentsRemaining' => max(0, $effectiveMaxDocuments - $usage->documents_uploaded),
                'signaturesRemaining' => max(0, $effectiveMaxSignatures - $usage->signatures_created),
            ];
        }

        return ApiResponse::success([
            'quotaSettings' => [
                'maxDocumentsPerUser' => $quotaSetting->max_documents_per_user,
                'maxSignaturesPerUser' => $quotaSetting->max_signatures_per_user,
                'maxDocumentSizeMb' => $quotaSetting->max_document_size_mb,
                'maxTotalStorageMb' => $quotaSetting->max_total_storage_mb,
            ],
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

        return ApiResponse::success([
            'maxDocumentsPerUser' => $quotaSetting->max_documents_per_user,
            'maxSignaturesPerUser' => $quotaSetting->max_signatures_per_user,
            'maxDocumentSizeMb' => $quotaSetting->max_document_size_mb,
            'maxTotalStorageMb' => $quotaSetting->max_total_storage_mb,
        ], 'Quota settings updated successfully');
    }

    public function updateUserOverride(Request $request, int $userId)
    {
        $user = Auth::user();
        $tenantId = session('current_tenant_id') ?? $user->current_tenant_id;

        if (!$tenantId) {
            return ApiResponse::error('No organization selected', 400);
        }

        if (!$user->hasPermissionInTenant('quota.manage', $tenantId)) {
            return ApiResponse::error('You do not have permission to manage quota', 403);
        }

        // ensure target user is a member of tenant
        $isMember = TenantUser::where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->exists();

        if (!$isMember) {
            return ApiResponse::error('User is not a member of this organization', 404);
        }

        $validated = $request->validate([
            'max_documents_per_user' => 'nullable|integer|min:1|max:10000',
            'max_signatures_per_user' => 'nullable|integer|min:1|max:10000',
            'max_total_storage_mb' => 'nullable|integer|min:100|max:100000',
        ]);

        $allNull = (
            !isset($validated['max_documents_per_user']) &&
            !isset($validated['max_signatures_per_user']) &&
            !isset($validated['max_total_storage_mb'])
        ) || (
            ($validated['max_documents_per_user'] ?? null) === null &&
            ($validated['max_signatures_per_user'] ?? null) === null &&
            ($validated['max_total_storage_mb'] ?? null) === null
        );

        if ($allNull) {
            UserQuotaOverride::where('tenant_id', $tenantId)
                ->where('user_id', $userId)
                ->delete();

            return ApiResponse::success(null, 'User quota override removed');
        }

        $override = UserQuotaOverride::updateOrCreate(
            ['tenant_id' => $tenantId, 'user_id' => $userId],
            [
                'max_documents_per_user' => $validated['max_documents_per_user'] ?? null,
                'max_signatures_per_user' => $validated['max_signatures_per_user'] ?? null,
                'max_total_storage_mb' => $validated['max_total_storage_mb'] ?? null,
            ]
        );

        return ApiResponse::success([
            'maxDocumentsPerUser' => $override->max_documents_per_user,
            'maxSignaturesPerUser' => $override->max_signatures_per_user,
            'maxTotalStorageMb' => $override->max_total_storage_mb,
        ], 'User quota override updated');
    }
}
