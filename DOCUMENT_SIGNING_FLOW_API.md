# 📋 Complete Document Signing Flow API Documentation

**Version:** 1.0  
**Last Updated:** January 9, 2026  
**Purpose:** Full flow from document upload → QR positioning → signing with detailed API specs for mobile dev integration

---

## 🎯 Overview - Complete Flow

```
┌─────────────────────────────────────────────────────────────────┐
│ 1. USER AUTHENTICATION                                          │
│    └─ Google Login / Email Login                                │
├─────────────────────────────────────────────────────────────────┤
│ 2. KYC VERIFICATION (if not verified)                           │
│    └─ Submit ID + Selfie Photo                                  │
├─────────────────────────────────────────────────────────────────┤
│ 3. CERTIFICATE ISSUANCE                                         │
│    └─ Generate Digital Certificate                              │
├─────────────────────────────────────────────────────────────────┤
│ 4. DOCUMENT UPLOAD                                              │
│    └─ Upload PDF (max 10MB)                                     │
├─────────────────────────────────────────────────────────────────┤
│ 5. QR POSITION SETUP (NEW!)                                     │
│    ├─ Get current QR position (default: center)                 │
│    └─ Drag & drop → Update QR position                          │
├─────────────────────────────────────────────────────────────────┤
│ 6. DOCUMENT SIGNING                                             │
│    ├─ Generate QR code with signature data                      │
│    ├─ Place QR at saved position                                │
│    └─ Sign PDF with certificate                                 │
├─────────────────────────────────────────────────────────────────┤
│ 7. DOWNLOAD SIGNED DOCUMENT                                     │
│    └─ Download PDF with embedded QR signature                   │
└─────────────────────────────────────────────────────────────────┘
```

---

## 📋 Prerequisites & Setup

### **1. Authentication Token Required**
All API endpoints (except login) require Bearer token:
```
Authorization: Bearer {token}
```

### **2. User Must Be Verified**
- KYC status must be **"verified"** to upload documents
- If not verified, submit KYC first (see KYC_CURL.md)

### **3. Active Certificate Required**
- User must have active certificate to sign documents
- Certificate auto-generated during KYC submission

### **4. File Requirements**
- Format: **PDF only**
- Max size: **10 MB**
- MIME type: `application/pdf`

### **5. Headers Required**
```
Authorization: Bearer {token}
Content-Type: application/json  (or multipart/form-data for uploads)
Accept: application/json
```

---

## 🔐 Step 1: Authentication

### **Option A: Google Login**

**Endpoint:**
```
GET /api/auth/google
```

**What to do:**
1. Redirect user to: `http://127.0.0.1:8001/api/auth/google`
2. User logs in with Google
3. Redirected back to: `http://127.0.0.1:8001/auth/google/callback`
4. Token returned in response

**Response:**
```json
{
  "token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
  "user": {
    "id": 1,
    "name": "John Doe",
    "email": "john@gmail.com",
    "avatar": "https://...",
    "kyc_status": "verified"
  }
}
```

---

### **Option B: Email Login**

**Endpoint:**
```
POST /api/auth/login
```

**Headers:**
```
Content-Type: application/json
Accept: application/json
```

**Request Body:**
```json
{
  "email": "user@example.com",
  "password": "password123"
}
```

**Response (Success - 200):**
```json
{
  "token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
  "user": {
    "id": 1,
    "name": "John Doe",
    "email": "user@example.com",
    "kyc_status": "verified"
  }
}
```

**Response (Error - 401):**
```json
{
  "message": "Invalid credentials"
}
```

---

## ✅ Step 2: Verify KYC Status

**Endpoint:**
```
GET /api/user
```

**Headers:**
```
Authorization: Bearer {token}
Accept: application/json
```

**Response:**
```json
{
  "id": 1,
  "name": "John Doe",
  "email": "user@example.com",
  "kyc_status": "verified",  // ← Check this!
  "avatar": "https://..."
}
```

**If `kyc_status` is NOT "verified":**
→ Submit KYC first (see POSTMAN_KYC_CURL.md)

