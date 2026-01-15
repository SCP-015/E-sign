<template>
  <div v-if="isOpen" class="modal modal-open" @click.self="close">
    <div class="modal-box w-11/12 max-w-5xl p-0 max-h-[95vh] overflow-hidden flex flex-col sm:max-h-[90vh]">
      <div class="flex flex-wrap items-center justify-between gap-3 border-b border-base-200 px-4 py-4 sm:px-6">
        <div>
          <h2 class="text-lg font-semibold">Sign Document</h2>
          <p class="text-xs text-base-content/60">Place your signature on the PDF.</p>
        </div>
        <button @click="close" class="btn btn-ghost btn-sm">✕</button>
      </div>

      <div class="grid flex-1 min-h-0 gap-4 overflow-y-auto px-4 py-4 sm:gap-6 sm:px-6 sm:py-6 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
        <div class="min-w-0 space-y-3">
          <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <h3 class="text-sm font-semibold">Document Preview</h3>
            <div class="flex flex-wrap items-center gap-2">
              <button class="btn btn-outline btn-xs" @click="prevPage" :disabled="placementPage <= 1 || pdfLoading">Prev</button>
              <span class="badge badge-outline text-xs">{{ placementPage }} / {{ pages || '-' }}</span>
              <button class="btn btn-outline btn-xs" @click="nextPage" :disabled="!pages || placementPage >= pages || pdfLoading">Next</button>
            </div>
          </div>

          <div class="rounded-2xl border border-base-200 bg-base-200/40 p-2 sm:p-3">
            <div class="relative min-h-64 max-h-[50vh] overflow-auto rounded-xl bg-white sm:max-h-[55vh] lg:max-h-[65vh]" ref="pdfViewer">
              <div v-if="pdfLoading" class="flex h-full items-center justify-center text-sm text-base-content/60">
                Loading PDF...
              </div>
              <div v-else class="relative p-2 sm:p-3">
                <div class="relative" ref="pageWrap">
                  <VuePDF
                    v-if="pdf"
                    class="w-full"
                    :pdf="pdf"
                    :page="placementPage"
                    @loaded="onPdfLoaded"
                  />

                  <div
                    v-if="(selectedSignatureId || assignMode) && signatureImageUrl"
                    class="absolute left-0 top-0 cursor-grab select-none touch-none"
                    :style="signatureOverlayStyle"
                    @pointerdown.prevent="onSigPointerDown"
                  >
                    <img
                      :src="signatureImageUrl"
                      class="h-full w-full rounded-lg object-contain"
                      :alt="assignMode ? 'Signature placeholder' : 'Signature'"
                    />
                    <div
                      v-if="assignMode"
                      class="absolute -top-6 left-0 rounded-full bg-primary/90 px-2 py-0.5 text-[10px] font-semibold text-white shadow"
                    >
                      Assigned to: {{ assignEmail || assignName || 'Signer' }}
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="min-w-0 space-y-4">
          <div>
            <h3 class="text-sm font-semibold">Signature Placement</h3>
            <p class="text-xs text-base-content/60">Choose a signature or assign to another signer.</p>
          </div>

          <div class="rounded-2xl border border-base-200 bg-base-100 p-4 space-y-4">
            <div class="flex items-center gap-2 rounded-full border border-base-200 bg-base-200/60 p-1 text-xs font-semibold">
              <button
                type="button"
                class="flex-1 rounded-full px-3 py-2 transition"
                :class="assignMode ? 'text-base-content/60' : 'bg-base-100 shadow text-base-content'"
                @click="assignMode = false"
              >
                Sign Myself
              </button>
              <button
                v-if="isDocumentOwner"
                type="button"
                class="flex-1 rounded-full px-3 py-2 transition"
                :class="assignMode ? 'bg-base-100 shadow text-base-content' : 'text-base-content/60'"
                @click="assignMode = true"
              >
                Assign to Other
              </button>
            </div>

            <div v-if="!assignMode" class="space-y-3">
              <label class="text-xs font-semibold">Select Signature</label>
              <select v-model="selectedSignatureId" class="select select-bordered select-sm w-full">
                <option :value="null">-- Choose a signature --</option>
                <option v-for="sig in signatures" :key="sig.id" :value="sig.id">
                  {{ sig.name }}
                </option>
              </select>

              <div v-if="selectedSignatureId && signatureImageUrl" class="flex h-20 items-center justify-center rounded-xl border border-base-200 bg-base-100">
                <img :src="signatureImageUrl" alt="Selected signature" class="max-h-16 max-w-full object-contain" />
              </div>

              <button @click="goToSignatureSetup" class="btn btn-outline btn-sm w-full">
                Create New Signature
              </button>
            </div>

            <div v-else class="space-y-3">
              <div class="space-y-2 rounded-xl border border-base-200 bg-base-200/40 p-3 text-xs">
                <label class="flex items-center gap-2">
                  <input v-model="includeOwner" type="checkbox" class="checkbox checkbox-xs">
                  <span class="font-semibold">Include me as signer</span>
                </label>
                <label v-if="includeOwner && assignees.length > 0 && signingMode === 'PARALLEL'" class="flex items-center gap-2">
                  <input v-model="ownerFirst" type="checkbox" class="checkbox checkbox-xs">
                  <span class="font-semibold">I sign first</span>
                </label>
                <div v-if="includeOwner && assignees.length > 0 && signingMode === 'SEQUENTIAL'" class="space-y-1">
                  <label class="flex items-center gap-2">
                    <input v-model="ownerFirst" type="checkbox" class="checkbox checkbox-xs">
                    <span class="font-semibold">I sign first</span>
                  </label>
                  <div v-if="!ownerFirst" class="ml-6">
                    <label class="text-xs font-semibold block mb-1">My signing order</label>
                    <input
                      v-model.number="ownerOrder"
                      type="number"
                      min="1"
                      :max="assignees.length + 1"
                      class="input input-bordered input-xs w-16"
                    >
                  </div>
                </div>
              </div>

              <div>
                <label class="text-xs font-semibold">Signing Mode</label>
                <select v-model="signingMode" class="select select-bordered select-sm w-full">
                  <option value="PARALLEL">PARALLEL</option>
                  <option value="SEQUENTIAL">SEQUENTIAL</option>
                </select>
              </div>

              <div class="space-y-2">
                <label class="text-xs font-semibold">Add Signers</label>
                <div class="flex gap-2">
                  <input
                    v-model="newAssigneeEmail"
                    type="email"
                    placeholder="Email"
                    class="input input-bordered input-sm flex-1"
                  >
                  <button
                    @click="addAssignee"
                    :disabled="!newAssigneeEmail"
                    class="btn btn-primary btn-sm"
                  >
                    Add
                  </button>
                </div>
              </div>

              <div v-if="assignees.length > 0" class="space-y-2">
                <label class="text-xs font-semibold">Signers List</label>
                <div class="space-y-2 rounded-xl border border-base-200 bg-base-200/40 p-3">
                  <div v-for="(signer, idx) in assignees" :key="idx" class="flex items-center gap-2 text-xs">
                    <div v-if="signingMode === 'SEQUENTIAL'" class="flex items-center gap-1">
                      <input
                        v-model.number="signer.order"
                        type="number"
                        :min="includeOwner && ownerFirst ? 2 : 1"
                        :disabled="includeOwner && ownerFirst"
                        class="input input-bordered input-xs w-12"
                      >
                    </div>
                    <div v-else class="w-12 text-center font-semibold text-base-content/60">
                      —
                    </div>
                    <div class="flex-1 min-w-0">
                      <div class="font-semibold truncate">{{ signer.name }}</div>
                      <div class="text-base-content/60 truncate">{{ signer.email }}</div>
                    </div>
                    <button
                      @click="removeAssignee(idx)"
                      class="btn btn-ghost btn-xs"
                    >
                      ✕
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div v-if="selectedSignatureId || assignMode" class="rounded-2xl border border-base-200 bg-base-100 p-4">
            <label class="text-xs font-semibold">Page</label>
            <div class="mt-2 flex flex-wrap items-center gap-2">
              <input
                v-model.number="placementPage"
                type="number"
                min="1"
                :max="pageCount"
                class="input input-bordered input-sm w-full sm:w-24"
              >
              <span class="text-xs text-base-content/60">of {{ pageCount }}</span>
            </div>
            <p class="mt-3 text-xs text-base-content/60">
              Drag the {{ assignMode ? 'placeholder' : 'signature' }} on the preview. Position and size will be saved automatically.
            </p>
          </div>
        </div>
      </div>

      <div class="modal-action border-t border-base-200 px-4 py-4 sm:px-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
        <button @click="close" class="btn btn-ghost">Cancel</button>
        <button
          v-if="canFinalize"
          @click="finalizeDocument"
          class="btn btn-primary"
          :disabled="finalizing || saving"
        >
          {{ finalizing ? 'Finalizing...' : 'Finalize Document' }}
        </button>
        <button
          v-else-if="!assignMode"
          @click="saveSignature"
          class="btn btn-primary"
          :disabled="!selectedSignatureId || saving || finalizing"
        >
          {{ saving ? 'Saving...' : 'Save Signature' }}
        </button>
        <button
          v-else
          @click="assignToOther"
          class="btn btn-primary"
          :disabled="assignees.length === 0 || saving || finalizing"
        >
          {{ saving ? 'Assigning...' : '📧 Send Invitation' }}
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

