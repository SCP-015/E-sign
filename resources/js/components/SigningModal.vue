<template>
  <div v-if="isOpen" class="modal modal-open" @click.self="close">
    <div class="modal-box w-11/12 max-w-7xl p-0 max-h-[95vh] overflow-hidden flex flex-col sm:max-h-[90vh]">
      <div class="flex flex-wrap items-center justify-between gap-3 border-b border-base-200 px-4 py-4 sm:px-6">
        <div>
          <h2 class="text-lg font-semibold">Sign Document</h2>
          <p class="text-xs text-base-content/60">Place your signature on the PDF.</p>
        </div>
        <button @click="close" class="btn btn-ghost btn-sm">✕</button>
      </div>

      <div class="grid flex-1 min-h-0 gap-4 overflow-y-auto px-4 py-4 sm:gap-6 sm:px-6 sm:py-6 lg:grid-cols-[minmax(0,3fr)_minmax(0,2fr)]">
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
            <div class="relative min-h-64 max-h-[60vh] overflow-auto rounded-xl bg-white sm:max-h-[65vh] lg:max-h-[75vh]" ref="pdfViewer">
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
                    <div class="absolute inset-0 rounded-lg border-2 border-dashed border-primary/60 pointer-events-none"></div>
                    <img
                      :src="signatureImageUrl"
                      class="h-full w-full rounded-lg object-contain"
                      :alt="assignMode ? 'Signature placeholder' : 'Signature'"
                    />
                    <div
                      class="absolute -left-1.5 -top-1.5 h-3 w-3 rounded border border-primary bg-base-100 cursor-nwse-resize"
                      @pointerdown.stop.prevent="onResizePointerDown('tl', $event)"
                    ></div>
                    <div
                      class="absolute -right-1.5 -top-1.5 h-3 w-3 rounded border border-primary bg-base-100 cursor-nesw-resize"
                      @pointerdown.stop.prevent="onResizePointerDown('tr', $event)"
                    ></div>
                    <div
                      class="absolute -left-1.5 -bottom-1.5 h-3 w-3 rounded border border-primary bg-base-100 cursor-nesw-resize"
                      @pointerdown.stop.prevent="onResizePointerDown('bl', $event)"
                    ></div>
                    <div
                      class="absolute -right-1.5 -bottom-1.5 h-3 w-3 rounded border border-primary bg-base-100 cursor-nwse-resize"
                      @pointerdown.stop.prevent="onResizePointerDown('br', $event)"
                    ></div>
                    <div
                      v-if="assignMode"
                      class="absolute -top-6 left-0 rounded-full bg-primary/90 px-2 py-0.5 text-[10px] font-semibold text-white shadow"
                    >
                      Assigned to: {{ assignEmail || assignName || 'Signer' }}
                    </div>
                  </div>

                  <div
                    v-if="showQrPreview"
                    class="absolute pointer-events-none z-10"
                    :style="qrOverlayStyle"
                    data-qr-preview="true"
                  >
                    <div class="relative h-full w-full rounded border-2 border-dashed border-warning/60 bg-warning/10 flex items-center justify-center shadow-lg">
                      <svg viewBox="0 0 100 100" class="h-full w-full text-warning/70 p-1">
                        <rect x="10" y="10" width="30" height="30" fill="currentColor" />
                        <rect x="60" y="10" width="30" height="30" fill="currentColor" />
                        <rect x="10" y="60" width="30" height="30" fill="currentColor" />
                        <rect x="65" y="65" width="8" height="8" fill="currentColor" />
                        <rect x="78" y="65" width="8" height="8" fill="currentColor" />
                        <rect x="65" y="78" width="8" height="8" fill="currentColor" />
                        <rect x="78" y="78" width="8" height="8" fill="currentColor" />
                      </svg>
                      <div class="absolute -top-6 left-1/2 -translate-x-1/2 whitespace-nowrap rounded-full bg-warning/90 px-2 py-0.5 text-[10px] font-semibold text-warning-content shadow">
                        QR Preview
                      </div>
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
                <div class="flex items-center gap-2 rounded-full border border-base-200 bg-base-200/60 p-1 text-[11px] font-semibold">
                  <button
                    type="button"
                    class="flex-1 rounded-full px-3 py-2 transition"
                    :class="assigneeInputMode === 'tenant' ? 'bg-base-100 shadow text-base-content' : 'text-base-content/60'"
                    @click="assigneeInputMode = 'tenant'"
                  >
                    Tenant Member
                  </button>
                  <button
                    type="button"
                    class="flex-1 rounded-full px-3 py-2 transition"
                    :class="assigneeInputMode === 'email' ? 'bg-base-100 shadow text-base-content' : 'text-base-content/60'"
                    @click="assigneeInputMode = 'email'"
                  >
                    Email
                  </button>
                </div>
                <div class="space-y-2">
                  <select
                    v-model="selectedTenantMemberEmail"
                    class="select select-bordered select-sm w-full"
                    :disabled="assigneeInputMode !== 'tenant' || tenantMembersLoading || selectableTenantMembers.length === 0"
                  >
                    <option value="">-- Select a tenant member --</option>
                    <option v-for="m in selectableTenantMembers" :key="m.email" :value="m.email">
                      {{ m.name }} ({{ m.email }})
                    </option>
                  </select>
                  <div v-if="tenantMembersLoading" class="text-[11px] text-base-content/60">Loading members...</div>
                </div>
                <div class="flex gap-2">
                  <input
                    v-model="newAssigneeEmail"
                    type="email"
                    placeholder="Email"
                    class="input input-bordered input-sm flex-1"
                    :disabled="assigneeInputMode !== 'email'"
                  >
                  <button
                    @click="addAssignee"
                    :disabled="!newAssigneeEmail || isSelfAssigneeEmail || isDuplicateAssigneeEmail"
                    class="btn btn-primary btn-sm"
                  >
                    Add
                  </button>
                </div>
                <div v-if="isSelfAssigneeEmail" class="text-[11px] text-warning">
                  You cannot assign a document to your own email.
                </div>
                <div v-else-if="isDuplicateAssigneeEmail" class="text-[11px] text-warning">
                  This signer is already in the list.
                </div>
                <div v-if="sequentialOrderError" class="text-[11px] text-warning">
                  {{ sequentialOrderError }}
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
          :disabled="(!canSendInvitationsFinal) || saving || finalizing"
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
import { isApiSuccess, unwrapApiData, unwrapApiList } from '../utils/api';
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