---

## 📤 Step 3: Upload Document

**Endpoint:**
```
POST /api/documents
```

**Headers:**
```
Authorization: Bearer {token}
Content-Type: multipart/form-data
Accept: application/json
```

**Request Body (Form Data):**
```
Key: file
Value: [PDF file]  (binary)
```

**cURL Example:**
```bash
TOKEN="your_token_here"

curl -X POST 'http://127.0.0.1:8001/api/documents' \
  -H "Authorization: Bearer $TOKEN" \
  -H 'Accept: application/json' \
  -F 'file=@/path/to/document.pdf'
```

**Response (Success - 201):**
```json
{
  "message": "Document uploaded successfully",
  "document": {
    "id": 5,
    "user_id": 1,
    "file_path": "user@example.com/documents/document.pdf",
    "original_filename": "document.pdf",
    "file_size": 2097152,
    "mime_type": "application/pdf",
    "status": "pending",
    "signed_path": null,
    "signed_at": null,
    "qr_position": {
      "x": 0.5,
      "y": 0.5,
      "width": 0.15,
      "height": 0.15,
      "page": 1
    },
    "created_at": "2026-01-09T08:30:00.000000Z",
    "updated_at": "2026-01-09T08:30:00.000000Z"
  }
}
```

**Response (Error - 422):**
```json
{
  "message": "The file field is required.",
  "errors": {
    "file": [
      "The file field is required."
    ]
  }
}
```

**Common Errors:**
| Error | Cause | Solution |
|-------|-------|----------|
| File field required | No file sent | Attach PDF file |
| File must be PDF | Wrong format | Use .pdf only |
| File size exceeds 10MB | File too large | Compress PDF |
| KYC not verified | User not verified | Submit KYC first |

---

## 🎯 Step 4: Get Current QR Position

**Endpoint:**
```
GET /api/documents/{documentId}/qr-position
```

**Headers:**
```
Authorization: Bearer {token}
Accept: application/json
```

**URL Parameters:**
```
{documentId} = Document ID from upload response (e.g., 5)
```

**cURL Example:**
```bash
TOKEN="your_token_here"
DOC_ID=5

curl -X GET "http://127.0.0.1:8001/api/documents/$DOC_ID/qr-position" \
  -H "Authorization: Bearer $TOKEN" \
  -H 'Accept: application/json'
```

**Response (Success - 200):**
```json
{
  "document_id": 5,
  "qr_position": {
    "x": 0.5,
    "y": 0.5,
    "width": 0.15,
    "height": 0.15,
    "page": 1
  }
}
```

**What Each Field Means:**
- `x`: Horizontal position (0 = left, 1 = right)
- `y`: Vertical position (0 = top, 1 = bottom)
- `width`: QR code width relative to page (0.15 = 15% of page width)
- `height`: QR code height relative to page (0.15 = 15% of page height)
- `page`: Which page to place QR (1 = first page)

**Example Positions:**
```
Top-Left:     x=0.05, y=0.05
Top-Right:    x=0.80, y=0.05
Bottom-Left:  x=0.05, y=0.80
Bottom-Right: x=0.80, y=0.80
Center:       x=0.5, y=0.5
```

---

## 🎨 Step 5: Update QR Position (Drag & Drop)

**Endpoint:**
```
PUT /api/documents/{documentId}/qr-position
```

**Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
Accept: application/json
```

**URL Parameters:**
```
{documentId} = Document ID (e.g., 5)
```

**Request Body:**
```json
{
  "x": 0.42,
  "y": 0.68,
  "width": 0.15,
  "height": 0.15,
  "page": 1
}
```

**Validation Rules:**
```
x:      numeric, min:0, max:1
y:      numeric, min:0, max:1
width:  numeric, min:0.01, max:0.5
height: numeric, min:0.01, max:0.5
page:   integer, min:1
```

**cURL Example:**
```bash
TOKEN="your_token_here"
DOC_ID=5

