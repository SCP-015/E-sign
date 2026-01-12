<template>
    <Head title="Dashboard" />
    <div class="min-h-screen bg-base-100">
        <main class="mx-auto w-full max-w-7xl space-y-6 px-4 py-6">
            <section>
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-base-content/50">Welcome back</p>
                <h2 class="mt-1 text-2xl font-bold">Halo, {{ user.name }}!</h2>
                <p class="text-sm text-base-content/60">Welcome back to your E-Sign dashboard.</p>
            </section>

            <KycBanner :status="user.kyc_status" />

            <StatsGrid :stats="stats" />

            <CertificateStatusCard :status="user.kyc_status" :expiry="certificateExpiry" />

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
                v-if="isVerified"
                :documents="documents"
                :formatDate="formatDate"
                :getFileName="getFileName"
                @sign="openSigningModal"
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
import { Head } from '@inertiajs/vue3';
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
const user = computed(() => authStore.user || {});
const isVerified = computed(() => user.value?.kyc_status === 'verified');

const dragActive = ref(false);
const documents = ref([]);
const fileInput = ref(null);
const showSigningModal = ref(false);
const selectedDocId = ref(null);
const selectedDocPageCount = ref(0);
const verifyModalOpen = ref(false);
const verifyModalResult = ref(null);

const signedCount = computed(() => documents.value.filter(d => d.status === 'signed' || d.status === 'COMPLETED').length);
const pendingCount = computed(() => documents.value.filter(d => d.status === 'pending' || d.status === 'IN_PROGRESS').length);
const certificateExpiry = computed(() => {
    const expiresAt = user.value?.certificate?.expires_at;
    if (!expiresAt) return '-';
    const date = new Date(expiresAt);
    return date.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
});

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

onMounted(async () => {
    try {
        await authStore.fetchUser();
        await fetchDocuments();
    } catch (e) {
        console.error('Failed to init dashboard:', e);
    }
});

const handleFileSelect = (e) => uploadFile(e.target.files[0]);
const handleDrop = (e) => {
    dragActive.value = false;
    uploadFile(e.dataTransfer.files[0]);
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

const verifyDocument = async (id) => {
    try {
        const docRes = await axios.get(`/api/documents/${id}`);
        const docData = docRes.data?.data ?? docRes.data;
        const verifyToken = docData?.verify_token;
        
        if (!verifyToken) {
            verifyModalResult.value = {
                title: 'Verification Failed',
                tone: 'error',
                summary: 'No verify token found for this document.',
                fields: [
                    { label: 'Document ID', value: id },
                ],
            };
            verifyModalOpen.value = true;
            return;
        }
        
        const res = await axios.get(`/api/verify/${verifyToken}`);
        const verifyData = res.data?.data ?? res.data;
        const status = verifyData?.status || 'unknown';
        const isValid = verifyData?.is_valid === true;
        const tone = isValid ? 'success' : 'error';
        verifyModalResult.value = {
            title: 'Verification Result',
            tone,
            statusLabel: isValid ? 'VALID' : 'INVALID',
            summary: verifyData?.message || (isValid ? 'Document signature verified successfully.' : 'Document verification failed.'),
            fields: [
                { label: 'Document ID', value: verifyData?.documentId || id },
                { label: 'File', value: verifyData?.fileName || 'Document' },
                { label: 'Completed At', value: formatDateTime(verifyData?.completedAt) },
            ],
            signers: (verifyData?.signers || []).map((signer) => ({
                name: signer.name,
                status: signer.status,
                signedAt: formatDateTime(signer.signedAt),
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
    return docOrPath.title || docOrPath.original_filename || (docOrPath.file_path ? docOrPath.file_path.split('/').pop() : 'document.pdf');
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
