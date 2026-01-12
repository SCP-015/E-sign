<template>
  <div class="signature-setup">
    <div class="setup-container">
      <h2>✍️ Setup Your Signature</h2>
      <p class="subtitle">Create and manage your digital signatures</p>

      <!-- Canvas for drawing signature -->
      <div class="signature-section">
        <h3>Draw Your Signature</h3>
        <div class="canvas-container">
          <canvas 
            ref="signatureCanvas"
            class="signature-canvas"
            @mousedown="startDrawing"
            @mousemove="draw"
            @mouseup="stopDrawing"
            @mouseout="stopDrawing"
          ></canvas>
        </div>
        <div class="canvas-actions">
          <button @click="clearCanvas" class="btn-secondary">🗑️ Clear</button>
          <button @click="saveSignature" class="btn-primary" :disabled="!isDrawn">
            💾 Save Signature
          </button>
        </div>
      </div>

      <!-- Message -->
      <div v-if="message" :class="['message', messageType]">
        {{ message }}
      </div>

      <!-- Saved Signatures List -->
      <div class="signatures-section">
        <h3>Your Saved Signatures</h3>
        
        <div v-if="signatures.length === 0" class="empty-state">
          <p>No signatures saved yet. Draw and save one above!</p>
        </div>

        <div v-else class="signatures-list">
          <div v-for="sig in signatures" :key="sig.id" class="signature-card">
            <div class="sig-preview">
              <img 
                v-if="signatureImageUrls[sig.id]"
                :src="signatureImageUrls[sig.id]" 
                :alt="sig.name" 
                class="sig-image"
                @error="handleImageError($event, sig.id)"
                :key="sig.id"
              >
            </div>
            <div class="sig-info">
              <h4>{{ sig.name }}</h4>
              <p class="sig-type">{{ sig.image_type.toUpperCase() }}</p>
              <p class="sig-date">{{ formatDate(sig.created_at) }}</p>
              <div v-if="sig.is_default" class="default-badge">✓ Default</div>
            </div>
            <div class="sig-actions">
              <button 
                v-if="!sig.is_default"
                @click="setDefault(sig.id)" 
                class="btn-mini"
                title="Set as default"
              >
                ⭐ Set Default
              </button>
              <button 
                @click="deleteSignature(sig.id)" 
                class="btn-mini btn-danger"
                title="Delete signature"
              >
                🗑️ Delete
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Back to Dashboard -->
      <div class="footer-actions">
        <button @click="goToDashboard" class="btn-secondary">
          ← Back to Dashboard
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue';
import { useRouter } from 'vue-router';
import axios from 'axios';

const router = useRouter();
const signatureCanvas = ref(null);
const signatures = ref([]);
const signatureImageUrls = ref({});
const message = ref('');
const messageType = ref('info');
const isDrawing = ref(false);
const isDrawn = ref(false);

let ctx = null;
let lastX = 0;
let lastY = 0;

function getTrimBounds(imageData, alphaThreshold = 1) {
  const { data, width, height } = imageData;

  let minX = width;
  let minY = height;
  let maxX = -1;
  let maxY = -1;

  for (let y = 0; y < height; y++) {
    for (let x = 0; x < width; x++) {
      const idx = (y * width + x) * 4;
      const a = data[idx + 3];
      if (a > alphaThreshold) {
        if (x < minX) minX = x;
        if (y < minY) minY = y;
        if (x > maxX) maxX = x;
        if (y > maxY) maxY = y;
      }
    }
  }

  if (maxX === -1) return null;
  return {
    x: minX,
    y: minY,
    w: maxX - minX + 1,
    h: maxY - minY + 1,
  };
}

async function canvasToCroppedPngBlob(sourceCanvas, { padding = 8, alphaThreshold = 1 } = {}) {
  const srcCtx = sourceCanvas.getContext('2d');
  const imageData = srcCtx.getImageData(0, 0, sourceCanvas.width, sourceCanvas.height);
  const bounds = getTrimBounds(imageData, alphaThreshold);

  const outCanvas = document.createElement('canvas');

  if (!bounds) {
    outCanvas.width = sourceCanvas.width;
    outCanvas.height = sourceCanvas.height;
    outCanvas.getContext('2d').drawImage(sourceCanvas, 0, 0);
  } else {
    const x = Math.max(0, bounds.x - padding);
    const y = Math.max(0, bounds.y - padding);
    const w = Math.min(sourceCanvas.width - x, bounds.w + padding * 2);
    const h = Math.min(sourceCanvas.height - y, bounds.h + padding * 2);

    outCanvas.width = w;
    outCanvas.height = h;

    const outCtx = outCanvas.getContext('2d');
    outCtx.drawImage(sourceCanvas, x, y, w, h, 0, 0, w, h);
  }

  const blob = await new Promise((resolve) => {
    outCanvas.toBlob(resolve, 'image/png');
  });
  return blob;
}

onMounted(() => {
  initCanvas();
  loadSignatures();
  
  // Debug: log axios config
  console.log('Axios defaults:', {
    baseURL: axios.defaults.baseURL,
    headers: axios.defaults.headers,
  });
});