curl -X PUT "http://127.0.0.1:8001/api/documents/$DOC_ID/qr-position" \
  -H "Authorization: Bearer $TOKEN" \
  -H 'Content-Type: application/json' \
  -H 'Accept: application/json' \
  -d '{
    "x": 0.42,
    "y": 0.68,
    "width": 0.15,
    "height": 0.15,
    "page": 1
  }'
```

**Response (Success - 200):**
```json
{
  "status": "success",
  "message": "QR position updated successfully",
  "qr_position": {
    "x": 0.42,
    "y": 0.68,
    "width": 0.15,
    "height": 0.15,
    "page": 1
  }
}
```

**Response (Error - 422):**
```json
{
  "message": "The x field must be between 0 and 1.",
  "errors": {
    "x": [
      "X coordinate must be between 0 and 1 (0% to 100%)"
    ]
  }
}
```

**Common Validation Errors:**
| Error | Cause | Fix |
|-------|-------|-----|
| x must be between 0 and 1 | x < 0 or x > 1 | Use decimal 0-1 |
| y must be between 0 and 1 | y < 0 or y > 1 | Use decimal 0-1 |
| width must be between 0.01 and 0.5 | width too small/large | Use 0.01-0.5 |
| height must be between 0.01 and 0.5 | height too small/large | Use 0.01-0.5 |
| page must be at least 1 | page < 1 | Use page ≥ 1 |

---

## ✍️ Step 6: Sign Document

**Endpoint:**
```
POST /api/documents/{documentId}/sign
```

**Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
Accept: application/json
```

**URL Parameters:**
```
{documentId} = Document ID (e.g., 5)
```

**Request Body:**
```json
{}
```

**Note:** No body required! Backend uses saved QR position from database.

**cURL Example:**
```bash
TOKEN="your_token_here"
DOC_ID=5

curl -X POST "http://127.0.0.1:8001/api/documents/$DOC_ID/sign" \
  -H "Authorization: Bearer $TOKEN" \
  -H 'Content-Type: application/json' \
  -H 'Accept: application/json' \
  -d '{}'
```

**Response (Success - 200):**
```json
{
  "message": "Document signed successfully",
  "signed_url": "http://127.0.0.1:8001/storage/user@example.com/documents/signed_document.pdf",
  "path": "user@example.com/documents/signed_document.pdf",
  "qr_position_used": {
    "x": 0.42,
    "y": 0.68,
    "width": 0.15,
    "height": 0.15,
    "page": 1
  }
}
```

**Response (Error - 400):**
```json
{
  "message": "No active certificate found. Please issue one first."
}
```

**Response (Error - 404):**
```json
{
  "message": "Document not found"
}
```

**What Happens Behind the Scenes:**
1. ✅ Fetch document from database
2. ✅ Verify user owns document
3. ✅ Get user's active certificate
4. ✅ Generate QR code with signature data:
   ```json
   {
     "document_id": 5,
     "signed_by": "John Doe",
     "signed_at": "2026-01-09T08:45:00Z",
     "certificate_id": 1
   }
   ```
5. ✅ Get saved QR position from database
6. ✅ Convert relative coordinates (0-1) to absolute pixels
7. ✅ Place QR code on PDF at calculated position
8. ✅ Sign PDF with user's certificate
9. ✅ Save signed PDF to storage
10. ✅ Update document status to "signed"
11. ✅ Record signed_at timestamp

---

## 📥 Step 7: Download Signed Document

**Endpoint:**
```
GET /api/documents/{documentId}/download
```

**Headers:**
```
Authorization: Bearer {token}
Accept: application/json
```

**URL Parameters:**
```
{documentId} = Document ID (e.g., 5)
```

**cURL Example:**
```bash
TOKEN="your_token_here"
DOC_ID=5

curl -X GET "http://127.0.0.1:8001/api/documents/$DOC_ID/download" \
  -H "Authorization: Bearer $TOKEN" \
  -o signed_document.pdf
```

**Response (Success - 200):**
```
[Binary PDF file content]
```

**Response Headers:**
```
Content-Type: application/pdf
Content-Disposition: attachment; filename="signed_document.pdf"
Content-Length: 2097152
```

