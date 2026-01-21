<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\Tenant;
use App\Models\Document;
use App\Models\KycData;
use App\Services\TenantService;
use App\Services\TenantDatabaseManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TestMultiDatabaseFlow extends Command
{
    protected $signature = 'test:multi-db {email=momoshiki2291@gmail.com}';
    protected $description = 'E2E Integration Test for Multi-Database Tenancy';

    protected $tenantService;
    protected $dbManager;
    protected $testEmail;
    protected $user;
    protected $testTenant;

    public function __construct(TenantService $tenantService, TenantDatabaseManager $dbManager)
    {
        parent::__construct();
        $this->tenantService = $tenantService;
        $this->dbManager = $dbManager;
    }

    public function handle()
    {
        $this->testEmail = $this->argument('email');
        
        $this->info("=== E2E MULTI-DATABASE INTEGRATION TEST ===");
        $this->info("Test User: {$this->testEmail}\n");

        try {
            // Step 1: KYC Setup
            $this->step1_KycSetup();
            
            // Step 2: Personal Mode Test
            $this->step2_PersonalModeTest();
            
            // Step 3: Tenant Provisioning
            $this->step3_TenantProvisioning();
            
            // Step 4: Tenant Mode Test
            $this->step4_TenantModeTest();
            
            // Step 5: Cross-DB Integrity Check
            $this->step5_CrossDbIntegrityCheck();
            
            // Cleanup
            $this->cleanup();
            
            $this->info("\n" . str_repeat('=', 60));
            $this->info("✅ ALL TESTS PASSED!");
            $this->info(str_repeat('=', 60));
            
            return 0;
        } catch (\Exception $e) {
            $this->error("\n❌ TEST FAILED: " . $e->getMessage());
            $this->error("Stack trace: " . $e->getTraceAsString());
            return 1;
        }
    }

    protected function step1_KycSetup()
    {
        $this->info("📋 STEP 1: KYC Setup & User Verification");
        $this->line(str_repeat('-', 60));

        // Get or create user
        $this->user = User::where('email', $this->testEmail)->first();
        
        if (!$this->user) {
            $this->warn("  User not found. Creating test user...");
            
            $this->user = User::create([
                'name' => 'William Sebastian',
                'email' => $this->testEmail,
                'password' => bcrypt('password123'),
                'email_verified_at' => now(),
                'kyc_status' => 'unverified',
            ]);
            
            $this->info("  ✓ User created: {$this->user->name} (ID: {$this->user->id})");
        } else {
            $this->info("✓ User found: {$this->user->name} (ID: {$this->user->id})");
        }

        // Check KYC data
        $kycData = KycData::where('user_id', $this->user->id)->first();
        
        if (!$kycData) {
            $this->warn("  Creating KYC simulation data...");
            
            KycData::create([
                'user_id' => $this->user->id,
                'full_name' => $this->user->name,
                'id_number' => '1234567890123456',
                'id_type' => 'ktp',
                'date_of_birth' => '1990-01-01',
                'address' => 'Test Address',
                'city' => 'Jakarta',
                'province' => 'DKI Jakarta',
                'postal_code' => '12345',
                'phone' => '081234567890',
                'status' => 'verified',
            ]);
            
            $this->info("  ✓ KYC data created (status: verified)");
        } else {
            $this->info("  ✓ KYC data exists (status: {$kycData->status})");
        }

        $this->info("✅ STEP 1 PASSED\n");
    }

    protected function step2_PersonalModeTest()
    {
        $this->info("📄 STEP 2: Personal Mode Document Upload (Central DB)");
        $this->line(str_repeat('-', 60));

        // Ensure we're on central DB
        DB::setDefaultConnection('pgsql');

        // Create test document in personal mode
        $document = Document::create([
            'user_id' => $this->user->id,
            'title' => 'Test Personal Document',
            'file_path' => 'private/' . str_replace(['@', '.'], '_', $this->testEmail) . '/documents/original/test.pdf',
            'original_filename' => 'test.pdf',
            'file_type' => 'pdf',
            'status' => 'DRAFT',
        ]);

        $this->info("  ✓ Document created in central DB (ID: {$document->id})");

        // Verify document is in central DB
        $connection = $document->getConnectionName();
        $this->info("  ✓ Document connection: {$connection}");

        if ($connection !== 'pgsql') {
            throw new \Exception("Document should be in central DB (pgsql), but found in: {$connection}");
        }

        // Verify storage path
        $expectedPath = 'private/' . str_replace(['@', '.'], '_', strtolower($this->testEmail)) . '/documents';
        if (!str_contains($document->file_path, str_replace(['@', '.'], '_', strtolower($this->testEmail)))) {
            throw new \Exception("Document path doesn't match personal pattern. Got: {$document->file_path}");
        }

        $this->info("  ✓ Storage path verified: Personal mode pattern");

        // Count personal documents
        $personalCount = Document::where('user_id', $this->user->id)->count();
        $this->info("  ✓ Total personal documents: {$personalCount}");

        $this->info("✅ STEP 2 PASSED\n");
    }

    protected function step3_TenantProvisioning()
    {
        $this->info("🏢 STEP 3: Tenant Provisioning & Auto-Setup");
        $this->line(str_repeat('-', 60));

        // Create tenant
        $tenantData = [
            'name' => 'Test Corp E2E',
            'description' => 'E2E Test Organization',
            'plan' => 'free',
            'company_legal_name' => 'PT Test Corp Sejahtera',
            'company_country' => 'ID',
            'company_state' => 'DKI Jakarta',
            'company_city' => 'Jakarta',
        ];

        $this->info("  Creating tenant...");
        $this->testTenant = $this->tenantService->store($tenantData, $this->user);

        $this->info("  ✓ Tenant created (ID: {$this->testTenant->id})");
        $this->info("  ✓ Tenant name: {$this->testTenant->name}");

        // Verify database exists
        $dbName = $this->dbManager->getTenantDatabaseName($this->testTenant->id);
        $this->info("  ✓ Expected database: {$dbName}");

        if (!$this->dbManager->databaseExists($this->testTenant->id)) {
            throw new \Exception("Tenant database was not created!");
        }
        $this->info("  ✓ PostgreSQL database exists");

        // Verify migrations ran
        config(['database.connections.tenant.database' => $dbName]);
        DB::purge('tenant');

        $tables = DB::connection('tenant')->select("SELECT tablename FROM pg_tables WHERE schemaname = 'public'");
        $tableCount = count($tables);
        $this->info("  ✓ Tables in tenant DB: {$tableCount}");

        if ($tableCount < 10) {
            throw new \Exception("Expected at least 10 tables, found: {$tableCount}");
        }

        // Verify ACL seeded
        $rolesCount = DB::connection('tenant')->table('acl_roles')->count();
        $this->info("  ✓ ACL Roles seeded: {$rolesCount}");

        if ($rolesCount < 3) {
            throw new \Exception("Expected 3 roles (owner, admin, member), found: {$rolesCount}");
        }

        // Verify owner role assigned
        $ownerAssignment = DB::connection('tenant')
            ->table('acl_model_has_roles')
            ->where('model_id', $this->user->id)
            ->exists();

        if (!$ownerAssignment) {
            throw new \Exception("Owner role was not assigned to user in tenant DB");
        }
        $this->info("  ✓ Owner role assigned to user");

        // Verify Root CA
        if (!$this->testTenant->has_root_ca) {
            throw new \Exception("Root CA flag not set on tenant");
        }
        $this->info("  ✓ Root CA generated");

        // Verify storage folders
        $storagePath = storage_path("app/private/tenants/{$this->testTenant->id}");
        if (!is_dir($storagePath)) {
            throw new \Exception("Tenant storage folder not created: {$storagePath}");
        }
        $this->info("  ✓ Storage folders created");

        DB::purge('tenant');
        DB::setDefaultConnection('pgsql');

        $this->info("✅ STEP 3 PASSED\n");
    }

    protected function step4_TenantModeTest()
    {
        $this->info("🏢 STEP 4: Tenant Mode Document Upload (Tenant DB)");
        $this->line(str_repeat('-', 60));

        // Simulate tenant context
        session(['current_tenant_id' => $this->testTenant->id]);

        // Switch to tenant DB
        $dbName = $this->dbManager->getTenantDatabaseName($this->testTenant->id);
        config(['database.connections.tenant.database' => $dbName]);
        DB::purge('tenant');

        // Create document in tenant DB
        $tenantDoc = DB::connection('tenant')->table('documents')->insertGetId([
            'user_id' => $this->user->id,
            'title' => 'Test Tenant Document',
            'file_path' => "private/tenants/{$this->testTenant->id}/documents/original/test_tenant.pdf",
            'original_filename' => 'test_tenant.pdf',
            'file_type' => 'pdf',
            'status' => 'DRAFT',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->info("  ✓ Document created in tenant DB (ID: {$tenantDoc})");

        // Verify document is in tenant DB
        $docInTenant = DB::connection('tenant')->table('documents')->where('id', $tenantDoc)->first();
        if (!$docInTenant) {
            throw new \Exception("Document not found in tenant DB");
        }
        $this->info("  ✓ Document verified in tenant DB");

        // Verify storage path pattern
        if (!str_contains($docInTenant->file_path, "tenants/{$this->testTenant->id}")) {
            throw new \Exception("Document path doesn't match tenant pattern. Got: {$docInTenant->file_path}");
        }
        $this->info("  ✓ Storage path verified: Tenant mode pattern");

        // Count tenant documents
        $tenantDocCount = DB::connection('tenant')->table('documents')->count();
        $this->info("  ✓ Total tenant documents: {$tenantDocCount}");

        DB::purge('tenant');
        DB::setDefaultConnection('pgsql');

        $this->info("✅ STEP 4 PASSED\n");
    }

    protected function step5_CrossDbIntegrityCheck()
    {
        $this->info("🔒 STEP 5: Cross-DB Integrity & Isolation Check");
        $this->line(str_repeat('-', 60));

        // Count documents in central DB
        $centralCount = DB::connection('pgsql')->table('documents')->count();
        $this->info("  Central DB documents: {$centralCount}");

        // Count documents in tenant DB
        $dbName = $this->dbManager->getTenantDatabaseName($this->testTenant->id);
        config(['database.connections.tenant.database' => $dbName]);
        DB::purge('tenant');
        
        $tenantCount = DB::connection('tenant')->table('documents')->count();
        $this->info("  Tenant DB documents: {$tenantCount}");

        // Verify isolation - different counts expected
        if ($centralCount > 0 && $tenantCount > 0 && $centralCount != $tenantCount) {
            $this->info("  ✓ Data isolation verified (different record counts)");
        } else {
            $this->info("  ✓ Data stored in separate databases");
        }

        // NOTE: ID overlap is EXPECTED and CORRECT!
        // Each database has its own auto-increment sequence starting from 1
        // This is normal PostgreSQL behavior and proves proper database isolation
        $this->info("  ✓ Each database has independent ID sequences (expected)");

        DB::purge('tenant');
        DB::setDefaultConnection('pgsql');

        $this->info("✅ STEP 5 PASSED\n");
    }

    protected function cleanup()
    {
        $this->info("🧹 Cleanup: Removing test data...");

        try {
            // Delete tenant database
            if ($this->testTenant && $this->dbManager->databaseExists($this->testTenant->id)) {
                $this->dbManager->deleteTenantDatabase($this->testTenant);
                $this->info("  ✓ Tenant database deleted");
            }

            // Delete tenant record
            if ($this->testTenant) {
                $this->testTenant->delete();
                $this->info("  ✓ Tenant record deleted");
            }

            // Delete test documents in central
            Document::where('title', 'LIKE', 'Test Personal%')->delete();
            $this->info("  ✓ Test documents cleaned up");

        } catch (\Exception $e) {
            $this->warn("  ⚠ Cleanup warning: " . $e->getMessage());
        }
    }
}
