<template>
  <div class="min-h-screen">
    <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 px-4 py-10">
      <div>
        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-base-content/50">Placement Lab</p>
        <h3 class="text-2xl font-bold">QR Signature Position</h3>
        <p class="text-sm text-base-content/60">Drag the QR badge to set placement coordinates.</p>
      </div>

      <div class="card border border-base-200 bg-base-100/90 shadow-sm">
        <div class="card-body gap-6">
          <div class="flex flex-col gap-4 lg:flex-row">
            <div class="flex-1">
              <div class="rounded-2xl border border-base-200 bg-base-200/40 p-4">
                <div class="relative mx-auto aspect-[210/297] w-full max-w-lg rounded-xl border border-base-200 bg-white shadow" ref="pdfPage">
                  <div class="absolute left-4 top-4 text-xs text-base-content/40">PDF Page Preview (A4)</div>
                  <div
                    ref="qrBox"
                    class="absolute flex cursor-grab items-center justify-center rounded-xl border-2 border-dashed border-primary/60 bg-primary/10 text-primary transition"
                    :style="qrBoxStyle"
                    @mousedown="startDrag"
                  >
                    <div class="flex flex-col items-center gap-2">
                      <svg viewBox="0 0 100 100" class="h-10 w-10">
                        <rect x="10" y="10" width="35" height="35" fill="currentColor" />
                        <rect x="55" y="10" width="35" height="35" fill="currentColor" />
                        <rect x="10" y="55" width="35" height="35" fill="currentColor" />
                        <rect x="60" y="60" width="10" height="10" fill="currentColor" />
                        <rect x="75" y="60" width="10" height="10" fill="currentColor" />
                        <rect x="60" y="75" width="10" height="10" fill="currentColor" />
                        <rect x="75" y="75" width="10" height="10" fill="currentColor" />
                      </svg>
                      <p class="text-xs font-semibold">Drag Me</p>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <div class="w-full lg:w-80">
              <div class="flex flex-col gap-4">
                <div class="rounded-2xl border border-base-200 bg-base-100 p-4 text-sm">
                  <h4 class="font-semibold">Current Position (0-1)</h4>
                  <div class="mt-3 space-y-1 font-mono text-xs text-base-content/70">
                    <p>X: {{ qrPosition.x.toFixed(4) }} ({{ (qrPosition.x * 100).toFixed(1) }}%)</p>
                    <p>Y: {{ qrPosition.y.toFixed(4) }} ({{ (qrPosition.y * 100).toFixed(1) }}%)</p>
                    <p>Width: {{ qrPosition.width.toFixed(4) }} ({{ (qrPosition.width * 100).toFixed(1) }}%)</p>
                    <p>Height: {{ qrPosition.height.toFixed(4) }} ({{ (qrPosition.height * 100).toFixed(1) }}%)</p>
                    <p>Page: {{ qrPosition.page }}</p>
                  </div>
                </div>

                <div class="flex flex-col gap-2">
                  <button @click="savePosition" class="btn btn-primary btn-sm" :disabled="!hasChanges">
                    Save Position to Backend
                  </button>
                  <button @click="resetPosition" class="btn btn-outline btn-sm">Reset to Center</button>
                  <button @click="testPresets" class="btn btn-ghost btn-sm">Try Presets</button>
                </div>

                <div v-if="message" :class="['alert shadow-sm', alertClass]">
                  <span>{{ message }}</span>
                </div>

                <div class="flex flex-wrap gap-2">
                  <button @click="setPreset('top-left')" class="btn btn-xs btn-outline">Top Left</button>
                  <button @click="setPreset('top-right')" class="btn btn-xs btn-outline">Top Right</button>
                  <button @click="setPreset('bottom-left')" class="btn btn-xs btn-outline">Bottom Left</button>
                  <button @click="setPreset('bottom-right')" class="btn btn-xs btn-outline">Bottom Right</button>
                  <button @click="setPreset('center')" class="btn btn-xs btn-outline">Center</button>
                </div>
              </div>
            </div>
          </div>
        </div>
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

const alertClass = computed(() => {
  if (messageType.value === 'success') return 'alert-success';
  if (messageType.value === 'error') return 'alert-error';
  return 'alert-info';
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
  
  let newX = event.clientX - pageRect.left - dragOffset.x;
  let newY = event.clientY - pageRect.top - dragOffset.y;
  
  const qrWidth = qrPosition.width * pageRect.width;
  const qrHeight = qrPosition.height * pageRect.height;
  
  newX = Math.max(0, Math.min(newX, pageRect.width - qrWidth));
  newY = Math.max(0, Math.min(newY, pageRect.height - qrHeight));
  
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
    await axios.put(`/api/documents/${props.documentId}/qr-position`, {
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
