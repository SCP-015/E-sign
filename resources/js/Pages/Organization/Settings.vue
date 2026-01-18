<template>
    <Head title="Portal Settings" />
    <div class="min-h-screen bg-base-100">
        <main class="mx-auto w-full max-w-4xl space-y-6 px-4 py-6">
            <!-- Back Button -->
            <a href="/dashboard" class="btn btn-ghost btn-sm gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
                Back to Dashboard
            </a>

            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold">Portal Settings</h1>
                    <p class="text-base-content/60">Customize your organization's profile and branding</p>
                </div>
            </div>

            <!-- Loading State -->
            <div v-if="loading" class="flex items-center justify-center py-20">
                <span class="loading loading-spinner loading-lg text-primary"></span>
            </div>

            <!-- No Permission -->
            <div v-else-if="!settings && !loading" class="alert alert-warning">
                <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
                <span>Please select an organization first to view settings.</span>
            </div>

            <!-- Settings Form -->
            <template v-else>
                <!-- Logo & Banner Section -->
                <div class="card bg-base-100 border border-base-200 shadow-sm">
                    <div class="card-body">
                        <h2 class="card-title text-lg">Branding</h2>
                        
                        <!-- Banner Preview -->
                        <div class="relative h-32 w-full rounded-lg bg-gradient-to-r from-primary/20 to-secondary/20 overflow-hidden">
                            <img 
                                v-if="settings.banner" 
                                :src="getStorageUrl(settings.banner)" 
                                class="w-full h-full object-cover"
                                alt="Banner"
                            />
                            <div v-else class="flex items-center justify-center h-full text-base-content/40">
                                <span>No banner uploaded</span>
                            </div>
                            <label v-if="canEdit" class="absolute bottom-2 right-2 btn btn-sm btn-primary gap-1">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                                Change Banner
                                <input type="file" class="hidden" accept="image/*" @change="uploadBanner" :disabled="uploading" />
                            </label>
                        </div>

                        <!-- Logo -->
                        <div class="flex items-center gap-4 mt-4">
                            <div class="avatar">
                                <div class="w-20 rounded-xl ring ring-base-200">
                                    <img 
                                        :src="settings.logo ? getStorageUrl(settings.logo) : `https://ui-avatars.com/api/?name=${encodeURIComponent(settings.name || 'O')}&background=6366f1&color=fff&size=80`" 
                                        alt="Logo"
                                    />
                                </div>
                            </div>
                            <div>
                                <p class="font-medium">Organization Logo</p>
                                <p class="text-sm text-base-content/60">Recommended: 200x200px, max 2MB</p>
                                <label v-if="canEdit" class="btn btn-sm btn-outline mt-2">
                                    Upload Logo
                                    <input type="file" class="hidden" accept="image/*" @change="uploadLogo" :disabled="uploading" />
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Basic Info -->
                <div class="card bg-base-100 border border-base-200 shadow-sm">
                    <div class="card-body">
                        <h2 class="card-title text-lg">Basic Information</h2>
                        
                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="form-control">
                                <label class="label"><span class="label-text">Organization Name</span></label>
                                <input 
                                    type="text" 
                                    v-model="form.name" 
                                    class="input input-bordered" 
                                    :disabled="!canEdit"
                                />
                            </div>
                            <div class="form-control">
                                <label class="label"><span class="label-text">Website</span></label>
                                <input 
                                    type="url" 
                                    v-model="form.website" 
                                    class="input input-bordered" 
                                    placeholder="https://example.com"
                                    :disabled="!canEdit"
                                />
                            </div>
                            <div class="form-control">
                                <label class="label"><span class="label-text">Phone</span></label>
                                <input 
                                    type="tel" 
                                    v-model="form.phone" 
                                    class="input input-bordered" 
                                    placeholder="+62 xxx xxxx xxxx"
                                    :disabled="!canEdit"
                                />
                            </div>
                            <div class="form-control md:col-span-2">
                                <label class="label"><span class="label-text">Address</span></label>
                                <input 
                                    type="text" 
                                    v-model="form.address" 
                                    class="input input-bordered" 
                                    :disabled="!canEdit"
                                />
                            </div>
                            <div class="form-control md:col-span-2">
                                <label class="label"><span class="label-text">Description</span></label>
                                <textarea 
                                    v-model="form.description" 
                                    class="textarea textarea-bordered h-24" 
                                    placeholder="Tell us about your organization..."
                                    :disabled="!canEdit"
                                ></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Social Media -->
                <div class="card bg-base-100 border border-base-200 shadow-sm">
                    <div class="card-body">
                        <h2 class="card-title text-lg">Social Media</h2>
                        
                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="form-control">
                                <label class="label">
                                    <span class="label-text flex items-center gap-2">
                                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                                        Facebook
                                    </span>
                                </label>
                                <input 
                                    type="url" 
                                    v-model="form.facebook" 
                                    class="input input-bordered" 
                                    placeholder="https://facebook.com/yourpage"
                                    :disabled="!canEdit"
                                />
                            </div>
                            <div class="form-control">
                                <label class="label">
                                    <span class="label-text flex items-center gap-2">
                                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M23.953 4.57a10 10 0 01-2.825.775 4.958 4.958 0 002.163-2.723c-.951.555-2.005.959-3.127 1.184a4.92 4.92 0 00-8.384 4.482C7.69 8.095 4.067 6.13 1.64 3.162a4.822 4.822 0 00-.666 2.475c0 1.71.87 3.213 2.188 4.096a4.904 4.904 0 01-2.228-.616v.06a4.923 4.923 0 003.946 4.827 4.996 4.996 0 01-2.212.085 4.936 4.936 0 004.604 3.417 9.867 9.867 0 01-6.102 2.105c-.39 0-.779-.023-1.17-.067a13.995 13.995 0 007.557 2.209c9.053 0 13.998-7.496 13.998-13.985 0-.21 0-.42-.015-.63A9.935 9.935 0 0024 4.59z"/></svg>
                                        Twitter
                                    </span>
                                </label>
                                <input 
                                    type="url" 
                                    v-model="form.twitter" 
                                    class="input input-bordered" 
                                    placeholder="https://twitter.com/yourhandle"
                                    :disabled="!canEdit"
                                />
                            </div>
                            <div class="form-control">
                                <label class="label">
                                    <span class="label-text flex items-center gap-2">
                                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12 0C8.74 0 8.333.015 7.053.072 5.775.132 4.905.333 4.14.63c-.789.306-1.459.717-2.126 1.384S.935 3.35.63 4.14C.333 4.905.131 5.775.072 7.053.012 8.333 0 8.74 0 12s.015 3.667.072 4.947c.06 1.277.261 2.148.558 2.913.306.788.717 1.459 1.384 2.126.667.666 1.336 1.079 2.126 1.384.766.296 1.636.499 2.913.558C8.333 23.988 8.74 24 12 24s3.667-.015 4.947-.072c1.277-.06 2.148-.262 2.913-.558.788-.306 1.459-.718 2.126-1.384.666-.667 1.079-1.335 1.384-2.126.296-.765.499-1.636.558-2.913.06-1.28.072-1.687.072-4.947s-.015-3.667-.072-4.947c-.06-1.277-.262-2.149-.558-2.913-.306-.789-.718-1.459-1.384-2.126C21.319 1.347 20.651.935 19.86.63c-.765-.297-1.636-.499-2.913-.558C15.667.012 15.26 0 12 0zm0 2.16c3.203 0 3.585.016 4.85.071 1.17.055 1.805.249 2.227.415.562.217.96.477 1.382.896.419.42.679.819.896 1.381.164.422.36 1.057.413 2.227.057 1.266.07 1.646.07 4.85s-.015 3.585-.074 4.85c-.061 1.17-.256 1.805-.421 2.227-.224.562-.479.96-.899 1.382-.419.419-.824.679-1.38.896-.42.164-1.065.36-2.235.413-1.274.057-1.649.07-4.859.07-3.211 0-3.586-.015-4.859-.074-1.171-.061-1.816-.256-2.236-.421-.569-.224-.96-.479-1.379-.899-.421-.419-.69-.824-.9-1.38-.165-.42-.359-1.065-.42-2.235-.045-1.26-.061-1.649-.061-4.844 0-3.196.016-3.586.061-4.861.061-1.17.255-1.814.42-2.234.21-.57.479-.96.9-1.381.419-.419.81-.689 1.379-.898.42-.166 1.051-.361 2.221-.421 1.275-.045 1.65-.06 4.859-.06l.045.03zm0 3.678c-3.405 0-6.162 2.76-6.162 6.162 0 3.405 2.76 6.162 6.162 6.162 3.405 0 6.162-2.76 6.162-6.162 0-3.405-2.76-6.162-6.162-6.162zM12 16c-2.21 0-4-1.79-4-4s1.79-4 4-4 4 1.79 4 4-1.79 4-4 4zm7.846-10.405c0 .795-.646 1.44-1.44 1.44-.795 0-1.44-.646-1.44-1.44 0-.794.646-1.439 1.44-1.439.793-.001 1.44.645 1.44 1.439z"/></svg>
                                        Instagram
                                    </span>
                                </label>
                                <input 
                                    type="url" 
                                    v-model="form.instagram" 
                                    class="input input-bordered" 
                                    placeholder="https://instagram.com/yourhandle"
                                    :disabled="!canEdit"
                                />
                            </div>
                            <div class="form-control">
                                <label class="label">
                                    <span class="label-text flex items-center gap-2">
                                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>
                                        LinkedIn
                                    </span>
                                </label>
                                <input 
                                    type="url" 
                                    v-model="form.linkedin" 
                                    class="input input-bordered" 
                                    placeholder="https://linkedin.com/company/yourcompany"
                                    :disabled="!canEdit"
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Save Button -->
                <div v-if="canEdit" class="flex justify-end">
                    <button 
                        class="btn btn-primary" 
                        @click="saveSettings"
                        :disabled="saving"
                    >
                        <span v-if="saving" class="loading loading-spinner loading-sm"></span>
                        Save Changes
                    </button>
                </div>
            </template>

            <!-- Toast -->
            <div class="toast toast-end">
                <div v-if="toast.show" :class="['alert', toast.type === 'success' ? 'alert-success' : 'alert-error']">
                    <span>{{ toast.message }}</span>
                </div>
            </div>
        </main>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted, computed } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import axios from 'axios';

