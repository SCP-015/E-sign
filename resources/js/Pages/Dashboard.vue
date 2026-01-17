<template>
    <Head title="Dashboard" />
    <div class="min-h-screen bg-base-100">
        <main class="mx-auto w-full max-w-7xl space-y-6 px-4 py-6">
            <section>
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-base-content/50">Welcome back</p>
                <h2 class="mt-1 text-2xl font-bold">Halo, {{ user.name }}!</h2>
                <p class="text-sm text-base-content/60">Welcome back to your E-Sign dashboard.</p>
            </section>

            <KycBanner :status="kycStatus" />

            <!-- Organization Menu (only show when in organization mode) -->
            <section v-if="organization" class="card border border-base-200 bg-base-100 shadow-sm">
                <div class="card-body">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <h3 class="text-lg font-semibold">Organization Management</h3>
                            <p class="text-sm text-base-content/60">{{ organization.name }}</p>
                        </div>
                        <div class="badge badge-primary">{{ organization.role || 'Member' }}</div>
                    </div>
                    
                    <div class="grid gap-3 md:grid-cols-3 lg:grid-cols-4">
                        <!-- Manage Members (all roles can view) -->
                        <a href="/organization/members" class="card card-compact border border-base-200 bg-base-100 shadow-sm transition-all hover:border-primary hover:shadow-md">
                            <div class="card-body items-center text-center">
                                <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-primary/10 text-primary">
                                    <svg viewBox="0 0 24 24" class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                        <circle cx="9" cy="7" r="4"></circle>
                                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                                        <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                                    </svg>
                                </div>
                                <h4 class="font-semibold">Manage Members</h4>
                                <p class="text-xs text-base-content/60">View and manage team</p>
                            </div>
                        </a>

                        <!-- Invite Users (manager, admin, owner) -->
                        <a v-if="canInviteMembers" href="/organization/invitations" class="card card-compact border border-base-200 bg-base-100 shadow-sm transition-all hover:border-secondary hover:shadow-md">
                            <div class="card-body items-center text-center">
                                <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-secondary/10 text-secondary">
                                    <svg viewBox="0 0 24 24" class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                        <circle cx="8.5" cy="7" r="4"></circle>
                                        <line x1="20" y1="8" x2="20" y2="14"></line>
                                        <line x1="17" y1="11" x2="23" y2="11"></line>
                                    </svg>
                                </div>
                                <h4 class="font-semibold">Invite Users</h4>
                                <p class="text-xs text-base-content/60">Generate invite codes</p>
                            </div>
                        </a>

                        <!-- Portal Settings (admin, owner) -->
                        <a v-if="canEditPortalSettings" href="/organization/settings" class="card card-compact border border-base-200 bg-base-100 shadow-sm transition-all hover:border-info hover:shadow-md">
                            <div class="card-body items-center text-center">
                                <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-info/10 text-info">
                                    <svg viewBox="0 0 24 24" class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2">
                                        <circle cx="12" cy="12" r="3"></circle>
                                        <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
                                    </svg>
                                </div>
                                <h4 class="font-semibold">Portal Settings</h4>
                                <p class="text-xs text-base-content/60">Customize branding</p>
                            </div>
                        </a>

                        <!-- Quota Management (owner only) -->
                        <a v-if="isOwner" href="/organization/quota" class="card card-compact border border-base-200 bg-base-100 shadow-sm transition-all hover:border-warning hover:shadow-md">
                            <div class="card-body items-center text-center">
                                <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-warning/10 text-warning">
                                    <svg viewBox="0 0 24 24" class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                                    </svg>
                                </div>
                                <h4 class="font-semibold">Quota Management</h4>
                                <p class="text-xs text-base-content/60">Manage limits</p>
                            </div>
                        </a>

                        <!-- Billing (owner only) -->
                        <a v-if="isOwner" href="/organization/billing" class="card card-compact border border-base-200 bg-base-100 shadow-sm transition-all hover:border-accent hover:shadow-md">
                            <div class="card-body items-center text-center">
                                <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-accent/10 text-accent">
                                    <svg viewBox="0 0 24 24" class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2">
                                        <rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect>
                                        <line x1="1" y1="10" x2="23" y2="10"></line>
                                    </svg>
                                </div>
                                <h4 class="font-semibold">Billing & Plans</h4>
                                <p class="text-xs text-base-content/60">Manage subscription</p>
                            </div>
                        </a>
                    </div>
                </div>
            </section>

            <StatsGrid :stats="stats" />

            <CertificateStatusCard :status="kycStatus" :expiry="certificateExpiry" />

            <section v-if="isVerified" class="card border border-base-200 bg-base-100 shadow-sm">
                <div class="card-body gap-4">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <h3 class="text-lg font-semibold">Upload Document</h3>
                        <span class="badge badge-ghost">PDF only</span>
                    </div>
                    <div
                        class="flex flex-col items-center gap-4 rounded-2xl border-2 border-dashed border-primary/30 bg-primary/5 px-6 py-8 text-center"
                        @dragover.prevent="dragActive = true"
                        @dragleave.prevent="dragActive = false"
                        @drop.prevent="handleDrop"
                        :class="dragActive ? 'bg-primary/10 border-primary/50' : ''"
                    >
                        <input type="file" ref="fileInput" @change="handleFileSelect" accept="application/pdf" hidden>
                        <div class="flex h-12 w-12 items-center justify-center rounded-full bg-base-100 shadow-sm">
                            <svg viewBox="0 0 24 24" class="h-6 w-6 text-primary" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M7 7h10v10H7z"></path>
                                <path d="M14 2H8a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V6z"></path>
                                <path d="M14 2v4h4"></path>
                            </svg>
                        </div>
                        <div>
                            <p class="text-sm font-semibold">Drag & drop a PDF here</p>
                            <p class="text-xs text-base-content/60">or choose a file from your device</p>
                        </div>
                        <button @click="fileInput?.click()" class="btn btn-primary btn-sm w-full">Choose File</button>
                        <p class="text-[10px] text-base-content/50">Maximum file size: 10MB</p>
                    </div>
                </div>
            </section>

            <section v-if="isVerified">
                <h3 class="text-lg font-semibold">Quick Tips</h3>
                <div class="mt-3 flex gap-3 overflow-x-auto pb-2 hide-scroll">
                    <div
                        v-for="tip in quickTips"
                        :key="tip.text"
                        class="min-w-60 rounded-xl border p-4 text-sm"
                        :class="tip.className"
                    >
                        <div class="flex items-start gap-2">
                            <span class="mt-1 h-2 w-2 rounded-full" :class="tip.dotClass"></span>
                            {{ tip.text }}
                        </div>
                    </div>
                </div>
            </section>

            <DocumentHistory
                :documents="recentDocuments"
                :totalCount="documents.length"
                :showAllHref="hasMoreDocuments ? '/documents' : ''"
                showAllLabel="Lihat selengkapnya"
                :actionsDisabled="documentsLocked"
                disabledHint="Lengkapi KYC terlebih dahulu untuk membuka aksi dokumen."
                :formatDate="formatDate"
                :getFileName="getFileName"
                :canSign="canSign"
                :canFinalize="canFinalize"
                @sign="openSigningModal"
                @finalize="finalizeDocument"
                @verify="verifyDocument"
                @download="downloadDocument"
            />
        </main>

        <SigningModal
            :isOpen="showSigningModal"
            :documentId="selectedDocId"
            :pageCount="selectedDocPageCount"
            @close="showSigningModal = false"
            @signed="onDocumentSigned"
        />

        <VerifyResultModal
            :isOpen="verifyModalOpen"
            :result="verifyModalResult"
            @close="closeVerifyModal"
        />
    </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { useAuthStore } from '../stores/auth';
