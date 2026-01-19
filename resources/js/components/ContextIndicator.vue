<template>
  <div class="flex items-center gap-2 px-3 py-1.5 rounded-lg" :class="indicatorClass">
    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
      <path v-if="isPersonal" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z"/>
      <path v-else d="M13 6a3 3 0 11-6 0 3 3 0 016 0zM18 8a2 2 0 11-4 0 2 2 0 014 0zM14 15a4 4 0 00-8 0v3h8v-3zM6 8a2 2 0 11-4 0 2 2 0 014 0zM16 18v-3a5.972 5.972 0 00-.75-2.906A3.005 3.005 0 0119 15v3h-3zM4.75 12.094A5.973 5.973 0 004 15v3H1v-3a3 3 0 013.75-2.906z"/>
    </svg>
    <span class="text-[13px] font-medium">{{ modeLabel }}</span>
  </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  tenantId: {
    type: String,
    default: null,
  },
  tenantName: {
    type: String,
    default: null,
  },
});

const isPersonal = computed(() => !props.tenantId);

const modeLabel = computed(() => {
  return isPersonal.value ? 'Personal Mode' : props.tenantName || 'Organization Mode';
});

const indicatorClass = computed(() => {
  return isPersonal.value
    ? 'bg-blue-50 text-blue-700 border border-blue-200'
    : 'bg-green-50 text-green-700 border border-green-200';
});
</script>
