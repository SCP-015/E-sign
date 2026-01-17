<template>
    <div class="p-6">
            <div class="mb-6 flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold">Member Management</h1>
                    <p class="text-base-content/60">Manage users and roles for {{ organization?.name || 'your organization' }}</p>
                </div>
                <button @click="showInviteModal = true" class="btn btn-primary gap-2">
                    <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                        <circle cx="8.5" cy="7" r="4"></circle>
                        <line x1="20" y1="8" x2="20" y2="14"></line>
                        <line x1="17" y1="11" x2="23" y2="11"></line>
                    </svg>
                    Invite Member
                </button>
            </div>

            <!-- Loading State -->
            <div v-if="loading" class="flex justify-center py-20">
                <span class="loading loading-spinner loading-lg text-primary"></span>
            </div>

            <!-- Error State -->
            <div v-else-if="error" class="alert alert-error mb-6">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span>{{ error }}</span>
            </div>

            <!-- Members Table -->
            <div v-else class="card bg-base-100 shadow-xl overflow-visible">
                <div class="overflow-x-auto overflow-visible">
                    <table class="table w-full">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Role</th>
                                <th>Joined At</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="member in members" :key="member.id">
                                <td>
                                    <div class="flex items-center gap-3">
                                        <div class="avatar placeholder">
                                            <div class="h-10 w-10 rounded-full bg-neutral text-neutral-content">
                                                <span>{{ member.name?.charAt(0) || 'U' }}</span>
                                            </div>
                                        </div>
                                        <div>
                                            <div class="font-bold">{{ member.name }}</div>
                                            <div class="text-sm opacity-50">{{ member.email }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="badge badge-ghost">{{ formatRole(member.role) }}</div>
                                    <div v-if="member.is_owner" class="badge badge-primary ml-2">Owner</div>
                                </td>
                                <td>{{ formatDate(member.joined_at) }}</td>
                                <td class="text-right">
                                    <template v-if="!member.is_owner && canManage">
                                        <div class="dropdown dropdown-end dropdown-left sm:dropdown-bottom">
                                            <label tabindex="0" class="btn btn-ghost btn-sm">Edit</label>
                                            <ul tabindex="0" class="dropdown-content menu mt-3 w-52 rounded-box bg-base-100 p-2 shadow-xl border border-base-200 z-[100]">
                                                <li class="menu-title"><span>Change Role</span></li>
                                                <li><a @click="updateRole(member, 'admin')" :class="member.role === 'admin' ? 'active' : ''">Administrator</a></li>
                                                <li><a @click="updateRole(member, 'manager')" :class="member.role === 'manager' ? 'active' : ''">Manager</a></li>
                                                <li><a @click="updateRole(member, 'user')" :class="member.role === 'user' ? 'active' : ''">User</a></li>
                                                <div class="divider my-1"></div>
                                                <li><a @click="confirmRemove(member)" class="text-error">Remove Member</a></li>
                                            </ul>
                                        </div>
                                    </template>
                                    <span v-else class="text-xs text-base-content/40 italic">{{ member.is_owner ? 'System Owner' : '-' }}</span>
                                </td>
                            </tr>
                            <tr v-if="members.length === 0">
                                <td colspan="4" class="text-center py-8 text-base-content/60">
                                    No members found
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Invite Modal -->
            <input type="checkbox" id="invite-modal" class="modal-toggle" v-model="showInviteModal" />
            <div class="modal" :class="{ 'modal-open': showInviteModal }">
                <div class="modal-box bg-base-100 border border-base-200">
                    <h3 class="text-lg font-bold">Invite New Member</h3>
                    <p class="py-4 text-sm text-base-content/60">Generate an invite code to share with your team. They can use this code to join your organization.</p>
                    
                    <div class="form-control w-full">
                        <label class="label">
                            <span class="label-text font-semibold">Assign Initial Role</span>
                        </label>
                        <select v-model="inviteForm.role" class="select select-bordered w-full">
                            <option value="admin">Administrator</option>
                            <option value="manager">Manager</option>
                            <option value="user">User</option>
                        </select>
                    </div>

                    <div class="form-control w-full mt-4">
                        <label class="label">
                            <span class="label-text font-semibold">Expiry (days)</span>
                        </label>
                        <input v-model.number="inviteForm.expiry_days" type="number" min="1" max="30" class="input input-bordered w-full" />
                    </div>

                    <div v-if="generatedCode" class="mt-6 rounded-xl bg-primary/5 p-6 border border-primary/10">
                        <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-primary/70">Invite Code</p>
                        <div class="flex items-center justify-between">
                            <span class="text-3xl font-mono font-bold tracking-widest text-primary">{{ generatedCode }}</span>
                            <button @click="copyCode" class="btn btn-primary btn-sm">Copy</button>
                        </div>
                        <p class="mt-4 text-xs text-base-content/60">Share this code with the person you want to invite.</p>
                    </div>

                    <div class="modal-action mt-8">
                        <button @click="closeInviteModal" class="btn btn-ghost">Close</button>
                        <button @click="generateInvite" class="btn btn-primary" :disabled="generatingInvite">
                            <span v-if="generatingInvite" class="loading loading-spinner"></span>
                            {{ generatedCode ? 'Regenerate Code' : 'Generate Invite Code' }}
                        </button>
                    </div>
                </div>
                <div class="modal-backdrop" @click="closeInviteModal"></div>
            </div>

            <!-- Remove Confirmation Modal -->
            <div v-if="memberToRemove" class="modal modal-open">
                <div class="modal-box bg-base-100 border border-error/20">
                    <h3 class="text-lg font-bold text-error">Remove Member?</h3>
                    <p class="py-4">Are you sure you want to remove <strong>{{ memberToRemove.name }}</strong> from the organization? They will lose all access immediately.</p>
                    <div class="modal-action">
                        <button @click="memberToRemove = null" class="btn btn-ghost">Cancel</button>
                        <button @click="removeMember" class="btn btn-error" :disabled="removingMember">
                            <span v-if="removingMember" class="loading loading-spinner"></span>
                            Confirm Removal
                        </button>
                    </div>
                </div>
                <div class="modal-backdrop" @click="memberToRemove = null"></div>
            </div>
        </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';
import { useToastStore } from '../../stores/toast';

const toastStore = useToastStore();

const loading = ref(true);
const error = ref('');
const organization = ref(null);
const members = ref([]);

// Invite state
const showInviteModal = ref(false);
const generatingInvite = ref(false);
const generatedCode = ref('');
const inviteForm = ref({ role: 'user', expiry_days: 7 });

// Remove state
const memberToRemove = ref(null);
const removingMember = ref(false);

const canManage = computed(() => {
    // Check if current user is admin or owner
    return true; // Simplified - you can add proper logic here
});

const formatRole = (role) => {
    const labels = {
        'admin': 'Administrator',
        'manager': 'Manager',
        'user': 'User',
    };
    return labels[role] || role;
};

const formatDate = (dateString) => {
    if (!dateString) return '-';
    return new Date(dateString).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
};

const fetchCurrentOrganization = async () => {
    const response = await axios.get('/api/organizations/current');
    if (response.data.success && response.data.data) {
        organization.value = response.data.data;
        return organization.value.id;
    }
    throw new Error('You are not in any organization');
};

const fetchMembers = async (orgId) => {
    const response = await axios.get(`/api/organizations/${orgId}/members`);
    if (response.data.success) {
        members.value = response.data.data;
    }
};

const generateInvite = async () => {
    generatingInvite.value = true;
    try {
        const response = await axios.post(`/api/organizations/${organization.value.id}/invitations`, {
            role: inviteForm.value.role,
            expiry_days: inviteForm.value.expiry_days,
        });
        if (response.data.success) {
            generatedCode.value = response.data.data.code;
            toastStore.success('Invite code generated!');
        }
    } catch (err) {
        toastStore.error(err.response?.data?.message || 'Failed to generate invite');
    } finally {
        generatingInvite.value = false;
    }
};

const closeInviteModal = () => {
    showInviteModal.value = false;
    generatedCode.value = '';
};

const copyCode = () => {
    navigator.clipboard.writeText(generatedCode.value);
    toastStore.success('Code copied to clipboard!');
};

const updateRole = async (member, role) => {
    try {
        await axios.put(`/api/organizations/${organization.value.id}/members/${member.id}`, { role });
        member.role = role;
        toastStore.success('Role updated successfully');
    } catch (err) {
        toastStore.error(err.response?.data?.message || 'Failed to update role');
    }
};

const confirmRemove = (member) => {
    memberToRemove.value = member;
};

const removeMember = async () => {
    if (!memberToRemove.value) return;
    removingMember.value = true;
    try {
        await axios.delete(`/api/organizations/${organization.value.id}/members/${memberToRemove.value.id}`);
        members.value = members.value.filter(m => m.id !== memberToRemove.value.id);
        memberToRemove.value = null;
        toastStore.success('Member removed successfully');
    } catch (err) {
        toastStore.error(err.response?.data?.message || 'Failed to remove member');
    } finally {
        removingMember.value = false;
    }
};

onMounted(async () => {
    try {
        const orgId = await fetchCurrentOrganization();
        await fetchMembers(orgId);
    } catch (e) {
        error.value = e.response?.data?.message || e.message || 'Failed to load data';
    } finally {
        loading.value = false;
    }
});
</script>
