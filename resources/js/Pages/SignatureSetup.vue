<template>
  <div class="min-h-screen">
    <div class="mx-auto flex w-full max-w-7xl flex-col gap-8 px-4 py-10">
      <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
          <p class="text-xs font-semibold uppercase tracking-[0.2em] text-base-content/50">Signature Studio</p>
          <h2 class="text-3xl font-bold">Setup Your Signature</h2>
          <p class="text-sm text-base-content/60">Create and manage your digital signatures.</p>
        </div>
        <button @click="goToDashboard" class="btn btn-outline btn-sm">Back to Dashboard</button>
      </div>

      <div class="grid gap-6 lg:grid-cols-[1.2fr,1fr]">
        <div class="card border border-base-200 bg-base-100/90 shadow-sm">
          <div class="card-body gap-4">
            <div>
              <h3 class="text-lg font-semibold">Draw Your Signature</h3>
              <p class="text-sm text-base-content/60">Use your mouse or trackpad to sign.</p>
            </div>

            <div class="rounded-2xl border border-base-200 bg-base-100 p-2">
              <canvas
                ref="signatureCanvas"
                class="h-72 w-full cursor-crosshair rounded-xl"
                @mousedown="startDrawing"
                @mousemove="draw"
                @mouseup="stopDrawing"
                @mouseout="stopDrawing"
              ></canvas>
            </div>

            <div class="flex flex-wrap gap-3">
              <button @click="clearCanvas" class="btn btn-ghost btn-sm">Clear</button>
              <button @click="saveSignature" class="btn btn-primary btn-sm" :disabled="!isDrawn">
                Save Signature
              </button>
            </div>
          </div>
        </div>

        <div class="card border border-base-200 bg-base-100/90 shadow-sm">
          <div class="card-body gap-4">
            <div>
              <h3 class="text-lg font-semibold">Your Saved Signatures</h3>
              <p class="text-sm text-base-content/60">Manage defaults or remove old ones.</p>
            </div>

            <div v-if="signatures.length === 0" class="rounded-2xl border border-dashed border-base-300 bg-base-200/40 p-6 text-center text-sm text-base-content/60">
              No signatures saved yet. Draw and save one above.
            </div>

            <div v-else class="grid gap-4">
              <div v-for="sig in signatures" :key="sig.id" class="rounded-2xl border border-base-200 bg-base-100 p-4 shadow-sm">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
                  <div class="flex w-full items-center justify-center rounded-xl border border-base-200 bg-base-100 p-2 sm:w-40">
                    <img :src="`/api/signatures/${sig.id}/image`" :alt="sig.name" class="max-h-20 w-full object-contain">
                  </div>
                  <div class="flex-1">
                    <h4 class="font-semibold">{{ sig.name }}</h4>
                    <p class="text-xs text-base-content/60">{{ sig.image_type.toUpperCase() }} · {{ formatDate(sig.created_at) }}</p>
                    <span v-if="sig.is_default" class="badge badge-success badge-sm mt-2">Default</span>
                  </div>
                  <div class="flex flex-wrap gap-2">
                    <button
                      v-if="!sig.is_default"
                      @click="setDefault(sig.id)"
                      class="btn btn-outline btn-xs"
                      title="Set as default"
                    >
                      Set Default
                    </button>
                    <button
                      @click="deleteSignature(sig.id)"
                      class="btn btn-error btn-outline btn-xs"
                      title="Delete signature"
                    >
                      Delete
                    </button>
                  </div>
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
import { ref, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import { useToastStore } from '../stores/toast';
import { formatApiError } from '../utils/errors';

const signatureCanvas = ref(null);
const signatures = ref([]);
const isDrawing = ref(false);
const isDrawn = ref(false);
const toastStore = useToastStore();

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
  ctx.clearRect(0, 0, signatureCanvas.value.width, signatureCanvas.value.height);
  isDrawn.value = false;
  toastStore.info('Canvas cleared.');
}

async function saveSignature() {
  if (!isDrawn.value) {
    toastStore.error('Please draw a signature first.');
    return;
  }

  try {
    const blob = await canvasToCroppedPngBlob(signatureCanvas.value, { padding: 8, alphaThreshold: 1 });

    if (!blob) {
      toastStore.error('Failed to create image from canvas.');
      return;
    }

    const formData = new FormData();
    formData.append('image', blob, 'signature.png');
    formData.append('name', `Signature ${new Date().toLocaleDateString()}`);
    formData.append('is_default', signatures.value.length === 0 ? '1' : '0');
    
    await axios.post('/api/signatures', formData);
    
    toastStore.success('Signature saved successfully.');
    clearCanvas();
    await loadSignatures();
  } catch (e) {
    console.error('Failed to save signature:', e);
    console.error('Response data:', e.response?.data);
    toastStore.error(formatApiError('Failed to save signature', e));
  }
}

async function loadSignatures() {
  try {
    const response = await axios.get('/api/signatures');
    signatures.value = response.data;
  } catch (e) {
    console.error('Failed to load signatures:', e);
    toastStore.error(formatApiError('Failed to load signatures', e));
  }
}

async function setDefault(id) {
  try {
    await axios.put(`/api/signatures/${id}/default`);
    toastStore.success('Signature set as default.');
    await loadSignatures();
  } catch (e) {
    toastStore.error(formatApiError('Failed to set default signature', e));
  }
}

async function deleteSignature(id) {
  if (!confirm('Are you sure you want to delete this signature?')) return;
  
  try {
    await axios.delete(`/api/signatures/${id}`);
    toastStore.success('Signature deleted.');
    await loadSignatures();
  } catch (e) {
    toastStore.error(formatApiError('Failed to delete signature', e));
  }
}

function formatDate(dateString) {
  if (!dateString) return '';
  const date = new Date(dateString);
  return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
}

function goToDashboard() {
  router.visit('/dashboard');
}
</script>
