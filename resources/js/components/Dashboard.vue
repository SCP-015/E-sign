<template>
    <div class="dashboard">
        <!-- Header Navigation -->
        <header class="header">
            <div class="header-content">
                <div class="logo-section">
                    <h1 class="logo">✍️ E-Sign</h1>
                    <p class="tagline">Digital Document Signing</p>
                </div>
                <div class="header-actions">
                    <div class="user-profile">
                        <img v-if="user.avatar" :src="user.avatar" :alt="user.name" class="avatar">
                        <div v-else class="avatar-placeholder">{{ user.name?.charAt(0).toUpperCase() }}</div>
                        <div class="user-details">
                            <p class="user-name">{{ user.name }}</p>
                            <p class="user-email">{{ user.email }}</p>
                        </div>
                    </div>
                    <button @click="logout" class="btn-logout">Logout</button>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Welcome Section -->
            <section class="welcome-section">
                <div class="welcome-card">
                    <h2 class="welcome-title">Halo, {{ user.name }}! 👋</h2>
                    <p class="welcome-subtitle">Welcome back to your E-Sign dashboard</p>
                    <div class="welcome-stats">
                        <div class="stat-item">
                            <span class="stat-number">{{ documents.length }}</span>
                            <span class="stat-label">Documents</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-number">{{ signedCount }}</span>
                            <span class="stat-label">Signed</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-number">{{ pendingCount }}</span>
                            <span class="stat-label">Pending</span>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Identity Status Section -->
            <section class="identity-section">
                <div class="section-header">
                    <h3>🔐 Digital Identity Status</h3>
                </div>
                
                <div v-if="user.kyc_status === 'unverified'" class="identity-card unverified">
                    <div class="identity-icon">⚠️</div>
                    <div class="identity-content">
                        <h4>Identity Not Verified</h4>
                        <p>To sign documents, please complete the KYC verification process on our Mobile App.</p>
                        <div class="identity-steps">
                            <div class="step">
                                <span class="step-number">1</span>
                                <span>Download E-Sign Mobile App</span>
                            </div>
                            <div class="step">
                                <span class="step-number">2</span>
                                <span>Login with {{ user.email }}</span>
                            </div>
                            <div class="step">
                                <span class="step-number">3</span>
                                <span>Complete KYC (Scan ID & Face)</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-else class="identity-card verified">
                    <div class="identity-icon">✅</div>
                    <div class="identity-content">
                        <h4>Identity Verified</h4>
                        <p>Your digital certificate is active and ready to sign documents.</p>
                        <div class="cert-details">
                            <span class="cert-badge">Certificate Active</span>
                            <p class="cert-info">Valid until {{ certificateExpiry }}</p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Upload Section (Only if Verified) -->
            <section v-if="user.kyc_status === 'verified'" class="upload-section">
                <div class="section-header">
                    <h3>📤 Upload Document</h3>
                </div>
                <div class="upload-card">
                    <div class="upload-area" @dragover.prevent="dragActive = true" @dragleave.prevent="dragActive = false" @drop.prevent="handleDrop" :class="{ 'drag-active': dragActive }">
                        <input type="file" ref="fileInput" @change="handleFileSelect" accept="application/pdf" hidden>
                        <div class="upload-content">
                            <div class="upload-icon">📄</div>
                            <h4>Drag & Drop PDF Here</h4>
                            <p>or</p>
                            <button @click="$refs.fileInput.click()" class="btn-primary">Choose File</button>
                            <p class="upload-hint">Maximum file size: 10MB</p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Documents Section -->
            <section v-if="user.kyc_status === 'verified'" class="documents-section">
                <div class="section-header">
                    <h3>📋 Document History</h3>
                    <span class="doc-count">{{ documents.length }} documents</span>
                </div>

                <div v-if="documents.length > 0" class="documents-grid">
                    <div v-for="doc in documents" :key="doc.id" class="document-card" :class="doc.status">
                        <div class="doc-header">
                            <div class="doc-icon">
                                <span v-if="doc.status === 'pending'">⏳</span>
                                <span v-else-if="doc.status === 'signed'">✅</span>
                                <span v-else>📄</span>
                            </div>
                            <div class="doc-meta">
                                <h4 class="doc-name">{{ getFileName(doc.file_path) }}</h4>
                                <p class="doc-date">{{ formatDate(doc.created_at) }}</p>
                            </div>
                            <span :class="['status-badge', doc.status]">{{ doc.status }}</span>
                        </div>
                        <div class="doc-actions">
                            <button v-if="doc.status === 'pending'" @click="signDocument(doc.id)" class="btn-primary btn-sm">
                                Sign Now
                            </button>
                            <button v-if="doc.status === 'signed'" @click="verifyDocument(doc.id)" class="btn-secondary btn-sm">
                                Verify Signature
                            </button>
                            <a v-if="doc.status === 'signed'" :href="getDownloadUrl(doc.signed_path)" target="_blank" class="btn-link btn-sm">
                                Download
                            </a>
                        </div>
                    </div>
                </div>

                <div v-else class="empty-state">
                    <div class="empty-icon">📭</div>
                    <h4>No Documents Yet</h4>
                    <p>Upload your first document to get started</p>
                </div>
            </section>
        </main>
    </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import { useRouter } from 'vue-router';
