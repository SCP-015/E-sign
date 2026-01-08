<template>
    <div class="dashboard">
        <nav class="navbar glass">
            <div class="logo">E-Sign</div>
            <div class="user-info">
                <span>{{ user.name }}</span>
                <button @click="logout" class="btn-sm">Logout</button>
            </div>
        </nav>

        <div class="content">
            <!-- KYC / Certificate Status Section -->
            <div class="card glass">
                <h2>Digital Identity</h2>
                
                <!-- Helper for Simulation -->
                <div v-if="user.kyc_status === 'unverified'">
                    <div class="alert-warning">
                        <h3>⚠️ Identity Unverified</h3>
                        <p>To sign documents, please download our <strong>Mobile App</strong> and complete the KYC process (scan ID & Face).</p>
                        <p class="small">Use your email <strong>{{ user.email }}</strong> to login on the app.</p>
                        <div class="simulation-tip">
                            (Dev Note: Simulate KYC via POST /api/kyc/submit in Postman)
                        </div>
                    </div>
                </div>

                <div v-else class="cert-status">
                    <span class="badge success">✅ Verified & Certificate Active</span>
                    <p class="mono">Certificate ID: {{ certificateId || 'Active' }}</p>
                </div>
            </div>

            <!-- Upload Section (Only if Verified) -->
            <div v-if="user.kyc_status === 'verified'" class="card glass mt-4">
                <h2>Upload Document</h2>
                <div class="upload-area" @dragover.prevent @drop.prevent="handleDrop">
                    <input type="file" ref="fileInput" @change="handleFileSelect" accept="application/pdf" hidden>
                    <button @click="$refs.fileInput.click()" class="btn-secondary">choose file</button>
                    <p>or drag & drop PDF here</p>
                </div>
            </div>

            <!-- Documents List -->
            <div v-if="user.kyc_status === 'verified'" class="card glass mt-4">
                <h2>Your Documents</h2>
                <div class="doc-list">
                    <div v-for="doc in documents" :key="doc.id" class="doc-item">
                        <div class="doc-info">
                            <span class="doc-name">{{ getFileName(doc.file_path) }}</span>
                            <span :class="['status', doc.status]">{{ doc.status }}</span>
                        </div>
                        <div class="actions">
                            <button v-if="doc.status === 'pending'" @click="signDocument(doc.id)" class="btn-primary" :disabled="!hasCertificate">
                                Sign Now
                            </button>
                            <button v-if="doc.status === 'signed'" @click="verifyDocument(doc.id)" class="btn-secondary">
                                Verify
                            </button>
                            <a v-if="doc.status === 'signed'" :href="getDownloadUrl(doc.signed_path)" target="_blank" class="btn-link">Download</a>
                        </div>
                    </div>
                    <div v-if="documents.length === 0" class="empty-state">No documents found.</div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import axios from 'axios';

const router = useRouter();
const user = ref({});
const loading = ref(false);
const hasCertificate = ref(false);
const certificateId = ref(null);
const documents = ref([]);
const fileInput = ref(null);

// Axios Config
const token = localStorage.getItem('token');
if (token) {
    axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;
} else {
    router.push('/');
}

const logout = () => {
    localStorage.removeItem('token');
    localStorage.removeItem('user');
    router.push('/');
};

const getFileName = (path) => path.split('/').pop();
const getDownloadUrl = (path) => `/storage/${path}`; // Assumes symlink and public access for MVP

// Fetch Initial Data (Mocking Dashboard Data Loading)
// Real app would have a /dashboard endpoint or separate calls
onMounted(async () => {
    try {
        // 1. Load from Cache first (for instant render)
        const cachedUser = JSON.parse(localStorage.getItem('user'));
        if (cachedUser) user.value = cachedUser;
        
        // 2. Refresh from API (Background)
        const res = await axios.get('/api/user');
        user.value = res.data;
        localStorage.setItem('user', JSON.stringify(res.data)); // Update cache

        // MVP: If verified, assume they have a certificate
        if (user.value.kyc_status === 'verified') {
            hasCertificate.value = true;
        }
        // For MVP, we don't have a specific "get my cert" endpoint easily exposed unless we check user relation
        // We'll trust the user state or we could hit /user again if it returned relation
        // Let's assume we might need to store cert status in local or fetch.
        // Re-fetch user to get latest state if we added relations to User model response?
        // Or separate call.
        
        // For now, let's just allow "Issue" to be idempotent-ish or check local state if we had one.
        // Actually, let's just try to list docs. We really need a GET /documents endpoint?
        // Plan didn't explicitly say "GET /documents". It said "Upload" and "Sign".
        // I will add a GET /documents endpoint to DocumentController quickly to support this UI.
        await fetchDocuments();
        
    } catch (e) {
        console.error(e);
    }
});