const currentOrganizationId = ref(null);
const tenantMembers = ref([]);
const tenantMembersLoading = ref(false);
const selectedTenantMemberEmail = ref('');
const assigneeInputMode = ref('tenant');

const includeOwner = ref(true);
const ownerFirst = ref(true);
const ownerOrder = ref(null);
const signingMode = ref('PARALLEL');

const qrPositionConfig = ref(null);

const isDocumentOwner = computed(() => {
  if (!documentOwnerId.value || !authStore.user?.id) return false;
  return String(documentOwnerId.value) === String(authStore.user.id);
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
    return String(signerUserId) !== String(userId);
  });
});
const shouldAutoFinalize = computed(() => isDocumentOwner.value && !hasOtherSigners.value);

const normalizedCurrentUserEmail = computed(() => String(authStore.user?.email || '').trim().toLowerCase());
const normalizedNewAssigneeEmail = computed(() => String(newAssigneeEmail.value || '').trim().toLowerCase());
const isSelfAssigneeEmail = computed(() => {
  if (!normalizedCurrentUserEmail.value) return false;
  return normalizedNewAssigneeEmail.value === normalizedCurrentUserEmail.value;
});
const isDuplicateAssigneeEmail = computed(() => {
  const email = normalizedNewAssigneeEmail.value;
  if (!email) return false;
  return assignees.value.some((a) => String(a?.email || '').trim().toLowerCase() === email);
});

const canSendInvitations = computed(() => {
  if (assignees.value.length > 0) return true;

  const hasPending = !!String(newAssigneeEmail.value || '').trim();
  if (!hasPending) return false;
  if (isSelfAssigneeEmail.value) return false;
  if (isDuplicateAssigneeEmail.value) return false;
  return true;
});

const sequentialOrderError = computed(() => {
  if (String(signingMode.value || '').toUpperCase() !== 'SEQUENTIAL') return '';

  const list = Array.isArray(assignees.value) ? assignees.value : [];
  const assigneeOrders = list
    .map((s) => Number(s?.order))
    .filter((n) => Number.isFinite(n) && n >= 1);

  let ownerOrderActual = null;
  if (includeOwner.value) {
    if (ownerFirst.value) {
      ownerOrderActual = 1;
    } else {
      const val = Number(ownerOrder.value);
      if (!Number.isFinite(val) || val < 1) {
        return 'Please set your signing order.';
      }
      ownerOrderActual = val;
    }
  }

  const combined = [...assigneeOrders];
  if (ownerOrderActual !== null) combined.push(ownerOrderActual);

  if (combined.length === 0) return '';

  const unique = new Set(combined);
  if (unique.size !== combined.length) {
    return 'Signing order must be unique.';
  }

  const expectedMax = combined.length;
  for (let i = 1; i <= expectedMax; i++) {
    if (!unique.has(i)) {
      return `Signing order must be sequential without gaps (1-${expectedMax}).`;
    }
  }

  return '';
});