import { useToastStore } from '../stores/toast';
import SigningModal from '../components/SigningModal.vue';
import VerifyResultModal from '../components/VerifyResultModal.vue';
import KycBanner from '../components/dashboard/KycBanner.vue';
import StatsGrid from '../components/dashboard/StatsGrid.vue';
import CertificateStatusCard from '../components/dashboard/CertificateStatusCard.vue';
import DocumentHistory from '../components/dashboard/DocumentHistory.vue';
import { formatApiError } from '../utils/errors';

const authStore = useAuthStore();
const toastStore = useToastStore();
const page = usePage();
const user = computed(() => authStore.user || {});
const kycStatus = computed(() => (user.value?.kyc_status ?? user.value?.kycStatus ?? 'unverified').toLowerCase());
const hasSignature = computed(() => user.value?.has_signature ?? user.value?.hasSignature ?? false);
const isVerified = computed(() => kycStatus.value === 'verified');
const documentsLocked = computed(() => !isVerified.value);

const dragActive = ref(false);
const documents = ref([]);
const organization = ref(page.props.auth?.organization ?? null);

const userRole = computed(() => {
    const role = organization.value?.role?.toLowerCase() || '';
    return role;
});

const isOwner = computed(() => userRole.value === 'owner');
const isAdmin = computed(() => userRole.value === 'admin' || userRole.value === 'administrator');
const isManager = computed(() => userRole.value === 'manager');

