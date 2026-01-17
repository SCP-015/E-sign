<?php

return [
    /*
    |--------------------------------------------------------------------------
    | E-Sign Permissions
    |--------------------------------------------------------------------------
    |
    | Daftar permission yang tersedia di aplikasi E-Sign.
    | Format: 'group.action' => 'group.action'
    |
    */

    // Permission untuk Manajemen Dokumen
    'documents' => [
        'view' => 'documents.view',           // Lihat dokumen sendiri
        'view_all' => 'documents.view_all',   // Lihat semua dokumen di org
        'upload' => 'documents.upload',       // Upload dokumen baru
        'sign' => 'documents.sign',           // Tanda tangan dokumen
        'request_signature' => 'documents.request_signature', // Minta tanda tangan orang lain
        'finalize' => 'documents.finalize',   // Finalisasi dokumen
        'delete' => 'documents.delete',       // Hapus dokumen
        'download' => 'documents.download',   // Download dokumen
    ],

    // Permission untuk Tanda Tangan
    'signatures' => [
        'view' => 'signatures.view',          // Lihat signature sendiri
        'create' => 'signatures.create',      // Buat/setup signature
        'manage_placements' => 'signatures.manage_placements', // Atur posisi signature di dokumen
    ],

    // Permission untuk Manajemen Organisasi
    'organization' => [
        'view' => 'organization.view',        // Lihat info organisasi
        'update' => 'organization.update',    // Update info organisasi
        'delete' => 'organization.delete',    // Hapus organisasi (owner only)
    ],

    // Permission untuk Manajemen Member
    'members' => [
        'view' => 'members.view',             // Lihat daftar member
        'invite' => 'members.invite',         // Undang member baru
        'remove' => 'members.remove',         // Hapus member
        'update_role' => 'members.update_role', // Ubah role member
    ],

    // Permission untuk Billing (hanya owner)
    'billing' => [
        'view' => 'billing.view',             // Lihat info billing
        'manage' => 'billing.manage',         // Kelola subscription/payment
    ],

    // Permission untuk Undangan
    'invitations' => [
        'view' => 'invitations.view',         // Lihat daftar undangan
        'create' => 'invitations.create',     // Buat kode undangan
        'delete' => 'invitations.delete',     // Hapus undangan
    ],

    // Permission untuk Quota Management (hanya owner)
    'quota' => [
        'view' => 'quota.view',               // Lihat kuota
        'manage' => 'quota.manage',           // Kelola kuota dokumen & signature
    ],

    // Permission untuk Portal Settings
    'portal_settings' => [
        'view' => 'portal_settings.view',     // Lihat settings portal
        'update' => 'portal_settings.update', // Update settings portal (foto, banner, sosmed)
    ],

    /*
    |--------------------------------------------------------------------------
    | Role Permission Mapping
    |--------------------------------------------------------------------------
    |
    | Mapping permission default untuk setiap role.
    | 
    | Hierarki:
    | - Owner: Full access (billing, quota, portal settings, semua)
    | - Admin: Manage members (invite, remove, update role), TANPA billing
    | - Manager: View members + invite member, TANPA billing, TANPA edit member
    | - User: View members only, TANPA billing, TANPA invite
    |
    */

    // Owner - Full access (semua permission termasuk billing & quota)
    'owner_permissions' => [
        // Documents - full
        'documents.view',
        'documents.view_all',
        'documents.upload',
        'documents.sign',
        'documents.request_signature',
        'documents.finalize',
        'documents.delete',
        'documents.download',
        // Signatures - full
        'signatures.view',
        'signatures.create',
        'signatures.manage_placements',
        // Organization - full
        'organization.view',
        'organization.update',
        'organization.delete',
        // Members - full
        'members.view',
        'members.invite',
        'members.remove',
        'members.update_role',
        // Billing - full (HANYA OWNER)
        'billing.view',
        'billing.manage',
        // Quota - full (HANYA OWNER)
        'quota.view',
        'quota.manage',
        // Portal Settings - full
        'portal_settings.view',
        'portal_settings.update',
        // Invitations - full
        'invitations.view',
        'invitations.create',
        'invitations.delete',
    ],

    // Admin - Manage members, TANPA billing & quota
    'admin_permissions' => [
        // Documents - full
        'documents.view',
        'documents.view_all',
        'documents.upload',
        'documents.sign',
        'documents.request_signature',
        'documents.finalize',
        'documents.delete',
        'documents.download',
        // Signatures - full
        'signatures.view',
        'signatures.create',
        'signatures.manage_placements',
        // Organization - view & update
        'organization.view',
        'organization.update',
        // Members - full (invite, remove, update role)
        'members.view',
        'members.invite',
        'members.remove',
        'members.update_role',
        // Portal Settings - full
        'portal_settings.view',
        'portal_settings.update',
        // Invitations - full
        'invitations.view',
        'invitations.create',
        'invitations.delete',
        // TANPA: billing.*, quota.*
    ],

    // Manager - View members + invite, TANPA edit/remove member, TANPA billing
    'manager_permissions' => [
        // Documents - full kecuali delete
        'documents.view',
        'documents.view_all',
        'documents.upload',
        'documents.sign',
        'documents.request_signature',
        'documents.finalize',
        'documents.download',
        // Signatures - full
        'signatures.view',
        'signatures.create',
        'signatures.manage_placements',
        // Organization - view only
        'organization.view',
        // Members - view + invite only
        'members.view',
        'members.invite',
        // Portal Settings - view only
        'portal_settings.view',
        // Invitations - view + create
        'invitations.view',
        'invitations.create',
        // TANPA: members.remove, members.update_role, billing.*, quota.*, portal_settings.update
    ],

    // User - Sangat terbatas, hanya view members, TANPA invite, TANPA billing
    'user_permissions' => [
        // Documents - basic (hanya dokumen sendiri)
        'documents.view',
        'documents.upload',
        'documents.sign',
        'documents.download',
        // Signatures - basic
        'signatures.view',
        'signatures.create',
        // Organization - view only
        'organization.view',
        // Members - view only
        'members.view',
        // Portal Settings - view only
        'portal_settings.view',
        // TANPA: members.invite, members.remove, members.update_role, billing.*, quota.*, invitations.*
    ],
];
