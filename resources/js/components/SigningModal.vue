<template>
  <div v-if="isOpen" class="signing-modal-overlay" @click.self="close">
    <div class="signing-modal">
      <!-- Header -->
      <div class="modal-header">
        <h2>Sign Document</h2>
        <button @click="close" class="close-btn">✕</button>
      </div>

      <!-- Content -->
      <div class="modal-body">
        <!-- PDF Preview -->
        <div class="pdf-preview-section">
          <h3>Document Preview</h3>
          <div class="pdf-viewer" ref="pdfViewer">
            <div class="pdf-toolbar">
              <button class="btn-mini" @click="prevPage" :disabled="placementPage <= 1 || pdfLoading">Prev</button>
              <span class="page-indicator">{{ placementPage }} / {{ pages || '-' }}</span>
              <button class="btn-mini" @click="nextPage" :disabled="!pages || placementPage >= pages || pdfLoading">Next</button>
            </div>

            <div v-if="pdfLoading" class="loading">Loading PDF...</div>
            <div v-else class="pdf-stage" ref="pdfStage">
              <div class="pdf-page-wrap" ref="pageWrap">
                <VuePDF
                  v-if="pdf"
                  class="pdf-page"
                  :pdf="pdf"
                  :page="placementPage"
                />

                <div
                  v-if="selectedSignatureId && signatureImageUrl"
                  class="signature-overlay"
                  :style="signatureOverlayStyle"
                  @pointerdown.prevent="onSigPointerDown"
                >
                  <img :src="signatureImageUrl" class="signature-img" alt="Signature" />
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Signature Placement -->
        <div class="signature-section">
          <h3>Place Your Signature</h3>
          
          <!-- Signature Selection -->
          <div class="signature-select">
            <label>Select Signature:</label>
            <select v-model="selectedSignatureId" class="form-control">
              <option :value="null">-- Choose a signature --</option>
              <option v-for="sig in signatures" :key="sig.id" :value="sig.id">
                {{ sig.name }}
              </option>
            </select>

            <div v-if="selectedSignatureId && signatureImageUrl" class="selected-sig-preview">
              <img :src="signatureImageUrl" alt="Selected signature" />
            </div>

            <button @click="goToSignatureSetup" class="btn-secondary btn-sm">
              + Create New Signature
            </button>
          </div>

          <!-- Page & Position Selection -->
          <div v-if="selectedSignatureId" class="placement-controls">
            <div class="control-group">
              <label>Page:</label>
              <input 
                v-model.number="placementPage" 
                type="number" 
                min="1" 
                :max="pageCount"
                class="form-control"
              >
              <span class="help-text">of {{ pageCount }}</span>
            </div>

            <p class="help-text">
              Drag the signature on the document preview. Position and size will be saved automatically.
            </p>
          </div>
        </div>
      </div>

      <!-- Footer -->
      <div class="modal-footer">
        <button @click="close" class="btn-secondary">Cancel</button>
        <button 
          @click="saveSignature" 
          class="btn-primary"
          :disabled="!selectedSignatureId || saving"
        >
          {{ saving ? 'Saving...' : '✓ Save Signature' }}
        </button>
      </div>

      <!-- Message -->
      <div v-if="message" :class="['message', messageType]">
        {{ message }}
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount, watch, nextTick } from 'vue';
import { useRouter } from 'vue-router';
import axios from 'axios';
import { useAuthStore } from '../stores/auth';
import { VuePDF, usePDF } from '@tato30/vue-pdf';
import '@tato30/vue-pdf/style.css';

const props = defineProps({
  isOpen: Boolean,
  documentId: Number,
  pageCount: Number,
});

const emit = defineEmits(['close', 'signed']);

const router = useRouter();
const authStore = useAuthStore();
const pdfLoading = ref(false);
const pdfViewer = ref(null);
const pdfStage = ref(null);
const pageWrap = ref(null);

const pdfSource = ref(null);
function onPdfError(reason) {
  console.error('PDF viewer error:', reason);
  showMessage('PDF viewer error: ' + (reason?.message || String(reason)), 'error');
}

const { pdf, pages } = usePDF(pdfSource, { onError: onPdfError });

