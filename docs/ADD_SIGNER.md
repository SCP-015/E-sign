# Konsep Add Signer (E-Sign)

Dokumen ini menjelaskan konsep "add signer" di project ini: pemilik dokumen menambahkan calon penanda-tangan, sistem menyimpan undangan, lalu signer menerima undangan untuk mengaitkan akun dan melakukan placement tanda tangan.

## Alur Singkat

1. Owner memanggil `POST /api/documents/{document}/signers` untuk menambahkan signer (email + nama).
2. Backend membuat record signer dengan status `PENDING`, generate `invite_token`, dan kirim email undangan.
3. Signer membuka link undangan (`/invite?code=...`) lalu login.
4. Backend menerima undangan, mengaitkan `user_id` ke signer.
5. Signer menyimpan placement tanda tangan via `POST /api/documents/{document}/placements`.
6. Signer berubah `SIGNED`. Jika semua signer sudah signed, status dokumen menjadi `signed` dan bisa lanjut finalize.

## Backend: Endpoint & Layanan

### 1) Add signer

- Endpoint: `POST /api/documents/{document}/signers`
- Controller: `app/Http/Controllers/SignerController.php`
- Request validation: `app/Http/Requests/StoreSignersRequest.php`
- Service: `app/Services/SignerService.php`

Yang terjadi di service:

- Cek dokumen milik owner.
- Loop daftar signer, untuk tiap signer:
  - Cari user berdasarkan email (optional).
  - Generate `invite_token` dan `invite_expires_at`.
  - Simpan ke tabel `document_signers` dengan `status=PENDING`.
  - Kirim email undangan.
- Update status dokumen jadi `IN_PROGRESS`.

### 2) Validasi/Accept undangan

- Endpoint: `GET /api/invitations/validate`
- Endpoint: `POST /api/invitations/accept`
- Controller: `app/Http/Controllers/InvitationController.php`

Yang terjadi saat accept:

- Cek token dan email (atau code).
- Jika valid dan belum expired, set `user_id` ke signer, set `invite_accepted_at`, dan hapus token.

### 3) Simpan placement tanda tangan

- Endpoint: `POST /api/documents/{document}/placements`
- Controller: `app/Http/Controllers/PlacementController.php`
- Service: `app/Services/PlacementService.php`

Yang terjadi di placement:

- Jika signer belum ada, bisa dibuat berdasarkan `signerUserId` atau `email`.
- Simpan placement (posisi tanda tangan).
- Jika placement punya `signatureId`, signer di-mark `SIGNED` dan `signed_at` diisi.
- Jika semua signer `SIGNED`, status dokumen jadi `signed` dan `verify_token` di-set.

## Frontend: Flow Undangan

- "Assign to Other" ada di `resources/js/components/SigningModal.vue`.
  - Step 1: `POST /documents/{document}/signers` (kirim email undangan).
  - Step 2: `POST /documents/{document}/placements` (simpan placeholder).
- Landing undangan + login ada di `resources/js/components/Login.vue`.
  - Validasi token.
  - Setelah login, otomatis `POST /invitations/accept`.

## Data Model

Tabel utama:

- `document_signers` (migration: `database/migrations/2026_01_09_032750_create_document_signers_table.php`)
- Field invite: `email`, `invite_token`, `invite_expires_at`, `invite_accepted_at`
  - Tambahan di `database/migrations/2026_01_11_000001_add_invite_fields_to_document_signers_table.php`
  - Tambahan di `database/migrations/2026_01_12_000001_add_invite_meta_to_document_signers_table.php`

Model terkait:

- `app/Models/DocumentSigner.php`
- `app/Models/Document.php` (relasi `signers()` dan `placements()`)

## Status & Perubahan State

- Saat add signer: signer `PENDING`, dokumen `IN_PROGRESS`.
- Setelah placement dengan `signatureId`: signer `SIGNED`.
- Jika semua signer `SIGNED`: dokumen `signed`.
- Finalize (manual atau auto) akan mengubah dokumen jadi `COMPLETED`.

## Catatan Penting

- `docs/API_REFERENCE.md` untuk add signer masih menyebut `userId`, tapi implementasi aktual memakai `email` + `name` di request.
- Endpoint placement bisa membuat signer baru jika belum ada (menggunakan `signerUserId` atau `email`).
