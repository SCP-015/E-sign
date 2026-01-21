# Testing Guide: Tenant-Scoped Documents & Signatures

## 🎯 Objective
Memverifikasi bahwa **STRICT ISOLATION** antara mode personal dan mode tenant berfungsi dengan benar.

---

## ✅ Test Scenarios

### Scenario 1: Upload di Personal Mode
**Steps:**
1. Login sebagai user
2. Pastikan `current_tenant_id = NULL` (mode personal)
3. Upload dokumen via API: `POST /api/documents/upload`
4. Verify response:
   ```json
   {
     "status": "success",
     "data": {
       "tenantId": null,
       "mode": "personal"
     }
   }
   ```
5. Cek database:
   ```sql
   SELECT id, title, tenant_id FROM documents WHERE user_id = {user_id} ORDER BY created_at DESC LIMIT 1;
   -- Expected: tenant_id = NULL
   ```
6. Cek file storage:
   ```bash
   ls -la storage/app/documents/personal/original/
   # Expected: file dengan format {user_id}_{timestamp}_original.pdf
   ```

**Expected Result:**
- ✅ Dokumen tersimpan dengan `tenant_id = NULL`
- ✅ File tersimpan di `storage/app/documents/personal/original/`
- ✅ Response API menunjukkan `mode: "personal"`

---

### Scenario 2: Switch ke Tenant Mode → Dokumen Personal TIDAK MUNCUL
**Steps:**
1. Dari scenario 1, user sudah punya dokumen personal
2. Switch ke tenant mode (set `current_tenant_id = {tenant_uuid}`)
3. Fetch documents: `GET /api/documents`
4. Verify response:
   ```json
   {
     "status": "success",
     "data": [],
     "context": {
       "mode": "tenant",
       "tenant_id": "{tenant_uuid}"
     }
   }
   ```

**Expected Result:**
- ✅ List dokumen KOSONG (dokumen personal tidak muncul)
- ✅ Context mode = "tenant"

---

### Scenario 3: Upload di Tenant Mode
**Steps:**
1. Pastikan `current_tenant_id = {tenant_uuid}`
2. Upload dokumen via API: `POST /api/documents/upload`
3. Verify response:
   ```json
   {
     "status": "success",
     "data": {
       "tenantId": "{tenant_uuid}",
       "mode": "tenant"
     }
   }
   ```
4. Cek database:
   ```sql
   SELECT id, title, tenant_id FROM documents WHERE user_id = {user_id} ORDER BY created_at DESC LIMIT 1;
   -- Expected: tenant_id = {tenant_uuid}
   ```
5. Cek file storage:
   ```bash
   ls -la storage/app/documents/{tenant_uuid}/original/
   # Expected: file dengan format {doc_id}_original.pdf
   ```

**Expected Result:**
- ✅ Dokumen tersimpan dengan `tenant_id = {tenant_uuid}`
- ✅ File tersimpan di `storage/app/documents/{tenant_uuid}/original/`
- ✅ Response API menunjukkan `mode: "tenant"`

---

### Scenario 4: Switch ke Personal Mode → Dokumen Tenant TIDAK MUNCUL
**Steps:**
1. Dari scenario 3, user sudah punya dokumen di tenant
2. Switch ke personal mode (set `current_tenant_id = NULL`)
3. Fetch documents: `GET /api/documents`
4. Verify response hanya menampilkan dokumen personal (dari scenario 1)

**Expected Result:**
- ✅ Dokumen tenant TIDAK MUNCUL di personal mode
- ✅ Hanya dokumen personal yang muncul

---

### Scenario 5: Multi-Tenant Isolation
**Steps:**
1. User adalah member dari Tenant A dan Tenant B
2. Upload dokumen di Tenant A
3. Switch ke Tenant B
4. Fetch documents: `GET /api/documents`

**Expected Result:**
- ✅ Dokumen Tenant A TIDAK MUNCUL di Tenant B
- ✅ List dokumen Tenant B kosong (atau hanya dokumen Tenant B)

---

### Scenario 6: Signature Personal (Portable)
**Steps:**
1. Mode personal (`current_tenant_id = NULL`)
2. Upload signature dengan `is_portable = true`: `POST /api/signatures`
3. Verify database:
   ```sql
   SELECT id, name, tenant_id FROM signatures WHERE user_id = {user_id} ORDER BY created_at DESC LIMIT 1;
   -- Expected: tenant_id = NULL
   ```
4. Switch ke Tenant Mode
5. Fetch signatures: `GET /api/signatures`

**Expected Result:**
- ✅ Signature personal MUNCUL di tenant mode (portable)
- ✅ Response menunjukkan `is_portable: true`

---

