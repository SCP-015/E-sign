<?php

namespace App\Traits;

/**
 * Trait UsesTenantConnection
 * 
 * Strict tenant-only connection untuk model yang HANYA ada di tenant database.
 * Tidak ada di central database sama sekali.
 * 
 * Digunakan untuk: ACL models, QuotaSettings, DocumentSigner, dll
 */
trait UsesTenantConnection
{
    /**
     * Get database connection name.
     */
    public function getConnectionName()
    {
        return 'tenant';
    }

    /**
     * Boot trait - enforce tenant connection.
     */
    public static function bootUsesTenantConnection(): void
    {
        static::creating(function ($model) {
            $model->setConnection('tenant');
        });

        static::updating(function ($model) {
            $model->setConnection('tenant');
        });
    }

    /**
     * Initialize trait - set connection to tenant.
     */
    public function initializeUsesTenantConnection(): void
    {
        $this->connection = 'tenant';
    }

    /**
     * Helper: Check if tenant database is connected.
     */
    public function isTenantDatabaseConnected(): bool
    {
        try {
            return config('database.connections.tenant.database') !== '';
        } catch (\Exception $e) {
            return false;
        }
    }
}
