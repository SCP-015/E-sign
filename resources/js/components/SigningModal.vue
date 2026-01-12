<template>
  <div v-if="isOpen" class="modal modal-open" @click.self="close">
    <div class="modal-box w-11/12 max-w-5xl p-0 max-h-[90vh] overflow-y-auto">
      <div class="flex items-center justify-between border-b border-base-200 px-6 py-4">
        <div>
          <h2 class="text-lg font-semibold">Sign Document</h2>
          <p class="text-xs text-base-content/60">Place your signature on the PDF.</p>
        </div>
        <button @click="close" class="btn btn-ghost btn-sm">✕</button>
      </div>

      <div class="grid gap-6 px-6 py-6 lg:grid-cols-[2fr_1fr]">
        <div class="space-y-3">
          <div class="items-center justify-between">
            <h3 class="text-sm font-semibold">Document Preview</h3>
            <div class="flex items-center gap-2">
              <button class="btn btn-outline btn-xs" @click="prevPage" :disabled="placementPage <= 1 || pdfLoading">Prev</button>
              <span class="badge badge-outline text-xs">{{ placementPage }} / {{ pages || '-' }}</span>
              <button class="btn btn-outline btn-xs" @click="nextPage" :disabled="!pages || placementPage >= pages || pdfLoading">Next</button>
            </div>
          </div>

          <div class="rounded-2xl border border-base-200 bg-base-200/40 p-3">
            <div class="relative h-130 overflow-auto rounded-xl bg-white" ref="pdfViewer">
              <div v-if="pdfLoading" class="flex h-full items-center justify-center text-sm text-base-content/60">
                Loading PDF...
              </div>
              <div v-else class="relative p-3">
                <div class="relative" ref="pageWrap">
                  <VuePDF
                    v-if="pdf"
                    class="w-full"
                    :pdf="pdf"
                    :page="placementPage"
                    @loaded="onPdfLoaded"
                  />

                  <div
                    v-if="selectedSignatureId && signatureImageUrl"
                    class="absolute left-0 top-0 cursor-grab select-none"
                    :style="signatureOverlayStyle"
                    @pointerdown.prevent="onSigPointerDown"
                  >
                    <img :src="signatureImageUrl" class="h-full w-full rounded-lg object-contain" alt="Signature" />
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="space-y-4">
          <div>
            <h3 class="text-sm font-semibold">Place Your Signature</h3>
            <p class="text-xs text-base-content/60">Choose a signature and position it on the preview.</p>
          </div>

          <div class="rounded-2xl border border-base-200 bg-base-100 p-4">
            <label class="text-xs font-semibold">Select Signature</label>
            <select v-model="selectedSignatureId" class="select select-bordered select-sm mt-2 w-full">
              <option :value="null">-- Choose a signature --</option>
              <option v-for="sig in signatures" :key="sig.id" :value="sig.id">
                {{ sig.name }}
              </option>
            </select>

            <div v-if="selectedSignatureId && signatureImageUrl" class="mt-4 flex h-20 items-center justify-center rounded-xl border border-base-200 bg-base-100">
              <img :src="signatureImageUrl" alt="Selected signature" class="max-h-16 max-w-full object-contain" />
            </div>

            <button @click="goToSignatureSetup" class="btn btn-outline btn-sm mt-4 w-full">
              Create New Signature
            </button>
          </div>

          <div v-if="selectedSignatureId" class="rounded-2xl border border-base-200 bg-base-100 p-4">
            <label class="text-xs font-semibold">Page</label>
            <div class="mt-2 flex items-center gap-2">
              <input
                v-model.number="placementPage"
                type="number"
                min="1"
                :max="pageCount"
                class="input input-bordered input-sm w-24"
              >
              <span class="text-xs text-base-content/60">of {{ pageCount }}</span>
            </div>
            <p class="mt-3 text-xs text-base-content/60">
              Drag the signature on the preview. Position and size will be saved automatically.
            </p>
          </div>
        </div>
      </div>

      <div class="modal-action border-t border-base-200 px-6 py-4">
        <button @click="close" class="btn btn-ghost">Cancel</button>
        <button
          @click="saveSignature"
          class="btn btn-primary"
          :disabled="!selectedSignatureId || saving"
        >
          {{ saving ? 'Saving...' : 'Save Signature' }}
        </button>
      </div>

    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount, watch, nextTick } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import { useAuthStore } from '../stores/auth';
import { useToastStore } from '../stores/toast';
import { formatApiError } from '../utils/errors';
import { VuePDF, usePDF } from '@tato30/vue-pdf';
import '@tato30/vue-pdf/style.css';

