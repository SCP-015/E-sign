# Mobile API Documentation: KYC & Auth

**Base URL**: `https://{domain}/api`

---

## 1. Google Mobile Login
Exchange a Google ID Token for an App Access Token.

**Endpoint**: `POST /auth/google/mobile`
**Headers**:
- `Accept: application/json`
- `Content-Type: application/json`

**Body (JSON)**:
```json
{
  "id_token": "eyJhbGciOiJSUzI1NiIsImtpZCI..." // Token from Google Sign-In SDK
}
```

**Response (200 OK)**:
```json
{
  "status": "success",
  "message": "Login successful",
  "token": "1|laravel_sanctum_token...", // Store this effectively
  "user": {
      "id": 1,
      "email": "user@example.com",
      "name": "User Name",
      "kyc_status": "unverified" // or "verified"
  }
}
```

---

## 2. Submit KYC Data
Submit user identity data to verify account and generate specific User Certificate.

**Endpoint**: `POST /kyc/submit`
**Headers**:
- `Authorization: Bearer <your_token>` (REQUIRED)
- `Content-Type: multipart/form-data`

**Body (Multipart)**:
| Key | Type | Description |
| :--- | :--- | :--- |
| `nik` | String | 16-digit National ID Number |
| `name` | String | Full Name as per ID Card |
| `id_card` | File | Image of ID Card (JPG/PNG, Max 2MB) |
| `selfie` | File | Selfie image for verification (JPG/PNG) |

**Security Note (Biometrics)**:
- **Transmission**: The payload is secured via **TLS/SSL (HTTPS)**. No additional application-level encryption is required for the payload body unless complying with specific banking regulations (e.g., AES encrypted payload). For this MVP/Standard use, HTTPS is sufficient.
- **Storage**: The backend stores these files in a **Private/Secure** directory (`storage/app/private`), accessible only via the application, not via public URL.

**Response (200 OK)**:
```json
{
  "message": "KYC Verified & Certificate Issued",
  "kyc_status": "verified",
  "certificate_id": 123
}
```

---

## 3. Check Status / Dashboard
Get current user status (KYC and Certificates).

**Endpoint**: `GET /digital-signature/dashboard` (or generic User profile)
