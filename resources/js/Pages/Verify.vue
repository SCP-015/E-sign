<template>
    <div class="min-h-screen bg-base-100">
        <main class="mx-auto w-full max-w-6xl space-y-6 px-4 py-8">
            <section>
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-base-content/50">Verification</p>
                <h2 class="mt-1 text-2xl font-bold">Verify Document</h2>
                <p class="text-sm text-base-content/60">
                    Upload a signed PDF to verify its signature against your document history.
                </p>
            </section>

            <section class="card border border-base-200 bg-base-100 shadow-sm">
                <div class="card-body gap-4">
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
                            <p class="text-sm font-semibold">Drag & drop a signed PDF here</p>
                            <p class="text-xs text-base-content/60">or choose a file from your device</p>
                        </div>
                        <button @click="fileInput?.click()" class="btn btn-primary btn-sm w-full">
                            Choose File
                        </button>
                        <p class="text-[10px] text-base-content/50">PDF only</p>
                    </div>

                    <div v-if="selectedFile" class="rounded-2xl border border-base-200 bg-base-100 p-4 text-sm">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="font-semibold">{{ selectedFile.name }}</p>
                                <p class="text-xs text-base-content/60">{{ formatFileSize(selectedFile.size) }}</p>
                            </div>
                            <button class="btn btn-ghost btn-xs" type="button" @click="clearFile">Remove</button>
                        </div>

                        <div class="mt-4 flex flex-wrap gap-2">
                            <button class="btn btn-primary btn-sm" type="button" @click="verifyFile" :disabled="verifying">
                                {{ verifying ? 'Verifying...' : 'Verify File' }}
                            </button>
                            <button class="btn btn-outline btn-sm" type="button" @click="fileInput?.click()">
                                Choose Another
                            </button>
                        </div>

                        <p v-if="matchHint" class="mt-3 text-xs text-base-content/60">{{ matchHint }}</p>
                    </div>
                </div>
            </section>
        </main>

        <VerifyResultModal
            :isOpen="verifyModalOpen"
            :result="verifyModalResult"
            @close="closeVerifyModal"
        />
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';
import { useAuthStore } from '../stores/auth';
import { formatApiError } from '../utils/errors';
import VerifyResultModal from '../components/VerifyResultModal.vue';

const authStore = useAuthStore();

const dragActive = ref(false);
const fileInput = ref(null);
const selectedFile = ref(null);
const verifying = ref(false);
const documents = ref([]);
const documentsLoaded = ref(false);
const verifyModalOpen = ref(false);
const verifyModalResult = ref(null);

const matchHint = computed(() => {
    if (!selectedFile.value) return '';
    if (!authStore.isAuthenticated) return 'Please sign in to verify using your document history.';
    if (!documentsLoaded.value) return 'Loading your document history...';
    return findMatchingDocument(selectedFile.value)
        ? 'Matched to a document in your history.'
        : 'No matching document found in your history.';
});

onMounted(async () => {
    if (authStore.isAuthenticated) {
        await loadDocuments();
    }
});

const handleFileSelect = (e) => {
    const file = e.target.files[0];
    if (!file) return;
    selectedFile.value = file;
};

const handleDrop = (e) => {
    dragActive.value = false;
    const file = e.dataTransfer.files[0];
    if (!file) return;
    selectedFile.value = file;
};

const clearFile = () => {
    selectedFile.value = null;
    if (fileInput.value) {
        fileInput.value.value = '';
    }
};

async function loadDocuments() {
    try {
        const res = await axios.get('/api/documents');
        documents.value = Array.isArray(res.data) ? res.data : (res.data?.data || []);
        documentsLoaded.value = true;
    } catch (e) {
        documentsLoaded.value = false;
    }
}