const canInviteMembers = computed(() => isOwner.value || isAdmin.value || isManager.value);
const canEditPortalSettings = computed(() => isOwner.value || isAdmin.value);

const recentDocuments = computed(() => documents.value.slice(0, 5));
const hasMoreDocuments = computed(() => documents.value.length > 5);
const fileInput = ref(null);
const showSigningModal = ref(false);
const selectedDocId = ref(null);
const selectedDocPageCount = ref(0);
const verifyModalOpen = ref(false);
const verifyModalResult = ref(null);

const signedCount = computed(() => documents.value.filter(d => d.status === 'signed' || d.status === 'COMPLETED').length);
const pendingCount = computed(() => documents.value.filter(d => d.status === 'pending' || d.status === 'IN_PROGRESS').length);
const certificateExpiry = computed(() => {
    const expiresAt = user.value?.certificate?.expires_at ?? user.value?.certificate?.expiresAt;
    if (!expiresAt) return '-';
    const date = new Date(expiresAt);
    return date.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
});

const isAssignedToMe = (doc) => {
    if (!doc?.signers || doc.signers.length === 0) {
        return Number(doc?.user_id ?? doc?.userId) === Number(user.value?.id);
    }
    return doc.signers.some((s) =>
        (s.user_id && Number(s.user_id) === Number(user.value?.id)) ||
        (s.userId && Number(s.userId) === Number(user.value?.id)) ||
        (s.email && s.email.toLowerCase() === user.value?.email?.toLowerCase())
    );
};

const hasISigned = (doc) => {
    if (!doc?.signers) return false;
    const mySigner = doc.signers.find((s) =>
        (s.user_id && Number(s.user_id) === Number(user.value?.id)) ||
        (s.userId && Number(s.userId) === Number(user.value?.id)) ||
        (s.email && s.email.toLowerCase() === user.value?.email?.toLowerCase())
    );
    return Boolean(mySigner?.signed_at ?? mySigner?.signedAt);
};

const canSign = (doc) => {
    const status = String(doc?.status || '').toLowerCase();
    if (status === 'signed' || status === 'completed') return false;
    if (kycStatus.value !== 'verified' || !hasSignature.value) return false;
    return isAssignedToMe(doc) && !hasISigned(doc);
};

const canFinalize = (doc) => {
    const status = String(doc?.status || '').toLowerCase();
    if (status !== 'signed') return false;
    const ownerId = doc?.user_id ?? doc?.userId;
    if (!ownerId || !authStore.user?.id) return false;
    return Number(ownerId) === Number(authStore.user.id);
};

const stats = computed(() => [
    { label: 'Documents', value: documents.value.length, valueClass: 'text-primary' },
    { label: 'Signed', value: signedCount.value, valueClass: 'text-success' },
    { label: 'Pending', value: pendingCount.value, valueClass: 'text-warning' },
]);

