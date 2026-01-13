<template>
    <section>
        <h3 class="text-lg font-semibold">Certificate Status</h3>
        <div class="card mt-3 border border-base-200 bg-base-100 shadow-sm">
            <div class="card-body gap-0 p-0">
                <div class="flex flex-col gap-2 border-b border-base-200 px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:gap-0">
                    <div class="min-w-0">
                        <p class="text-sm font-semibold">Digital ID</p>
                        <p class="text-xs text-base-content/60">{{ idStatusText }}</p>
                    </div>
                    <span :class="['badge', badgeClass]">{{ badgeText }}</span>
                </div>
                <div class="flex flex-col gap-2 px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:gap-0">
                    <div class="min-w-0">
                        <p class="text-sm font-semibold">Certificate Expiry</p>
                        <p class="text-xs text-base-content/60">{{ expiryText }}</p>
                    </div>
                    <svg viewBox="0 0 24 24" class="h-4 w-4 text-base-content/40" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M8 7V3"></path>
                        <path d="M16 7V3"></path>
                        <rect x="3" y="7" width="18" height="14" rx="2"></rect>
                    </svg>
                </div>
            </div>
        </div>
    </section>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    status: {
        type: String,
        default: 'unverified',
    },
    expiry: {
        type: String,
        default: '-',
    },
});

const isVerified = computed(() => props.status === 'verified');
const idStatusText = computed(() => (isVerified.value ? 'Verified' : 'Verification Pending'));
const badgeText = computed(() => (isVerified.value ? 'Active' : 'Needs Action'));
const badgeClass = computed(() => (isVerified.value ? 'badge-success' : 'badge-warning'));
const expiryText = computed(() => (props.expiry === '-' ? 'Not available yet' : props.expiry));
</script>
