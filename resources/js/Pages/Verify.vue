<template>
    <div class="min-h-screen bg-base-100">
        <main class="mx-auto w-full max-w-6xl space-y-6 px-4 py-8">
            <section>
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-base-content/50">Verification</p>
                <h2 class="mt-1 text-2xl font-bold">Verify Document</h2>
                <p class="text-sm text-base-content/60">
                    Upload a signed PDF to verify its signature using the public verify API.
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
import { ref, computed } from 'vue';
import axios from 'axios';
import { formatApiError } from '../utils/errors';
import VerifyResultModal from '../components/VerifyResultModal.vue';

const dragActive = ref(false);
const fileInput = ref(null);
const selectedFile = ref(null);
const verifying = ref(false);
const verifyHint = ref('');
const verifyModalOpen = ref(false);
const verifyModalResult = ref(null);

const matchHint = computed(() => {
    if (!selectedFile.value) return '';
    if (verifying.value) return 'Verifying your document...';
    return verifyHint.value || 'Ready to verify the uploaded file.';
});

const handleFileSelect = (e) => {
    const file = e.target.files[0];
    if (!file) return;
    selectedFile.value = file;
    verifyHint.value = '';
};

const handleDrop = (e) => {
    dragActive.value = false;
    const file = e.dataTransfer.files[0];
    if (!file) return;
    selectedFile.value = file;
    verifyHint.value = '';
};

const clearFile = () => {
    selectedFile.value = null;
    if (fileInput.value) {
        fileInput.value.value = '';
    }
    verifyHint.value = '';
};

const verifyFile = async () => {
    if (!selectedFile.value) return;

    verifying.value = true;
    try {
        const formData = new FormData();
        formData.append('file', selectedFile.value);
        const res = await axios.post('/api/verify/upload', formData);
        const payload = res.data?.data ?? res.data;
        const message = res.data?.message ?? payload?.message;
        const isValid = payload?.is_valid === true;
        verifyHint.value = message || '';

        const fields = [
            { label: 'File', value: payload?.file_name || selectedFile.value.name },
            { label: 'Document ID', value: payload?.document_id || '-' },
            { label: 'Signed By', value: payload?.signed_by || '-' },
            { label: 'Signed At', value: formatDateTime(payload?.signed_at) },
        ];

        if (payload?.ltv) {
            fields.push(
                { label: 'Certificate #', value: payload.ltv.certificate_number || '-' },
                { label: 'Cert Valid From', value: formatDateTime(payload.ltv.certificate_not_before) },
                { label: 'Cert Valid To', value: formatDateTime(payload.ltv.certificate_not_after) },
                { label: 'TSA URL', value: payload.ltv.tsa_url || '-' },
                { label: 'TSA At', value: formatDateTime(payload.ltv.tsa_at) },
            );
        }

        openVerifyModal({
            title: isValid ? 'Signature Verified' : 'Signature Invalid',
            tone: isValid ? 'success' : 'error',
            statusLabel: isValid ? 'VALID' : 'INVALID',
            summary: message || (isValid ? 'Signature is valid.' : 'Signature is not valid.'),
            fields,
        });
    } catch (e) {
        verifyHint.value = formatApiError('Verification failed', e);
        openVerifyModal({
            title: 'Verification Failed',
            tone: 'error',
            summary: formatApiError('Verification failed', e),
            fields: [
                { label: 'File', value: selectedFile.value.name },
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

</script>
