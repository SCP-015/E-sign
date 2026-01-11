# API Reference (E-Sign)

This document describes the HTTP API defined in `routes/api.php`.

## Base URL

- `{{base_url}}/api`
- Example local: `http://localhost:8001/api`

## Postman Environment

Create environment variables:

- `base_url` = `http://localhost:8001`
- `token` = (set after login)
- `document_id` = (set after upload)
- `signature_id` = (one of your uploaded signatures)
- `placement_id` = (set after creating placements)
- `verify_token` = (set after finalize)

## Authentication

Most endpoints require Bearer token:

- Header: `Authorization: Bearer {{token}}`

Protected endpoints are wrapped by `auth:api` middleware.

## Common Headers

- `Accept: application/json`
- For JSON requests: `Content-Type: application/json`
- For file upload: `Content-Type: multipart/form-data`

## Response Format

Most JSON endpoints return:

```json
{
  "status": "success" | "error",
  "data": {},
  "message": "OK"
}
```

Some endpoints return **binary** (PDF/image) and do not follow the JSON wrapper.

## Status Codes

- `200` OK
- `201` Created
- `400` Bad Request
- `401` Unauthenticated/Invalid credentials
- `404` Not Found
- `422` Validation error
- `500` Server error

---

# Auth

## POST /auth/login

Create user if not exists; otherwise validate password.

- **Auth**: No
- **Content-Type**: `application/json`

Request body:

```json
{
  "email": "user@example.com",
  "password": "password123",
  "name": "Optional Name" 
}
```

cURL:

```bash
curl -X POST "{{base_url}}/api/auth/login" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"email":"user@example.com","password":"password123"}'
```

Success (200):

```json
{
  "status": "success",
  "data": {
    "token": "<passport_access_token>",
    "user": {
      "id": 1,
      "name": "Test User",
      "email": "user@example.com",
      "avatar": null,
      "kyc_status": "unverified",
      "certificate": null,
      "email_verified_at": null,
      "created_at": "2026-01-01T00:00:00.000000Z",
      "updated_at": "2026-01-01T00:00:00.000000Z"
    }
  },
  "message": "Login successful"
}
```

Error (401):

```json
{
  "status": "error",
  "data": null,
  "message": "Invalid credentials"
}
```

## GET /auth/google/redirect

Start Google OAuth web login.

- **Auth**: No
- **Response**: HTTP redirect to Google

## GET /auth/google/callback

Google OAuth web callback.

- **Auth**: No
- **Response**: Redirect to `/?token=<token>` on success (token in query string)

## POST /auth/google/mobile

Mobile Google login (ID token / access token / auth code).

- **Auth**: No
- **Content-Type**: `application/json`

Request body (at least one is required):

```json
{
  "id_token": "...",
  "access_token": "...",
  "code": "..."
}
```

Success (200):

```json
{
  "status": "success",
  "data": {
    "token_type": "Bearer",
    "access_token": "<passport_access_token>",
    "user": {
      "id": 1,
      "name": "User Name",
      "email": "user@gmail.com",
      "avatar": "https://...",
      "email_verified_at": null,
      "kyc_status": "unverified",
      "created_at": "2026-01-01T00:00:00.000000Z"
    },
    "certificate": null,
    "has_certificate": false,
    "documents_count": 0
  },
  "message": "OK"
}
```

## POST /auth/google/mobile/code

Mobile Google login using only auth `code`.

- **Auth**: No
- **Content-Type**: `application/json`

Request body:

```json
{
  "code": "..."
}
```

## POST /auth/logout

Invalidate tokens.

- **Auth**: Yes

cURL:

```bash
curl -X POST "{{base_url}}/api/auth/logout" \
  -H "Authorization: Bearer {{token}}" \
  -H "Accept: application/json"
```

---

# User

## GET /user

Return current authenticated user profile.

- **Auth**: Yes

---

# Certificates

## POST /certificates/issue

Issue/renew a user certificate (requires KYC verified).

- **Auth**: Yes

Notes:

- Renew does **not** delete historical certificates. Previous active certificates are marked as `inactive`.
- Document verification uses stored **LTV evidence** (captured at finalize/signing time), so old documents can remain valid even after certificate expiry.

Success (200):

```json
{
  "status": "success",
  "data": {
    "certificate": {
      "id": 123,
      "certificate_number": "CERT-2026-00001",
      "status": "active",
      "issued_at": "2026-01-01T00:00:00.000000Z",
      "expires_at": "2027-01-01T00:00:00.000000Z"
    }
  },
  "message": "Certificate issued successfully"
}
```

