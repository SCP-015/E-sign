<template>
    <section v-if="status === 'unverified'" class="card border border-warning/30 bg-warning/10 shadow-sm">
        <div class="card-body gap-4">
            <div class="flex items-start gap-3">
                <div class="rounded-full bg-warning/20 p-2 text-warning">
                    <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M12 9v4"></path>
                        <path d="M12 17h.01"></path>
                        <path d="M10.3 3.2l-7 12.1a2 2 0 0 0 1.7 3h14a2 2 0 0 0 1.7-3L13.7 3.2a2 2 0 0 0-3.4 0z"></path>
                    </svg>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-warning">Identity Not Verified</h3>
                    <p class="text-sm text-warning/80">Complete KYC in the mobile app to unlock signing capabilities.</p>
                </div>
            </div>
            <div class="rounded-2xl border border-warning/20 bg-base-100/70 p-4">
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-warning/80">Required steps</p>
                <ol class="mt-3 space-y-2 text-sm text-base-content/80">
                    <li v-for="(step, index) in steps" :key="step" class="flex items-center gap-2">
                        <span class="flex h-5 w-5 items-center justify-center rounded-full bg-warning/20 text-xs font-bold">
                            {{ index + 1 }}
                        </span>
                        {{ step }}
                    </li>
                </ol>
            </div>
            <div class="flex flex-col gap-3 sm:flex-row">
                <button type="button" class="btn btn-warning btn-sm flex-1">Download App</button>
                <button type="button" class="btn btn-outline btn-warning btn-sm flex-1">Learn More</button>
            </div>
        </div>
    </section>

    <section v-else class="card border border-success/30 bg-success/10 shadow-sm">
        <div class="card-body gap-3">
            <div class="flex items-start gap-3">
                <div class="rounded-full bg-success/20 p-2 text-success">
                    <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M12 3l7 4v5c0 5-3 9-7 9s-7-4-7-9V7l7-4z"></path>
                        <path d="M9 12l2 2 4-4"></path>
                    </svg>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-success">Identity Verified</h3>
                    <p class="text-sm text-success/80">Your certificate is active and ready to sign documents.</p>
                </div>
            </div>
        </div>
    </section>
</template>

<script setup>
const props = defineProps({
    status: {
        type: String,
        default: 'unverified',
    },
});

const steps = [
    'Download our mobile app',
    'Scan your ID document',
    'Complete face verification',
];
</script>
