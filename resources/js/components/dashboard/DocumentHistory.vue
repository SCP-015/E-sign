<template>
    <section class="space-y-3">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <h3 class="text-lg font-semibold">Document History</h3>
            <div class="flex flex-wrap items-center gap-2">
                <span class="badge badge-outline text-xs">{{ totalCount ?? documents.length }} documents</span>
                <Link v-if="showAllHref" :href="showAllHref" class="btn btn-ghost btn-xs">
                    {{ showAllLabel }}
                </Link>
            </div>
        </div>

        <div
            v-if="actionsDisabled"
            class="rounded-xl border border-warning/20 bg-warning/10 px-4 py-3 text-xs text-base-content/70"
        >
            {{ disabledHint }}
        </div>

        <div v-if="documents.length > 0" class="space-y-3">
            <div
                v-for="doc in documents"
                :key="doc.id"
                class="card border border-base-200 bg-base-100 shadow-sm"
            >
                <div class="card-body gap-3">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0">
                            <p class="text-xs text-base-content/40">
                                {{ formatDate(doc.created_at || doc.createdAt) }}
                            </p>
                            <h4 class="wrap-break-word font-semibold">{{ getFileName(doc) }}</h4>
                        </div>
                        <span
                            :class="[
                                'badge badge-sm uppercase',
                                doc.status === 'pending' || doc.status === 'IN_PROGRESS'
                                    ? 'badge-warning'
                                    : doc.status === 'signed' || doc.status === 'COMPLETED'
                                        ? 'badge-success'
                                        : 'badge-ghost',
                            ]"
                        >
                            {{ doc.status }}
                        </span>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-if="shouldShowSign(doc)"
                            @click="emit('sign', doc.id, doc.page_count ?? doc.pageCount)"
                            class="btn btn-primary btn-sm w-full sm:w-auto"
                            :class="actionsDisabled ? 'btn-disabled' : ''"
                            :disabled="actionsDisabled"
                        >
                            Sign Now
                        </button>
                        <button
                            v-if="shouldShowFinalize(doc)"
                            @click="emit('finalize', doc.id)"
                            class="btn btn-primary btn-sm w-full sm:w-auto"
                            :class="actionsDisabled ? 'btn-disabled' : ''"
                            :disabled="actionsDisabled"
                        >
                            Finalize
                        </button>
                        <button
                            v-if="doc.status === 'signed' || doc.status === 'COMPLETED'"
                            @click="emit('verify', doc.id)"
                            class="btn btn-outline btn-sm w-full sm:w-auto"
                            :class="actionsDisabled ? 'btn-disabled' : ''"
                            :disabled="actionsDisabled"
                        >
                            Verify Signature
                        </button>
                        <button
                            v-if="doc.status === 'signed' || doc.status === 'COMPLETED'"
                            @click="emit('download', doc.id)"
                            class="btn btn-ghost btn-sm w-full sm:w-auto"
                            :class="actionsDisabled ? 'btn-disabled' : ''"
                            :disabled="actionsDisabled"
                        >
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
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    documents: {
        type: Array,
        default: () => [],
    },
    totalCount: {
        type: Number,
        default: null,
    },
    showAllHref: {
        type: String,
        default: '',
    },
    showAllLabel: {
        type: String,
        default: 'View all',
    },
    actionsDisabled: {
        type: Boolean,
        default: false,
    },
    disabledHint: {
        type: String,
        default: 'Complete KYC first to access documents.',
    },
    formatDate: {
        type: Function,
        required: true,
    },
    getFileName: {
        type: Function,
        required: true,
    },
    canSign: {
        type: Function,
        default: null,
    },
    canFinalize: {
        type: Function,
        default: null,
    },
});

const emit = defineEmits(['sign', 'verify', 'download', 'finalize']);

const shouldShowSign = (doc) => {
    if (props.actionsDisabled) {
        return doc.status === 'pending' || doc.status === 'IN_PROGRESS';
    }
    if (props.canSign) {
        return props.canSign(doc);
    }
    return doc.status === 'pending' || doc.status === 'IN_PROGRESS';
};

const shouldShowFinalize = (doc) => {
    if (props.canFinalize) {
        return props.canFinalize(doc);
    }
    return false;
};
</script>