**Response (Error - 400):**
```json
{
  "message": "Document is not signed yet"
}
```

**Response (Error - 404):**
```json
{
  "message": "Signed document file not found"
}
```

---

## 📊 Step 8: Get Document List

**Endpoint:**
```
GET /api/documents
```

**Headers:**
```
Authorization: Bearer {token}
Accept: application/json
```

**cURL Example:**
```bash
TOKEN="your_token_here"

curl -X GET 'http://127.0.0.1:8001/api/documents' \
  -H "Authorization: Bearer $TOKEN" \
  -H 'Accept: application/json'
```

**Response (Success - 200):**
```json
[
  {
    "id": 5,
    "user_id": 1,
    "file_path": "user@example.com/documents/document.pdf",
    "original_filename": "document.pdf",
    "file_size": 2097152,
    "mime_type": "application/pdf",
    "status": "signed",
    "signed_path": "user@example.com/documents/signed_document.pdf",
    "signed_at": "2026-01-09T08:45:00.000000Z",
    "qr_position": {
      "x": 0.42,
      "y": 0.68,
      "width": 0.15,
      "height": 0.15,
      "page": 1
    },
    "created_at": "2026-01-09T08:30:00.000000Z",
    "updated_at": "2026-01-09T08:45:00.000000Z"
  }
]
```

---

## 🔍 Complete Postman Collection

### **1. Setup in Postman**

**Create Environment Variables:**
```
base_url: http://127.0.0.1:8001
token: (will be filled after login)
doc_id: (will be filled after upload)
```

**In Postman, use:**
```
{{base_url}}
{{token}}
{{doc_id}}
```

---

### **2. Login Request**

**Name:** `1. Login`

```
POST {{base_url}}/api/auth/login
```

**Headers:**
```
Content-Type: application/json
Accept: application/json
```

**Body (raw JSON):**
```json
{
  "email": "user@example.com",
  "password": "password123"
}
```

**Tests (Script):**
```javascript
if (pm.response.code === 200) {
    var jsonData = pm.response.json();
    pm.environment.set("token", jsonData.token);
    console.log("Token saved:", jsonData.token);
}
```

---

### **3. Get User Info**

**Name:** `2. Get User Info`

```
GET {{base_url}}/api/user
```

**Headers:**
```
Authorization: Bearer {{token}}
Accept: application/json
```

---

### **4. Upload Document**

**Name:** `3. Upload Document`

```
POST {{base_url}}/api/documents
```

**Headers:**
```
Authorization: Bearer {{token}}
Accept: application/json
```

**Body (form-data):**
```
Key: file
Type: File
Value: [Select PDF file]
```

**Tests (Script):**
```javascript
if (pm.response.code === 201) {
    var jsonData = pm.response.json();
    pm.environment.set("doc_id", jsonData.document.id);
    console.log("Document ID saved:", jsonData.document.id);
}
```

---

### **5. Get QR Position**

**Name:** `4. Get QR Position`

```
GET {{base_url}}/api/documents/{{doc_id}}/qr-position
```

**Headers:**
```
Authorization: Bearer {{token}}
Accept: application/json
```

---

### **6. Update QR Position**

**Name:** `5. Update QR Position`

```
PUT {{base_url}}/api/documents/{{doc_id}}/qr-position
```

**Headers:**
```
Authorization: Bearer {{token}}
Content-Type: application/json
Accept: application/json
```

**Body (raw JSON):**
```json
{
  "x": 0.42,
  "y": 0.68,
  "width": 0.15,
  "height": 0.15,
  "page": 1
}
```

---

### **7. Sign Document**

**Name:** `6. Sign Document`

```
POST {{base_url}}/api/documents/{{doc_id}}/sign
```

**Headers:**
```
Authorization: Bearer {{token}}
Content-Type: application/json
Accept: application/json
```

**Body (raw JSON):**
```json
{}
```

---

### **8. Download Signed Document**

**Name:** `7. Download Document`

```
GET {{base_url}}/api/documents/{{doc_id}}/download
```

**Headers:**
```
Authorization: Bearer {{token}}
Accept: application/json
```

