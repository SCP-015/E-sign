<?php

namespace App\Services\Tenant;

use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantContextService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class PortalSettingsService
{
    public function __construct(private readonly TenantContextService $tenantContextService)
    {
    }

    public function show(User $actor): array
    {
        $context = $this->tenantContextService->getCurrentContext($actor);
        $tenant = $context['data']['tenant'] ?? null;

        if (!$tenant) {
            return [
                'status' => 'error',
                'message' => 'No organization selected',
                'code' => 400,
                'data' => null,
            ];
        }

        $tenant = Tenant::find($tenant->id);
        if (!$tenant) {
            return [
                'status' => 'error',
                'message' => 'Organization not found',
                'code' => 404,
                'data' => null,
            ];
        }

        return [
            'status' => 'success',
            'message' => 'OK',
            'code' => 200,
            'data' => $tenant,
        ];
    }

    public function update(User $actor, array $payload): array
    {
        $context = $this->tenantContextService->getCurrentContext($actor);
        $tenant = $context['data']['tenant'] ?? null;

        if (!$tenant) {
            return [
                'status' => 'error',
                'message' => 'No organization selected',
                'code' => 400,
                'data' => null,
            ];
        }

        $tenantId = (string) $tenant->id;
        if (!$actor->hasPermissionInTenant('portal_settings.update', $tenantId)) {
            return [
                'status' => 'error',
                'message' => 'You do not have permission to update portal settings',
                'code' => 403,
                'data' => null,
            ];
        }

        $tenant = Tenant::find($tenantId);
        if (!$tenant) {
            return [
                'status' => 'error',
                'message' => 'Organization not found',
                'code' => 404,
                'data' => null,
            ];
        }

        $tenant->update($payload);

        return [
            'status' => 'success',
            'message' => 'Portal settings updated successfully',
            'code' => 200,
            'data' => $tenant,
        ];
    }

    public function uploadLogo(User $actor, UploadedFile $logo): array
    {
        return $this->uploadAsset($actor, $logo, 'logo', 2048, 'Logo uploaded successfully');
    }

    public function uploadBanner(User $actor, UploadedFile $banner): array
    {
        return $this->uploadAsset($actor, $banner, 'banner', 5120, 'Banner uploaded successfully');
    }

    private function uploadAsset(User $actor, UploadedFile $file, string $field, int $maxKb, string $successMessage): array
    {
        $context = $this->tenantContextService->getCurrentContext($actor);
        $tenant = $context['data']['tenant'] ?? null;

        if (!$tenant) {
            return [
                'status' => 'error',
                'message' => 'No organization selected',
                'code' => 400,
                'data' => null,
            ];
        }

        $tenantId = (string) $tenant->id;
        if (!$actor->hasPermissionInTenant('portal_settings.update', $tenantId)) {
            return [
                'status' => 'error',
                'message' => 'You do not have permission to update portal settings',
                'code' => 403,
                'data' => null,
            ];
        }

        $tenant = Tenant::find($tenantId);
        if (!$tenant) {
            return [
                'status' => 'error',
                'message' => 'Organization not found',
                'code' => 404,
                'data' => null,
            ];
        }

        $existing = (string) ($tenant->{$field} ?? '');
        if ($existing) {
            Storage::disk('public')->delete($existing);
        }

        $dir = "tenants/{$tenantId}/{$field}";
        $path = $file->store($dir, 'public');
        $tenant->update([$field => $path]);

        return [
            'status' => 'success',
            'message' => $successMessage,
            'code' => 200,
            'data' => [$field => $path],
        ];
    }
}