const MAX_UPLOAD_BYTES = 10 * 1024 * 1024;

const quickTips = [
    {
        text: 'Keep PDFs under 10MB for faster processing speeds.',
        className: 'border-info/20 bg-info/10 text-info-content',
        dotClass: 'bg-info',
    },
    {
        text: 'Use Setup Signature before signing new documents.',
        className: 'border-primary/20 bg-primary/10 text-primary',
        dotClass: 'bg-primary',
    },
    {
        text: 'Verify signatures anytime from the document list.',
        className: 'border-warning/20 bg-warning/10 text-warning-content',
        dotClass: 'bg-warning',
    },
];

const fetchCurrentOrganization = async () => {
    try {
        const response = await axios.get('/api/organizations/current');
        if (response.data?.success && response.data?.data) {
            organization.value = response.data.data;
            console.log('Current organization loaded:', organization.value);
            return;
        }
        organization.value = null;
        console.log('No current organization');
    } catch (e) {
        console.error('Failed to fetch organization:', e);
        organization.value = null;
    }
};

onMounted(async () => {
    try {
        await authStore.fetchUser();
        await fetchCurrentOrganization();
        await fetchDocuments();

        window.addEventListener('organizations-updated', fetchCurrentOrganization);
    } catch (e) {
        console.error('Failed to init dashboard:', e);
    }
});

onUnmounted(() => {
    window.removeEventListener('organizations-updated', fetchCurrentOrganization);
});

const handleFileSelect = (e) => uploadFile(e.target.files[0]);
const handleDrop = (e) => {
    dragActive.value = false;
    uploadFile(e.dataTransfer.files[0]);
};

const getUploadBlockMessage = (payload) => {
    if (!payload || typeof payload !== 'object') return null;
    const requiresKyc = payload.requires_kyc ?? payload.requiresKyc;
    const requiresSignature = payload.requires_signature ?? payload.requiresSignature;
    const requiresCertificate = payload.requires_certificate ?? payload.requiresCertificate;

    const missing = [];
    if (requiresKyc === true) missing.push('KYC');
    if (requiresSignature === true) missing.push('tanda tangan');
    if (requiresCertificate === true) missing.push('sertifikat');

    if (missing.length === 0) return null;
    return `Upload ditolak: ${missing.join(', ')} belum lengkap.`;
};

const getOwnerInfo = (owner) => {
    if (!owner) return null;
    if (typeof owner === 'string') return { name: owner, email: null, avatar: null };
    if (typeof owner !== 'object') return null;
    return {
        name: owner.name || owner.full_name || owner.fullName || null,
        email: owner.email || null,
        avatar: owner.avatar || null,
    };
};

const uploadFile = async (file) => {
    if (!file) return;
    if (file.size > MAX_UPLOAD_BYTES) {
        toastStore.error('File size exceeds 10MB.');
        return;
    }
    const formData = new FormData();
    formData.append('file', file);
    
    try {
        await axios.post('/api/documents', formData);
        await fetchDocuments();
        toastStore.success('Document uploaded successfully.');
    } catch (e) {
        const payload = e?.response?.data?.data ?? e?.response?.data ?? null;
        const requirementMessage = getUploadBlockMessage(payload);
        if (requirementMessage) {
            toastStore.error(requirementMessage);
            return;
        }
        if (payload?.message) {
            toastStore.error(payload.message);
            return;
        }
        toastStore.error(formatApiError('Upload failed', e));
    }
};

const openSigningModal = (docId, pageCount) => {
    selectedDocId.value = docId;
    selectedDocPageCount.value = pageCount || 1;
    showSigningModal.value = true;
};

const onDocumentSigned = async () => {
    await fetchDocuments();
};

const finalizeDocument = async (id) => {
    try {
        await axios.post(`/api/documents/${id}/finalize`);
        toastStore.success('Document finalized.');
        await fetchDocuments();
    } catch (e) {
        toastStore.error(formatApiError('Failed to finalize document', e));
    }
};