const signatures = ref([]);
const selectedSignatureId = ref(null);
const signatureImageUrl = ref('');
const signatureImageObjectUrl = ref('');
const placementPage = ref(1);
const saving = ref(false);
const message = ref('');
const messageType = ref('info');

const sigX = ref(24);
const sigY = ref(24);
const sigW = ref(160);
const sigH = ref(60);
const isDragging = ref(false);
const dragOffsetX = ref(0);
const dragOffsetY = ref(0);

onMounted(async () => {
  await loadSignatures();
});

onBeforeUnmount(() => {
  cleanupSignatureImageUrl();
  detachDragListeners();
});

// Watch for modal open
watch(() => props.isOpen, async (newVal) => {
  if (newVal && props.documentId) {
    await loadSignatures();
    await loadPdf();
    await nextTick();
  } else if (!newVal) {
    cleanupPdf();
  }
});

watch(() => placementPage.value, async () => {
  if (props.isOpen) {
    resetSignaturePosition();
  }
});

watch(() => selectedSignatureId.value, async (newVal) => {
  cleanupSignatureImageUrl();
  if (newVal) {
    await loadSignatureImage(newVal);
    resetSignaturePosition();
  }
});

async function loadPdf() {
  pdfLoading.value = true;
  try {
    const res = await axios.get(`/api/documents/${props.documentId}/view-url`, {
      responseType: 'arraybuffer',
    });
    pdfSource.value = new Uint8Array(res.data);
    placementPage.value = 1;
  } catch (e) {
    console.error('Failed to load PDF:', e);
    showMessage('Failed to load PDF: ' + (e.response?.data?.message || e.message), 'error');
  } finally {
    pdfLoading.value = false;
  }
}

function cleanupPdf() {
  pdfSource.value = null;
}

function prevPage() {
  if (placementPage.value > 1) placementPage.value -= 1;
}

function nextPage() {
  if (pages.value && placementPage.value < pages.value) placementPage.value += 1;
}

async function loadSignatureImage(signatureId) {
  try {
    const res = await axios.get(`/api/signatures/${signatureId}/image`, {
      responseType: 'blob',
    });
    const blob = new Blob([res.data], { type: res.data?.type || 'image/png' });
    const url = URL.createObjectURL(blob);
    signatureImageObjectUrl.value = url;
    signatureImageUrl.value = url;

    // Update overlay size based on actual image dimensions (cropped PNG)
    await new Promise((resolve) => {
      const img = new Image();
      img.onload = () => {
        const wrapEl = pageWrap.value;
        const wrapW = wrapEl?.getBoundingClientRect?.().width || 0;

        const ratio = img.naturalWidth && img.naturalHeight ? img.naturalWidth / img.naturalHeight : 1;

        // Choose a default display height, then compute width by ratio.
        // Clamp so it doesn't become too large or too small.
        const targetH = 60;
        let targetW = Math.round(targetH * ratio);

        // Reasonable bounds
        const maxW = wrapW ? Math.max(80, Math.floor(wrapW * 0.7)) : 420;
        targetW = Math.min(Math.max(targetW, 80), maxW);

        sigH.value = targetH;
        sigW.value = targetW;
        resolve();
      };
      img.onerror = () => resolve();
      img.src = url;
    });
  } catch (e) {
    console.error('Failed to load signature image:', e);
    showMessage('Failed to load signature image', 'error');
  }
}

function cleanupSignatureImageUrl() {
  if (signatureImageObjectUrl.value) {
    URL.revokeObjectURL(signatureImageObjectUrl.value);
    signatureImageObjectUrl.value = '';
  }
  signatureImageUrl.value = '';
}

function resetSignaturePosition() {
  sigX.value = 24;
  sigY.value = 24;
  clampSignature();
}

function clampSignature() {
  const wrapEl = pageWrap.value;
  if (!wrapEl) return;
  const rect = wrapEl.getBoundingClientRect();
  const maxX = Math.max(0, rect.width - sigW.value);
  const maxY = Math.max(0, rect.height - sigH.value);
  sigX.value = Math.min(Math.max(0, sigX.value), maxX);
  sigY.value = Math.min(Math.max(0, sigY.value), maxY);
}

