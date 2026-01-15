<template>
    <Teleport to="body">
        <transition name="verify">
            <div
                v-if="isOpen"
                class="fixed inset-0 z-50 flex items-center justify-center bg-base-100/60 px-4 backdrop-blur-sm"
                @click.self="close"
            >
                <div class="flex max-h-[90vh] w-full max-w-lg flex-col overflow-hidden rounded-3xl border border-base-200 bg-base-100 shadow-2xl">
                    <div class="flex items-start gap-3 border-b border-base-200 px-6 py-5">
                        <div :class="['flex h-11 w-11 items-center justify-center rounded-2xl', iconBadgeClass]">
                            <svg v-if="tone === 'success'" viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M20 6L9 17l-5-5"></path>
                            </svg>
                            <svg v-else-if="tone === 'error'" viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                            <svg v-else-if="tone === 'warning'" viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M12 9v4"></path>
                                <path d="M12 17h.01"></path>
                                <path d="M10.3 4.6l-7 12.1a1 1 0 0 0 .9 1.5h15.6a1 1 0 0 0 .9-1.5l-7-12.1a1 1 0 0 0-1.7 0z"></path>
                            </svg>
                            <svg v-else viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M12 8h.01"></path>
                                <path d="M12 12v4"></path>
                                <path d="M12 2a10 10 0 1 0 0 20a10 10 0 0 0 0-20z"></path>
                            </svg>
                        </div>
                        <div class="flex-1 min-w-0">
                            <h3 class="text-lg font-semibold">{{ result?.title || 'Verification Result' }}</h3>
                            <p class="wrap-break-word text-sm text-base-content/70">{{ result?.summary || 'No details available.' }}</p>
                        </div>
                        <button type="button" class="btn btn-ghost btn-xs" @click="close" aria-label="Close">
                            <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>

                    <div class="flex-1 min-h-0 space-y-4 overflow-y-auto px-6 py-5">
                        <div v-if="result?.statusLabel" class="flex items-center gap-2">
                            <span :class="['badge badge-sm uppercase', badgeClass]">{{ result.statusLabel }}</span>
                        </div>

                        <div v-if="result?.owner" class="rounded-2xl border border-base-200 bg-base-100 p-4">
                            <h4 class="text-xs font-semibold uppercase tracking-[0.2em] text-base-content/50">Document Owner</h4>
                            <div class="mt-3 flex items-center gap-3">
                                <div class="h-10 w-10 overflow-hidden rounded-full bg-base-200">
                                    <img
                                        v-if="result.owner.avatar"
                                        :src="result.owner.avatar"
                                        :alt="result.owner.name || 'Owner'"
                                        class="h-full w-full object-cover"
                                    >
                                </div>
                                <div class="min-w-0">
                                    <p class="wrap-break-word font-semibold">{{ result.owner.name || '-' }}</p>
                                    <p class="wrap-break-word text-xs text-base-content/60">{{ result.owner.email || '-' }}</p>
                                </div>
                            </div>
                        </div>

                        <div v-if="result?.fields?.length" class="grid gap-3 text-sm">
                            <div v-for="field in result.fields" :key="field.label" class="flex flex-col gap-1 sm:flex-row sm:items-start sm:justify-between sm:gap-4">
                                <span class="text-base-content/60">{{ field.label }}</span>
                                <span class="wrap-break-word font-medium text-base-content sm:text-right">{{ field.value || '-' }}</span>
                            </div>
                        </div>

                        <div v-if="result?.signers?.length" class="space-y-2">
                            <h4 class="text-xs font-semibold uppercase tracking-[0.2em] text-base-content/50">Signers</h4>
                            <div class="space-y-2">
                                <div v-for="signer in result.signers" :key="signer.name + signer.status" class="rounded-xl border border-base-200 bg-base-100 p-3 text-sm">
                                    <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                                        <div class="min-w-0">
                                            <span class="wrap-break-word font-semibold block">{{ signer.name || 'Signer' }}</span>
                                            <span class="wrap-break-word text-xs text-base-content/60">{{ signer.email || '-' }}</span>
                                        </div>
                                        <span class="badge badge-outline badge-xs uppercase">{{ signer.status || 'unknown' }}</span>
                                    </div>
                                    <p class="mt-1 text-xs text-base-content/60">Signed at: {{ signer.signedAt || signer.signed_at || '-' }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex gap-3 border-t border-base-200 px-6 py-4">
                        <button type="button" class="btn btn-primary w-full" @click="close">
                            Close
                        </button>
                    </div>
                </div>
            </div>
        </transition>
    </Teleport>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    isOpen: {
        type: Boolean,
        default: false,
    },
    result: {
        type: Object,
        default: null,
    },
});

const emit = defineEmits(['close']);

const tone = computed(() => props.result?.tone || 'info');

const iconBadgeClass = computed(() => {
    if (tone.value === 'success') return 'bg-success/15 text-success';
    if (tone.value === 'error') return 'bg-error/15 text-error';
    if (tone.value === 'warning') return 'bg-warning/20 text-warning';
    return 'bg-info/15 text-info';
});

const badgeClass = computed(() => {
    if (tone.value === 'success') return 'badge-success';
    if (tone.value === 'error') return 'badge-error';
    if (tone.value === 'warning') return 'badge-warning';
    return 'badge-info';
});

const close = () => emit('close');
</script>

<style scoped>
.verify-enter-active,
.verify-leave-active {
    transition: all 0.2s ease;
}

.verify-enter-from,
.verify-leave-to {
    opacity: 0;
    transform: translateY(10px);
}
</style>