onBeforeUnmount(() => {
  cleanupSignatureImageUrls();
});

function initCanvas() {
  const canvas = signatureCanvas.value;
  canvas.width = canvas.offsetWidth;
  canvas.height = canvas.offsetHeight;
  
  ctx = canvas.getContext('2d');
  ctx.lineCap = 'round';
  ctx.lineJoin = 'round';
  ctx.lineWidth = 2;
  ctx.strokeStyle = '#000';
  // Keep transparent background so saved PNG is transparent
  ctx.clearRect(0, 0, canvas.width, canvas.height);
}

function startDrawing(e) {
  const rect = signatureCanvas.value.getBoundingClientRect();
  lastX = e.clientX - rect.left;
  lastY = e.clientY - rect.top;
  isDrawing.value = true;
}

function draw(e) {
  if (!isDrawing.value) return;
  
  const rect = signatureCanvas.value.getBoundingClientRect();
  const x = e.clientX - rect.left;
  const y = e.clientY - rect.top;
  
  ctx.beginPath();
  ctx.moveTo(lastX, lastY);
  ctx.lineTo(x, y);
  ctx.stroke();
  
  lastX = x;
  lastY = y;
  isDrawn.value = true;
}

function stopDrawing() {
  isDrawing.value = false;
}

function clearCanvas() {
  // Keep transparent background
  ctx.clearRect(0, 0, signatureCanvas.value.width, signatureCanvas.value.height);
  isDrawn.value = false;
  showMessage('Canvas cleared', 'info');
}

async function saveSignature() {
  if (!isDrawn.value) {
    showMessage('Please draw a signature first', 'error');
    return;
  }

  try {
    const blob = await canvasToCroppedPngBlob(signatureCanvas.value, { padding: 8, alphaThreshold: 1 });

    if (!blob) {
      showMessage('Failed to create image from canvas', 'error');
      return;
    }

    const formData = new FormData();
    formData.append('image', blob, 'signature.png');
    formData.append('name', `Signature ${new Date().toLocaleDateString()}`);
    formData.append('is_default', signatures.value.length === 0 ? '1' : '0'); // First signature is default (1 or 0)
    
    const response = await axios.post('/api/signatures', formData);
    // Check for error in ApiResponse format
    if (response?.data?.status && response.data.status !== 'success') {
      throw new Error(response.data.message || 'Failed to save signature');
    }
    
    showMessage('✅ Signature saved successfully!', 'success');
    clearCanvas();
    await loadSignatures();
  } catch (e) {
    console.error('Failed to save signature:', e);
    console.error('Response data:', e.response?.data);
    
    // Get detailed error message
    let errorMsg = e.message;
    if (e.response?.data?.message) {
      errorMsg = e.response.data.message;
    }
    // Handle both Laravel validation errors and ApiResponse format
    if (e.response?.data?.data && e.response?.data?.data?.errors) {
      const errors = e.response.data.data.errors;
      errorMsg = Object.keys(errors).map(key => `${key}: ${errors[key].join(', ')}`).join('; ');
    } else if (e.response?.data?.errors) {
      const errors = e.response.data.errors;
      errorMsg = Object.keys(errors).map(key => `${key}: ${errors[key].join(', ')}`).join('; ');
    }
    
    showMessage('❌ Failed to save: ' + errorMsg, 'error');
  }
}

async function loadSignatures() {
  try {
    const response = await axios.get('/api/signatures');
    // Handle both direct array and ApiResponse format
    const list = response.data?.data ?? response.data;
    signatures.value = Array.isArray(list) ? list : [];
    await loadSignatureImages();
  } catch (e) {
    console.error('Failed to load signatures:', e);
    showMessage(e.response?.data?.message || 'Failed to load signatures', 'error');
  }
}

function cleanupSignatureImageUrls() {
  const urls = signatureImageUrls.value;
  Object.keys(urls).forEach((id) => {
    try {
      URL.revokeObjectURL(urls[id]);
    } catch (e) {
      // ignore
    }
  });
  signatureImageUrls.value = {};
}

async function loadSignatureImages() {
  cleanupSignatureImageUrls();
  const urls = {};
  for (const sig of signatures.value) {
    try {
      const res = await axios.get(`/api/signatures/${sig.id}/image`, {
        responseType: 'blob',
      });
      urls[sig.id] = URL.createObjectURL(res.data);
    } catch (e) {
      console.error('Failed to fetch signature image:', sig.id, e);
    }
  }
  signatureImageUrls.value = urls;
}

async function setDefault(id) {
  try {
    const res = await axios.put(`/api/signatures/${id}/default`);
    const msg = res?.data?.message || '✅ Signature set as default';
    showMessage(msg, 'success');
    await loadSignatures();
  } catch (e) {
    showMessage('Failed to set default: ' + (e.response?.data?.message || e.message), 'error');
  }
}

async function deleteSignature(id) {
  if (!confirm('Are you sure you want to delete this signature?')) return;
  
  try {
    const res = await axios.delete(`/api/signatures/${id}`);
    const msg = res?.data?.message || '✅ Signature deleted';
    showMessage(msg, 'success');
    await loadSignatures();
  } catch (e) {
    showMessage('Failed to delete: ' + (e.response?.data?.message || e.message), 'error');
  }
}