---

# KYC

## POST /kyc/submit

Submit KYC data and generate a certificate.

- **Auth**: Yes
- **Content-Type**: `multipart/form-data`

Form-data fields:

- `full_name` (string, required)
- `id_type` (string, required) one of: `ktp|passport|sim|other`
- `id_number` (string, required)
- `date_of_birth` (string, required) format `YYYY-MM-DD`
- `address` (string, required)
- `city` (string, required)
- `province` (string, required)
- `postal_code` (string, required)
- `id_photo` (file jpg/jpeg/png, required, max 5MB)
- `selfie_photo` (file jpg/jpeg/png, required, max 5MB)

---

# Documents

## GET /documents

List documents owned by the authenticated user.

- **Auth**: Yes

Success (200):

```json
{
  "status": "success",
  "data": [
    {
      "id": 1,
      "title": "My Doc",
      "file_path": "...",
      "status": "pending",
      "verify_token": null,
      "created_at": "...",
      "updated_at": "..."
    }
  ],
  "message": "OK"
}
```

## POST /documents

Upload a PDF.

- **Auth**: Yes
- **Content-Type**: `multipart/form-data`

Form-data fields:

- `file` (pdf, required, max 10MB)
- `title` (string, optional) Note: accepted by controller.

Success (201):

```json
{
  "status": "success",
  "data": {
    "documentId": 1,
    "fileName": "<basename.pdf>",
    "fileType": "pdf",
    "fileSizeBytes": 12345,
    "pageCount": 2,
    "status": "pending",
    "createdAt": "2026-01-01T00:00:00.000000Z"
  },
  "message": "OK"
}
```

Error (400) when certificate missing:

```json
{
  "status": "error",
  "data": null,
  "message": "No active certificate found. Please complete KYC verification first."
}
```

## GET /documents/{document}

Show document summary.

- **Auth**: Yes

Success (200):

```json
{
  "status": "success",
  "data": {
    "documentId": 1,
    "status": "pending",
    "pageCount": 2,
    "verify_token": null
  },
  "message": "OK"
}
```

## GET /documents/{document}/view-url

Return the original PDF inline.

- **Auth**: Yes
- **Response**: `application/pdf` (inline)

## POST /documents/{document}/sign

Sign the document with a selected signature image and a normalized position.

- **Auth**: Yes
- **Content-Type**: `application/json`

Request body:

```json
{
  "signature_id": 10,
  "signature_position": {
    "x": 0.1,
    "y": 0.8,
    "width": 0.2,
    "height": 0.1,
    "page": 1
  }
}
```

Success (200):

```json
{
  "status": "success",
  "data": {
    "document": {
      "id": 1,
      "title": "My Doc",
      "status": "signed",
      "verify_token": null
    }
  },
  "message": "Document signed successfully"
}
```

## POST /documents/{document}/finalize

Finalize document (requires all signers signed), embed verify URL QR, set status `COMPLETED`.

- **Auth**: Yes
- **Content-Type**: `application/json`

Request body (optional):

```json
{
  "qrPlacement": {
    "page": "LAST",
    "position": "BOTTOM_RIGHT",
    "marginBottom": 15,
    "marginRight": 15,
    "size": 35
  }
}
```

Success (200):

```json
{
  "status": "success",
  "data": {
    "documentId": 1,
    "status": "COMPLETED",
    "verifyUrl": "{{base_url}}/api/verify/<token>",
    "qrValue": "{{base_url}}/api/verify/<token>",
    "finalPdfUrl": "{{base_url}}/api/documents/1/download",
    "completedAt": "2026-01-01T00:00:00.000000Z"
  },
  "message": "OK"
}
```

Notes:

- Finalize stores **LTV evidence** (certificate snapshot + validity window + signing timestamp) to keep documents verifiable even if the user's certificate later expires.
- Optional TSA: if server has `TSA_URL` configured, finalize will attempt to request a TSA token (best-effort).

Error (400) when certificate expired:

```json
{
  "status": "error",
  "data": null,
  "message": "Certificate is not active or has expired. Please renew your certificate before finalizing."
}
```

## GET /documents/{document}/download

Download final/signed PDF.

- **Auth**: Yes
- **Response**: `application/pdf` (attachment)

---

# Document Signers

## POST /documents/{document}/signers