const page = usePage();
const loading = ref(true);
const saving = ref(false);
const uploading = ref(false);
const settings = ref(null);
const canEdit = ref(false);

const form = reactive({
    name: '',
    description: '',
    website: '',
    phone: '',
    address: '',
    facebook: '',
    twitter: '',
    instagram: '',
    linkedin: '',
});

const toast = reactive({
    show: false,
    message: '',
    type: 'success'
});

const showToast = (message, type = 'success') => {
    toast.message = message;
    toast.type = type;
    toast.show = true;
    setTimeout(() => { toast.show = false; }, 3000);
};

const getStorageUrl = (path) => {
    if (!path) return '';
    if (path.startsWith('http')) return path;
    return `/storage/${path}`;
};

const isApiSuccess = (payload) => {
    return payload?.success === true || payload?.status === 'success';
};

const fetchSettings = async () => {
    try {
        loading.value = true;
        const response = await axios.get('/api/portal-settings');
        const payload = response?.data;
        if (!isApiSuccess(payload)) {
            throw new Error(payload?.message || 'Gagal memuat portal settings');
        }
        settings.value = payload?.data ?? null;
        if (settings.value) {
            Object.assign(form, settings.value);
        }
        canEdit.value = true;
    } catch (error) {
        if (error.response?.status === 403) {
            canEdit.value = false;
        } else if (error.response?.status === 400) {
            console.log('No organization selected');
            settings.value = null;
        }
        console.error('Failed to fetch settings:', error.response?.data || error.message);
    } finally {
        loading.value = false;
    }
};