const documentOwnerId = ref(null);
const documentSigners = ref([]);
const documentStatus = ref(null);

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
const finalizing = ref(false);

const assignMode = ref(false);
const assignees = ref([]);
const newAssigneeEmail = ref('');
const newAssigneeName = ref('');

const includeOwner = ref(true);
const ownerFirst = ref(true);
const ownerOrder = ref(null);
const signingMode = ref('PARALLEL');

const isDocumentOwner = computed(() => {
  if (!documentOwnerId.value || !authStore.user?.id) return false;
  return Number(documentOwnerId.value) === Number(authStore.user.id);
});
const canFinalize = computed(() => {
  if (!isDocumentOwner.value) return false;
  const status = String(documentStatus.value || '').toLowerCase();
  return status === 'signed';
});
const hasOtherSigners = computed(() => {
  const userId = authStore.user?.id;
  if (!userId) return false;

  return documentSigners.value.some((signer) => {
    const signerUserId = signer?.user_id ?? signer?.userId;
    if (!signerUserId) return true;
    return Number(signerUserId) !== Number(userId);
  });
});
const shouldAutoFinalize = computed(() => isDocumentOwner.value && !hasOtherSigners.value);

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

watch(() => assignMode.value, (newVal) => {
  if (newVal) {
    selectedSignatureId.value = null;
    // Set a placeholder for assignment if needed
    signatureImageUrl.value = 'https://placehold.co/400x200?text=Signer+Placeholder';
    resetSignaturePosition();
  } else {
    loadSignatures();
  }
});

