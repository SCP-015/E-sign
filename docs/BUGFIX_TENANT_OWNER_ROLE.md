# Bugfix: Tenant Owner Role

## Masalah
Ketika membuat tenant/organisasi baru, creator mendapat role `admin` bukan `owner`, sehingga beberapa menu tidak terlihat karena permission terbatas.

## Root Cause
1. **TenantService.php**: Method `store()` memberikan role `'admin'` ke creator tenant
2. **Migration create_tenant_users_table**: Enum `role` tidak termasuk nilai `'owner'`, hanya `['admin', 'user']`

## Solusi

### 1. Update TenantService
File: `app/Services/TenantService.php`

Mengubah role dari `'admin'` menjadi `'owner'` pada line 41:
```php
// Sebelum
'role' => 'admin',

// Sesudah
'role' => 'owner',
```

### 2. Update Enum Role
File: `database/migrations/2026_01_17_000001_create_tenant_users_table.php`

Menambahkan `'owner'` ke dalam enum:
```php
// Sebelum
$table->enum('role', ['admin', 'user'])->default('user');

// Sesudah
$table->enum('role', ['owner', 'admin', 'user'])->default('user');
```

### 3. Migration untuk Database Existing
**File**: `database/migrations/2026_01_18_090001_add_owner_to_tenant_users_role_enum.php`
- Mengubah constraint enum di PostgreSQL untuk menambahkan nilai `'owner'`

**File**: `database/migrations/2026_01_18_090000_fix_tenant_owner_roles.php`
- Memperbaiki data existing: mengubah role dari `'admin'` ke `'owner'` untuk semua record dengan `is_owner = true`

## 5. Setup ACL (Access Control List)
ACL system menggunakan tabel terpisah untuk manage permissions secara dinamis:

**Tabel ACL**:
- `acl_roles`: Menyimpan roles (owner, admin, user/member)
- `acl_permissions`: Menyimpan semua permissions
- `acl_role_has_permissions`: Mapping role → permissions
- `acl_model_has_roles`: Assignment user → role per tenant

**Auto-Setup via Migration**:
Migration `2026_01_18_000001_create_acl_tables.php` sekarang **otomatis mengisi** roles, permissions, dan mappings dari `config/permissions.php` ketika migration dijalankan.

**Tidak perlu manual run seeder lagi!** Cukup jalankan:
```bash
php artisan migrate
```

ACL akan otomatis terisi dengan:
- 3 roles (owner, admin, user/member)
- 27 permissions dari config
- 74 role-permission mappings

Migration `2026_01_18_000002_sync_existing_tenant_users_to_acl.php` akan otomatis sinkronisasi user-role assignments dari `tenant_users` ke ACL.

## Testing
1. Buat tenant baru → creator harus mendapat role `'owner'`
2. Cek menu yang sebelumnya tidak terlihat → sekarang harus terlihat untuk owner
3. Data existing yang sudah diperbaiki → owner lama harus bisa akses semua menu
4. Verifikasi ACL:
   ```bash
   # Cek jumlah data ACL
   php artisan tinker --execute="
   echo 'Roles: ' . DB::table('acl_roles')->count() . PHP_EOL;
   echo 'Permissions: ' . DB::table('acl_permissions')->count() . PHP_EOL;
   echo 'User-Role assignments: ' . DB::table('acl_model_has_roles')->count() . PHP_EOL;
   "
   ```

## 4. Update Permission Check di Controllers
Menambahkan `'owner'` ke permission check di semua controller terkait organization management:

**File**: `app/Http/Controllers/OrganizationInvitationController.php`
- Method `index()`: Owner bisa melihat undangan
- Method `store()`: Owner bisa membuat undangan
- Method `destroy()`: Owner bisa menghapus undangan

**File**: `app/Http/Controllers/OrganizationMemberController.php`
- Method `update()`: Owner bisa mengubah role member
- Method `destroy()`: Owner bisa menghapus member

**File**: `app/Http/Controllers/OrganizationController.php`
- Method `update()`: Owner bisa mengupdate organization

## Impact
- **Tenant baru**: Creator langsung mendapat role `'owner'` dengan akses penuh
- **Tenant existing**: Data sudah diperbaiki melalui migration
- **ACL**: Sudah sinkron melalui migration `sync_existing_tenant_users_to_acl.php`
- **Permissions**:
  - Owner: Full access
  - Admin: Manage members (invite, remove, update role), TANPA billing
  - Member: View members only, TANPA billing, TANPA invite