const issueCertificate = async () => {
    loading.value = true;
    try {
        // We need to send a dummy CSR or just let server generate.
        // Server refactor: generateUserCertificate(user).
        // Controller issue(Request $request) -> checks user. Logic updated to internal generation.
        // But Controller validation might still want "csr"? I removed it in refactor? 
        // Let's check Controller code.
        // Controller issue() was refactored to NOT validate 'csr'.
        const res = await axios.post('/api/certificates/issue');
        hasCertificate.value = true;
        certificateId.value = res.data.certificate_id;
        alert('Certificate Issued!');
    } catch (e) {
        alert('Error: ' + e.response?.data?.message);
    } finally {
        loading.value = false;
    }
};

const handleFileSelect = (e) => uploadFile(e.target.files[0]);
const handleDrop = (e) => uploadFile(e.dataTransfer.files[0]);

const uploadFile = async (file) => {
    if (!file) return;
    const formData = new FormData();
    formData.append('file', file);
    
    try {
        await axios.post('/api/documents', formData);
        await fetchDocuments(); // Refresh list
    } catch (e) {
        alert('Upload Failed');
    }
};

const signDocument = async (id) => {
    try {
        await axios.post(`/api/documents/${id}/sign`);
        alert('Signed Successfully!');
        fetchDocuments();
    } catch (e) {
        alert('Signing Failed: ' + e.response?.data?.message);
    }
};

const verifyDocument = async (id) => {
    try {
        const res = await axios.post('/api/documents/verify', { document_id: id });
        if (res.data.verified) {
            alert('VALID: ' + res.data.message);
        } else {
            alert('INVALID: ' + res.data.error);
        }
    } catch (e) {
        alert('Verification Error');
    }
};

// We need a way to list documents. I'll mock it or add the endpoint.
// Since I can run commands, I will add the endpoint in the next step.
const fetchDocuments = async () => {
   // Placeholder for now
   const res = await axios.get('/api/documents'); 
   documents.value = res.data;
};

</script>

<style scoped>
.dashboard {
    max-width: 1200px;
    margin: 0 auto;
    padding: 2rem;
    width: 100%;
}

.navbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem 2rem;
    margin-bottom: 2rem;
}

.logo {
    font-size: 1.5rem;
    font-weight: 700;
    color: #38bdf8;
}

.user-info {
    display: flex;
    gap: 1rem;
    align-items: center;
}

.card {
    padding: 2rem;
    margin-bottom: 2rem;
}

h2 {
    margin-top: 0;
    border-bottom: 1px solid rgba(255,255,255,0.1);
    padding-bottom: 1rem;
    margin-bottom: 1.5rem;
}

.upload-area {
    border: 2px dashed rgba(255,255,255,0.2);
    padding: 3rem;
    text-align: center;
    border-radius: 8px;
    transition: all 0.3s;
}

.upload-area:hover {
    border-color: #38bdf8;
    background: rgba(56, 189, 248, 0.05);
}

.doc-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem;
    border-bottom: 1px solid rgba(255,255,255,0.05);
}

.status {
    font-size: 0.8rem;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    margin-left: 1rem;
}

.status.pending { background: rgba(251, 191, 36, 0.2); color: #fbbf24; }
.status.signed { background: rgba(52, 211, 153, 0.2); color: #34d399; }

.btn-primary {
    background: #38bdf8;
    color: #0f172a;
    border: none;
    padding: 0.5rem 1rem;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 600;
}

.btn-secondary {
    background: transparent;
    border: 1px solid #94a3b8;
    color: #94a3b8;
    padding: 0.5rem 1rem;
    border-radius: 6px;
    cursor: pointer;
}

.btn-sm {
    padding: 0.25rem 0.75rem;
    font-size: 0.875rem;
}

.btn-link {
    color: #38bdf8;
    text-decoration: none;
    margin-left: 1rem;
}

.mt-4 { margin-top: 1.5rem; }

.alert-warning {
    background: rgba(251, 191, 36, 0.1);
    border: 1px solid rgba(251, 191, 36, 0.3);
    padding: 1rem;
    border-radius: 8px;
    color: #fbbf24;
}

.alert-warning h3 {
    margin-top: 0;
    margin-bottom: 0.5rem;
    font-size: 1.1rem;
}

.simulation-tip {
    font-family: monospace;
    font-size: 0.8rem;
    opacity: 0.7;
    margin-top: 1rem;
    background: rgba(0,0,0,0.3);
    padding: 0.5rem;
    border-radius: 4px;
}
</style>
