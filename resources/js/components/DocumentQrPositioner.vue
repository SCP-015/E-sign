<template>
  <div class="qr-positioner">
    <h3>🎯 QR Signature Position - Drag & Drop Test</h3>
    
    <div class="document-preview" ref="previewContainer">
      <!-- PDF Preview Placeholder (white page simulation) -->
      <div class="pdf-page" ref="pdfPage">
        <div class="page-label">PDF Page Preview (A4 Size)</div>
        
        <!-- Draggable QR Code -->
        <div 
          class="qr-box"
          ref="qrBox"
          :style="qrBoxStyle"
          @mousedown="startDrag"
        >
          <div class="qr-placeholder">
            <svg viewBox="0 0 100 100" class="qr-icon">
              <rect x="10" y="10" width="35" height="35" fill="currentColor"/>
              <rect x="55" y="10" width="35" height="35" fill="currentColor"/>
              <rect x="10" y="55" width="35" height="35" fill="currentColor"/>
              <rect x="60" y="60" width="10" height="10" fill="currentColor"/>
              <rect x="75" y="60" width="10" height="10" fill="currentColor"/>
              <rect x="60" y="75" width="10" height="10" fill="currentColor"/>
              <rect x="75" y="75" width="10" height="10" fill="currentColor"/>
            </svg>
            <p>Drag Me!</p>
          </div>
        </div>
      </div>
    </div>

    <div class="controls">
      <div class="position-info">
        <h4>Current Position (Relative 0-1):</h4>
        <div class="info-grid">
          <div><strong>X:</strong> {{ qrPosition.x.toFixed(4) }} ({{ (qrPosition.x * 100).toFixed(1) }}%)</div>
          <div><strong>Y:</strong> {{ qrPosition.y.toFixed(4) }} ({{ (qrPosition.y * 100).toFixed(1) }}%)</div>
          <div><strong>Width:</strong> {{ qrPosition.width.toFixed(4) }} ({{ (qrPosition.width * 100).toFixed(1) }}%)</div>
          <div><strong>Height:</strong> {{ qrPosition.height.toFixed(4) }} ({{ (qrPosition.height * 100).toFixed(1) }}%)</div>
          <div><strong>Page:</strong> {{ qrPosition.page }}</div>
        </div>
      </div>

      <div class="actions">
        <button @click="savePosition" class="btn-save" :disabled="!hasChanges">
          💾 Save Position to Backend
        </button>
        <button @click="resetPosition" class="btn-reset">
          🔄 Reset to Center
        </button>
        <button @click="testPresets" class="btn-preset">
          📍 Try Presets
        </button>
      </div>

      <div v-if="message" class="message" :class="messageType">
        {{ message }}
      </div>

      <div class="preset-buttons">
        <button @click="setPreset('top-left')" class="btn-mini">↖️ Top Left</button>
        <button @click="setPreset('top-right')" class="btn-mini">↗️ Top Right</button>
        <button @click="setPreset('bottom-left')" class="btn-mini">↙️ Bottom Left</button>
        <button @click="setPreset('bottom-right')" class="btn-mini">↘️ Bottom Right</button>
        <button @click="setPreset('center')" class="btn-mini">🎯 Center</button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import axios from 'axios';

const props = defineProps({
  documentId: {
    type: Number,
    required: true
  }
});

const qrBox = ref(null);
const pdfPage = ref(null);
const isDragging = ref(false);
const dragOffset = reactive({ x: 0, y: 0 });
const message = ref('');
const messageType = ref('info');
const hasChanges = ref(false);
const initialPosition = ref(null);

const qrPosition = reactive({
  x: 0.5,
  y: 0.5,
  width: 0.15,
  height: 0.15,
  page: 1
});

