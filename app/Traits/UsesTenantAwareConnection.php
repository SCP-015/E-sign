<?php

namespace App\Traits;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Trait UsesTenantAwareConnection
 * 
 * Dynamic connection switching untuk model yang ada di KEDUA database
 * (Central untuk Personal, Tenant untuk Organization context).
 * 
 * Digunakan untuk: Document, Signature, Certificate
 */
trait UsesTenantAwareConnection
{
    /**
     * Initialize trait - set connection on model instantiation.
     * This makes queries tenant-aware too (not only creates).
     */
    public function initializeUsesTenantAwareConnection(): void
    {
        $this->setTenantAwareConnection();
    }

    /**
     * Boot trait - set connection based on context.
     */
    public static function bootUsesTenantAwareConnection(): void
    {
        static::creating(function ($model) {
            $model->setTenantAwareConnection();
        });
    }

    /**
     * Set connection berdasarkan current tenant context.
     */
    public function setTenantAwareConnection(): void
    {
        $tenantId = $this->getCurrentTenantId();

        if ($tenantId) {
            // Tenant context - use tenant database
            $this->setConnection('tenant');
        } else {
            // Personal context - use central database
            $this->setConnection('pgsql');
        }
    }

    /**
     * Get current tenant ID from session or user.
     */
    protected function getCurrentTenantId(): ?string
    {
        // Priority 1: From session
        if (session()->has('current_tenant_id')) {
            return session('current_tenant_id');
        }

        // Priority 2: From authenticated user
        $user = Auth::user();
        if ($user && isset($user->current_tenant_id)) {
            return $user->current_tenant_id;
        }

        return null;
    }

    /**
     * Scope: Force query ke central database (personal mode).
     */
    public function scopePersonal($query)
    {
        $query->getModel()->setConnection('pgsql');
        return $query;
    }

    /**
     * Scope: Force query ke tenant database.
     */
    public function scopeTenant($query, string $tenantId = null)
    {
        $tid = $tenantId ?? $this->getCurrentTenantId();
        
        if (!$tid) {
            throw new \Exception('Tenant ID required for tenant scope');
        }

        // Set tenant database name dynamically
        config(['database.connections.tenant.database' => $this->getTenantDatabaseName($tid)]);
        DB::purge('tenant');

        $query->getModel()->setConnection('tenant');
        return $query;
    }

    /**
     * Get tenant database name dari tenant ID.
     */
    protected function getTenantDatabaseName(string $tenantId): string
    {
        $cleanId = str_replace('-', '_', strtolower($tenantId));
        $prefix = config('tenancy.database.prefix', 'tenant_');
        return "{$prefix}{$cleanId}";
    }

    /**
     * Helper: Check if currently in tenant context.
     */
    public function isInTenantContext(): bool
    {
        return $this->getCurrentTenantId() !== null;
    }

    /**
     * Helper: Get connection name being used.
     */
    public function getActiveConnection(): string
    {
        return $this->isInTenantContext() ? 'tenant' : 'pgsql';
    }
}