function onSigPointerDown(e) {
  const wrapEl = pageWrap.value;
  const viewerEl = pdfViewer.value;
  if (!wrapEl || !viewerEl) return;

  isDragging.value = true;
  const wrapRect = wrapEl.getBoundingClientRect();
  const localX = e.clientX - wrapRect.left;
  const localY = e.clientY - wrapRect.top;
  dragOffsetX.value = localX - sigX.value;
  dragOffsetY.value = localY - sigY.value;
  attachDragListeners();
}

function onPointerMove(e) {
  if (!isDragging.value) return;
  const wrapEl = pageWrap.value;
  const viewerEl = pdfViewer.value;
  if (!wrapEl || !viewerEl) return;

  const wrapRect = wrapEl.getBoundingClientRect();
  const localX = e.clientX - wrapRect.left;
  const localY = e.clientY - wrapRect.top;

  sigX.value = localX - dragOffsetX.value;
  sigY.value = localY - dragOffsetY.value;
  clampSignature();
}

function onPointerUp() {
  if (!isDragging.value) return;
  isDragging.value = false;
  detachDragListeners();
}

function attachDragListeners() {
  window.addEventListener('pointermove', onPointerMove);
  window.addEventListener('pointerup', onPointerUp);
}

function detachDragListeners() {
  window.removeEventListener('pointermove', onPointerMove);
  window.removeEventListener('pointerup', onPointerUp);
}

const signatureOverlayStyle = computed(() => {
  return {
    width: sigW.value + 'px',
    height: sigH.value + 'px',
    transform: `translate(${sigX.value}px, ${sigY.value}px)`,
  };
});

async function loadSignatures() {
  try {
    const res = await axios.get('/api/signatures');
    console.log('Signatures loaded:', res.data);
    signatures.value = Array.isArray(res.data) ? res.data : [];
    if (signatures.value.length > 0) {
      selectedSignatureId.value = signatures.value[0].id;
    } else {
      console.warn('No signatures found for user');
    }
  } catch (e) {
    console.error('Failed to load signatures:', e);
    showMessage('Failed to load signatures: ' + (e.response?.data?.message || e.message), 'error');
  }
}

async function saveSignature() {
  if (!selectedSignatureId.value) {
    showMessage('Please select a signature', 'error');
    return;
  }

  if (!pageWrap.value) {
    showMessage('PDF is not ready yet', 'error');
    return;
  }

  const wrapRect = pageWrap.value.getBoundingClientRect();
  const w = Math.max(1, wrapRect.width);
  const h = Math.max(1, wrapRect.height);

  const xNorm = (sigX.value) / w;
  const yNorm = (sigY.value) / h;
  const wNorm = sigW.value / w;
  const hNorm = sigH.value / h;

  saving.value = true;
  try {
    const response = await axios.post(`/api/documents/${props.documentId}/placements`, {
      signerUserId: authStore.user.id,
      placements: [
        {
          page: placementPage.value,
          x: xNorm,
          y: yNorm,
          w: wNorm,
          h: hNorm,
          signatureId: selectedSignatureId.value,
        }
      ]
    });

    showMessage('✅ Signature placed successfully!', 'success');
    setTimeout(() => {
      emit('signed');
      close();
    }, 1500);
  } catch (e) {
    showMessage('Failed to save signature: ' + (e.response?.data?.message || e.message), 'error');
  } finally {
    saving.value = false;
  }
}

function showMessage(msg, type = 'info') {
  message.value = msg;
  messageType.value = type;
  setTimeout(() => {
    message.value = '';
  }, 3000);
}

function goToSignatureSetup() {
  close();
  router.push('/signature-setup');
}

function close() {
  emit('close');
}
</script>

<style scoped>
.signing-modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.7);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
}

.signing-modal {
  background: white;
  border-radius: 12px;
  width: 90%;
  max-width: 1000px;
  max-height: 90vh;
  display: flex;
  flex-direction: column;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
}

.modal-header {
  padding: 20px;
  border-bottom: 1px solid #eee;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.modal-header h2 {
  margin: 0;
  font-size: 20px;
  color: #333;
}

.close-btn {
  background: none;
  border: none;
  font-size: 24px;
  cursor: pointer;
  color: #999;
}

.close-btn:hover {
  color: #333;
}

.modal-body {
  flex: 1;
  overflow-y: auto;
  padding: 20px;
  display: grid;
  grid-template-columns: 1fr 300px;
  gap: 20px;
}

