<template>
  <Head title="Redirecting" />
  <div class="flex min-h-screen items-center justify-center">
    <div class="flex flex-col items-center gap-2 text-center">
      <span class="loading loading-spinner loading-md text-primary"></span>
      <p class="text-sm text-base-content/70">Redirecting...</p>
    </div>
  </div>
</template>

<script setup>
import { onMounted } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { useAuthStore } from '../stores/auth';

defineOptions({
  layout: null,
});

const authStore = useAuthStore();

onMounted(() => {
  const query = window.location.search;
  if (query) {
    router.visit(`/login${query}`);
    return;
  }

  const target = authStore.isAuthenticated ? '/dashboard' : '/login';
  router.visit(target);
});
</script>
