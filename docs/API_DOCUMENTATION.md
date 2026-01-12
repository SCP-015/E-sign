# API Reference (E-Sign)

This document describes the HTTP API defined in `routes/api.php`.

## Base URL

- `{{base_url}}/api`
- Example local: `http://localhost:8001/api`

## Postman Environment

Create environment variables:

- `base_url` = `http://localhost:8001`
- `token` = (set after login)
- `documentId` = (set after upload)
- `signatureId` = (one of your uploaded signatures)
- `placementId` = (set after creating placements)
- `verifyToken` = (set after finalize)

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

**Important:** all JSON `data` keys are automatically converted to **camelCase** by `ApiResponse`.

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
      "kycStatus": "unverified",
      "hasSignature": false,
      "certificate": null,
      "emailVerifiedAt": null,
      "createdAt": "2026-01-01T00:00:00.000000Z",
      "updatedAt": "2026-01-01T00:00:00.000000Z"
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
- **Response**: Redirect to `/?auth_code=<code>` on success

The frontend must exchange the `auth_code` for a bearer token via `GET /auth/exchange`.

## GET /auth/exchange

Exchange `auth_code` (from web google callback) into bearer token.

- **Auth**: No

Query params:

```text
code=<auth_code>
```

Success (200):

```json
{
  "status": "success",
  "data": {
    "token": "<passport_access_token>"
  },
  "message": "OK"
}
```

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
    "tokenType": "Bearer",
    "accessToken": "<passport_access_token>",
    "user": {
      "id": 1,
      "name": "User Name",
      "email": "user@gmail.com",
      "avatar": "https://...",
      "emailVerifiedAt": null,
      "kycStatus": "unverified",
      "createdAt": "2026-01-01T00:00:00.000000Z"
    },
    "certificate": null,
    "hasCertificate": false,
    "documentsCount": 0
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

Return current authenticated user profile with KYC status and certificate info.

- **Auth**: Yes

Success (200):

```json
{
  "status": "success",
  "data": {
    "id": 1,
    "name": "User Name",
    "email": "user@example.com",
    "avatar": null,
    "kycStatus": "verified",
    "hasSignature": false,
    "certificate": {
      "id": 123,
      "userId": 1,
      "certificateNumber": "CERT-2026-00001",
      "status": "active",
      "issuedAt": "2026-01-01T00:00:00.000000Z",
      "expiresAt": "2027-01-01T00:00:00.000000Z",
      "createdAt": "2026-01-01T00:00:00.000000Z",
      "updatedAt": "2026-01-01T00:00:00.000000Z"
    },
    "emailVerifiedAt": null,
    "createdAt": "2026-01-01T00:00:00.000000Z",
    "updatedAt": "2026-01-01T00:00:00.000000Z"
  },
  "message": "OK"
}
```

Notes:

