<template>
  <Head title="QR Signature Position" />
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
              <div class="rounded-2xl border border-base-200 bg-base-200/40 p-3 sm:p-4">
                <div class="relative mx-auto aspect-[210/297] w-full max-w-lg rounded-xl border border-base-200 bg-white shadow" ref="pdfPage">
                  <div class="absolute left-4 top-4 text-xs text-base-content/40">PDF Page Preview (A4)</div>
                  <div
                    ref="qrBox"
                    class="absolute flex cursor-grab items-center justify-center rounded-xl border-2 border-dashed border-primary/60 bg-primary/10 text-primary transition touch-none"
                    :style="qrBoxStyle"
                    @pointerdown="startDrag"
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
import { ref, reactive, computed, onMounted, onBeforeUnmount } from 'vue';
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { useToastStore } from '../stores/toast';
import { formatApiError } from '../utils/errors';

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
const hasChanges = ref(false);
const toastStore = useToastStore();

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

onBeforeUnmount(() => {
  stopDrag();
});

async function loadPosition() {
  try {
    const response = await axios.get(`/api/documents/${props.documentId}/qr-position`);
    const payload = response.data?.data ?? response.data;
    const pos = payload?.qr_position ?? payload?.qrPosition ?? payload;
    if (!pos) {
      throw new Error('Missing QR position data');
    }
    
    qrPosition.x = pos.x;
    qrPosition.y = pos.y;
    qrPosition.width = pos.width;
    qrPosition.height = pos.height;
    qrPosition.page = pos.page;
    
    hasChanges.value = false;
  } catch (error) {
    console.error('Failed to load position:', error);
    toastStore.error(formatApiError('Failed to load QR position', error));
  }
}

function startDrag(event) {
  event.preventDefault();
  if (!pdfPage.value || !qrBox.value) return;
  isDragging.value = true;

  const qrRect = qrBox.value.getBoundingClientRect();

  dragOffset.x = event.clientX - qrRect.left;
  dragOffset.y = event.clientY - qrRect.top;

  if (qrBox.value.setPointerCapture) {
    qrBox.value.setPointerCapture(event.pointerId);
  }

  window.addEventListener('pointermove', onDrag);
  window.addEventListener('pointerup', stopDrag);
  window.addEventListener('pointercancel', stopDrag);

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

function stopDrag(event) {
  isDragging.value = false;
  window.removeEventListener('pointermove', onDrag);
  window.removeEventListener('pointerup', stopDrag);
  window.removeEventListener('pointercancel', stopDrag);
  if (event?.pointerId != null && qrBox.value?.releasePointerCapture) {
    try {
      qrBox.value.releasePointerCapture(event.pointerId);
    } catch (e) {
      // No-op if the pointer is already released.
    }
  }
  if (qrBox.value) {
    qrBox.value.style.cursor = 'grab';
  }
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
    
    hasChanges.value = false;
    
    toastStore.success('QR position saved.');
  } catch (error) {
    console.error('Failed to save position:', error);
    toastStore.error(formatApiError('Failed to save QR position', error));
  }
}

function resetPosition() {
  qrPosition.x = 0.5;
  qrPosition.y = 0.5;
  qrPosition.width = 0.15;
  qrPosition.height = 0.15;
  qrPosition.page = 1;
  hasChanges.value = true;
  toastStore.info('QR position reset to center.');
}

function setPreset(preset, showToast = true) {
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
    if (showToast) {
      const label = preset.replace('-', ' ');
      toastStore.info(`Moved to ${label}.`);
    }
  }
}

function testPresets() {
  const presets = ['top-left', 'top-right', 'bottom-right', 'bottom-left', 'center'];
  let index = 0;

  toastStore.info('Preset tour started.');
  
  const interval = setInterval(() => {
    if (index >= presets.length) {
      clearInterval(interval);
      toastStore.success('Preset tour complete.');
      return;
    }
    
    setPreset(presets[index], false);
    index++;
  }, 1000);
}
</script>