const qrBoxStyle = computed(() => {
  if (!pdfPage.value) return {};
  
  const pageWidth = pdfPage.value.offsetWidth;
  const pageHeight = pdfPage.value.offsetHeight;
  
  return {
    left: `${qrPosition.x * pageWidth}px`,
    top: `${qrPosition.y * pageHeight}px`,
    width: `${qrPosition.width * pageWidth}px`,
    height: `${qrPosition.height * pageHeight}px`,
  };
});

onMounted(async () => {
  await loadPosition();
});

async function loadPosition() {
  try {
    const response = await axios.get(`/api/documents/${props.documentId}/qr-position`);
    const pos = response.data.qr_position;
    
    qrPosition.x = pos.x;
    qrPosition.y = pos.y;
    qrPosition.width = pos.width;
    qrPosition.height = pos.height;
    qrPosition.page = pos.page;
    
    initialPosition.value = { ...pos };
    hasChanges.value = false;
    
    showMessage('Position loaded from backend', 'success');
  } catch (error) {
    console.error('Failed to load position:', error);
    showMessage('Failed to load position: ' + (error.response?.data?.message || error.message), 'error');
  }
}

function startDrag(event) {
  event.preventDefault();
  isDragging.value = true;
  
  const pageRect = pdfPage.value.getBoundingClientRect();
  const qrRect = qrBox.value.getBoundingClientRect();
  
  dragOffset.x = event.clientX - qrRect.left;
  dragOffset.y = event.clientY - qrRect.top;
  
  document.addEventListener('mousemove', onDrag);
  document.addEventListener('mouseup', stopDrag);
  
  qrBox.value.style.cursor = 'grabbing';
}

function onDrag(event) {
  if (!isDragging.value) return;
  
  const pageRect = pdfPage.value.getBoundingClientRect();
  
  // Calculate new position relative to page
  let newX = event.clientX - pageRect.left - dragOffset.x;
  let newY = event.clientY - pageRect.top - dragOffset.y;
  
  // Constrain within page bounds
  const qrWidth = qrPosition.width * pageRect.width;
  const qrHeight = qrPosition.height * pageRect.height;
  
  newX = Math.max(0, Math.min(newX, pageRect.width - qrWidth));
  newY = Math.max(0, Math.min(newY, pageRect.height - qrHeight));
  
  // Convert to relative coordinates (0-1)
  qrPosition.x = newX / pageRect.width;
  qrPosition.y = newY / pageRect.height;
  
  hasChanges.value = true;
}

function stopDrag() {
  isDragging.value = false;
  document.removeEventListener('mousemove', onDrag);
  document.removeEventListener('mouseup', stopDrag);
  qrBox.value.style.cursor = 'grab';
}

async function savePosition() {
  try {
    const response = await axios.put(`/api/documents/${props.documentId}/qr-position`, {
      x: qrPosition.x,
      y: qrPosition.y,
      width: qrPosition.width,
      height: qrPosition.height,
      page: qrPosition.page
    });
    
    initialPosition.value = { ...qrPosition };
    hasChanges.value = false;
    
    showMessage('✅ Position saved successfully!', 'success');
  } catch (error) {
    console.error('Failed to save position:', error);
    showMessage('❌ Failed to save: ' + (error.response?.data?.message || error.message), 'error');
  }
}

function resetPosition() {
  qrPosition.x = 0.5;
  qrPosition.y = 0.5;
  qrPosition.width = 0.15;
  qrPosition.height = 0.15;
  qrPosition.page = 1;
  hasChanges.value = true;
  showMessage('Position reset to center', 'info');
}

function setPreset(preset) {
  const presets = {
    'top-left': { x: 0.05, y: 0.05 },
    'top-right': { x: 0.80, y: 0.05 },
    'bottom-left': { x: 0.05, y: 0.80 },
    'bottom-right': { x: 0.80, y: 0.80 },
    'center': { x: 0.425, y: 0.425 },
  };
  
  const pos = presets[preset];
  if (pos) {
    qrPosition.x = pos.x;
    qrPosition.y = pos.y;
    hasChanges.value = true;
    showMessage(`Moved to ${preset}`, 'info');
  }
}

