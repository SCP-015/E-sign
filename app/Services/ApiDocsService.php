<?php

namespace App\Services;

class ApiDocsService
{
    public function getSpec(): array
    {
        return [
            'title' => 'E-Sign API Documentation',
            'version' => '1.0.0',
            'baseUrl' => url('/api'),
            'contentType' => 'application/json',
            'architecture' => [
                'notes' => [
                    'This application uses multi-database architecture: one central database (personal mode) and one database per tenant (organization mode).',
                    'Mobile clients must control the current context using either POST /organizations/switch (stateful context) and/or X-Tenant-Id header (stateless context).',
                ],
                'centralDatabase' => [
                    'connection' => 'pgsql',
                    'contains' => [
                        'users',
                        'oauth_* (Passport)',
                        'signatures (portable across personal/tenant)',
                        'tenants (organizations) + membership/invitations tables',
                        'kyc',
                    ],
                ],
                'tenantDatabase' => [
                    'connection' => 'tenant',
                    'contains' => [
                        'documents',
                        'document signers',
                        'signature placements',
                        'tenant certificates + root certificate authorities',
                        'tenant portal settings',
                    ],
                ],
                'storage' => [
                    'notes' => [
                        'Personal mode files are stored under user folder.',
                        'Tenant mode files are stored under tenants/{tenantUlid}/... folder.',
                    ],
                ],
            ],
            'authentication' => [
                'type' => 'bearer',
                'header' => 'Authorization',
                'format' => 'Bearer {token}',
                'notes' => [
                    'All protected endpoints require a valid access token.',
                    'Token is typically returned by /auth/login or /auth/google/mobile endpoints.',
                ],
            ],
            'tenantContext' => [
                'header' => 'X-Tenant-Id',
                'type' => 'string',
                'notes' => [
                    'If header is provided, the API runs in tenant mode (organization context).',
                    'If header is omitted, the API runs in personal mode.',
                    'X-Tenant-Id value is the tenant ULID (organizationId).',
                ],
                'examples' => [
                    '01KFDQ0YRJ56XDGZ2M42WQW4VA',
                ],
            ],
            'flowChecklist' => [
                'personalSigningFlow' => [
                    'POST /auth/login (or google mobile login) -> get token',
                    'GET /kyc/me -> ensure verified; if not, POST /kyc/submit',
                    'POST /certificates/issue (if required by UI)',
                    'POST /documents (upload)',
                    'POST /documents/{id}/signers',
                    'POST /documents/{id}/placements',
                    'POST /documents/{id}/sign',
                    'POST /documents/{id}/finalize',
                    'POST /documents/verify (optional)',
                ],
                'tenantSigningFlow' => [
                    'POST /organizations (create) OR POST /organizations/join (join)',
                    'POST /organizations/switch with organizationId -> enters tenant mode (optional)',
                    'For every request in tenant mode, send X-Tenant-Id header (recommended for mobile).',
                    'POST /documents (upload with X-Tenant-Id)',
                    'POST /documents/{id}/signers (X-Tenant-Id)',
                    'POST /documents/{id}/placements (X-Tenant-Id)',
                    'POST /documents/{id}/sign (X-Tenant-Id)',
                    'POST /documents/{id}/finalize (X-Tenant-Id)',
                ],
            ],
            'responseFormat' => [
                'success' => [
                    'status' => 'success',
                    'success' => true,
                    'data' => 'mixed',
                    'message' => 'string',
                ],
                'error' => [
                    'status' => 'error',
                    'success' => false,
                    'data' => 'mixed|null',
                    'message' => 'string',
                ],
                'notes' => [
                    'All responses use camelCase keys.',
                    'HTTP status codes are meaningful (200/201/400/401/403/404/410/422/500).',
                ],
            ],
            'errorCatalog' => [
                '401' => [
                    'name' => 'Unauthenticated',
                    'example' => [
                        'status' => 'error',
                        'success' => false,
                        'data' => null,
                        'message' => 'Unauthenticated',
                    ],
                ],
                '403' => [
                    'name' => 'Forbidden',
                    'notes' => [
                        'Used for permissions, KYC restriction, and organization role restrictions.',
                    ],
                ],
                '404' => [
                    'name' => 'Not found',
                ],
                '410' => [
                    'name' => 'Gone',
                    'notes' => [
                        'Used for expired exchange code in /auth/exchange.',
                    ],
                ],
                '422' => [
                    'name' => 'Unprocessable entity',
                    'notes' => [
                        'Validation failures and business rule failures are commonly returned as 422.',
                    ],
                ],
            ],
            'endpoints' => [
                [
                    'tag' => 'Auth',
                    'name' => 'Login',
                    'method' => 'POST',
                    'path' => '/auth/login',
                    'authRequired' => false,
                    'tenantHeader' => 'ignored',
                    'request' => [
                        'body' => [
                            'email' => 'string',
                            'password' => 'string',
                        ],
                        'validation' => [
                            'email' => 'required|email',
                            'password' => 'required|string',
                        ],
                    ],
                    'responses' => [
                        [
                            'code' => 200,
                            'description' => 'Login success',
                            'example' => [
                                'status' => 'success',
                                'success' => true,
                                'data' => [
                                    'token' => 'ACCESS_TOKEN',
                                    'user' => [
                                        'id' => '01K...',
                                        'name' => 'John Doe',
                                        'email' => 'john@example.com',
                                    ],
                                ],
                                'message' => 'OK',
                            ],
                        ],
                        [
                            'code' => 401,
                            'description' => 'Invalid credentials',
                            'example' => [
                                'status' => 'error',
                                'success' => false,
                                'data' => null,
                                'message' => 'Invalid credentials',
                            ],
                        ],
                    ],
                ],
                [
                    'tag' => 'Auth',
                    'name' => 'Google Redirect (Web)',
                    'method' => 'GET',
                    'path' => '/auth/google/redirect',
                    'authRequired' => false,
                    'tenantHeader' => 'ignored',
                    'notes' => [
                        'Web-only: redirects user to Google OAuth consent screen.',
                    ],
                    'responses' => [
                        [
                            'code' => 302,
                            'description' => 'Redirect to Google',
                        ],
                    ],
                ],
                [
                    'tag' => 'Auth',
                    'name' => 'Google Callback (Web)',
                    'method' => 'GET',
                    'path' => '/auth/google/callback',
                    'authRequired' => false,
                    'tenantHeader' => 'ignored',
                    'notes' => [
                        'Web-only: handles Google callback and redirects to web with auth_code for token exchange.',
                    ],
                    'responses' => [
                        [
                            'code' => 302,
                            'description' => 'Redirect back to web with auth_code',
                        ],
                        [
                            'code' => 500,
                            'description' => 'Google login failed',
                        ],
                    ],
                ],
                [
                    'tag' => 'Auth',
                    'name' => 'Google Mobile Login (Token)',
                    'method' => 'POST',
                    'path' => '/auth/google/mobile',
                    'authRequired' => false,
                    'tenantHeader' => 'ignored',
                    'request' => [
                        'body' => [
                            'idToken' => 'string|null',
                            'accessToken' => 'string|null',
                            'code' => 'string|null',
                        ],
                        'notes' => [
                            'Send at least one of idToken/accessToken/code based on your Google sign-in flow.',
                        ],
                    ],
                    'responses' => [
                        [
                            'code' => 200,
                            'description' => 'Login success',
                            'example' => [
                                'status' => 'success',
                                'success' => true,
                                'data' => [
                                    'token' => 'ACCESS_TOKEN',
                                    'user' => [
                                        'id' => '01K...',
                                        'name' => 'John Doe',
                                        'email' => 'john@example.com',
                                    ],
                                ],
                                'message' => 'OK',
                            ],
                        ],
                        [
                            'code' => 422,
                            'description' => 'Google login failed',
                            'example' => [
                                'status' => 'error',
                                'success' => false,
                                'data' => null,
                                'message' => 'Google Login Failed: ...',
                            ],
                        ],
                    ],
                ],
                [
                    'tag' => 'Auth',
                    'name' => 'Google Mobile Login (Code)',
                    'method' => 'POST',
                    'path' => '/auth/google/mobile/code',
                    'authRequired' => false,
                    'tenantHeader' => 'ignored',
                    'request' => [
                        'body' => [
                            'code' => 'string',
                        ],
                    ],
                    'responses' => [
                        [
                            'code' => 200,
                            'description' => 'Login success',
                        ],
                        [
                            'code' => 422,
                            'description' => 'Google login failed',
                        ],
                    ],
                ],
                [
                    'tag' => 'Meta',
                    'name' => 'API Docs',
                    'method' => 'GET',
                    'path' => '/docs',
                    'authRequired' => false,
                    'tenantHeader' => 'ignored',
                    'notes' => [
                        'Returns this API documentation spec in JSON format.',
                    ],
                    'responses' => [
                        [
                            'code' => 200,
                            'description' => 'Docs JSON',
                        ],
                    ],
                ],
                [
                    'tag' => 'Meta',
                    'name' => 'Swagger OAuth2 Callback',
                    'method' => 'GET',
                    'path' => '/oauth2-callback',
                    'authRequired' => false,
                    'tenantHeader' => 'ignored',
                    'notes' => [
                        'Used by Swagger UI (L5-Swagger) for OAuth2 redirect handling. Mobile clients typically do not need this endpoint.',
                    ],
                    'responses' => [
                        [
                            'code' => 200,
                            'description' => 'HTML callback page',
                        ],
                    ],
                ],
                [
                    'tag' => 'Auth',
                    'name' => 'Exchange Web Auth Code (Deep-link)',
                    'method' => 'GET',
                    'path' => '/auth/exchange',
                    'authRequired' => false,
                    'tenantHeader' => 'ignored',
                    'request' => [
                        'query' => [
                            'code' => 'string',
                        ],
                    ],
                    'responses' => [
                        [
                            'code' => 200,
                            'description' => 'Exchange success',
                            'example' => [
                                'status' => 'success',
                                'success' => true,
                                'data' => [
                                    'token' => 'ACCESS_TOKEN',
                                ],
                                'message' => 'OK',
                            ],
                        ],
                        [
                            'code' => 410,
                            'description' => 'Invalid or expired code',
                        ],
                    ],
                ],
                [
                    'tag' => 'Auth',
                    'name' => 'Logout',
                    'method' => 'POST',
                    'path' => '/auth/logout',
                    'authRequired' => true,
                    'tenantHeader' => 'optional',
                    'responses' => [
                        [
                            'code' => 200,
                            'description' => 'Logout success',
                        ],
                        [
                            'code' => 401,
                            'description' => 'Unauthenticated',
                        ],
                    ],
                ],
                [
                    'tag' => 'User',
                    'name' => 'Get Current User',
                    'method' => 'GET',
                    'path' => '/user',
                    'authRequired' => true,
                    'tenantHeader' => 'optional',
                    'request' => [
                        'headers' => [
                            'Authorization' => 'Bearer {token}',
                            'X-Tenant-Id' => 'optional tenant ULID',
                        ],
                    ],
                    'responses' => [
                        [
                            'code' => 200,
                            'description' => 'User data',
                            'example' => [
                                'status' => 'success',
                                'success' => true,
                                'data' => [
                                    'id' => '01K...',
                                    'name' => 'John Doe',
                                    'email' => 'john@example.com',
                                    'kycStatus' => 'verified',
                                ],
                                'message' => 'OK',
                            ],
                        ],
                        [
                            'code' => 401,
                            'description' => 'Unauthenticated',
                            'example' => [
                                'status' => 'error',
                                'success' => false,
                                'data' => null,
                                'message' => 'Unauthenticated',
                            ],
                        ],
                    ],
                ],
                [
                    'tag' => 'User',
                    'name' => 'Get Profile',
                    'method' => 'GET',
                    'path' => '/profile',
                    'authRequired' => true,
                    'tenantHeader' => 'optional',
                    'responses' => [
                        [
                            'code' => 200,
                            'description' => 'Profile data',
                        ],
                    ],
                ],
                [
                    'tag' => 'Certificates',
                    'name' => 'Issue Certificate',
                    'method' => 'POST',
                    'path' => '/certificates/issue',
                    'authRequired' => true,
                    'tenantHeader' => 'optional',
                    'notes' => [
                        'In personal mode: issues personal signing certificate.',
                        'In tenant mode: issues/ensures tenant certificate for current user under tenant Root CA.',
                    ],
                    'responses' => [
                        [
                            'code' => 200,
                            'description' => 'Issued/ensured',
                        ],
                        [
                            'code' => 403,
                            'description' => 'KYC required (personal mode)',
                        ],
                    ],
                ],
                [
                    'tag' => 'Invitations',
                    'name' => 'Validate Invitation (Public)',
                    'method' => 'GET',
                    'path' => '/invitations/validate',
                    'authRequired' => false,
                    'tenantHeader' => 'ignored',
                    'request' => [
                        'query' => [
                            'code' => 'string',
                        ],
                    ],
                    'responses' => [
                        [
                            'code' => 200,
                            'description' => 'Invitation is valid',
                        ],
                        [
                            'code' => 404,
                            'description' => 'Invitation not found',
                        ],
                    ],
                ],
                [
                    'tag' => 'Invitations',
                    'name' => 'Accept Invitation',
                    'method' => 'POST',
                    'path' => '/invitations/accept',
                    'authRequired' => true,
                    'tenantHeader' => 'ignored',
                    'request' => [
                        'body' => [
                            'code' => 'string',
                        ],
                    ],
                    'responses' => [
                        [
                            'code' => 200,
                            'description' => 'Accepted',
                        ],
                        [
                            'code' => 422,
                            'description' => 'Accept failed',
                        ],
                    ],
                ],
                [
                    'tag' => 'Organizations',
                    'name' => 'List My Organizations',
                    'method' => 'GET',
                    'path' => '/organizations',
                    'authRequired' => true,
                    'tenantHeader' => 'ignored',
                    'responses' => [
                        [
                            'code' => 200,
                            'description' => 'Organizations list',
                            'example' => [
                                'status' => 'success',
                                'success' => true,
                                'data' => [
                                    'organizations' => [
                                        [
                                            'id' => '01KFDQ...',
                                            'name' => 'My Company',
                                            'slug' => 'my-company',
                                            'code' => 'ABCD1234',
                                            'description' => null,
                                            'plan' => 'free',
                                            'isOwner' => true,
                                            'role' => 'owner',
                                        ],
                                    ],
                                    'canCreate' => true,
                                ],
                                'message' => 'OK',
                            ],
                        ],
                    ],
                ],
                [
                    'tag' => 'Organizations',
                    'name' => 'Create Organization (Create Tenant)',
                    'method' => 'POST',
                    'path' => '/organizations',
                    'authRequired' => true,
                    'tenantHeader' => 'ignored',
                    'request' => [
                        'body' => [
                            'name' => 'string',
                            'description' => 'string|null',
                            'companyLegalName' => 'string|null',
                            'companyCountry' => 'string|null (2 chars)',
                            'companyState' => 'string|null',
                            'companyCity' => 'string|null',
                            'companyAddress' => 'string|null',
                            'companyPostalCode' => 'string|null',
                            'companyOrganizationUnit' => 'string|null',
                        ],
                        'validation' => [
                            'name' => 'required|string|max:255',
                            'description' => 'nullable|string|max:1000',
                            'companyLegalName' => 'nullable|string|max:255',
                            'companyCountry' => 'nullable|string|max:2',
                            'companyState' => 'nullable|string|max:255',
                            'companyCity' => 'nullable|string|max:255',
                            'companyAddress' => 'nullable|string|max:500',
                            'companyPostalCode' => 'nullable|string|max:20',
                            'companyOrganizationUnit' => 'nullable|string|max:255',
                        ],
                        'notes' => [
                            'This endpoint creates a new tenant (organization) and prepares tenant database + Root CA DN fields if provided.',
                            'After creating, you may call POST /organizations/switch to enter tenant mode, or send X-Tenant-Id in subsequent requests.',
                        ],
                    ],
                    'responses' => [
                        [
                            'code' => 201,
                            'description' => 'Organization created',
                            'example' => [
                                'status' => 'success',
                                'success' => true,
                                'data' => [
                                    'id' => '01KFDQ0YRJ56XDGZ2M42WQW4VA',
                                    'name' => 'My Company',
                                    'slug' => 'my-company',
                                    'code' => 'ABCD1234',
                                ],
                                'message' => 'Organization created successfully.',
                            ],
                        ],
                        [
                            'code' => 403,
                            'description' => 'Not allowed / plan limit',
                            'example' => [
                                'status' => 'error',
                                'success' => false,
                                'data' => null,
                                'message' => 'Failed to create organization: ...',
                            ],
                        ],
                        [
                            'code' => 422,
                            'description' => 'Validation or create failed',
                            'example' => [
                                'status' => 'error',
                                'success' => false,
                                'data' => null,
                                'message' => 'Failed to create organization: ...',
                            ],
                        ],
                    ],
                ],
                [
                    'tag' => 'Organizations',
                    'name' => 'Join Organization By Code',
                    'method' => 'POST',
                    'path' => '/organizations/join',
                    'authRequired' => true,
                    'tenantHeader' => 'ignored',
                    'request' => [
                        'body' => [
                            'code' => 'string',
                        ],
                        'validation' => [
                            'code' => 'required|string',
                        ],
                    ],
                    'responses' => [
                        [
                            'code' => 200,
                            'description' => 'Joined successfully',
                            'example' => [
                                'status' => 'success',
                                'success' => true,
                                'data' => [
                                    'id' => '01K...',
                                    'name' => 'My Company',
                                    'slug' => 'my-company',
                                ],
                                'message' => 'Joined organization successfully.',
                            ],
                        ],
                        [
                            'code' => 422,
                            'description' => 'Join failed',
                            'example' => [
                                'status' => 'error',
                                'success' => false,
                                'data' => null,
                                'message' => 'Failed to join organization: ...',
                            ],
                        ],
                    ],
                ],
                [
                    'tag' => 'Organizations',
                    'name' => 'Switch Current Organization Context',
                    'method' => 'POST',
                    'path' => '/organizations/switch',
                    'authRequired' => true,
                    'tenantHeader' => 'ignored',
                    'request' => [
                        'body' => [
                            'organizationId' => 'string|null',
                        ],
                        'notes' => [
                            'Send organizationId to enter tenant mode.',
                            'Send null to return to personal mode.',
                        ],
                    ],
                    'responses' => [
                        [
                            'code' => 200,
                            'description' => 'Context switched',
                            'example' => [
                                'status' => 'success',
                                'success' => true,
                                'data' => [
                                    'mode' => 'tenant',
                                    'organization' => [
                                        'id' => '01K...',
                                        'name' => 'My Company',
                                    ],
                                ],
                                'message' => 'OK',
                                'mode' => 'tenant',
                            ],
                        ],
                    ],
                ],
                [
                    'tag' => 'Organizations',
                    'name' => 'Get Organization Detail',
                    'method' => 'GET',
                    'path' => '/organizations/{organizationId}',
                    'authRequired' => true,
                    'tenantHeader' => 'ignored',
                    'request' => [
                        'pathParams' => [
                            'organizationId' => 'tenant ULID',
                        ],
                    ],
                    'responses' => [
                        [
                            'code' => 200,
                            'description' => 'Detail',
                        ],
                        [
                            'code' => 403,
                            'description' => 'Not a member',
                        ],
                    ],
                ],
                [
                    'tag' => 'Organizations',
                    'name' => 'Update Organization',
                    'method' => 'PUT',
                    'path' => '/organizations/{organizationId}',
                    'authRequired' => true,
                    'tenantHeader' => 'ignored',
                    'request' => [
                        'pathParams' => [
                            'organizationId' => 'tenant ULID',
                        ],
                        'body' => [
                            'name' => 'string (optional)',
                            'description' => 'string|null (optional)',
                        ],
                    ],
                    'responses' => [
                        [
                            'code' => 200,
                            'description' => 'Updated',
                        ],
                        [
                            'code' => 403,
                            'description' => 'Only owner/admin',
                        ],
                    ],
                ],
                [
                    'tag' => 'Organizations',
                    'name' => 'Delete Organization',
                    'method' => 'DELETE',
                    'path' => '/organizations/{organizationId}',
                    'authRequired' => true,
                    'tenantHeader' => 'ignored',
                    'responses' => [
                        [
                            'code' => 200,
                            'description' => 'Deleted',
                        ],
                        [
                            'code' => 403,
                            'description' => 'Not allowed',
                        ],
                    ],
                ],
                [
                    'tag' => 'Organization Members',
                    'name' => 'List Members',
                    'method' => 'GET',
                    'path' => '/organizations/{organizationId}/members',
                    'authRequired' => true,
                    'tenantHeader' => 'ignored',
                    'responses' => [
                        [
                            'code' => 200,
                            'description' => 'Members list',
                        ],
                    ],
                ],
                [
                    'tag' => 'Organization Members',
                    'name' => 'Update Member Role',
                    'method' => 'PUT',
                    'path' => '/organizations/{organizationId}/members/{memberId}',
                    'authRequired' => true,
                    'tenantHeader' => 'ignored',
                    'request' => [
                        'body' => [
                            'role' => 'string',
                        ],
                    ],
                    'responses' => [
                        [
                            'code' => 200,
                            'description' => 'Role updated',
                        ],
                    ],
                ],
                [
                    'tag' => 'Organization Members',
                    'name' => 'Remove Member',
                    'method' => 'DELETE',
                    'path' => '/organizations/{organizationId}/members/{memberId}',
                    'authRequired' => true,
                    'tenantHeader' => 'ignored',
                    'responses' => [
                        [
                            'code' => 200,
                            'description' => 'Removed',
                        ],
                    ],
                ],
                [
                    'tag' => 'Organization Invitations',
                    'name' => 'List Organization Invitations',
                    'method' => 'GET',
                    'path' => '/organizations/{organizationId}/invitations',
                    'authRequired' => true,
                    'tenantHeader' => 'ignored',
                    'responses' => [
                        [
                            'code' => 200,
                            'description' => 'Invitations list',
                        ],
                    ],
                ],
                [
                    'tag' => 'Organization Invitations',
                    'name' => 'Create Organization Invitation',
                    'method' => 'POST',
                    'path' => '/organizations/{organizationId}/invitations',
                    'authRequired' => true,
                    'tenantHeader' => 'ignored',
                    'responses' => [
                        [
                            'code' => 201,
                            'description' => 'Invitation created',
                        ],
                    ],
                ],
                [
                    'tag' => 'Organization Invitations',
                    'name' => 'Delete Organization Invitation',
                    'method' => 'DELETE',
                    'path' => '/organizations/{organizationId}/invitations/{invitationId}',
                    'authRequired' => true,
                    'tenantHeader' => 'ignored',
                    'responses' => [
                        [
                            'code' => 200,
                            'description' => 'Invitation deleted',
                        ],
                    ],
                ],
                [
                    'tag' => 'Documents',
                    'name' => 'List Documents',
                    'method' => 'GET',
                    'path' => '/documents',
                    'authRequired' => true,
                    'tenantHeader' => 'optional',
                    'request' => [
                        'headers' => [
                            'Authorization' => 'Bearer {token}',
                            'X-Tenant-Id' => 'optional tenant ULID',
                        ],
                        'query' => [
                            'page' => 'int (optional)',
                            'perPage' => 'int (optional)',
                        ],
                    ],
                    'responses' => [
                        [
                            'code' => 200,
                            'description' => 'List of documents',
                            'example' => [
                                'status' => 'success',
                                'success' => true,
                                'data' => [
                                    'documents' => [
                                        [
                                            'id' => '01K...',
                                            'title' => 'Contract.pdf',
                                            'status' => 'draft',
                                            'fileName' => 'contract.pdf',
                                            'mimeType' => 'application/pdf',
                                            'createdAt' => '2026-01-20T12:00:00Z',
                                        ],
                                    ],
                                ],
                                'message' => 'OK',
                            ],
                        ],
                    ],
                ],
                [
                    'tag' => 'Documents',
                    'name' => 'Sync Documents',
                    'method' => 'POST',
                    'path' => '/documents/sync',
                    'authRequired' => true,
                    'tenantHeader' => 'optional',
                    'notes' => [
                        'Used by clients to synchronize document metadata/state.',
                    ],
                    'responses' => [
                        [
                            'code' => 200,
                            'description' => 'Sync completed',
                        ],
                    ],
                ],
                [
                    'tag' => 'Documents',
                    'name' => 'Upload Document',
                    'method' => 'POST',
                    'path' => '/documents',
                    'authRequired' => true,
                    'tenantHeader' => 'optional',
                    'request' => [
                        'headers' => [
                            'Authorization' => 'Bearer {token}',
                            'X-Tenant-Id' => 'optional tenant ULID',
                        ],
                        'contentType' => 'multipart/form-data',
                        'body' => [
                            'file' => 'file (pdf)',
                            'title' => 'string (optional)',
                        ],
                        'notes' => [
                            'This endpoint requires kyc.verified middleware (personal mode) and is also used in tenant mode.',
                        ],
                    ],
                    'responses' => [
                        [
                            'code' => 201,
                            'description' => 'Upload success',
                            'example' => [
                                'status' => 'success',
                                'success' => true,
                                'data' => [
                                    'document' => [
                                        'id' => '01K...',
                                        'title' => 'Contract',
                                        'status' => 'draft',
                                    ],
                                ],
                                'message' => 'Document uploaded successfully',
                            ],
                        ],
                        [
                            'code' => 403,
                            'description' => 'KYC required (personal mode)',
                            'example' => [
                                'status' => 'error',
                                'success' => false,
                                'data' => null,
                                'message' => 'KYC verification required',
                            ],
                        ],
                    ],
                ],
                [
                    'tag' => 'Documents',
                    'name' => 'Get Document Detail',
                    'method' => 'GET',
                    'path' => '/documents/{documentId}',
                    'authRequired' => true,
                    'tenantHeader' => 'optional',
                    'request' => [
                        'pathParams' => [
                            'documentId' => 'ULID',
                        ],
                    ],
                    'responses' => [
                        [
                            'code' => 200,
                            'description' => 'Document detail',
                            'example' => [
                                'status' => 'success',
                                'success' => true,
                                'data' => [
                                    'id' => '01K...',
                                    'title' => 'Contract',
                                    'status' => 'draft',
                                ],
                                'message' => 'OK',
                            ],
                        ],
                        [
                            'code' => 404,
                            'description' => 'Not found',
                            'example' => [
                                'status' => 'error',
                                'success' => false,
                                'data' => null,
                                'message' => 'Document not found',
                            ],
                        ],
                    ],
                ],
                [
                    'tag' => 'Documents',
                    'name' => 'Get Document View Url',
                    'method' => 'GET',
                    'path' => '/documents/{documentId}/view-url',
                    'authRequired' => true,
                    'tenantHeader' => 'optional',
                    'responses' => [
                        [
                            'code' => 200,
                            'description' => 'Temporary view URL',
                        ],
                    ],
                ],
                [
                    'tag' => 'Documents',
                    'name' => 'Get Document QR Position',
                    'method' => 'GET',
                    'path' => '/documents/{documentId}/qr-position',
                    'authRequired' => true,
                    'tenantHeader' => 'optional',
                    'responses' => [
                        [
                            'code' => 200,
                            'description' => 'QR position info',
                        ],
                    ],
                ],
                [
                    'tag' => 'Documents',
                    'name' => 'Download Document',
                    'method' => 'GET',
                    'path' => '/documents/{documentId}/download',
                    'authRequired' => true,
                    'tenantHeader' => 'optional',
                    'notes' => [
                        'Returns a binary file (PDF).',
                    ],
                    'responses' => [
                        [
                            'code' => 200,
                            'description' => 'Binary response (PDF)',
                        ],
                    ],
                ],
                [
                    'tag' => 'Documents',
                    'name' => 'Add Signers',
                    'method' => 'POST',
                    'path' => '/documents/{documentId}/signers',
                    'authRequired' => true,
                    'tenantHeader' => 'optional',
                    'request' => [
                        'pathParams' => [
                            'documentId' => 'ULID',
                        ],
                        'body' => [
                            'signers' => [
                                [
                                    'email' => 'string',
                                    'name' => 'string',
                                    'order' => 'int',
                                ],
                            ],
                        ],
                    ],
                    'responses' => [
                        [
                            'code' => 200,
                            'description' => 'Signers saved',
                            'example' => [
                                'status' => 'success',
                                'success' => true,
                                'data' => [
                                    'signers' => [],
                                ],
                                'message' => 'OK',
                            ],
                        ],
                    ],
                ],
                [
                    'tag' => 'Documents',
                    'name' => 'Get Signers',
                    'method' => 'GET',
                    'path' => '/documents/{documentId}/signers',
                    'authRequired' => true,
                    'tenantHeader' => 'optional',
                    'request' => [
                        'pathParams' => [
                            'documentId' => 'ULID',
                        ],
                    ],
                    'responses' => [
                        [
                            'code' => 200,
                            'description' => 'Signers list',
                            'example' => [
                                'status' => 'success',
                                'success' => true,
                                'data' => [
                                    'signers' => [],
                                ],
                                'message' => 'OK',
                            ],
                        ],
                    ],
                ],
                [
                    'tag' => 'Documents',
                    'name' => 'Save Placements',
                    'method' => 'POST',
                    'path' => '/documents/{documentId}/placements',
                    'authRequired' => true,
                    'tenantHeader' => 'optional',
                    'request' => [
                        'pathParams' => [
                            'documentId' => 'ULID',
                        ],
                        'body' => [
                            'placements' => [
                                [
                                    'page' => 'int',
                                    'x' => 'number',
                                    'y' => 'number',
                                    'width' => 'number',
                                    'height' => 'number',
                                    'type' => 'string (signature|initial|date|name|email|qr)',
                                    'assigneeEmail' => 'string (optional)',
                                    'signatureId' => 'ULID (optional, central signature id)',
                                ],
                            ],
                        ],
                    ],
                    'responses' => [
                        [
                            'code' => 200,
                            'description' => 'Placements saved',
                            'example' => [
                                'status' => 'success',
                                'success' => true,
                                'data' => [
                                    'placements' => [],
                                ],
                                'message' => 'OK',
                            ],
                        ],
                        [
                            'code' => 422,
                            'description' => 'Validation error / placement save error',
                            'example' => [
                                'status' => 'error',
                                'success' => false,
                                'data' => null,
                                'message' => 'Failed to save placement: ...',
                            ],
                        ],
                    ],
                ],
                [
                    'tag' => 'Documents',
                    'name' => 'Update Placement',
                    'method' => 'PUT',
                    'path' => '/documents/{documentId}/placements/{placementId}',
                    'authRequired' => true,
                    'tenantHeader' => 'optional',
                    'request' => [
                        'pathParams' => [
                            'documentId' => 'ULID',
                            'placementId' => 'ULID',
                        ],
                        'body' => [
                            'page' => 'int (optional)',
                            'x' => 'number (optional)',
                            'y' => 'number (optional)',
                            'width' => 'number (optional)',
                            'height' => 'number (optional)',
                            'type' => 'string (optional)',
                            'assigneeEmail' => 'string (optional)',
                            'signatureId' => 'ULID (optional)',
                        ],
                    ],
                    'responses' => [
                        [
                            'code' => 200,
                            'description' => 'Placement updated',
                        ],
                        [
                            'code' => 422,
                            'description' => 'Update failed',
                        ],
                    ],
                ],
                [
                    'tag' => 'Documents',
                    'name' => 'Get Placements',
                    'method' => 'GET',
                    'path' => '/documents/{documentId}/placements',
                    'authRequired' => true,
                    'tenantHeader' => 'optional',
                    'request' => [
                        'pathParams' => [
                            'documentId' => 'ULID',
                        ],
                    ],
                    'responses' => [
                        [
                            'code' => 200,
                            'description' => 'Placements list',
                            'example' => [
                                'status' => 'success',
                                'success' => true,
                                'data' => [
                                    'placements' => [],
                                ],
                                'message' => 'OK',
                            ],
                        ],
                    ],
                ],
                [
                    'tag' => 'Signatures',
                    'name' => 'List My Signatures',
                    'method' => 'GET',
                    'path' => '/signatures',
                    'authRequired' => true,
                    'tenantHeader' => 'optional',
                    'notes' => [
                        'Signatures are central (portable) and should be available in both personal and tenant modes.',
                    ],
                    'responses' => [
                        [
                            'code' => 200,
                            'description' => 'Signatures list',
                            'example' => [
                                'status' => 'success',
                                'success' => true,
                                'data' => [
                                    'signatures' => [],
                                ],
                                'message' => 'OK',
                            ],
                        ],
                    ],
                ],
                [
                    'tag' => 'Signatures',
                    'name' => 'Create Signature',
                    'method' => 'POST',
                    'path' => '/signatures',
                    'authRequired' => true,
                    'tenantHeader' => 'optional',
                    'request' => [
                        'contentType' => 'multipart/form-data',
                        'body' => [
                            'image' => 'file (png)',
                            'name' => 'string (optional)',
                            'isDefault' => 'boolean (optional)',
                        ],
                    ],
                    'responses' => [
                        [
                            'code' => 201,
                            'description' => 'Signature created',
                            'example' => [
                                'status' => 'success',
                                'success' => true,
                                'data' => [
                                    'signature' => [
                                        'id' => '01K...',
                                        'name' => 'My Signature',
                                        'isDefault' => true,
                                    ],
                                ],
                                'message' => 'OK',
                            ],
                        ],
                    ],
                ],
                [
                    'tag' => 'Signatures',
                    'name' => 'Get Signature Image',
                    'method' => 'GET',
                    'path' => '/signatures/{signatureId}/image',
                    'authRequired' => true,
                    'tenantHeader' => 'optional',
                    'notes' => [
                        'Returns a binary image (PNG).',
                    ],
                    'responses' => [
                        [
                            'code' => 200,
                            'description' => 'Binary response (PNG)',
                        ],
                    ],
                ],
                [
                    'tag' => 'Signatures',
                    'name' => 'Set Default Signature',
                    'method' => 'PUT',
                    'path' => '/signatures/{signatureId}/default',
                    'authRequired' => true,
                    'tenantHeader' => 'optional',
                    'responses' => [
                        [
                            'code' => 200,
                            'description' => 'Default updated',
                        ],
                    ],
                ],
                [
                    'tag' => 'Signatures',
                    'name' => 'Delete Signature',
                    'method' => 'DELETE',
                    'path' => '/signatures/{signatureId}',
                    'authRequired' => true,
                    'tenantHeader' => 'optional',
                    'responses' => [
                        [
                            'code' => 200,
                            'description' => 'Deleted',
                        ],
                    ],
                ],
                [
                    'tag' => 'Documents',
                    'name' => 'Sign Document',
                    'method' => 'POST',
                    'path' => '/documents/{documentId}/sign',
                    'authRequired' => true,
                    'tenantHeader' => 'optional',
                    'request' => [
                        'pathParams' => [
                            'documentId' => 'ULID',
                        ],
                        'body' => [
                            'signatureId' => 'ULID',
                            'otp' => 'string (optional)',
                        ],
                        'notes' => [
                            'Requires kyc.verified middleware; in tenant mode KYC may be bypassed based on current middleware behavior.',
                        ],
                    ],
                    'responses' => [
                        [
                            'code' => 200,
                            'description' => 'Signed successfully',
                            'example' => [
                                'status' => 'success',
                                'success' => true,
                                'data' => [
                                    'documentId' => '01K...',
                                    'status' => 'signed',
                                ],
                                'message' => 'OK',
                            ],
                        ],
                        [
                            'code' => 422,
                            'description' => 'Sign failed',
                            'example' => [
                                'status' => 'error',
                                'success' => false,
                                'data' => null,
                                'message' => 'Failed to sign document: ...',
                            ],
                        ],
                    ],
                ],
                [
                    'tag' => 'Documents',
                    'name' => 'Finalize Document',
                    'method' => 'POST',
                    'path' => '/documents/{documentId}/finalize',
                    'authRequired' => true,
                    'tenantHeader' => 'optional',
                    'request' => [
                        'pathParams' => [
                            'documentId' => 'ULID',
                        ],
                    ],
                    'responses' => [
                        [
                            'code' => 200,
                            'description' => 'Finalize success',
                            'example' => [
                                'status' => 'success',
                                'success' => true,
                                'data' => [
                                    'documentId' => '01K...',
                                    'status' => 'finalized',
                                ],
                                'message' => 'OK',
                            ],
                        ],
                    ],
                ],
                [
                    'tag' => 'KYC',
                    'name' => 'Submit KYC',
                    'method' => 'POST',
                    'path' => '/kyc/submit',
                    'authRequired' => true,
                    'tenantHeader' => 'ignored',
                    'request' => [
                        'contentType' => 'multipart/form-data',
                        'body' => [
                            'selfie' => 'file',
                            'ktp' => 'file',
                            'name' => 'string',
                            'nik' => 'string',
                        ],
                    ],
                    'responses' => [
                        [
                            'code' => 200,
                            'description' => 'Submitted',
                            'example' => [
                                'status' => 'success',
                                'success' => true,
                                'data' => null,
                                'message' => 'OK',
                            ],
                        ],
                    ],
                ],
                [
                    'tag' => 'KYC',
                    'name' => 'Get My KYC Status',
                    'method' => 'GET',
                    'path' => '/kyc/me',
                    'authRequired' => true,
                    'tenantHeader' => 'ignored',
                    'responses' => [
                        [
                            'code' => 200,
                            'description' => 'KYC status',
                        ],
                    ],
                ],
                [
                    'tag' => 'KYC',
                    'name' => 'Get My KYC File',
                    'method' => 'GET',
                    'path' => '/kyc/me/file/{type}',
                    'authRequired' => true,
                    'tenantHeader' => 'ignored',
                    'request' => [
                        'pathParams' => [
                            'type' => 'string',
                        ],
                    ],
                    'responses' => [
                        [
                            'code' => 200,
                            'description' => 'Binary response',
                        ],
                    ],
                ],
                [
                    'tag' => 'Quota',
                    'name' => 'Get Quota',
                    'method' => 'GET',
                    'path' => '/quota',
                    'authRequired' => true,
                    'tenantHeader' => 'optional',
                    'responses' => [
                        [
                            'code' => 200,
                            'description' => 'Quota info',
                        ],
                    ],
                ],
                [
                    'tag' => 'Quota',
                    'name' => 'Update Quota',
                    'method' => 'PUT',
                    'path' => '/quota',
                    'authRequired' => true,
                    'tenantHeader' => 'optional',
                    'responses' => [
                        [
                            'code' => 200,
                            'description' => 'Updated',
                        ],
                    ],
                ],
                [
                    'tag' => 'Quota',
                    'name' => 'Update User Quota Override',
                    'method' => 'PUT',
                    'path' => '/quota/users/{userId}',
                    'authRequired' => true,
                    'tenantHeader' => 'optional',
                    'request' => [
                        'pathParams' => [
                            'userId' => 'ULID',
                        ],
                    ],
                    'responses' => [
                        [
                            'code' => 200,
                            'description' => 'Updated',
                        ],
                    ],
                ],
                [
                    'tag' => 'Portal Settings',
                    'name' => 'Get Portal Settings',
                    'method' => 'GET',
                    'path' => '/portal-settings',
                    'authRequired' => true,
                    'tenantHeader' => 'optional',
                    'responses' => [
                        [
                            'code' => 200,
                            'description' => 'Settings',
                        ],
                    ],
                ],
                [
                    'tag' => 'Portal Settings',
                    'name' => 'Update Portal Settings',
                    'method' => 'PUT',
                    'path' => '/portal-settings',
                    'authRequired' => true,
                    'tenantHeader' => 'optional',
                    'responses' => [
                        [
                            'code' => 200,
                            'description' => 'Updated',
                        ],
                    ],
                ],
                [
                    'tag' => 'Portal Settings',
                    'name' => 'Upload Portal Logo',
                    'method' => 'POST',
                    'path' => '/portal-settings/logo',
                    'authRequired' => true,
                    'tenantHeader' => 'optional',
                    'request' => [
                        'contentType' => 'multipart/form-data',
                        'body' => [
                            'file' => 'file',
                        ],
                    ],
                    'responses' => [
                        [
                            'code' => 200,
                            'description' => 'Uploaded',
                        ],
                    ],
                ],
                [
                    'tag' => 'Portal Settings',
                    'name' => 'Upload Portal Banner',
                    'method' => 'POST',
                    'path' => '/portal-settings/banner',
                    'authRequired' => true,
                    'tenantHeader' => 'optional',
                    'request' => [
                        'contentType' => 'multipart/form-data',
                        'body' => [
                            'file' => 'file',
                        ],
                    ],
                    'responses' => [
                        [
                            'code' => 200,
                            'description' => 'Uploaded',
                        ],
                    ],
                ],
                [
                    'tag' => 'Verify',
                    'name' => 'Verify Signed Document (Auth)',
                    'method' => 'POST',
                    'path' => '/documents/verify',
                    'authRequired' => true,
                    'tenantHeader' => 'optional',
                    'request' => [
                        'contentType' => 'multipart/form-data',
                        'body' => [
                            'file' => 'file (pdf)',
                        ],
                    ],
                    'responses' => [
                        [
                            'code' => 200,
                            'description' => 'Verification result',
                            'example' => [
                                'status' => 'success',
                                'success' => true,
                                'data' => [
                                    'valid' => true,
                                    'details' => [],
                                ],
                                'message' => 'OK',
                            ],
                        ],
                    ],
                ],
                [
                    'tag' => 'Verify',
                    'name' => 'Public Verify Upload',
                    'method' => 'POST',
                    'path' => '/verify/upload',
                    'authRequired' => false,
                    'tenantHeader' => 'ignored',
                    'request' => [
                        'contentType' => 'multipart/form-data',
                        'body' => [
                            'file' => 'file (pdf)',
                        ],
                    ],
                    'responses' => [
                        [
                            'code' => 200,
                            'description' => 'Verification token created',
                            'example' => [
                                'status' => 'success',
                                'success' => true,
                                'data' => [
                                    'token' => 'VERIFY_TOKEN',
                                    'url' => 'http://127.0.0.1:8001/api/verify/VERIFY_TOKEN',
                                ],
                                'message' => 'OK',
                            ],
                        ],
                    ],
                ],
                [
                    'tag' => 'Verify',
                    'name' => 'Public Verify Result',
                    'method' => 'GET',
                    'path' => '/verify/{token}',
                    'authRequired' => false,
                    'tenantHeader' => 'ignored',
                    'request' => [
                        'pathParams' => [
                            'token' => 'string',
                        ],
                    ],
                    'responses' => [
                        [
                            'code' => 200,
                            'description' => 'Verification result',
                        ],
                        [
                            'code' => 404,
                            'description' => 'Token not found',
                        ],
                    ],
                ],
            ],
        ];
    }
}