const verifyDocument = async (id) => {
    let docData = null;
    try {
        const docRes = await axios.get(`/api/documents/${id}`);
        docData = docRes.data?.data ?? docRes.data;
        const verifyToken = docData?.verify_token || docData?.verifyToken;
        
        if (!verifyToken) {
            try {
                const fallbackRes = await axios.post('/api/documents/verify', { document_id: id });
                const payload = fallbackRes.data?.data ?? fallbackRes.data;
                const isValid = payload?.isValid === true || payload?.is_valid === true;
                const tone = isValid ? 'success' : 'error';
                const ownerInfo = getOwnerInfo(payload?.document_owner ?? payload?.documentOwner);
                const signers = Array.isArray(payload?.signers)
                    ? payload.signers.map((signer) => ({
                        name: signer?.name,
                        status: signer?.status,
                        signedAt: formatDateTime(signer?.signed_at ?? signer?.signedAt),
                    }))
                    : [];
                const signedBy = !signers.length ? (payload?.signed_by ?? payload?.signedBy) : null;
                const signedAt = payload?.signed_at ?? payload?.signedAt ?? null;
                const fields = [
                    { label: 'File', value: payload?.file_name || payload?.fileName || 'Document' },
                ];
                if (signedBy) {
                    fields.push({ label: 'Signed By', value: signedBy });
                }
                if (!signers.length) {
                    fields.push({ label: 'Signed At', value: signedAt ? formatDateTime(signedAt) : '-' });
                }
                const completedAt = payload?.completed_at ?? payload?.completedAt;
                if (completedAt) {
                    fields.push({ label: 'Completed At', value: formatDateTime(completedAt) });
                }

                if (payload?.ltv) {
                    const certNotBefore = payload.ltv.certificate_not_before ?? payload.ltv.certificateNotBefore;
                    const certNotAfter = payload.ltv.certificate_not_after ?? payload.ltv.certificateNotAfter;
                    if (certNotBefore) {
                        fields.push({ label: 'Cert Valid From', value: formatDateTime(certNotBefore) });
                    }
                    if (certNotAfter) {
                        fields.push({ label: 'Cert Valid To', value: formatDateTime(certNotAfter) });
                    }
                }

                verifyModalResult.value = {
                    title: 'Verification Result',
                    tone,
                    statusLabel: isValid ? 'VALID' : 'INVALID',
                    summary: payload?.message || (isValid ? 'Document signature verified successfully.' : 'Document verification failed.'),
                    owner: ownerInfo,
                    fields,
                    signers: signers.length ? signers : undefined,
                };
                verifyModalOpen.value = true;
            } catch (error) {
                verifyModalResult.value = {
                    title: 'Verification Failed',
                    tone: 'error',
                    summary: formatApiError('Verification failed', error),
                    fields: [
                        { label: 'File', value: docData ? getFileName(docData) : 'Document' },
                        { label: 'Signed At', value: '-' },
                    ],
                };
                verifyModalOpen.value = true;
            }
            return;
        }
        
        const res = await axios.get(`/api/verify/${verifyToken}`);
        const verifyData = res.data?.data ?? res.data;
        const isValid = verifyData?.isValid === true || verifyData?.is_valid === true;
        const tone = isValid ? 'success' : 'error';
        const ownerInfo = getOwnerInfo(verifyData?.document_owner ?? verifyData?.documentOwner);
        const signers = Array.isArray(verifyData?.signers)
            ? verifyData.signers.map((signer) => ({
                name: signer?.name,
                email: signer?.email,
                status: signer?.status,
                signedAt: formatDateTime(signer?.signed_at ?? signer?.signedAt),
            }))
            : [];
        const signedBy = !signers.length ? (verifyData?.signed_by ?? verifyData?.signedBy) : null;
        const signedAt = verifyData?.signed_at ?? verifyData?.signedAt ?? null;
        const fields = [
            { label: 'File', value: verifyData?.file_name || verifyData?.fileName || 'Document' },
        ];
        if (signedBy) {
            fields.push({ label: 'Signed By', value: signedBy });
        }
        if (!signers.length) {
            fields.push({ label: 'Signed At', value: signedAt ? formatDateTime(signedAt) : '-' });
        }
        const completedAt = verifyData?.completed_at ?? verifyData?.completedAt;
        if (completedAt) {
            fields.push({ label: 'Completed At', value: formatDateTime(completedAt) });
        }
        if (verifyData?.ltv) {
            const certNotBefore = verifyData.ltv.certificate_not_before ?? verifyData.ltv.certificateNotBefore;
            const certNotAfter = verifyData.ltv.certificate_not_after ?? verifyData.ltv.certificateNotAfter;
            if (certNotBefore) {
                fields.push({ label: 'Cert Valid From', value: formatDateTime(certNotBefore) });
            }
            if (certNotAfter) {
                fields.push({ label: 'Cert Valid To', value: formatDateTime(certNotAfter) });
            }
        }
        verifyModalResult.value = {
            title: 'Verification Result',
            tone,
            statusLabel: isValid ? 'VALID' : 'INVALID',
            summary: verifyData?.message || (isValid ? 'Document signature verified successfully.' : 'Document verification failed.'),
            owner: ownerInfo,
            fields,
            signers: signers.length ? signers : undefined,
        };
        verifyModalOpen.value = true;
    } catch (e) {
        verifyModalResult.value = {
            title: 'Verification Failed',
            tone: 'error',
            summary: formatApiError('Verification failed', e),
            fields: [
                { label: 'File', value: docData ? getFileName(docData) : 'Document' },
                { label: 'Signed At', value: '-' },
            ],
        };
        verifyModalOpen.value = true;
    }
};