const canSendInvitationsFinal = computed(() => {
  if (!canSendInvitations.value) return false;
  if (String(signingMode.value || '').toUpperCase() === 'SEQUENTIAL' && sequentialOrderError.value) return false;
  return true;
});

const selectableTenantMembers = computed(() => {
  const currentUserId = String(authStore.user?.id);
  const currentEmail = normalizedCurrentUserEmail.value;
  return (Array.isArray(tenantMembers.value) ? tenantMembers.value : [])
    .filter((m) => {
      const id = String(m?.userId ?? m?.user_id ?? m?.id);
      const email = String(m?.email || m?.user?.email || '').trim().toLowerCase();
      if (currentUserId && id && id === currentUserId) return false;
      if (currentEmail && email && email === currentEmail) return false;
      return true;
    })
    .map((m) => {
      const user = m?.user || {};
      const email = m?.email || user?.email || '';
      const name = m?.name || user?.name || email;
      return {
        userId: m?.userId ?? m?.user_id ?? null,
        name,
        email,
        role: m?.role,
      };
    })
    .filter((m) => !!m.email);
});

const sigX = ref(24);
const sigY = ref(24);
const sigW = ref(160);
const sigH = ref(60);
const sigRatio = ref(sigW.value / sigH.value);
const sigMinW = 60;
const sigMinH = 24;
const sigMaxW = ref(600);
const sigMaxH = ref(240);
const isDragging = ref(false);
const isResizing = ref(false);
const dragOffsetX = ref(0);
const dragOffsetY = ref(0);
const resizeHandle = ref(null);
const resizeStartW = ref(0);
const resizeStartH = ref(0);
const resizeStartX = ref(0);
const resizeStartY = ref(0);
const resizeStartSigX = ref(0);
const resizeStartSigY = ref(0);

const pdfPageSizeMm = ref(null);

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
    await loadTenantMembers();
    await loadQrPosition();
    await nextTick();
  } else if (!newVal) {
    cleanupPdf();
  }
});