const verifyFile = async () => {
    if (!selectedFile.value) return;

    const localScan = await scanSignatureMarkers(selectedFile.value);

    if (!authStore.isAuthenticated) {
        openVerifyModal({
            title: localScan.isValid ? 'Signature Detected' : 'Signature Not Found',
            tone: localScan.isValid ? 'success' : 'warning',
            statusLabel: localScan.isValid ? 'DETECTED' : 'NOT FOUND',
            summary: `${localScan.message} (local scan)`,
            fields: [
                { label: 'File', value: selectedFile.value.name },
                { label: 'Scan', value: 'Local signature marker scan' },
            ],
        });
        return;
    }

    if (!documentsLoaded.value) {
        await loadDocuments();
    }

    const matched = findMatchingDocument(selectedFile.value);
    if (!matched) {
        openVerifyModal({
            title: localScan.isValid ? 'Signature Detected' : 'Signature Not Found',
            tone: localScan.isValid ? 'success' : 'warning',
            statusLabel: localScan.isValid ? 'DETECTED' : 'NOT FOUND',
            summary: `${localScan.message} (local scan)`,
            fields: [
                { label: 'File', value: selectedFile.value.name },
                { label: 'Scan', value: 'Local signature marker scan' },
            ],
        });
        return;
    }

    const docId = getDocumentId(matched);
    if (!docId) {
        openVerifyModal({
            title: 'Verification Failed',
            tone: 'error',
            summary: 'Unable to resolve document ID for verification.',
            fields: [
                { label: 'File', value: selectedFile.value.name },
            ],
        });
        return;
    }

    verifying.value = true;
    try {
        const res = await axios.post('/api/documents/verify', {
            document_id: docId,
        });

        const data = res.data || {};
        const isValid = data.is_valid === true;
        openVerifyModal({
            title: isValid ? 'Signature Verified' : 'Signature Invalid',
            tone: isValid ? 'success' : 'error',
            statusLabel: isValid ? 'VALID' : 'INVALID',
            summary: data.message || (isValid ? 'Signature is valid.' : 'Signature is not valid.'),
            fields: [
                { label: 'Document ID', value: data.document_id || docId },
                { label: 'File', value: data.file_name || selectedFile.value.name },
                { label: 'Signed By', value: data.signed_by || '-' },
                { label: 'Signed At', value: formatDateTime(data.signed_at) },
            ],
        });
    } catch (e) {
        openVerifyModal({
            title: 'Verification Failed',
            tone: 'error',
            summary: `${formatApiError('Verification failed', e)} (local scan used below)`,
            fields: [
                { label: 'Document ID', value: docId },
                { label: 'Local scan', value: localScan.message },
            ],
        });
    } finally {
        verifying.value = false;
    }
};

const openVerifyModal = (result) => {
    verifyModalResult.value = result;
    verifyModalOpen.value = true;
};

const closeVerifyModal = () => {
    verifyModalOpen.value = false;
    verifyModalResult.value = null;
};

const formatFileSize = (size) => {
    if (!size && size !== 0) return '-';
    if (size < 1024) return `${size} B`;
    if (size < 1024 * 1024) return `${(size / 1024).toFixed(1)} KB`;
    return `${(size / (1024 * 1024)).toFixed(2)} MB`;
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

function normalizeName(name) {
    return (name || '').trim().toLowerCase();
}

function extractDocName(doc) {
    return (
        doc.original_filename ||
        doc.title ||
        doc.file_name ||
        (doc.file_path ? doc.file_path.split('/').pop() : '')
    );
}

function getDocumentId(doc) {
    return doc.documentId || doc.document_id || doc.id || null;
}

function findMatchingDocument(file) {
    if (!file) return null;
    const fileName = normalizeName(file.name);
    const fileSize = file.size;

    const scored = documents.value.map((doc) => {
        const docName = normalizeName(extractDocName(doc));
        const sizeBytes = doc.file_size_bytes || doc.file_size || null;
        let score = 0;

        if (docName && fileName && docName === fileName) score += 2;
        if (sizeBytes && fileSize && Number(sizeBytes) === Number(fileSize)) score += 1;

        return { doc, score };
    }).filter((entry) => entry.score > 0);

    scored.sort((a, b) => b.score - a.score);
    return scored[0]?.doc || null;
}

async function scanSignatureMarkers(file) {
    try {
        const buffer = await file.arrayBuffer();
        const text = new TextDecoder('latin1').decode(new Uint8Array(buffer));
        const hasSignature =
            text.includes('/SigFlags') ||
            text.includes('/Sig') ||
            text.includes('/Contents') ||
            text.includes('/AcroForm');

        return {
            isValid: hasSignature,
            message: hasSignature ? 'Signature markers found in PDF.' : 'No signature markers found in PDF.',
        };
    } catch (e) {
        return {
            isValid: false,
            message: 'Failed to scan PDF content.',
        };
    }
}
</script>