- `kycStatus`: `'unverified'` (default) or `'verified'` (after KYC submit)
- `certificate`: Only present if user has an active certificate
- Mobile/FE should call this endpoint after login or after KYC submit to refresh user status

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
      "certificateNumber": "CERT-2026-00001",
      "status": "active",
      "issuedAt": "2026-01-01T00:00:00.000000Z",
      "expiresAt": "2027-01-01T00:00:00.000000Z"
    }
  },
  "message": "Certificate issued successfully"
}
```

---

# KYC

## POST /kyc/submit

Submit KYC data and generate a certificate. Sets user `kycStatus` to `'verified'`.

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

Success (200):

```json
{
  "status": "success",
  "data": {
    "id": 1,
    "fullName": "User Name",
    "idType": "ktp",
    "idNumber": "1234567890123456",
    "dateOfBirth": "1990-01-01",
    "address": "Jl. Test No. 123",
    "city": "Jakarta",
    "province": "DKI Jakarta",
    "postalCode": "12345",
    "idPhotoPath": "private/user@example.com/kyc/id_card/...",
    "selfiePhotoPath": "private/user@example.com/kyc/selfie/...",
    "kycStatus": "verified",
    "certificate": {
      "id": 123,
      "certificateNumber": "CERT-2026-00001",
      "status": "active",
      "issuedAt": "2026-01-01T00:00:00.000000Z",
      "expiresAt": "2027-01-01T00:00:00.000000Z"
    }
  },
  "message": "KYC data submitted successfully"
}
```

Notes:

- Previous active certificates are marked as `inactive` (history preserved)
- A new active certificate is generated
- User `kycStatus` is set to `'verified'`
- Mobile/FE can use response data to update local user state or call `GET /user` to refresh

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
      "userId": 1,
      "title": "My Doc",
      "filePath": "...",
      "originalFilename": "...",
      "fileSize": 12345,
      "fileSizeBytes": 12345,
      "mimeType": "application/pdf",
      "fileType": "pdf",
      "status": "pending",
      "pageCount": 2,
      "verifyToken": null,
      "signedAt": null,
      "completedAt": null,
      "createdAt": "...",
      "updatedAt": "...",
      "signers": []
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
    "id": 1,
    "userId": 1,
    "title": "My Doc",
    "filePath": "...",
    "originalFilename": "...",
    "fileSize": 12345,
    "fileSizeBytes": 12345,
    "mimeType": "application/pdf",
    "fileType": "pdf",
    "status": "pending",
    "pageCount": 2,
    "verifyToken": null,
    "signedAt": null,
    "completedAt": null,
    "createdAt": "...",
    "updatedAt": "...",
    "signers": []
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
  "signatureId": 10,
  "signaturePosition": {
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
    "message": "Document signed successfully",
    "document": {
      "id": 1,
      "userId": 1,
      "title": "My Doc",
      "status": "signed",
      "verifyToken": null
    }
  },
  "message": "OK"
}
```

## POST /documents/{document}/finalize

Finalize document (requires all signers signed), embed verify URL QR, set status `COMPLETED`, and store LTV evidence.

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