.pdf-preview-section h3,
.signature-section h3 {
  margin-top: 0;
  margin-bottom: 15px;
  font-size: 16px;
  color: #333;
}

.pdf-viewer {
  border: 1px solid #ddd;
  border-radius: 8px;
  overflow: hidden;
  background: #f5f5f5;
  height: 500px;
  position: relative;
  overflow: auto;
}

.pdf-toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  padding: 8px 10px;
  background: #fff;
  border-bottom: 1px solid #eee;
  position: sticky;
  top: 0;
  z-index: 20;
}

.page-indicator {
  font-size: 13px;
  color: #333;
}

.btn-mini {
  background: #6c757d;
  color: #fff;
  border: none;
  border-radius: 6px;
  padding: 6px 10px;
  cursor: pointer;
  font-size: 12px;
}

.btn-mini:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.pdf-stage {
  position: relative;
  padding: 10px;
}

.pdf-page-wrap {
  position: relative;
  width: 100%;
}

.pdf-page {
  width: 100%;
}

.pdf-canvas-wrap {
  position: relative;
  min-height: 100%;
}

.pdf-canvas {
  display: block;
  width: 100%;
  height: auto;
}

.signature-overlay {
  position: absolute;
  top: 0;
  left: 0;
  cursor: grab;
  user-select: none;
  touch-action: none;
  z-index: 10;
}

.signature-overlay:active {
  cursor: grabbing;
}

.signature-img {
  width: 100%;
  height: 100%;
  object-fit: contain;
  border: none;
  border-radius: 6px;
}

.selected-sig-preview {
  width: 100%;
  height: 80px;
  border: 1px solid #eee;
  border-radius: 8px;
  background: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 10px;
}

.selected-sig-preview img {
  max-width: 100%;
  max-height: 100%;
  object-fit: contain;
}

.loading {
  display: flex;
  align-items: center;
  justify-content: center;
  height: 100%;
  color: #999;
}

.signature-select {
  margin-bottom: 20px;
}

.signature-select label {
  display: block;
  margin-bottom: 8px;
  font-weight: 500;
  color: #333;
}

.form-control {
  width: 100%;
  padding: 8px 12px;
  border: 1px solid #ddd;
  border-radius: 6px;
  font-size: 14px;
  margin-bottom: 10px;
}

.form-control:focus {
  outline: none;
  border-color: #667eea;
  box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.btn-secondary {
  background: #6c757d;
  color: white;
  border: none;
  padding: 8px 12px;
  border-radius: 6px;
  cursor: pointer;
  font-size: 12px;
  width: 100%;
}

.btn-secondary:hover {
  background: #5a6268;
}

.placement-controls {
  margin-top: 20px;
}

.control-group {
  margin-bottom: 15px;
}

.control-group label {
  display: block;
  margin-bottom: 5px;
  font-weight: 500;
  font-size: 13px;
  color: #333;
}

.position-inputs,
.size-inputs {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
}

.position-inputs > div,
.size-inputs > div {
  display: flex;
  flex-direction: column;
}

.position-inputs label,
.size-inputs label {
  font-size: 12px;
  margin-bottom: 3px;
}

.help-text {
  font-size: 12px;
  color: #999;
  margin-top: 5px;
}

.modal-footer {
  padding: 15px 20px;
  border-top: 1px solid #eee;
  display: flex;
  gap: 10px;
  justify-content: flex-end;
}

.btn-primary,
.btn-secondary {
  padding: 10px 16px;
  border: none;
  border-radius: 6px;
  cursor: pointer;
  font-weight: 500;
  transition: all 0.3s;
}

.btn-primary {
  background: #667eea;
  color: white;
}

.btn-primary:hover:not(:disabled) {
  background: #5568d3;
}

.btn-primary:disabled {
  background: #ccc;
  cursor: not-allowed;
}

.message {
  position: fixed;
  bottom: 20px;
  right: 20px;
  padding: 15px 20px;
  border-radius: 8px;
  font-weight: 500;
  z-index: 1001;
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

@media (max-width: 768px) {
  .modal-body {
    grid-template-columns: 1fr;
  }

  .pdf-viewer {
    height: 300px;
  }
}
</style>
