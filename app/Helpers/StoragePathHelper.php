<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class StoragePathHelper
{
    /**
     * Get storage path untuk dokumen berdasarkan tenant context.
     * 
     * Personal Mode: storage/app/private/documents/{email}/
     * Tenant Mode: storage/app/private/tenants/{tenant_uuid}/documents/
     * 
     * @param string|null $tenantId
     * @param string $type 'original' atau 'final'
     * @param string|null $userEmail (required for personal mode)
     * @return string
     */
    public static function getDocumentPath(?string $tenantId, string $type = 'original', ?string $userEmail = null): string
    {
        if ($tenantId === null) {
            // Personal mode - use email-based path (existing structure)
            if (!$userEmail) {
                $userEmail = Auth::user()?->email ?? 'guest';
            }
            $email = strtolower((string) $userEmail);
            return "{$email}/documents/{$type}";
        }
        
        // Tenant mode - use tenant UUID path
        return "tenants/{$tenantId}/documents/{$type}";
    }

    /**
     * Generate filename untuk dokumen
     * 
     * @param string|null $tenantId
     * @param int $userId
     * @param string $originalName
     * @param int|null $documentId (untuk tenant mode setelah document dibuat)
     * @return string
     */
    public static function generateDocumentFilename(
        ?string $tenantId, 
        string $userId, 
        string $originalName,
        ?string $documentId = null
    ): string {
        $timestamp = now()->format('YmdHis');
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        
        if ($tenantId === null) {
            return "{$userId}_{$timestamp}_original.{$extension}";
        }
        
        if ($documentId) {
            return "{$documentId}_original.{$extension}";
        }
        
        return "{$timestamp}_temp.{$extension}";
    }

    /**
     * Generate filename untuk signed document
     */
    public static function generateSignedFilename(
        ?string $tenantId,
        string $userId,
        string $documentId,
        string $originalName
    ): string {
        $timestamp = now()->format('YmdHis');
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        
        if ($tenantId === null) {
            return "{$userId}_{$timestamp}_signed.{$extension}";
        }
        
        return "{$documentId}_signed.{$extension}";
    }

    /**
     * Ensure directory exists untuk tenant tertentu
     */
    public static function ensureDirectoryExists(?string $tenantId): void
    {
        if ($tenantId === null) {
            return;
        }

        $paths = [
            self::getDocumentPath($tenantId, 'original'),
            self::getDocumentPath($tenantId, 'final'),
        ];

        foreach ($paths as $path) {
            if (!Storage::disk('private')->exists($path)) {
                Storage::disk('private')->makeDirectory($path);
            }
        }
    }

    /**
     * Get full storage path (untuk move/rename file)
     */
    public static function getFullPath(?string $tenantId, string $type, string $filename, ?string $userEmail = null): string
    {
        $basePath = self::getDocumentPath($tenantId, $type, $userEmail);
        return "{$basePath}/{$filename}";
    }

    /**
     * Move file dari temp ke final location (setelah document ID tersedia)
     */
    public static function moveToFinalLocation(
        string $tempPath,
        ?string $tenantId,
        string $documentId,
        string $originalName
    ): string {
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        $finalFilename = "{$documentId}_original.{$extension}";
        $finalPath = self::getFullPath($tenantId, 'original', $finalFilename);
        
        Storage::disk('private')->move($tempPath, $finalPath);
        
        return $finalPath;
    }

    /**
     * Get certificate storage path (personal vs tenant).
     * 
     * Personal: storage/app/private/{email}/certificates/
     * Tenant: storage/app/private/tenants/{tenant_uuid}/certificates/
     */
    public static function getCertificatePath(?string $tenantId, ?string $userEmail = null): string
    {
        if ($tenantId === null) {
            if (!$userEmail) {
                $userEmail = Auth::user()?->email ?? 'guest';
            }
            $email = strtolower((string) $userEmail);
            return "{$email}/certificates";
        }

        if (!$userEmail) {
            $userEmail = Auth::user()?->email ?? 'guest';
        }
        $email = strtolower((string) $userEmail);
        return "tenants/{$tenantId}/certificates/{$email}";
    }

    /**
     * Get signature storage path (portable - always personal).
     * 
     * Signatures are portable, stored in personal  space only.
     */
    public static function getSignaturePath(string $userEmail): string
    {
        $email = strtolower((string) $userEmail);
        return "{$email}/signatures";
    }
}