- **LTV Evidence**: Finalize captures and stores certificate snapshot (fingerprint, serial, validity window) and signing timestamp. This allows documents to remain valid even after the user's certificate expires.
- Finalize response does not include the evidence payload; evidence is returned by verification endpoints.

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
    {"email": "signer1@example.com", "name": "Signer 1", "order": 1},
    {"email": "signer2@example.com", "name": "Signer 2", "order": 2}
  ]
}
```

## GET /documents/{document}/signers

List signers.

- **Auth**: Yes

---

# Signature Placements

## POST /documents/{document}/placements

Save signature placements for a signer. This marks the signer as `SIGNED` and may trigger auto-finalize if conditions are met.

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

Success (200):

```json
{
  "status": "success",
  "data": {
    "documentId": 1,
    "signerId": 10,
    "placements": [
      {
        "placementId": 99,
        "page": 1,
        "x": 0.1,
        "y": 0.8,
        "w": 0.2,
        "h": 0.1,
        "signatureId": 10
      }
    ],
    "signerStatus": "SIGNED"
  },
  "message": "Placements saved successfully"
}
```

Notes:

- Document status flow: `pending` → `IN_PROGRESS` → `signed` → `COMPLETED` (after finalize)

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
- `isDefault` (boolean, optional)

Success (201):

```json
{
  "status": "success",
  "data": {
    "signature": {
      "id": 10,
      "name": "My Signature",
      "imageType": "png",
      "isDefault": true,
      "createdAt": "2026-01-01T00:00:00.000000Z"
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

Verify a document by `documentId` (authenticated endpoint).

- **Auth**: Yes
- **Content-Type**: `application/json`

Request body:

```json
{
  "documentId": 1
}
```

Success (200):

```json
{
  "status": "success",
  "data": {
    "isValid": true,
    "message": "Signature is valid",
    "signedBy": "User Name",
    "signedAt": "2026-01-01T00:00:00.000000Z",
    "documentId": 1,
    "fileName": "My Doc",
    "ltv": {
      "certificateNumber": "CERT-2026-00001",
      "certificateFingerprintSha256": "...",
      "certificateNotBefore": "2026-01-01T00:00:00.000000Z",
      "certificateNotAfter": "2027-01-01T00:00:00.000000Z",
      "tsaUrl": null,
      "tsaAt": null,
      "hasTsaToken": false
    }
  },
  "message": "Signature is valid"
}
```

Notes:

- Validates using stored **LTV evidence** (certificate validity at signing time).
- If evidence is missing (e.g., documents signed before LTV implementation), the service will try to **auto-backfill** evidence from available certificates (guarded by `LTV_BACKFILL_ON_DEMAND=true` env flag).
- Returns `isValid: false` if evidence cannot be validated or is missing and backfill is disabled.

## POST /verify/upload

Public verification by uploading a signed PDF.

- **Auth**: No
- **Content-Type**: `multipart/form-data`

Form-data fields:

- `file` (pdf, required, max 10MB)

Success (200):

```json
{
  "status": "success",
  "data": {
    "isValid": true,
    "message": "Signature is valid",
    "signedBy": "Signer Name",
    "signedEmail": "signer@example.com",
    "signedAt": "2026-01-01T00:00:00.000000Z",
    "documentId": 1,
    "fileName": "My Doc",
    "ltv": {
      "certificateNumber": "CERT-2026-00001",
      "certificateFingerprintSha256": "...",
      "certificateNotBefore": "2026-01-01T00:00:00.000000Z",
      "certificateNotAfter": "2027-01-01T00:00:00.000000Z",
      "tsaUrl": null,
      "tsaAt": null,
      "hasTsaToken": false
    }
  },
  "message": "Signature is valid"
}
```

Success (200) (verify token NOT found in PDF):

```json
{
  "status": "success",
  "data": {
    "isValid": true,
    "message": "Signature is valid (signer identity unknown)",
    "signedBy": null,
    "signedEmail": null,
    "signedAt": null,
    "documentId": null,
    "fileName": "<uploaded.pdf>"
  },
  "message": "Signature is valid (signer identity unknown)"
}
```

Notes:

- If the PDF contains a `verifyToken` marker/URL (embedded during finalize), the backend will load the document and evaluate stored LTV evidence.
- If evidence is missing (e.g., old documents), the service will try to **auto-backfill** evidence from available certificates (guarded by `LTV_BACKFILL_ON_DEMAND=true` env flag).
- If `verifyToken` cannot be extracted from the PDF, the endpoint still returns HTTP 200 with `isValid: true` and message `"Signature is valid (signer identity unknown)"`.

## GET /verify/{token}

Public verification by `verifyToken`.

- **Auth**: No

Success (200):

```json
{
  "status": "success",
  "data": {
    "documentId": 1,
    "status": "COMPLETED",
    "isValid": true,
    "message": "Document is valid",
    "fileName": "My Doc",
    "completedAt": "2026-01-01T00:00:00.000000Z",
    "signers": [
      {"name": "Signer 1", "email": "signer1@example.com", "status": "SIGNED", "signedAt": "..."}
    ],
    "ltv": {
      "signedAt": "2026-01-01T00:00:00.000000Z",
      "certificateNumber": "CERT-2026-00001",
      "certificateFingerprintSha256": "...",
      "certificateNotBefore": "2026-01-01T00:00:00.000000Z",
      "certificateNotAfter": "2027-01-01T00:00:00.000000Z",
      "tsaUrl": null,
      "tsaAt": null,
      "hasTsaToken": false
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
    "isValid": false,
    "message": "Document cannot be validated (missing/invalid LTV evidence)",
    "fileName": "My Doc",
    "completedAt": "2026-01-01T00:00:00.000000Z",
    "signers": []
  },
  "message": "Document cannot be validated (missing/invalid LTV evidence)"
}
```

---

# Invitations

## GET /invitations/validate

Validate an invitation link.

- **Auth**: No

Query params (either form supported):

- `code` (string)

or

- `email` (string)
- `token` (string)

Success (200):

```json
{
  "status": "success",
  "data": {
    "valid": true,
    "email": "signer@example.com",
    "documentId": 1,
    "documentTitle": "My Doc",
    "signerId": 10,
    "expiresAt": "2026-01-20T00:00:00.000000Z"
  },
  "message": "OK"
}
```

## POST /invitations/accept

Accept an invitation for the logged-in user.

- **Auth**: Yes
- **Content-Type**: `application/json`

Request body (either form supported):

```json
{ "code": "<invite_code>" }
```

or

```json
{ "email": "signer@example.com", "token": "<invite_token>" }
```

Success (200):

```json
{
  "status": "success",
  "data": {
    "status": "accepted",
    "documentId": 1,
    "signerId": 10
  },
  "message": "OK"
}
```