function showMessage(msg, type = 'info') {
  message.value = msg;
  messageType.value = type;
  
  setTimeout(() => {
    message.value = '';
  }, 3000);
}

function formatDate(dateString) {
  if (!dateString) return '';
  const date = new Date(dateString);
  return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
}

function goToDashboard() {
  router.push('/dashboard');
}
</script>

<style scoped>
.signature-setup {
  min-height: 100vh;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  padding: 40px 20px;
}

.setup-container {
  max-width: 900px;
  margin: 0 auto;
  background: white;
  border-radius: 12px;
  padding: 40px;
  box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
}

h2 {
  font-size: 28px;
  color: #333;
  margin-bottom: 10px;
  text-align: center;
}

.subtitle {
  text-align: center;
  color: #666;
  margin-bottom: 30px;
  font-size: 16px;
}

h3 {
  font-size: 20px;
  color: #333;
  margin-bottom: 20px;
  margin-top: 30px;
}

/* Canvas Section */
.signature-section {
  margin-bottom: 40px;
}

.canvas-container {
  border: 2px solid #ddd;
  border-radius: 8px;
  overflow: hidden;
  margin-bottom: 15px;
  background: white;
}

.signature-canvas {
  display: block;
  width: 100%;
  height: 300px;
  cursor: crosshair;
  background: white;
}

.canvas-actions {
  display: flex;
  gap: 10px;
  justify-content: center;
}

/* Messages */
.message {
  padding: 15px 20px;
  border-radius: 8px;
  margin: 20px 0;
  text-align: center;
  font-weight: 500;
}

.message.success {
  background: #d4edda;
  color: #155724;
  border: 1px solid #c3e6cb;
}

.message.error {
  background: #f8d7da;
  color: #721c24;
  border: 1px solid #f5c6cb;
}

.message.info {
  background: #d1ecf1;
  color: #0c5460;
  border: 1px solid #bee5eb;
}

/* Signatures Section */
.signatures-section {
  margin-top: 40px;
  padding-top: 30px;
  border-top: 2px solid #eee;
}

.empty-state {
  text-align: center;
  padding: 40px 20px;
  color: #999;
  font-size: 16px;
}

.signatures-list {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
  gap: 20px;
}

.signature-card {
  border: 1px solid #ddd;
  border-radius: 8px;
  padding: 15px;
  background: #f9f9f9;
  transition: all 0.3s;
}

.signature-card:hover {
  border-color: #667eea;
  box-shadow: 0 4px 12px rgba(102, 126, 234, 0.1);
}

.sig-preview {
  width: 100%;
  height: 120px;
  border: 1px solid #ddd;
  border-radius: 4px;
  overflow: hidden;
  margin-bottom: 12px;
  background: white;
  display: flex;
  align-items: center;
  justify-content: center;
}

.sig-image {
  max-width: 100%;
  max-height: 100%;
  object-fit: contain;
}

.sig-info {
  margin-bottom: 12px;
}

.sig-info h4 {
  margin: 0 0 5px 0;
  font-size: 14px;
  color: #333;
}

.sig-type {
  font-size: 12px;
  color: #999;
  margin: 3px 0;
}

.sig-date {
  font-size: 12px;
  color: #999;
  margin: 3px 0;
}

.default-badge {
  display: inline-block;
  background: #667eea;
  color: white;
  padding: 4px 8px;
  border-radius: 4px;
  font-size: 12px;
  margin-top: 8px;
}

.sig-actions {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}

/* Buttons */
.btn-primary,
.btn-secondary,
.btn-mini,
.btn-danger {
  padding: 10px 16px;
  border: none;
  border-radius: 6px;
  font-size: 14px;
  cursor: pointer;
  transition: all 0.3s;
  font-weight: 500;
}

.btn-primary {
  background: #667eea;
  color: white;
}

.btn-primary:hover:not(:disabled) {
  background: #5568d3;
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
}

.btn-primary:disabled {
  background: #ccc;
  cursor: not-allowed;
  opacity: 0.6;
}

.btn-secondary {
  background: #6c757d;
  color: white;
}

.btn-secondary:hover {
  background: #5a6268;
  transform: translateY(-2px);
}

.btn-mini {
  padding: 6px 12px;
  font-size: 12px;
  background: #667eea;
  color: white;
}

.btn-mini:hover {
  background: #5568d3;
}

.btn-danger {
  background: #dc3545;
  color: white;
}

.btn-danger:hover {
  background: #c82333;
}

/* Footer */
.footer-actions {
  margin-top: 40px;
  text-align: center;
  padding-top: 20px;
  border-top: 2px solid #eee;
}

@media (max-width: 768px) {
  .setup-container {
    padding: 20px;
  }

  h2 {
    font-size: 24px;
  }

  .signatures-list {
    grid-template-columns: 1fr;
  }

  .signature-canvas {
    height: 250px;
  }
}
</style>