**Note:** In Postman, click "Send and Download" to save PDF file.

---

### **9. Get Document List**

**Name:** `8. Get Document List`

```
GET {{base_url}}/api/documents
```

**Headers:**
```
Authorization: Bearer {{token}}
Accept: application/json
```

---

## 🚀 Complete Flow - Step by Step

### **Scenario: Upload, Position QR, Sign, and Download**

```bash
#!/bin/bash

# Configuration
BASE_URL="http://127.0.0.1:8001"
EMAIL="user@example.com"
PASSWORD="password123"
PDF_FILE="/path/to/document.pdf"

echo "=== Step 1: Login ==="
LOGIN_RESPONSE=$(curl -s -X POST "$BASE_URL/api/auth/login" \
  -H 'Content-Type: application/json' \
  -d "{
    \"email\": \"$EMAIL\",
    \"password\": \"$PASSWORD\"
  }")

TOKEN=$(echo $LOGIN_RESPONSE | jq -r '.token')
echo "Token: $TOKEN"

echo -e "\n=== Step 2: Upload Document ==="
UPLOAD_RESPONSE=$(curl -s -X POST "$BASE_URL/api/documents" \
  -H "Authorization: Bearer $TOKEN" \
  -F "file=@$PDF_FILE")

DOC_ID=$(echo $UPLOAD_RESPONSE | jq -r '.document.id')
echo "Document ID: $DOC_ID"

echo -e "\n=== Step 3: Get Current QR Position ==="
curl -s -X GET "$BASE_URL/api/documents/$DOC_ID/qr-position" \
  -H "Authorization: Bearer $TOKEN" | jq '.'

echo -e "\n=== Step 4: Update QR Position ==="
curl -s -X PUT "$BASE_URL/api/documents/$DOC_ID/qr-position" \
  -H "Authorization: Bearer $TOKEN" \
  -H 'Content-Type: application/json' \
  -d '{
    "x": 0.42,
    "y": 0.68,
    "width": 0.15,
    "height": 0.15,
    "page": 1
  }' | jq '.'

echo -e "\n=== Step 5: Sign Document ==="
curl -s -X POST "$BASE_URL/api/documents/$DOC_ID/sign" \
  -H "Authorization: Bearer $TOKEN" \
  -H 'Content-Type: application/json' \
  -d '{}' | jq '.'

echo -e "\n=== Step 6: Download Signed Document ==="
curl -X GET "$BASE_URL/api/documents/$DOC_ID/download" \
  -H "Authorization: Bearer $TOKEN" \
  -o "signed_document_$DOC_ID.pdf"

echo -e "\nDone! Signed document saved as: signed_document_$DOC_ID.pdf"
```

---

## ⚠️ Important Notes for Mobile Dev

### **1. Relative Coordinates (0-1 System)**
- **NOT pixel-based** - works across all screen sizes
- `x=0.5, y=0.5` = center of page (always!)
- `x=0.8, y=0.8` = bottom-right corner (always!)
- Frontend calculates: `x = qrLeft / canvasWidth`

### **2. QR Code Content**
Backend generates QR code containing:
```json
{
  "document_id": 5,
  "signed_by": "John Doe",
  "signed_at": "2026-01-09T08:45:00Z",
  "certificate_id": 1
}
```

### **3. Error Handling**
Always check response status:
- `200/201` = Success
- `400` = Bad request (validation error)
- `401` = Unauthorized (invalid token)
- `404` = Not found
- `422` = Validation failed

### **4. Token Management**
- Token obtained from login response
- Include in all requests: `Authorization: Bearer {token}`
- Token expires after 24 hours (adjust in config)
- Refresh token if needed

### **5. File Upload Limits**
- Max file size: **10 MB**
- Format: **PDF only**
- MIME type: `application/pdf`
- Check file size before upload

### **6. QR Position Constraints**
```
x: 0 ≤ x ≤ 1
y: 0 ≤ y ≤ 1
width: 0.01 ≤ width ≤ 0.5
height: 0.01 ≤ height ≤ 0.5
page: ≥ 1
```

