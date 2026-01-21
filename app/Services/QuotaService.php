<?php

namespace App\Services;

use App\Models\QuotaSetting;
use App\Models\Signature;
use App\Models\Tenant\User as TenantUser;
use App\Models\User;
use App\Models\UserQuotaOverride;
use App\Models\UserQuotaUsage;
use Illuminate\Support\Facades\DB;

class QuotaService
{
    public function __construct(
        private readonly TenantContextService $tenantContextService,
        private readonly TenantDatabaseManager $tenantDatabaseManager
    )
    {
    }

    public function overview(User $actor): array
    {
        $tenantId = $this->getTenantIdFromContext($actor);
        if (!$tenantId) {
            return [
                'status' => 'error',
                'message' => 'No organization selected',
                'code' => 400,
                'data' => null,
            ];
        }

        $this->configureTenantConnection($tenantId);

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

            if ((int) $usage->signatures_created === 0) {
                $signatureCount = Signature::where('user_id', $member->user_id)->count();
                if ($signatureCount > 0) {
                    $usage->update(['signatures_created' => $signatureCount]);
                    $usage->refresh();
                }
            }

            $effectiveMaxDocuments = $override?->max_documents_per_user ?? $quotaSetting->max_documents_per_user;
            $effectiveMaxSignatures = $override?->max_signatures_per_user ?? $quotaSetting->max_signatures_per_user;
            $effectiveMaxStorage = $override?->max_total_storage_mb ?? $quotaSetting->max_total_storage_mb;

            $usageData[] = [
                'user' => $member->user,
                'userId' => $member->user_id,
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

        return [
            'status' => 'success',
            'message' => 'OK',
            'code' => 200,
            'data' => [
                'quotaSetting' => $quotaSetting,
                'usage' => $usageData,
            ],
        ];
    }

    public function updateSettings(User $actor, array $payload): array
    {
        $tenantId = $this->getTenantIdFromContext($actor);
        if (!$tenantId) {
            return [
                'status' => 'error',
                'message' => 'No organization selected',
                'code' => 400,
                'data' => null,
            ];
        }

        $this->configureTenantConnection($tenantId);

        if (!$actor->hasPermissionInTenant('quota.manage', $tenantId)) {
            return [
                'status' => 'error',
                'message' => 'You do not have permission to manage quota',
                'code' => 403,
                'data' => null,
            ];
        }

        $quotaSetting = QuotaSetting::getOrCreateForTenant($tenantId);
        $quotaSetting->update($payload);

        return [
            'status' => 'success',
            'message' => 'Quota settings updated successfully',
            'code' => 200,
            'data' => $quotaSetting,
        ];
    }

    public function updateUserOverride(User $actor, string $userId, array $payload): array
    {
        $tenantId = $this->getTenantIdFromContext($actor);
        if (!$tenantId) {
            return [
                'status' => 'error',
                'message' => 'No organization selected',
                'code' => 400,
                'data' => null,
            ];
        }

        $this->configureTenantConnection($tenantId);

        if (!$actor->hasPermissionInTenant('quota.manage', $tenantId)) {
            return [
                'status' => 'error',
                'message' => 'You do not have permission to manage quota',
                'code' => 403,
                'data' => null,
            ];
        }

        $isMember = TenantUser::where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->exists();

        if (!$isMember) {
            return [
                'status' => 'error',
                'message' => 'User is not a member of this organization',
                'code' => 404,
                'data' => null,
            ];
        }

        $allNull = (
            !isset($payload['max_documents_per_user']) &&
            !isset($payload['max_signatures_per_user']) &&
            !isset($payload['max_total_storage_mb'])
        ) || (
            ($payload['max_documents_per_user'] ?? null) === null &&
            ($payload['max_signatures_per_user'] ?? null) === null &&
            ($payload['max_total_storage_mb'] ?? null) === null
        );

        if ($allNull) {
            UserQuotaOverride::where('tenant_id', $tenantId)
                ->where('user_id', $userId)
                ->delete();

            return [
                'status' => 'success',
                'message' => 'User quota override removed',
                'code' => 200,
                'data' => null,
            ];
        }

        $override = UserQuotaOverride::updateOrCreate(
            ['tenant_id' => $tenantId, 'user_id' => $userId],
            [
                'max_documents_per_user' => $payload['max_documents_per_user'] ?? null,
                'max_signatures_per_user' => $payload['max_signatures_per_user'] ?? null,
                'max_total_storage_mb' => $payload['max_total_storage_mb'] ?? null,
            ]
        );

        return [
            'status' => 'success',
            'message' => 'User quota override updated',
            'code' => 200,
            'data' => $override,
        ];
    }

    private function getTenantIdFromContext(User $actor): ?string
    {
        $context = $this->tenantContextService->getCurrentContext($actor);
        $tenant = $context['data']['tenant'] ?? null;

        return $tenant ? (string) $tenant->id : null;
    }

    private function configureTenantConnection(string $tenantId): void
    {
        $dbName = $this->tenantDatabaseManager->getTenantDatabaseName($tenantId);
        config(['database.connections.tenant.database' => $dbName]);
        DB::purge('tenant');
    }
}