const props = defineProps({
  isOpen: Boolean,
  documentId: Number,
  pageCount: Number,
});

const emit = defineEmits(['close', 'signed']);

const authStore = useAuthStore();
const toastStore = useToastStore();
const pdfLoading = ref(false);
const pdfViewer = ref(null);
const pageWrap = ref(null);

const pdfSource = ref(null);
function onPdfError(reason) {
  console.error('PDF viewer error:', reason);
  toastStore.error('PDF viewer error: ' + (reason?.message || String(reason)));
}

const { pdf, pages } = usePDF(pdfSource, { onError: onPdfError });

const signatures = ref([]);
const selectedSignatureId = ref(null);
const signatureImageUrl = ref('');
const signatureImageObjectUrl = ref('');
const placementPage = ref(1);
const saving = ref(false);

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
    toastStore.error(formatApiError('Failed to load PDF', e));
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

function getPdfBounds() {
  const wrapEl = pageWrap.value;
  if (!wrapEl) return null;
  const wrapRect = wrapEl.getBoundingClientRect();
  const pageEl =
    wrapEl.querySelector('.vue-pdf__page') ||
    wrapEl.querySelector('.page') ||
    wrapEl.querySelector('canvas');
  if (!pageEl) {
    return { x: 0, y: 0, width: wrapRect.width, height: wrapRect.height };
  }

  const pageRect = pageEl.getBoundingClientRect();
  return {
    x: pageRect.left - wrapRect.left,
    y: pageRect.top - wrapRect.top,
    width: pageRect.width,
    height: pageRect.height,
  };
}

function onPdfLoaded() {
  nextTick(() => {
    clampSignature();
  });
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

    await new Promise((resolve) => {
      const img = new Image();
      img.onload = () => {
        const bounds = getPdfBounds();
        const wrapW = bounds?.width || 0;

        const ratio = img.naturalWidth && img.naturalHeight ? img.naturalWidth / img.naturalHeight : 1;

        const targetH = 60;
        let targetW = Math.round(targetH * ratio);

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
    toastStore.error(formatApiError('Failed to load signature image', e));
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
  const bounds = getPdfBounds();
  sigX.value = (bounds?.x || 0) + 24;
  sigY.value = (bounds?.y || 0) + 24;
  clampSignature();
}

function clampSignature() {
  const bounds = getPdfBounds();
  if (!bounds) return;
  const maxX = Math.max(bounds.x, bounds.x + bounds.width - sigW.value);
  const maxY = Math.max(bounds.y, bounds.y + bounds.height - sigH.value);
  sigX.value = Math.min(Math.max(bounds.x, sigX.value), maxX);
  sigY.value = Math.min(Math.max(bounds.y, sigY.value), maxY);
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
    const list = res.data?.data ?? res.data;
    signatures.value = Array.isArray(list) ? list : [];

    if (signatures.value.length > 0) {
      const defaultSig = signatures.value.find((s) => s.is_default) || signatures.value[0];
      selectedSignatureId.value = defaultSig?.id ?? null;
    } else {
      selectedSignatureId.value = null;
    }
  } catch (e) {
    console.error('Failed to load signatures:', e);
    toastStore.error(formatApiError('Failed to load signatures', e));
  }
}

async function saveSignature() {
  if (!selectedSignatureId.value) {
    toastStore.error('Please select a signature.');
    return;
  }

  const signerUserId = authStore.user?.id;
  if (!signerUserId) {
    toastStore.error('Unauthenticated.');
    return;
  }

  const bounds = getPdfBounds();
  if (!bounds) {
    toastStore.error('PDF is not ready yet.');
    return;
  }

  const w = Math.max(1, bounds.width);
  const h = Math.max(1, bounds.height);

  const xNorm = (sigX.value - bounds.x) / w;
  const yNorm = (sigY.value - bounds.y) / h;
  const wNorm = sigW.value / w;
  const hNorm = sigH.value / h;

  saving.value = true;
  try {
    await axios.post(`/api/documents/${props.documentId}/placements`, {
      signerUserId,
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

    toastStore.success('Signature placement saved.');
    setTimeout(() => {
      emit('signed');
      close();
    }, 1500);
  } catch (e) {
    toastStore.error(formatApiError('Failed to save signature placement', e));
  } finally {
    saving.value = false;
  }
}

function goToSignatureSetup() {
  close();
  router.visit('/signature-setup');
}

function close() {
  emit('close');
}
</script>