import axios from 'axios';
import { useAuthStore } from '../stores/auth';

const router = useRouter();
const authStore = useAuthStore();
const user = computed(() => authStore.user || {});

const loading = ref(false);
const dragActive = ref(false);
const documents = ref([]);
const fileInput = ref(null);

const signedCount = computed(() => documents.value.filter(d => d.status === 'signed').length);
const pendingCount = computed(() => documents.value.filter(d => d.status === 'pending').length);
const certificateExpiry = computed(() => {
    const date = new Date();
    date.setFullYear(date.getFullYear() + 1);
    return date.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
});

const logout = async () => {
    try {
        await axios.post('/api/auth/logout');
    } catch (e) {
        console.error('Logout error:', e);
    }
    authStore.logout();
    router.push('/');
};

const getFileName = (path) => path ? path.split('/').pop() : 'document.pdf';
const getDownloadUrl = (path) => `/storage/${path}`;
const formatDate = (dateString) => {
    if (!dateString) return '';
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
};

onMounted(async () => {
    try {
        await authStore.fetchUser();
        await fetchDocuments();
    } catch (e) {
        console.error('Failed to init dashboard:', e);
    }
});

const handleFileSelect = (e) => uploadFile(e.target.files[0]);
const handleDrop = (e) => {
    dragActive.value = false;
    uploadFile(e.dataTransfer.files[0]);
};

const uploadFile = async (file) => {
    if (!file) return;
    const formData = new FormData();
    formData.append('file', file);
    
    try {
        await axios.post('/api/documents', formData);
        await fetchDocuments();
    } catch (e) {
        alert('Upload Failed: ' + (e.response?.data?.message || e.message));
    }
};

const signDocument = async (id) => {
    try {
        await axios.post(`/api/documents/${id}/sign`);
        alert('Document signed successfully!');
        await fetchDocuments();
    } catch (e) {
        alert('Signing Failed: ' + (e.response?.data?.message || e.message));
    }
};

const verifyDocument = async (id) => {
    try {
        const res = await axios.post('/api/documents/verify', { document_id: id });
        if (res.data.is_valid) {
            alert('✅ Signature is valid!');
        } else {
            alert('❌ Signature is invalid');
        }
    } catch (e) {
        alert('Verification Error: ' + (e.response?.data?.message || e.message));
    }
};

const fetchDocuments = async () => {
    try {
        const res = await axios.get('/api/documents');
        // Response is direct array, not wrapped in data key
        documents.value = Array.isArray(res.data) ? res.data : (res.data?.data || []);
    } catch (e) {
        console.error('Failed to fetch documents:', e);
        documents.value = [];
    }
};

</script>

<style scoped>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

.dashboard {
    min-height: 100vh;
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
    color: #e2e8f0;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
}

/* Header */
.header {
    background: rgba(15, 23, 42, 0.8);
    backdrop-filter: blur(10px);
    border-bottom: 1px solid rgba(148, 163, 184, 0.1);
    padding: 1.5rem 0;
    position: sticky;
    top: 0;
    z-index: 100;
}