function testPresets() {
  const presets = ['top-left', 'top-right', 'bottom-right', 'bottom-left', 'center'];
  let index = 0;
  
  const interval = setInterval(() => {
    if (index >= presets.length) {
      clearInterval(interval);
      showMessage('Preset tour complete!', 'success');
      return;
    }
    
    setPreset(presets[index]);
    index++;
  }, 1000);
}

function showMessage(msg, type = 'info') {
  message.value = msg;
  messageType.value = type;
  
  setTimeout(() => {
    message.value = '';
  }, 3000);
}
</script>

<style scoped>
.qr-positioner {
  max-width: 900px;
  margin: 20px auto;
  padding: 20px;
  background: #f5f5f5;
  border-radius: 8px;
}

h3 {
  text-align: center;
  color: #333;
  margin-bottom: 20px;
}

.document-preview {
  background: #ddd;
  padding: 20px;
  border-radius: 4px;
  margin-bottom: 20px;
}

.pdf-page {
  position: relative;
  width: 100%;
  max-width: 600px;
  aspect-ratio: 210 / 297; /* A4 ratio */
  background: white;
  margin: 0 auto;
  box-shadow: 0 4px 8px rgba(0,0,0,0.1);
  overflow: hidden;
}

.page-label {
  position: absolute;
  top: 10px;
  left: 10px;
  font-size: 12px;
  color: #999;
  pointer-events: none;
}

.qr-box {
  position: absolute;
  background: rgba(59, 130, 246, 0.2);
  border: 2px dashed #3b82f6;
  cursor: grab;
  user-select: none;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: background 0.2s;
}

.qr-box:hover {
  background: rgba(59, 130, 246, 0.3);
  border-color: #2563eb;
}

.qr-placeholder {
  text-align: center;
  color: #3b82f6;
}

.qr-icon {
  width: 60%;
  height: 60%;
  margin-bottom: 5px;
}

.qr-placeholder p {
  margin: 0;
  font-size: 12px;
  font-weight: bold;
}

.controls {
  background: white;
  padding: 20px;
  border-radius: 4px;
}

.position-info h4 {
  margin-top: 0;
  color: #666;
}

.info-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 10px;
  margin-bottom: 20px;
  font-family: monospace;
  background: #f9f9f9;
  padding: 15px;
  border-radius: 4px;
}

.actions {
  display: flex;
  gap: 10px;
  margin-bottom: 15px;
  flex-wrap: wrap;
}

button {
  padding: 10px 20px;
  border: none;
  border-radius: 4px;
  cursor: pointer;
  font-size: 14px;
  font-weight: 500;
  transition: all 0.2s;
}

.btn-save {
  background: #10b981;
  color: white;
}

.btn-save:hover:not(:disabled) {
  background: #059669;
}

.btn-save:disabled {
  background: #d1d5db;
  cursor: not-allowed;
}

.btn-reset {
  background: #6b7280;
  color: white;
}

.btn-reset:hover {
  background: #4b5563;
}

.btn-preset {
  background: #8b5cf6;
  color: white;
}

.btn-preset:hover {
  background: #7c3aed;
}

.preset-buttons {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}

.btn-mini {
  padding: 6px 12px;
  font-size: 12px;
  background: #e5e7eb;
  color: #374151;
}

.btn-mini:hover {
  background: #d1d5db;
}

.message {
  padding: 12px;
  border-radius: 4px;
  margin-top: 15px;
  font-weight: 500;
}

.message.success {
  background: #d1fae5;
  color: #065f46;
  border: 1px solid #10b981;
}

.message.error {
  background: #fee2e2;
  color: #991b1b;
  border: 1px solid #ef4444;
}

.message.info {
  background: #dbeafe;
  color: #1e40af;
  border: 1px solid #3b82f6;
}
</style>