const saveSettings = async () => {
    try {
        saving.value = true;
        const response = await axios.put('/api/portal-settings', form);
        const payload = response?.data;
        if (!isApiSuccess(payload)) {
            throw new Error(payload?.message || 'Gagal menyimpan portal settings');
        }
        showToast('Settings saved successfully!');
        settings.value = { ...(settings.value || {}), ...(payload?.data || {}), ...form };
    } catch (error) {
        showToast(error.response?.data?.message || 'Failed to save settings', 'error');
    } finally {
        saving.value = false;
    }
};

const uploadLogo = async (event) => {
    const file = event.target.files[0];
    if (!file) return;

    const formData = new FormData();
    formData.append('logo', file);

    try {
        uploading.value = true;
        const response = await axios.post('/api/portal-settings/logo', formData, {
            headers: { 
                'Content-Type': 'multipart/form-data'
            }
        });
        const payload = response?.data;
        if (!isApiSuccess(payload)) {
            throw new Error(payload?.message || 'Gagal upload logo');
        }
        if (settings.value) {
            settings.value.logo = payload?.data?.logo ?? payload?.data?.path ?? settings.value.logo;
        }
        showToast('Logo uploaded successfully!');
    } catch (error) {
        const message = error.response?.data?.message || error.message || 'Failed to upload logo';
        showToast(message, 'error');
    } finally {
        uploading.value = false;
    }
};

const uploadBanner = async (event) => {
    const file = event.target.files[0];
    if (!file) return;

    const formData = new FormData();
    formData.append('banner', file);

    try {
        uploading.value = true;
        const response = await axios.post('/api/portal-settings/banner', formData, {
            headers: { 
                'Content-Type': 'multipart/form-data'
            }
        });
        const payload = response?.data;
        if (!isApiSuccess(payload)) {
            throw new Error(payload?.message || 'Gagal upload banner');
        }
        if (settings.value) {
            settings.value.banner = payload?.data?.banner ?? payload?.data?.path ?? settings.value.banner;
        }
        showToast('Banner uploaded successfully!');
    } catch (error) {
        const message = error.response?.data?.message || error.message || 'Failed to upload banner';
        showToast(message, 'error');
    } finally {
        uploading.value = false;
    }
};

onMounted(() => {
    fetchSettings();
});
</script>