### **7. Multi-Page PDF Support**
- `page` parameter specifies which page to place QR
- Default: page 1
- Can be any page number ≥ 1

### **8. Coordinate Conversion Example (Frontend)**
```javascript
// When user drags QR on canvas
const canvas = document.getElementById('pdf-canvas');
const qr = document.getElementById('qr-box');

const canvasRect = canvas.getBoundingClientRect();
const qrRect = qr.getBoundingClientRect();

// Calculate relative position
const relativeX = (qrRect.left - canvasRect.left) / canvasRect.width;
const relativeY = (qrRect.top - canvasRect.top) / canvasRect.height;
const relativeWidth = qrRect.width / canvasRect.width;
const relativeHeight = qrRect.height / canvasRect.height;

// Send to backend
await axios.put(`/api/documents/${docId}/qr-position`, {
  x: relativeX,
  y: relativeY,
  width: relativeWidth,
  height: relativeHeight,
  page: 1
});
```

---

## 📞 Support & Troubleshooting

### **Common Issues**

| Issue | Cause | Solution |
|-------|-------|----------|
| 401 Unauthorized | Invalid/expired token | Re-login and get new token |
| 422 File validation | Wrong format/size | Use PDF ≤ 10MB |
| 400 No certificate | User not verified | Submit KYC first |
| 404 Document not found | Wrong document ID | Check document ID |
| QR not visible in PDF | Position out of bounds | Use 0-1 range |
| Download opens in browser | Wrong content-type | Use responseType: 'blob' |

### **Testing Checklist**

- [ ] User can login
- [ ] User KYC status is "verified"
- [ ] User has active certificate
- [ ] Can upload PDF (≤ 10MB)
- [ ] Can get QR position (default center)
- [ ] Can update QR position with valid coordinates
- [ ] Can sign document (generates QR code)
- [ ] Can download signed document
- [ ] QR code visible in signed PDF
- [ ] QR code at correct position

---

## 🎓 Example: Complete Mobile App Flow

### **Flutter/React Native Implementation**

```dart
// 1. Login
final loginResponse = await dio.post(
  '/api/auth/login',
  data: {
    'email': email,
    'password': password,
  },
);
final token = loginResponse.data['token'];

// 2. Upload Document
final uploadResponse = await dio.post(
  '/api/documents',
  data: FormData.fromMap({
    'file': await MultipartFile.fromFile(pdfPath),
  }),
  options: Options(
    headers: {'Authorization': 'Bearer $token'},
  ),
);
final docId = uploadResponse.data['document']['id'];

// 3. Get QR Position
final posResponse = await dio.get(
  '/api/documents/$docId/qr-position',
  options: Options(
    headers: {'Authorization': 'Bearer $token'},
  ),
);
final qrPos = posResponse.data['qr_position'];

// 4. Update QR Position (after drag)
await dio.put(
  '/api/documents/$docId/qr-position',
  data: {
    'x': newX,
    'y': newY,
    'width': 0.15,
    'height': 0.15,
    'page': 1,
  },
  options: Options(
    headers: {'Authorization': 'Bearer $token'},
  ),
);

// 5. Sign Document
final signResponse = await dio.post(
  '/api/documents/$docId/sign',
  options: Options(
    headers: {'Authorization': 'Bearer $token'},
  ),
);

// 6. Download Document
final downloadResponse = await dio.get(
  '/api/documents/$docId/download',
  options: Options(
    headers: {'Authorization': 'Bearer $token'},
    responseType: ResponseType.bytes,
  ),
);
final pdfBytes = downloadResponse.data;
// Save to file...
```

---

## 📝 Summary

**Complete flow:**
1. ✅ Login → Get token
2. ✅ Verify KYC status
3. ✅ Upload PDF document
4. ✅ Get default QR position (center)
5. ✅ Update QR position (drag & drop)
6. ✅ Sign document (QR embedded at position)
7. ✅ Download signed PDF
8. ✅ QR code visible at saved position

**All endpoints secured with Bearer token authentication.**

**Ready for mobile integration!** 🚀