watch(() => placementPage.value, async () => {
  if (props.isOpen) {
    resetSignaturePosition();
    await nextTick();
    await updatePdfPageSize();
    refreshSigSizeLimits();
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

function normalizeAssigneeOrders() {
  if (signingMode.value !== 'SEQUENTIAL') return;
  if (!assignees.value || assignees.value.length === 0) return;

  const includeMe = !!includeOwner.value;

  // If I am included and signing first, invitees must start from order 2.
  if (includeMe && ownerFirst.value) {
    let next = 2;
    assignees.value = assignees.value.map((s) => ({
      ...s,
      order: next++,
    }));
    return;
  }

  // Otherwise, normalize to a clean 1..N sequence (skipping my order if set).
  const reserved = includeMe && ownerOrder.value ? Number(ownerOrder.value) : null;
  let next = 1;
  assignees.value = assignees.value.map((s) => {
    while (reserved !== null && next === reserved) next++;
    return {
      ...s,
      order: next++,
    };
  });
}

watch(
  () => [signingMode.value, includeOwner.value, ownerFirst.value, ownerOrder.value],
  () => {
    normalizeAssigneeOrders();
  }
);

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

async function loadQrPosition() {
  try {
    const res = await axios.get(`/api/documents/${props.documentId}/qr-position`);
    console.log('[QR Position] Raw response:', res.data);
    
    // Parse response structure from Postman: { data: { qrPosition: {...}, documentId: 14 } }
    const payload = res.data?.data ?? res.data;
    console.log('[QR Position] Payload:', payload);
    
    // Try both snake_case and camelCase
    const qrConfig = payload?.qrPosition ?? payload?.qr_position ?? null;
    console.log('[QR Position] QR Config:', qrConfig);
    
    if (qrConfig) {
      qrPositionConfig.value = qrConfig;
    } else {
      console.warn('[QR Position] No QR config found in response');
      qrPositionConfig.value = null;
    }
  } catch (e) {
    console.error('[QR Position] Failed to load:', e);
    qrPositionConfig.value = null;
  }
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
    updatePdfPageSize();
    refreshSigSizeLimits();
  });
}

async function updatePdfPageSize() {
  try {
    if (!pdf.value || !placementPage.value) {
      pdfPageSizeMm.value = null;
      return;
    }

    const documentProxy = await pdf.value?.promise;
    if (!documentProxy) {
      pdfPageSizeMm.value = null;
      return;
    }

    const page = await documentProxy.getPage(placementPage.value);
    // pdf.js viewport at scale=1 matches PDF points (1 pt = 1/72 inch)
    const viewport = page.getViewport({ scale: 1 });
    const widthMm = (viewport.width * 25.4) / 72;
    const heightMm = (viewport.height * 25.4) / 72;

    pdfPageSizeMm.value = {
      widthMm,
      heightMm,
    };
  } catch (e) {
    console.error('[QR Preview] Failed to resolve PDF page size:', e);
    pdfPageSizeMm.value = null;
  }
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
        if (sigH.value) sigRatio.value = sigW.value / sigH.value;
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

function refreshSigSizeLimits() {
  const bounds = getPdfBounds();
  const wrapW = bounds?.width || 0;
  const wrapH = bounds?.height || 0;

  sigMaxW.value = wrapW ? Math.max(sigMinW, Math.floor(wrapW * 0.95)) : 600;
  sigMaxH.value = wrapH ? Math.max(sigMinH, Math.floor(wrapH * 0.95)) : 240;

  applySignatureSize(sigW.value, sigH.value);
}

function clampSize(nextW, nextH) {
  const w = Math.min(Math.max(Math.round(nextW), sigMinW), sigMaxW.value);
  const h = Math.min(Math.max(Math.round(nextH), sigMinH), sigMaxH.value);
  return { w, h };
}

function applySignatureSize(nextW, nextH) {
  const w = Number.isFinite(nextW) ? nextW : sigW.value;
  const h = Number.isFinite(nextH) ? nextH : sigH.value;

  sigW.value = Math.min(Math.max(Math.round(w), sigMinW), sigMaxW.value);
  sigH.value = Math.min(Math.max(Math.round(h), sigMinH), sigMaxH.value);
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
  isResizing.value = false;
  const wrapRect = wrapEl.getBoundingClientRect();
  const localX = e.clientX - wrapRect.left;
  const localY = e.clientY - wrapRect.top;
  dragOffsetX.value = localX - sigX.value;
  dragOffsetY.value = localY - sigY.value;
  attachDragListeners();
}

function onResizePointerDown(handle, e) {
  const wrapEl = pageWrap.value;
  const viewerEl = pdfViewer.value;
  if (!wrapEl || !viewerEl) return;

  isResizing.value = true;
  isDragging.value = false;
  resizeHandle.value = handle;

  const wrapRect = wrapEl.getBoundingClientRect();
  resizeStartX.value = e.clientX - wrapRect.left;
  resizeStartY.value = e.clientY - wrapRect.top;
  resizeStartW.value = sigW.value;
  resizeStartH.value = sigH.value;
  resizeStartSigX.value = sigX.value;
  resizeStartSigY.value = sigY.value;

  attachDragListeners();
}

function onPointerMove(e) {
  const wrapEl = pageWrap.value;
  const viewerEl = pdfViewer.value;
  if (!wrapEl || !viewerEl) return;

  const wrapRect = wrapEl.getBoundingClientRect();
  const localX = e.clientX - wrapRect.left;
  const localY = e.clientY - wrapRect.top;

  if (isResizing.value) {
    const dx = localX - resizeStartX.value;
    const dy = localY - resizeStartY.value;
    const handle = String(resizeHandle.value || 'br');

    const ratio = Number(sigRatio.value) || (resizeStartH.value ? resizeStartW.value / resizeStartH.value : 1);
    const signX = handle.includes('l') ? -1 : 1;

    const delta = Math.abs(dx) > Math.abs(dy) ? dx : dy;
    const nextWRaw = resizeStartW.value + delta * signX;
    const nextHRaw = ratio ? nextWRaw / ratio : resizeStartH.value;
    const { w: nextW, h: nextH } = clampSize(nextWRaw, nextHRaw);

    sigW.value = nextW;
    sigH.value = nextH;

    if (handle.includes('l')) {
      sigX.value = resizeStartSigX.value + (resizeStartW.value - nextW);
    }
    if (handle.includes('t')) {
      sigY.value = resizeStartSigY.value + (resizeStartH.value - nextH);
    }
    clampSignature();
    return;
  }

  if (isDragging.value) {
    sigX.value = localX - dragOffsetX.value;
    sigY.value = localY - dragOffsetY.value;
    clampSignature();
  }
}

function onPointerUp() {
  if (!isDragging.value && !isResizing.value) return;
  isDragging.value = false;
  isResizing.value = false;
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

const showQrPreview = computed(() => {
  // Check all conditions
  const hasPdf = !!pdf.value;
  const totalPages = pages.value || 0;
  const currentPage = placementPage.value || 0;
  const hasConfig = !!qrPositionConfig.value;
  const isLastPage = totalPages > 0 && currentPage === totalPages;
  
  const shouldShow = hasPdf && isLastPage && hasConfig;
  
  console.log('[QR Preview] Should show:', shouldShow, {
    hasPdf,
    totalPages,
    currentPage,
    isLastPage,
    hasConfig,
    configDetails: qrPositionConfig.value
  });
  
  return shouldShow;
});

const qrOverlayStyle = computed(() => {
  const bounds = getPdfBounds();
  if (!bounds || !qrPositionConfig.value || !pdfPageSizeMm.value) return { display: 'none' };
  
  const config = qrPositionConfig.value;
  
  // Get QR config values from API (these are in PDF units)
  const qrSize = config.size ?? 35;
  const marginRight = config.marginRight ?? 15;
  const marginBottom = config.marginBottom ?? 15;
  
  // Convert mm (TCPDF default unit in finalize) to pixels using actual rendered page size
  const pxPerMmX = bounds.width / pdfPageSizeMm.value.widthMm;
  const pxPerMmY = bounds.height / pdfPageSizeMm.value.heightMm;
  
  const qrSizePx = qrSize * pxPerMmX;
  const marginRightPx = marginRight * pxPerMmX;
  const marginBottomPx = marginBottom * pxPerMmY;
  
  // Position from bottom-right corner
  const qrX = bounds.x + bounds.width - qrSizePx - marginRightPx;
  const qrY = bounds.y + bounds.height - qrSizePx - marginBottomPx;
  
  return {
    left: `${qrX}px`,
    top: `${qrY}px`,
    width: `${qrSizePx}px`,
    height: `${qrSizePx}px`,
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

  if (isSelfAssigneeEmail.value) {
    toastStore.error('You cannot assign a document to your own email.');
    return;
  }

  if (isDuplicateAssigneeEmail.value) {
    toastStore.error('This signer is already in the list.');
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
  selectedTenantMemberEmail.value = '';
}

async function loadTenantMembers() {
  tenantMembersLoading.value = true;
  try {
    const currentOrgRes = await axios.get('/api/organizations/current');
    const payload = currentOrgRes?.data;
    const org = isApiSuccess(payload) ? unwrapApiData(payload) : null;
    const orgId = org?.id ?? org?.organizationId ?? null;
    currentOrganizationId.value = orgId;

    if (!orgId) {
      tenantMembers.value = [];
      return;
    }

    const membersRes = await axios.get(`/api/organizations/${orgId}/members`);
    const membersPayload = membersRes?.data;
    if (!isApiSuccess(membersPayload)) {
      tenantMembers.value = [];
      return;
    }

    tenantMembers.value = unwrapApiList(membersPayload);
  } catch (e) {
    tenantMembers.value = [];
  } finally {
    tenantMembersLoading.value = false;
  }
}

watch(selectedTenantMemberEmail, (email) => {
  const selected = selectableTenantMembers.value.find(
    (m) => String(m.email || '').trim().toLowerCase() === String(email || '').trim().toLowerCase()
  );
  if (!selected) return;

  newAssigneeEmail.value = selected.email;
  newAssigneeName.value = selected.name;
});

watch(assigneeInputMode, (mode) => {
  if (mode === 'tenant') {
    newAssigneeEmail.value = '';
    newAssigneeName.value = '';
    return;
  }

  selectedTenantMemberEmail.value = '';
});

function removeAssignee(idx) {
  assignees.value.splice(idx, 1);
}

async function assignToOther() {
  if (String(signingMode.value || '').toUpperCase() === 'SEQUENTIAL' && sequentialOrderError.value) {
    toastStore.error(sequentialOrderError.value);
    return;
  }

  if (assignees.value.length === 0 && canSendInvitations.value) {
    addAssignee();
  }

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
          const minAssigneeOrder = Math.min(...finalSigners.map(s => (s.order || 1)));
          if (minAssigneeOrder <= 1) {
            finalSigners = finalSigners.map(s => ({
              ...s,
              order: (s.order || 1) + 1
            }));
          }
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
