<template>
    <section class="grid grid-cols-1 gap-3 sm:grid-cols-3">
        <div v-for="stat in stats" :key="stat.label" class="card border border-base-200 bg-base-100 shadow-sm">
            <div class="card-body items-center text-center gap-1 py-4">
                <p class="text-xs text-base-content/50">{{ stat.label }}</p>
                <p class="text-2xl font-bold" :class="stat.valueClass">{{ stat.value }}</p>
            </div>
        </div>
    </section>
</template>

<script setup>
const props = defineProps({
    stats: {
        type: Array,
        default: () => [],
    },
});
</script>