async function loadPdf() {
  pdfLoading.value = true;
  try {
    // Fetch document details to get owner_id
    const docRes = await axios.get(`/api/documents/${props.documentId}`);
    const doc = docRes.data?.data ?? docRes.data;
    documentOwnerId.value = doc.user_id ?? doc.userId;
    documentSigners.value = Array.isArray(doc.signers) ? doc.signers : [];
    documentStatus.value = doc.status ?? null;
    
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
      const defaultSig =
        signatures.value.find((s) => s.is_default === true || s.isDefault === true) || signatures.value[0];
      selectedSignatureId.value = defaultSig?.id ?? null;
    } else {
      selectedSignatureId.value = null;
    }
  } catch (e) {
    console.error('Failed to load signatures:', e);
    toastStore.error(formatApiError('Failed to load signatures', e));
    signatures.value = [];
    selectedSignatureId.value = null;
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

  const coords = getNormalizedCoordinates();
  if (!coords) {
    toastStore.error('PDF is not ready yet.');
    return;
  }
  const { xNorm, yNorm, wNorm, hNorm } = coords;

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

    if (shouldAutoFinalize.value) {
      try {
        await axios.post(`/api/documents/${props.documentId}/finalize`);
        toastStore.success('Document signed and finalized.');
      } catch (e) {
        toastStore.error(formatApiError('Signature saved but finalize failed', e));
      }
    } else {
      toastStore.success('Signature placement saved.');
    }
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

function addAssignee() {
  if (!newAssigneeEmail.value) {
    toastStore.error('Please fill in email.');
    return;
  }

  let nextOrder = 1;
  if (signingMode.value === 'SEQUENTIAL') {
    // For SEQUENTIAL, auto-assign order avoiding owner's order
    const usedOrders = new Set(assignees.value.map(s => s.order || 0));
    
    // If owner is included and signing first, start from order 2
    if (includeOwner.value && ownerFirst.value) {
      usedOrders.add(1); // Owner takes order 1
      nextOrder = 2;
    } else if (includeOwner.value && !ownerFirst.value && ownerOrder.value) {
      usedOrders.add(ownerOrder.value);
    }
    
    while (usedOrders.has(nextOrder)) {
      nextOrder++;
    }
  } else {
    // For PARALLEL, order doesn't matter
    nextOrder = assignees.value.length + 1;
  }

  assignees.value.push({
    email: newAssigneeEmail.value,
    name: newAssigneeName.value || newAssigneeEmail.value.split('@')[0],
    order: nextOrder,
  });

  newAssigneeEmail.value = '';
  newAssigneeName.value = '';
}

function removeAssignee(idx) {
  assignees.value.splice(idx, 1);
}

async function assignToOther() {
  if (assignees.value.length === 0) {
    toastStore.error('Please add at least one signer.');
    return;
  }

  const coords = getNormalizedCoordinates();
  if (!coords) {
    toastStore.error('PDF is not ready yet.');
    return;
  }

  saving.value = true;
  try {
    const normalizedMode = String(signingMode.value || 'PARALLEL').toUpperCase();
    const modePayload = normalizedMode === 'SEQUENTIAL' ? 'SEQUENTIAL' : 'PARALLEL';

    const includeMe = !!includeOwner.value;
    let finalOwnerOrder = null;
    let finalSigners = [...assignees.value];
    
    if (includeMe) {
      if (modePayload === 'SEQUENTIAL') {
        if (ownerFirst.value) {
          finalOwnerOrder = 1;
          // Shift all assignee orders down by 1
          finalSigners = finalSigners.map(s => ({
            ...s,
            order: (s.order || 1) + 1
          }));
        } else if (ownerOrder.value) {
          finalOwnerOrder = ownerOrder.value;
          // Adjust assignee orders to avoid collision with owner order
          finalSigners = finalSigners.map(s => {
            let newOrder = s.order || 1;
            if (newOrder >= finalOwnerOrder) {
              newOrder++;
            }
            return { ...s, order: newOrder };
          });
        } else {
          finalOwnerOrder = Math.max(...assignees.value.map(s => s.order || 0)) + 1;
        }
      } else {
        // PARALLEL mode: owner order doesn't matter
        finalOwnerOrder = null;
      }
    }

    await axios.post(`/api/documents/${props.documentId}/signers`, {
      includeOwner: includeMe,
      ownerOrder: finalOwnerOrder,
      signingMode: modePayload,
      signers: finalSigners,
    });

    for (const signer of assignees.value) {
      await axios.post(`/api/documents/${props.documentId}/placements`, {
        email: signer.email,
        placements: [
          {
            page: placementPage.value,
            x: coords.xNorm,
            y: coords.yNorm,
            w: coords.wNorm,
            h: coords.hNorm,
          },
        ],
      });
    }

    toastStore.success('Invitations sent successfully.');
    setTimeout(() => {
      emit('signed');
      close();
    }, 1500);
  } catch (e) {
    toastStore.error(formatApiError('Failed to send invitations', e));
  } finally {
    saving.value = false;
  }
}

async function finalizeDocument() {
  if (!props.documentId) {
    toastStore.error('Document is not available.');
    return;
  }

  finalizing.value = true;
  try {
    await axios.post(`/api/documents/${props.documentId}/finalize`);
    toastStore.success('Document finalized.');
    emit('signed');
    close();
  } catch (e) {
    toastStore.error(formatApiError('Failed to finalize document', e));
  } finally {
    finalizing.value = false;
  }
}

function getNormalizedCoordinates() {
  const bounds = getPdfBounds();
  if (!bounds) return null;

  const w = Math.max(1, bounds.width);
  const h = Math.max(1, bounds.height);

  return {
    xNorm: (sigX.value - bounds.x) / w,
    yNorm: (sigY.value - bounds.y) / h,
    wNorm: sigW.value / w,
    hNorm: sigH.value / h,
  };
}
function goToSignatureSetup() {
  close();
  router.visit('/signature-setup');
}

function close() {
  assignees.value = [];
  newAssigneeEmail.value = '';
  newAssigneeName.value = '';
  assignMode.value = false;
  ownerOrder.value = null;
  ownerFirst.value = true;
  emit('close');
}
</script>