.header-content {
    max-width: 1400px;
    margin: 0 auto;
    padding: 0 2rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.logo-section {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.logo {
    font-size: 1.75rem;
    font-weight: 800;
    background: linear-gradient(135deg, #38bdf8 0%, #06b6d4 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.tagline {
    font-size: 0.875rem;
    color: #94a3b8;
}

.header-actions {
    display: flex;
    align-items: center;
    gap: 2rem;
}

.user-profile {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.avatar, .avatar-placeholder {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    object-fit: cover;
    background: linear-gradient(135deg, #38bdf8 0%, #06b6d4 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    color: #0f172a;
    flex-shrink: 0;
}

.user-details {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.user-name {
    font-weight: 600;
    font-size: 0.95rem;
}

.user-email {
    font-size: 0.8rem;
    color: #94a3b8;
}

.btn-logout {
    padding: 0.625rem 1.25rem;
    background: rgba(239, 68, 68, 0.1);
    border: 1px solid rgba(239, 68, 68, 0.3);
    color: #ef4444;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 600;
    transition: all 0.3s;
}

.btn-logout:hover {
    background: rgba(239, 68, 68, 0.2);
    border-color: rgba(239, 68, 68, 0.5);
}

/* Main Content */
.main-content {
    max-width: 1400px;
    margin: 0 auto;
    padding: 2rem;
}

/* Welcome Section */
.welcome-section {
    margin-bottom: 3rem;
}

.welcome-card {
    background: linear-gradient(135deg, rgba(56, 189, 248, 0.1) 0%, rgba(6, 182, 212, 0.1) 100%);
    border: 1px solid rgba(56, 189, 248, 0.2);
    border-radius: 12px;
    padding: 2.5rem;
    backdrop-filter: blur(10px);
}

.welcome-title {
    font-size: 2rem;
    font-weight: 800;
    margin-bottom: 0.5rem;
    background: linear-gradient(135deg, #38bdf8 0%, #06b6d4 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.welcome-subtitle {
    font-size: 1rem;
    color: #94a3b8;
    margin-bottom: 1.5rem;
}

.welcome-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 1.5rem;
    margin-top: 2rem;
}

.stat-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 1rem;
    background: rgba(15, 23, 42, 0.5);
    border-radius: 8px;
    border: 1px solid rgba(148, 163, 184, 0.1);
}

.stat-number {
    font-size: 2rem;
    font-weight: 800;
    color: #38bdf8;
}

.stat-label {
    font-size: 0.875rem;
    color: #94a3b8;
    margin-top: 0.5rem;
}

/* Section Headers */
.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
}

.section-header h3 {
    font-size: 1.5rem;
    font-weight: 700;
}

.doc-count {
    background: rgba(56, 189, 248, 0.1);
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-size: 0.875rem;
    color: #38bdf8;
}

/* Identity Section */
.identity-section {
    margin-bottom: 3rem;
}

.identity-card {
    display: flex;
    gap: 2rem;
    padding: 2rem;
    border-radius: 12px;
    border: 1px solid rgba(148, 163, 184, 0.1);
    backdrop-filter: blur(10px);
}

.identity-card.unverified {
    background: rgba(251, 191, 36, 0.05);
    border-color: rgba(251, 191, 36, 0.2);
}

.identity-card.verified {
    background: rgba(52, 211, 153, 0.05);
    border-color: rgba(52, 211, 153, 0.2);
}

.identity-icon {
    font-size: 3rem;
    flex-shrink: 0;
}

.identity-content h4 {
    font-size: 1.25rem;
    margin-bottom: 0.5rem;
}

.identity-content p {
    color: #cbd5e1;
    margin-bottom: 1rem;
}

.identity-steps {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    margin-top: 1rem;
}

.step {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.step-number {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    background: rgba(251, 191, 36, 0.2);
    border-radius: 50%;
    font-weight: 700;
    color: #fbbf24;
    flex-shrink: 0;
}

.cert-details {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-top: 1rem;
}

.cert-badge {
    background: rgba(52, 211, 153, 0.2);
    color: #34d399;
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-size: 0.875rem;
    font-weight: 600;
}

.cert-info {
    color: #94a3b8;
    font-size: 0.875rem;
}

/* Upload Section */
.upload-section {
    margin-bottom: 3rem;
}

.upload-card {
    background: rgba(15, 23, 42, 0.5);
    border: 1px solid rgba(148, 163, 184, 0.1);
    border-radius: 12px;
    padding: 2rem;
    backdrop-filter: blur(10px);
}

.upload-area {
    border: 2px dashed rgba(56, 189, 248, 0.3);
    border-radius: 12px;
    padding: 3rem;
    text-align: center;
    transition: all 0.3s;
    cursor: pointer;
}

.upload-area:hover,
.upload-area.drag-active {
    border-color: #38bdf8;
    background: rgba(56, 189, 248, 0.1);
}

.upload-icon {
    font-size: 3rem;
    margin-bottom: 1rem;
}

.upload-content h4 {
    font-size: 1.25rem;
    margin-bottom: 0.5rem;
}

.upload-content p {
    color: #94a3b8;
    margin: 0.5rem 0;
}

.upload-hint {
    font-size: 0.875rem;
    color: #64748b;
    margin-top: 1rem;
}

/* Documents Section */
.documents-section {
    margin-bottom: 3rem;
}

.documents-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 1.5rem;
}

.document-card {
    background: rgba(15, 23, 42, 0.5);
    border: 1px solid rgba(148, 163, 184, 0.1);
    border-radius: 12px;
    padding: 1.5rem;
    backdrop-filter: blur(10px);
    transition: all 0.3s;
}

.document-card:hover {
    border-color: rgba(56, 189, 248, 0.3);
    background: rgba(56, 189, 248, 0.05);
}

.document-card.signed {
    border-color: rgba(52, 211, 153, 0.2);
}

.document-card.pending {
    border-color: rgba(251, 191, 36, 0.2);
}

.doc-header {
    display: flex;
    gap: 1rem;
    align-items: flex-start;
    margin-bottom: 1rem;
}

.doc-icon {
    font-size: 2rem;
    flex-shrink: 0;
}

.doc-meta {
    flex: 1;
}

.doc-name {
    font-weight: 600;
    margin-bottom: 0.25rem;
    display: block;
    word-break: break-word;
}

.doc-date {
    font-size: 0.875rem;
    color: #94a3b8;
}

.status-badge {
    padding: 0.375rem 0.75rem;
    border-radius: 6px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
}

.status-badge.pending {
    background: rgba(251, 191, 36, 0.2);
    color: #fbbf24;
}

.status-badge.signed {
    background: rgba(52, 211, 153, 0.2);
    color: #34d399;
}

.doc-actions {
    display: flex;
    gap: 0.75rem;
    flex-wrap: wrap;
    margin-top: 1rem;
    padding-top: 1rem;
    border-top: 1px solid rgba(148, 163, 184, 0.1);
}

/* Buttons */
.btn-primary {
    background: linear-gradient(135deg, #38bdf8 0%, #06b6d4 100%);
    color: #0f172a;
    border: none;
    padding: 0.625rem 1.25rem;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 600;
    font-size: 0.875rem;
    transition: all 0.3s;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 16px rgba(56, 189, 248, 0.3);
}

.btn-primary:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.btn-secondary {
    background: transparent;
    border: 1px solid rgba(148, 163, 184, 0.3);
    color: #94a3b8;
    padding: 0.625rem 1.25rem;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 600;
    font-size: 0.875rem;
    transition: all 0.3s;
}

.btn-secondary:hover {
    border-color: rgba(148, 163, 184, 0.5);
    color: #cbd5e1;
}

.btn-link {
    color: #38bdf8;
    text-decoration: none;
    font-weight: 600;
    font-size: 0.875rem;
    transition: all 0.3s;
}

.btn-link:hover {
    color: #06b6d4;
    text-decoration: underline;
}

.btn-sm {
    padding: 0.5rem 1rem;
    font-size: 0.8rem;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 3rem 2rem;
    color: #94a3b8;
}

.empty-icon {
    font-size: 3rem;
    margin-bottom: 1rem;
}

.empty-state h4 {
    font-size: 1.25rem;
    margin-bottom: 0.5rem;
    color: #cbd5e1;
}

.empty-state p {
    color: #94a3b8;
}

/* Responsive */
@media (max-width: 768px) {
    .header-content {
        flex-direction: column;
        gap: 1rem;
        align-items: flex-start;
    }

    .header-actions {
        width: 100%;
        justify-content: space-between;
    }

    .main-content {
        padding: 1rem;
    }

    .welcome-title {
        font-size: 1.5rem;
    }

    .welcome-stats {
        grid-template-columns: repeat(3, 1fr);
        gap: 1rem;
    }

    .documents-grid {
        grid-template-columns: 1fr;
    }

    .identity-card {
        flex-direction: column;
    }
}
</style>