const fetchDocuments = async () => {
    try {
        const res = await axios.get('/api/documents');
        const list = res.data?.data ?? res.data;
        documents.value = Array.isArray(list) ? list : [];
    } catch (e) {
        console.error('Failed to fetch documents:', e);
        documents.value = [];
        toastStore.error(formatApiError('Failed to fetch documents', e));
    }
};

const downloadDocument = async (id) => {
    try {
        const response = await axios.get(`/api/documents/${id}/download`, {
            responseType: 'blob',
        });
        
        const contentDisposition = response.headers['content-disposition'];
        let filename = 'signed_document.pdf';
        if (contentDisposition) {
            const filenameMatch = contentDisposition.match(/filename="?(.+)"?/);
            if (filenameMatch && filenameMatch[1]) {
                filename = filenameMatch[1];
            }
        }
        
        const blob = new Blob([response.data], { type: 'application/pdf' });
        const url = window.URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = filename;
        document.body.appendChild(link);
        link.click();
        
        document.body.removeChild(link);
        window.URL.revokeObjectURL(url);
        toastStore.success('Download started.');
    } catch (e) {
        toastStore.error(formatApiError('Download failed', e));
    }
};

const getFileName = (docOrPath) => {
    if (!docOrPath) return 'document.pdf';
    if (typeof docOrPath === 'string') return docOrPath.split('/').pop();
    return (
        docOrPath.title ||
        docOrPath.original_filename ||
        docOrPath.originalFilename ||
        (docOrPath.file_path ? docOrPath.file_path.split('/').pop() : null) ||
        (docOrPath.filePath ? docOrPath.filePath.split('/').pop() : 'document.pdf')
    );
};
const formatDate = (dateString) => {
    if (!dateString) return '';
    const date = new Date(dateString);
    if (Number.isNaN(date.getTime())) return '';
    const day = String(date.getDate()).padStart(2, '0');
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const year = date.getFullYear();
    const hours = String(date.getHours()).padStart(2, '0');
    const minutes = String(date.getMinutes()).padStart(2, '0');
    return `${day} ${month} ${year} ${hours}:${minutes}`;
};

const formatDateTime = (dateString) => {
    if (!dateString) return '-';
    const date = new Date(dateString);
    return date.toLocaleString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const closeVerifyModal = () => {
    verifyModalOpen.value = false;
    verifyModalResult.value = null;
};
</script>
