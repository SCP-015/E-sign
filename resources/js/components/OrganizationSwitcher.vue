<template>
    <div class="dropdown dropdown-end">
        <label tabindex="0" class="btn btn-ghost gap-2 normal-case">
            <div class="flex items-center gap-2">
                <div v-if="currentOrganization" class="flex items-center gap-2">
                    <div class="flex h-7 w-7 items-center justify-center rounded-lg bg-primary/10 text-xs font-semibold text-primary">
                        {{ currentOrganization.name.charAt(0).toUpperCase() }}
                    </div>
                    <div class="flex flex-col items-start">
                        <span class="text-sm font-medium">{{ currentOrganization.name }}</span>
                        <span class="text-xs text-base-content/60">{{ roleLabel }}</span>
                    </div>
                </div>
                <div v-else class="flex items-center gap-2">
                    <div class="flex h-7 w-7 items-center justify-center rounded-lg bg-base-200 text-xs">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                    </div>
                    <span class="text-sm font-medium">Personal Mode</span>
                </div>
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
            </div>
        </label>

        <ul tabindex="0" class="menu dropdown-content z-50 mt-3 w-72 rounded-box border border-base-200 bg-base-100 p-2 shadow-lg">
            <!-- Personal Mode Option -->
            <li>
                <button
                    type="button"
                    class="flex items-center gap-3"
                    :class="{ 'bg-base-200': !currentOrganization }"
                    @click="switchToPersonal"
                >
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-base-200">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                    </div>
                    <div class="flex flex-col items-start">
                        <span class="text-sm font-medium">Personal Mode</span>
                        <span class="text-xs text-base-content/60">Mode individu</span>
                    </div>
                    <span v-if="!currentOrganization" class="ml-auto text-primary">
                        <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                        </svg>
                    </span>
                </button>
            </li>

            <li v-if="organizations.length > 0" class="menu-title mt-2">
                <span class="text-xs font-medium text-base-content/50">Organizations ({{ organizations.length }})</span>
            </li>

            <!-- Organization List -->
            <li v-for="org in organizations" :key="org.id">
                <button
                    type="button"
                    class="flex items-center gap-3"
                    :class="{ 'bg-primary/10': currentOrganization?.id === org.id }"
                    @click="switchOrganization(org)"
                >
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-primary/10 font-semibold text-primary">
                        {{ org.name.charAt(0).toUpperCase() }}
                    </div>
                    <div class="flex flex-col items-start">
                        <span class="text-sm font-medium">{{ org.name }}</span>
                        <span class="text-xs text-base-content/60">{{ getRoleLabel(org.role) }}</span>
                    </div>
                    <span v-if="currentOrganization?.id === org.id" class="ml-auto text-primary">
                        <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                        </svg>
                    </span>
                </button>
            </li>

            <div class="divider my-1"></div>

            <!-- Setup Organization (Create or Join) -->
            <li>
                <button type="button" class="flex items-center gap-3 text-primary" @click="handleSetupOrganization">
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg border-2 border-dashed border-primary/50">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                    </div>
                    <span class="text-sm font-medium">Setup Organization</span>
                </button>
            </li>
        </ul>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import { useToastStore } from '../stores/toast';

const emit = defineEmits(['organization-changed']);

const organizations = ref([]);
const currentOrganization = ref(null);
const canCreate = ref(true);
const isSwitching = ref(false);
const toastStore = useToastStore();

const roleLabel = computed(() => {
    if (!currentOrganization.value) return '';
    return getRoleLabel(currentOrganization.value.role);
});

function getRoleLabel(role) {
    const labels = {
        admin: 'Admin',
        manager: 'Manager',
        user: 'Member',
    };
    return labels[role] || role;
}

function closeDropdown() {
    try {
        const el = document.activeElement;
        if (el && typeof el.blur === 'function') {
            el.blur();
        }
        document.body.click();
    } catch (e) {
        // noop
    }
}

async function fetchOrganizations() {
    try {
        const response = await axios.get('/api/organizations');
        if (response.data.success) {
            organizations.value = response.data.data;
            canCreate.value = response.data.can_create;
        }
    } catch (error) {
        console.error('Failed to fetch organizations:', error);
    }
}

async function fetchCurrentOrganization() {
    try {
        const response = await axios.get('/api/organizations/current');
        if (response.data.success && response.data.data) {
            currentOrganization.value = response.data.data;
        } else {
            currentOrganization.value = null;
        }
    } catch (error) {
        console.error('Failed to fetch current organization:', error);
    }
}

async function switchOrganization(org) {
    if (isSwitching.value) return;
    isSwitching.value = true;
    closeDropdown();
    try {
        const response = await axios.post('/api/organizations/switch', {
            organization_id: org.id,
        });
        if (!response.data?.success) {
            throw new Error(response.data?.message || 'Gagal beralih organization');
        }
        await fetchCurrentOrganization();
        window.dispatchEvent(new Event('organizations-updated'));
        toastStore.success('Berhasil beralih ke ' + org.name);
        emit('organization-changed', currentOrganization.value);
        router.reload({ preserveScroll: true });
    } catch (error) {
        const message = error.response?.data?.message || error.message || 'Gagal beralih organization';
        toastStore.error(message);
    } finally {
        isSwitching.value = false;
    }
}

async function switchToPersonal() {
    if (isSwitching.value) return;
    isSwitching.value = true;
    closeDropdown();
    try {
        const response = await axios.post('/api/organizations/switch', {
            organization_id: null,
        });
        if (!response.data?.success) {
            throw new Error(response.data?.message || 'Gagal beralih ke mode personal');
        }
        await fetchCurrentOrganization();
        window.dispatchEvent(new Event('organizations-updated'));
        toastStore.success('Berhasil beralih ke mode personal');
        emit('organization-changed', currentOrganization.value);
        router.reload({ preserveScroll: true });
    } catch (error) {
        const message = error.response?.data?.message || error.message || 'Gagal beralih ke mode personal';
        toastStore.error(message);
    } finally {
        isSwitching.value = false;
    }
}

function handleSetupOrganization() {
    closeDropdown();
    router.visit('/organization/setup');
}

onMounted(() => {
    fetchOrganizations();
    fetchCurrentOrganization();

    window.addEventListener('organizations-updated', fetchOrganizations);
    window.addEventListener('organizations-updated', fetchCurrentOrganization);
});

onUnmounted(() => {
    window.removeEventListener('organizations-updated', fetchOrganizations);
    window.removeEventListener('organizations-updated', fetchCurrentOrganization);
});
</script>
