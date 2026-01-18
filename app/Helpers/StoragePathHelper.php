<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Storage;

class StoragePathHelper
{
    /**
     * Get storage path untuk dokumen berdasarkan tenant context
     * 
     * @param string|null $tenantId
     * @param string $type 'original' atau 'final'
     * @return string
     */
    public static function getDocumentPath(?string $tenantId, string $type = 'original'): string
    {
        if ($tenantId === null) {
            return "documents/personal/{$type}";
        }
        
        return "documents/{$tenantId}/{$type}";
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
        int $userId, 
        string $originalName,
        ?int $documentId = null
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
        int $userId,
        int $documentId,
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
        $paths = [
            self::getDocumentPath($tenantId, 'original'),
            self::getDocumentPath($tenantId, 'final'),
        ];

        foreach ($paths as $path) {
            if (!Storage::exists($path)) {
                Storage::makeDirectory($path, 0755, true);
            }
        }
    }

    /**
     * Get full storage path (untuk move/rename file)
     */
    public static function getFullPath(?string $tenantId, string $type, string $filename): string
    {
        $basePath = self::getDocumentPath($tenantId, $type);
        return "{$basePath}/{$filename}";
    }

    /**
     * Move file dari temp ke final location (setelah document ID tersedia)
     */
    public static function moveToFinalLocation(
        string $tempPath,
        ?string $tenantId,
        int $documentId,
        string $originalName
    ): string {
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        $finalFilename = "{$documentId}_original.{$extension}";
        $finalPath = self::getFullPath($tenantId, 'original', $finalFilename);
        
        Storage::move($tempPath, $finalPath);
        
        return $finalPath;
    }
}
