<template>
    <Head title="Documents" />
    <div class="min-h-screen bg-base-100">
        <main class="mx-auto w-full max-w-7xl space-y-6 px-4 py-6">
            <section class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex-1">
                    <div class="flex items-center gap-3">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-base-content/50">Documents</p>
                            <h2 class="mt-1 text-2xl font-bold">Document History</h2>
                        </div>
                        <ContextIndicator :tenant-id="currentTenantId" :tenant-name="currentTenantName" />
                    </div>
                    <div class="mt-2 p-3 bg-blue-50 border border-blue-200 rounded-lg">
                        <p class="text-[13px] text-blue-800">
                            <strong>{{ isPersonalMode ? 'Mode Personal' : 'Mode Organisasi' }}:</strong>
                            {{ isPersonalMode 
                                ? 'Dokumen pribadi Anda. Tidak akan terlihat saat Anda berada di mode organisasi.' 
                                : 'Dokumen yang Anda upload di sini hanya terlihat di organisasi ini.'
                            }}
                        </p>
                    </div>
                </div>
                <div class="flex w-full flex-col gap-2 sm:w-auto sm:flex-row sm:items-center sm:justify-end">
                    <button
                        type="button"
                        class="btn btn-outline btn-sm w-full sm:w-auto"
                        :class="syncing ? 'btn-disabled' : ''"
                        :disabled="syncing"
                        @click="syncDocuments"
                    >
                        Sync Documents
                    </button>
                    <Link href="/dashboard" class="btn btn-outline btn-sm w-full sm:w-auto">
                        Back to Dashboard
                    </Link>
                </div>
            </section>

            <DocumentHistory
                :documents="documents"
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
import { Head, Link, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { useAuthStore } from '../stores/auth';
import { useToastStore } from '../stores/toast';
import SigningModal from '../components/SigningModal.vue';
import VerifyResultModal from '../components/VerifyResultModal.vue';
import DocumentHistory from '../components/dashboard/DocumentHistory.vue';
import ContextIndicator from '../components/ContextIndicator.vue';
import { formatApiError } from '../utils/errors';

const page = usePage();

const authStore = useAuthStore();
const toastStore = useToastStore();
const user = computed(() => authStore.user || {});
const kycStatus = computed(() => (user.value?.kyc_status ?? user.value?.kycStatus ?? 'unverified').toLowerCase());
const hasSignature = computed(() => user.value?.has_signature ?? user.value?.hasSignature ?? false);

const hydratedOrganization = ref(null);
const currentOrganization = computed(() => hydratedOrganization.value || page.props.organization || page.props?.auth?.organization || null);
const currentTenantId = computed(() => currentOrganization.value?.id || null);
const currentTenantName = computed(() => currentOrganization.value?.name || null);
const isPersonalMode = computed(() => !currentTenantId.value);

const documents = ref([]);
const showSigningModal = ref(false);
const selectedDocId = ref(null);
const selectedDocPageCount = ref(0);
const verifyModalOpen = ref(false);
const verifyModalResult = ref(null);
const syncing = ref(false);

const hideMissingDocumentsKey = 'hideMissingDocuments';
const shouldHideMissingDocuments = () => localStorage.getItem(hideMissingDocumentsKey) === 'true';
const enableHideMissingDocuments = () => localStorage.setItem(hideMissingDocumentsKey, 'true');

const refreshDocuments = async () => {
    if (shouldHideMissingDocuments()) {
        await syncDocuments({ showToast: false });
        return;
    }
    await fetchDocuments();
};

const handleOrganizationUpdate = async () => {
    await refreshDocuments();
};

const hydrateOrganization = async () => {
    try {
        const res = await axios.get('/api/organizations/current');
        const payload = res?.data;
        if ((payload?.success === true || payload?.status === 'success') && payload?.data) {
            hydratedOrganization.value = payload.data;
        }
    } catch (e) {
        // noop
    }
};

const isApiSuccess = (payload) => {
    return payload?.success === true || payload?.status === 'success';
};

const syncDocuments = async (options = {}) => {
    const showToast = options?.showToast !== false;
    syncing.value = true;
    try {
        const beforeCount = Array.isArray(documents.value) ? documents.value.length : 0;
        const res = await axios.post('/api/documents/sync');
        const payload = res?.data;
        if (!isApiSuccess(payload)) {
            throw new Error(payload?.message || 'Sync failed');
        }

        const data = payload?.data;
        const list = Array.isArray(data?.documents) ? data.documents : (Array.isArray(data) ? data : []);
        documents.value = list;

        enableHideMissingDocuments();

        if (showToast) {
            const removedCount = Math.max(0, beforeCount - (Array.isArray(list) ? list.length : 0));
            if (removedCount > 0) {
                toastStore.success(`Sync completed. ${removedCount} missing document(s) were hidden.`);
            } else {
                toastStore.success('Sync completed. No missing documents found.');
            }
        }
    } catch (e) {
        if (showToast) {
            toastStore.error(formatApiError('Failed to sync documents', e));
        }
    } finally {
        syncing.value = false;
    }
};

onMounted(async () => {
    try {
        await authStore.fetchUser();
        await hydrateOrganization();
        await refreshDocuments();
        window.addEventListener('organization-updated', handleOrganizationUpdate);
    } catch (e) {
        console.error('Failed to init documents:', e);
    }
});

onUnmounted(() => {
    window.removeEventListener('organization-updated', handleOrganizationUpdate);
});

const openSigningModal = (docId, pageCount) => {
    selectedDocId.value = docId;
    selectedDocPageCount.value = pageCount || 1;
    showSigningModal.value = true;
};

const onDocumentSigned = async () => {
    await refreshDocuments();
};

const isAssignedToMe = (doc) => {
    if (!doc?.signers || doc.signers.length === 0) {
        return Number(doc?.user_id ?? doc?.userId) === Number(authStore.user?.id);
    }
    return doc.signers.some((s) =>
        Number(s.user_id ?? s.userId) === Number(authStore.user?.id) ||
        String(s.email || '').toLowerCase() === String(authStore.user?.email || '').toLowerCase()
    );
};

const hasISigned = (doc) => {
    if (!doc?.signers) return false;
    const mySigner = doc.signers.find((s) =>
        Number(s.user_id ?? s.userId) === Number(authStore.user?.id) ||
        String(s.email || '').toLowerCase() === String(authStore.user?.email || '').toLowerCase()
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
                        email: signer?.email,
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
                signedAt: formatDateTime(signer?.signed_at?? signer?.signedAt),
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
        const res = await axios.get('/api/documents', {
            params: { tenant_id: currentTenantId.value }
        });
        const list = res.data?.data ?? res.data;
        documents.value = Array.isArray(list) ? list : [];
        
        console.log('Documents fetched:', {
            mode: isPersonalMode.value ? 'personal' : 'tenant',
            tenant_id: currentTenantId.value,
            count: documents.value.length
        });
    } catch (e) {
        console.error('Failed to fetch documents:', e);
        documents.value = [];
        toastStore.error(formatApiError('Failed to fetch documents', e));
    }
};

const finalizeDocument = async (id) => {
    try {
        await axios.post(`/api/documents/${id}/finalize`);
        toastStore.success('Document finalized.');
        await refreshDocuments();
    } catch (e) {
        toastStore.error(formatApiError('Failed to finalize document', e));
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
