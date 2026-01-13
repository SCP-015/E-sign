<template>
    <div
        class="pointer-events-none fixed left-4 right-4 top-4 z-[2000] flex w-auto flex-col gap-3 sm:left-auto sm:right-4 sm:w-96"
        role="status"
        aria-live="polite"
    >
        <transition-group name="toast" tag="div" class="flex flex-col gap-3">
            <div v-for="toast in toasts" :key="toast.id" class="pointer-events-auto">
                <div
                    :class="[
                        'relative overflow-hidden rounded-2xl border bg-base-100/95 shadow-xl backdrop-blur',
                        accentClass(toast.type),
                    ]"
                >
                    <div class="flex items-start gap-3 p-4">
                        <div :class="['mt-0.5 flex h-9 w-9 items-center justify-center rounded-xl', iconBadgeClass(toast.type)]">
                            <svg v-if="toast.type === 'success'" viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M20 6L9 17l-5-5"></path>
                            </svg>
                            <svg v-else-if="toast.type === 'error'" viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                            <svg v-else-if="toast.type === 'warning'" viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2">
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

                        <div class="flex-1 space-y-1">
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-base-content/50">
                                {{ typeLabel(toast.type) }}
                            </p>
                            <p class="text-sm text-base-content/80 whitespace-pre-line">{{ toast.message }}</p>
                        </div>

                        <button
                            type="button"
                            class="btn btn-ghost btn-xs h-8 w-8 rounded-full"
                            aria-label="Dismiss"
                            @click.stop="remove(toast.id)"
                        >
                            <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>

                    <div v-if="toast.duration > 0" class="absolute bottom-0 left-0 h-0.5 w-full bg-base-200/60">
                        <div
                            class="toast-progress h-full"
                            :class="progressClass(toast.type)"
                            :style="progressStyle(toast)"
                        ></div>
                    </div>
                </div>
            </div>
        </transition-group>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { useToastStore } from '../stores/toast';

const toastStore = useToastStore();
const toasts = computed(() => toastStore.toasts);

const typeLabel = (type) => {
    if (type === 'success') return 'Success';
    if (type === 'error') return 'Error';
    if (type === 'warning') return 'Warning';
    return 'Info';
};

const accentClass = (type) => {
    if (type === 'success') return 'border-success/30 border-l-4';
    if (type === 'error') return 'border-error/30 border-l-4';
    if (type === 'warning') return 'border-warning/40 border-l-4';
    return 'border-info/30 border-l-4';
};

const iconBadgeClass = (type) => {
    if (type === 'success') return 'bg-success/15 text-success';
    if (type === 'error') return 'bg-error/15 text-error';
    if (type === 'warning') return 'bg-warning/20 text-warning';
    return 'bg-info/15 text-info';
};

const progressClass = (type) => {
    if (type === 'success') return 'bg-success/80';
    if (type === 'error') return 'bg-error/80';
    if (type === 'warning') return 'bg-warning/80';
    return 'bg-info/80';
};

const progressStyle = (toast) => {
    return {
        '--toast-duration': `${toast.duration}ms`,
    };
};

const remove = (id) => {
    toastStore.remove(id);
};
</script>

<style scoped>
.toast-enter-active,
.toast-leave-active {
    transition: all 0.2s ease;
}

.toast-enter-from,
.toast-leave-to {
    opacity: 0;
    transform: translateX(12px);
}

.toast-progress {
    transform-origin: left;
    animation: toast-progress var(--toast-duration) linear forwards;
}

@keyframes toast-progress {
    from {
        transform: scaleX(1);
    }
    to {
        transform: scaleX(0);
    }
}
</style>