Add signers to a document.

- **Auth**: Yes
- **Content-Type**: `application/json`

Request body:

```json
{
  "signers": [
    {"userId": 2, "name": "Signer 1", "order": 1},
    {"userId": 3, "name": "Signer 2", "order": 2}
  ]
}
```

## GET /documents/{document}/signers

List signers.

- **Auth**: Yes

---

# Signature Placements

## POST /documents/{document}/placements

Save signature placements for a signer. This also marks the signer as `SIGNED` and may set document status to `signed` when everyone is signed.

- **Auth**: Yes
- **Content-Type**: `application/json`

Request body:

```json
{
  "signerUserId": 2,
  "placements": [
    {
      "page": 1,
      "x": 0.1,
      "y": 0.8,
      "w": 0.2,
      "h": 0.1,
      "signatureId": 10
    }
  ]
}
```

## GET /documents/{document}/placements

List all placements.

- **Auth**: Yes

## PUT /documents/{document}/placements/{placement}

Update a placement.

- **Auth**: Yes
- **Content-Type**: `application/json`

Request body (any of):

```json
{
  "x": 0.2,
  "y": 0.7,
  "w": 0.25,
  "h": 0.12
}
```

---

# User Signatures

## GET /signatures

List uploaded signatures for the authenticated user.

- **Auth**: Yes

## POST /signatures

Upload a signature image.

- **Auth**: Yes
- **Content-Type**: `multipart/form-data`

Form-data fields:

- `image` (file png/svg, required, max 2MB)
- `name` (string, optional)
- `is_default` (boolean, optional)

Success (201):

```json
{
  "status": "success",
  "data": {
    "signature": {
      "id": 10,
      "name": "My Signature",
      "image_type": "png",
      "is_default": true,
      "created_at": "2026-01-01T00:00:00.000000Z"
    }
  },
  "message": "Signature uploaded successfully"
}
```

## GET /signatures/{signature}/image

Fetch signature binary.

- **Auth**: Yes
- **Response**: `image/png` or `image/svg+xml`

## PUT /signatures/{signature}/default

Set a signature as default.

- **Auth**: Yes

## DELETE /signatures/{signature}

Delete a signature.

- **Auth**: Yes

---

# Verification

## POST /documents/verify

Verify a document by `document_id` (authenticated endpoint).

- **Auth**: Yes
- **Content-Type**: `application/json`

Request body:

```json
{
  "document_id": 1
}
```

Notes:

- This endpoint validates using stored **LTV evidence** (certificate validity at signing time).
- If evidence is missing, the service will try to **auto-backfill** evidence from available certificates.

## POST /verify/upload

Public verification by uploading a signed PDF.

- **Auth**: No
- **Content-Type**: `multipart/form-data`

Form-data fields:

- `file` (pdf, required, max 10MB)

Notes:

- If the PDF contains a `verify_token` marker/URL, the backend will try to load the document and evaluate stored LTV evidence.
- If evidence is missing (e.g., old documents), the service will try to **auto-backfill** evidence from available certificates.

## GET /verify/{token}

Public verification by `verify_token`.

- **Auth**: No

Success (200):

```json
{
  "status": "success",
  "data": {
    "documentId": 1,
    "status": "COMPLETED",
    "is_valid": true,
    "message": "Document is valid",
    "fileName": "My Doc",
    "completedAt": "2026-01-01T00:00:00.000000Z",
    "signers": [
      {"name": "Signer 1", "status": "SIGNED", "signedAt": "..."}
    ],
    "ltv": {
      "signedAt": "2026-01-01T00:00:00.000000Z",
      "certificate_number": "CERT-2026-00001",
      "certificate_fingerprint_sha256": "...",
      "certificate_not_before": "2026-01-01T00:00:00.000000Z",
      "certificate_not_after": "2027-01-01T00:00:00.000000Z",
      "tsa_url": null,
      "tsa_at": null,
      "has_tsa_token": false
    }
  },
  "message": "Document is valid"
}
```

Example (200) but invalid (missing/invalid evidence):

```json
{
  "status": "success",
  "data": {
    "documentId": 1,
    "status": "COMPLETED",
    "is_valid": false,
    "message": "Document cannot be validated (missing/invalid LTV evidence)",
    "fileName": "My Doc",
    "completedAt": "2026-01-01T00:00:00.000000Z",
    "signers": []
  },
  "message": "Document cannot be validated (missing/invalid LTV evidence)"
}
```
