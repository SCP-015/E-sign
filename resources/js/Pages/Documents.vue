<template>
    <Head title="Documents" />
    <div class="min-h-screen bg-base-100">
        <main class="mx-auto w-full max-w-7xl space-y-6 px-4 py-6">
            <section class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-base-content/50">Documents</p>
                    <h2 class="mt-1 text-2xl font-bold">Document History</h2>
                    <p class="text-sm text-base-content/60">All documents you own or are assigned to.</p>
                </div>
                <Link href="/dashboard" class="btn btn-outline btn-sm w-full sm:w-auto">
                    Back to Dashboard
                </Link>
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
import { ref, onMounted, computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import axios from 'axios';
import { useAuthStore } from '../stores/auth';
import { useToastStore } from '../stores/toast';
import SigningModal from '../components/SigningModal.vue';
import VerifyResultModal from '../components/VerifyResultModal.vue';
import DocumentHistory from '../components/dashboard/DocumentHistory.vue';
import { formatApiError } from '../utils/errors';

const authStore = useAuthStore();
const toastStore = useToastStore();
const user = computed(() => authStore.user || {});
const kycStatus = computed(() => (user.value?.kyc_status ?? user.value?.kycStatus ?? 'unverified').toLowerCase());
const hasSignature = computed(() => user.value?.has_signature ?? user.value?.hasSignature ?? false);

const documents = ref([]);
const showSigningModal = ref(false);
const selectedDocId = ref(null);
const selectedDocPageCount = ref(0);
const verifyModalOpen = ref(false);
const verifyModalResult = ref(null);

onMounted(async () => {
    try {
        await authStore.fetchUser();
        await fetchDocuments();
    } catch (e) {
        console.error('Failed to init documents:', e);
    }
});

const openSigningModal = (docId, pageCount) => {
    selectedDocId.value = docId;
    selectedDocPageCount.value = pageCount || 1;
    showSigningModal.value = true;
};

const onDocumentSigned = async () => {
    await fetchDocuments();
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
    if (typeof owner === 'string') return { name: owner, email: null };
    if (typeof owner !== 'object') return null;
    return {
        name: owner.name || owner.full_name || owner.fullName || null,
        email: owner.email || null,
    };
};

const verifyDocument = async (id) => {
    try {
        const docRes = await axios.get(`/api/documents/${id}`);
        const docData = docRes.data?.data ?? docRes.data;
        const verifyToken = docData?.verify_token || docData?.verifyToken;

        if (!verifyToken) {
            try {
                const fallbackRes = await axios.post('/api/documents/verify', { document_id: id });
                const payload = fallbackRes.data?.data ?? fallbackRes.data;
                const isValid = payload?.isValid === true || payload?.is_valid === true;
                const tone = isValid ? 'success' : 'error';
                const ownerInfo = getOwnerInfo(payload?.document_owner ?? payload?.documentOwner);
                const fields = [
                    { label: 'Document ID', value: payload?.document_id || id },
                    { label: 'File', value: payload?.file_name || 'Document' },
                    { label: 'Signed By', value: payload?.signed_by || '-' },
                    { label: 'Signed At', value: formatDateTime(payload?.signed_at) },
                ];
                if (ownerInfo) {
                    fields.splice(
                        1,
                        0,
                        { label: 'Owner Name', value: ownerInfo.name || '-' },
                        { label: 'Owner Email', value: ownerInfo.email || '-' },
                    );
                }

                if (payload?.ltv) {
                    fields.push(
                        { label: 'Certificate #', value: payload.ltv.certificate_number || '-' },
                        { label: 'Cert Valid From', value: formatDateTime(payload.ltv.certificate_not_before) },
                        { label: 'Cert Valid To', value: formatDateTime(payload.ltv.certificate_not_after) },
                        { label: 'TSA URL', value: payload.ltv.tsa_url || '-' },
                        { label: 'TSA At', value: formatDateTime(payload.ltv.tsa_at) },
                    );
                }

                verifyModalResult.value = {
                    title: 'Verification Result',
                    tone,
                    statusLabel: isValid ? 'VALID' : 'INVALID',
                    summary: payload?.message || (isValid ? 'Document signature verified successfully.' : 'Document verification failed.'),
                    fields,
                };
                verifyModalOpen.value = true;
            } catch (error) {
                verifyModalResult.value = {
                    title: 'Verification Failed',
                    tone: 'error',
                    summary: formatApiError('Verification failed', error),
                    fields: [
                        { label: 'Document ID', value: id },
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
        const fields = [
            { label: 'Document ID', value: verifyData?.document_id || verifyData?.documentId || id },
            { label: 'File', value: verifyData?.file_name || verifyData?.fileName || 'Document' },
            { label: 'Completed At', value: formatDateTime(verifyData?.completed_at ?? verifyData?.completedAt) },
        ];
        if (ownerInfo) {
            fields.splice(
                1,
                0,
                { label: 'Owner Name', value: ownerInfo.name || '-' },
                { label: 'Owner Email', value: ownerInfo.email || '-' },
            );
        }

        verifyModalResult.value = {
            title: 'Verification Result',
            tone,
            statusLabel: isValid ? 'VALID' : 'INVALID',
            summary: verifyData?.message || (isValid ? 'Document signature verified successfully.' : 'Document verification failed.'),
            fields,
            signers: (verifyData?.signers || []).map((signer) => ({
                name: signer.name,
                status: signer.status,
                signedAt: formatDateTime(signer.signed_at ?? signer.signedAt),
            })),
        };
        verifyModalOpen.value = true;
    } catch (e) {
        verifyModalResult.value = {
            title: 'Verification Failed',
            tone: 'error',
            summary: formatApiError('Verification failed', e),
            fields: [
                { label: 'Document ID', value: id },
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

const finalizeDocument = async (id) => {
    try {
        await axios.post(`/api/documents/${id}/finalize`);
        toastStore.success('Document finalized.');
        await fetchDocuments();
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
