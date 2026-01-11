<template>
    <div class="pointer-events-none fixed right-4 top-4 z-50 flex w-full max-w-sm flex-col gap-2">
        <transition-group name="toast" tag="div" class="flex flex-col gap-2">
            <div v-for="toast in toasts" :key="toast.id" class="pointer-events-auto">
                <div :class="['alert shadow-lg', toastClass(toast.type)]">
                    <div class="flex w-full items-start justify-between gap-3">
                        <p class="text-sm whitespace-pre-line">{{ toast.message }}</p>
                        <button
                            type="button"
                            class="btn btn-ghost btn-xs"
                            aria-label="Dismiss"
                            @click="remove(toast.id)"
                        >
                            x
                        </button>
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

const toastClass = (type) => {
    if (type === 'success') return 'alert-success';
    if (type === 'error') return 'alert-error';
    if (type === 'warning') return 'alert-warning';
    return 'alert-info';
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
    transform: translateY(-6px);
}
</style>