### Scenario 7: Signature Tenant (Non-Portable)
**Steps:**
1. Mode tenant (`current_tenant_id = {tenant_uuid}`)
2. Upload signature dengan `is_portable = false`: `POST /api/signatures`
3. Verify database:
   ```sql
   SELECT id, name, tenant_id FROM signatures WHERE user_id = {user_id} ORDER BY created_at DESC LIMIT 1;
   -- Expected: tenant_id = {tenant_uuid}
   ```
4. Switch ke Personal Mode
5. Fetch signatures: `GET /api/signatures`

**Expected Result:**
- ✅ Signature tenant TIDAK MUNCUL di personal mode
- ✅ Hanya signature personal yang muncul

---

### Scenario 8: Certificate Portability
**Steps:**
1. Generate certificate di personal mode
2. Switch ke tenant mode
3. Sign document dengan certificate tersebut

**Expected Result:**
- ✅ Certificate bisa dipakai di tenant mode (portable)
- ✅ Signing berhasil

---

## 🧪 Manual Testing Commands

### Setup Test User & Tenant
```bash
# Buat test user
php artisan tinker
$user = User::create([
    'name' => 'Test User',
    'email' => 'test@example.com',
    'password' => bcrypt('password'),
]);

# Buat test tenant
$tenant = Tenant::create([
    'id' => Str::uuid(),
    'name' => 'Test Organization',
]);

# Attach user ke tenant
$tenant->users()->attach($user->id, ['role' => 'owner']);
```

### Test Upload Personal Mode
```bash
curl -X POST http://localhost:8000/api/documents/upload \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: multipart/form-data" \
  -F "file=@test.pdf" \
  -F "title=Test Personal Document"
```

### Test Upload Tenant Mode
```bash
# Set current_tenant_id di session atau user table dulu
curl -X POST http://localhost:8000/api/documents/upload \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: multipart/form-data" \
  -F "file=@test.pdf" \
  -F "title=Test Tenant Document"
```

### Verify Database
```sql
-- Cek dokumen personal
SELECT id, title, tenant_id, created_at 
FROM documents 
WHERE user_id = {user_id} AND tenant_id IS NULL;

-- Cek dokumen tenant
SELECT id, title, tenant_id, created_at 
FROM documents 
WHERE user_id = {user_id} AND tenant_id = '{tenant_uuid}';

-- Cek signature personal (portable)
SELECT id, name, tenant_id, created_at 
FROM signatures 
WHERE user_id = {user_id} AND tenant_id IS NULL;

-- Cek signature tenant (non-portable)
SELECT id, name, tenant_id, created_at 
FROM signatures 
WHERE user_id = {user_id} AND tenant_id = '{tenant_uuid}';
```

---

## 🚨 Common Issues & Troubleshooting

### Issue 1: Dokumen Personal Muncul di Tenant Mode
**Diagnosis:**
```sql
SELECT id, title, tenant_id FROM documents WHERE user_id = {user_id};
```
**Fix:** Pastikan scope `forCurrentContext()` dipanggil di semua query.

### Issue 2: File Tidak Ditemukan
**Diagnosis:**
```bash
ls -la storage/app/documents/
```
**Fix:** Jalankan `StoragePathHelper::ensureDirectoryExists($tenantId)`.

### Issue 3: Migration Error (Duplicate Column)
**Diagnosis:** Kolom `tenant_id` sudah ada di database.
**Fix:** Skip migration atau drop column dulu jika perlu rollback.

---

## 📊 Performance Testing

### Query Performance
```sql
-- Test query dengan index
EXPLAIN ANALYZE 
SELECT * FROM documents 
WHERE tenant_id = '{tenant_uuid}' AND user_id = {user_id};

-- Expected: menggunakan index idx_tenant_documents
```

### Load Testing
```bash
# Test concurrent uploads
ab -n 100 -c 10 -H "Authorization: Bearer {token}" \
   -p test.pdf \
   http://localhost:8000/api/documents/upload
```

---

## ✅ Success Criteria Checklist

- [ ] Dokumen personal TIDAK muncul di tenant mode
- [ ] Dokumen tenant A TIDAK muncul di tenant B
- [ ] Dokumen tenant TIDAK muncul di personal mode
- [ ] Signature personal (portable) MUNCUL di semua mode
- [ ] Signature tenant TIDAK muncul di mode lain
- [ ] Certificate portable (bisa dipakai di semua mode)
- [ ] File storage terpisah per folder (personal vs tenant)
- [ ] Query performance < 200ms
- [ ] No data loss setelah implementasi
- [ ] API response konsisten (status, data, context)

---

## 🔄 Rollback Plan

Jika ada masalah setelah deployment:

1. **Rollback Migration:**
   ```bash
   php artisan migrate:rollback --step=1
   ```

2. **Restore Database Backup:**
   ```bash
   pg_restore -U postgres -d esignmvp backup.sql
   ```

3. **Revert Code:**
   ```bash
   git revert {commit_hash}
   ```

4. **Clear Cache:**
   ```bash
   php artisan cache:clear
   php artisan config:clear
   php artisan route:clear
   ```
