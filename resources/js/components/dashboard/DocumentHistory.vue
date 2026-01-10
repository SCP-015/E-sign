<template>
    <section class="space-y-3">
        <div class="flex items-center justify-between">
            <h3 class="text-lg font-semibold">Document History</h3>
            <span class="badge badge-outline text-xs">{{ documents.length }} documents</span>
        </div>

        <div v-if="documents.length > 0" class="space-y-3">
            <div
                v-for="doc in documents"
                :key="doc.id"
                class="card border border-base-200 bg-base-100 shadow-sm"
            >
                <div class="card-body gap-3">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-xs text-base-content/40">{{ formatDate(doc.created_at) }}</p>
                            <h4 class="font-semibold">{{ getFileName(doc.file_path) }}</h4>
                        </div>
                        <span
                            :class="[
                                'badge badge-sm uppercase',
                                doc.status === 'pending'
                                    ? 'badge-warning'
                                    : doc.status === 'signed'
                                        ? 'badge-success'
                                        : 'badge-ghost',
                            ]"
                        >
                            {{ doc.status }}
                        </span>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button v-if="doc.status === 'pending'" @click="emit('sign', doc.id, doc.page_count)" class="btn btn-primary btn-sm">
                            Sign Now
                        </button>
                        <button v-if="doc.status === 'signed'" @click="emit('verify', doc.id)" class="btn btn-outline btn-sm">
                            Verify Signature
                        </button>
                        <button v-if="doc.status === 'signed'" @click="emit('download', doc.id)" class="btn btn-ghost btn-sm">
                            Download
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div v-else class="card border border-dashed border-base-300 bg-base-100/70 shadow-sm">
            <div class="card-body items-center text-center">
                <div class="text-3xl">📭</div>
                <h4 class="text-lg font-semibold">No Documents Yet</h4>
                <p class="text-sm text-base-content/60">Upload your first document to get started.</p>
            </div>
        </div>
    </section>
</template>

<script setup>
const props = defineProps({
    documents: {
        type: Array,
        default: () => [],
    },
    formatDate: {
        type: Function,
        required: true,
    },
    getFileName: {
        type: Function,
        required: true,
    },
});

const emit = defineEmits(['sign', 'verify', 'download']);
</script>
