<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useRoute } from 'vue-router'
import type { Device, DeviceEvent, ReplayAudit } from '@/types/api'
import { devicesApi } from '@/services/api'
import { useUiStore } from '@/stores/ui'

const route = useRoute()
const uiStore = useUiStore()

const deviceId = computed(() => route.params.id as string)
const device = ref<Device | null>(null)
const activeTab = ref<'events' | 'replay' | 'audit'>('events')

// Events tab
const events = ref<DeviceEvent[]>([])
const eventsNextCursor = ref<string | null>(null)
const eventsLoading = ref(false)
const eventsLoadingMore = ref(false)
const eventStatusFilter = ref<string>('')
const expandedEventId = ref<number | null>(null)

// Replay tab
const sinceSeq = ref(0)
const untilSeq = ref<number | undefined>(undefined)
const replayReason = ref('')
const replayLoading = ref(false)
const showReplayConfirm = ref(false)

// Audit tab
const audits = ref<ReplayAudit[]>([])
const auditsLoading = ref(false)

onMounted(async () => {
  try {
    device.value = await devicesApi.get(deviceId.value)
  } catch {
    uiStore.addNotification({ type: 'error', message: 'Failed to load device' })
  }
  await fetchEvents(true)
})

async function fetchEvents(reset = false) {
  if (reset) {
    eventsLoading.value = true
    events.value = []
    eventsNextCursor.value = null
  } else {
    eventsLoadingMore.value = true
  }

  try {
    const result = await devicesApi.events(deviceId.value, {
      status: eventStatusFilter.value || undefined,
      cursor: reset ? undefined : eventsNextCursor.value ?? undefined,
      limit: 50,
    })
    if (reset) {
      events.value = result.items
    } else {
      events.value.push(...result.items)
    }
    eventsNextCursor.value = result.next_cursor
  } catch {
    uiStore.addNotification({ type: 'error', message: 'Failed to load events' })
  } finally {
    eventsLoading.value = false
    eventsLoadingMore.value = false
  }
}

async function fetchAudits() {
  auditsLoading.value = true
  try {
    audits.value = await devicesApi.replayAudits(deviceId.value)
  } catch {
    uiStore.addNotification({ type: 'error', message: 'Failed to load audit trail' })
  } finally {
    auditsLoading.value = false
  }
}

async function switchTab(tab: 'events' | 'replay' | 'audit') {
  activeTab.value = tab
  if (tab === 'audit' && audits.value.length === 0) {
    await fetchAudits()
  }
}

async function handleReplay() {
  if (!showReplayConfirm.value) {
    showReplayConfirm.value = true
    return
  }
  replayLoading.value = true
  showReplayConfirm.value = false
  try {
    const audit = await devicesApi.initiateReplay(deviceId.value, {
      since_sequence_no: sinceSeq.value,
      until_sequence_no: untilSeq.value,
      reason: replayReason.value || undefined,
    })
    audits.value.unshift(audit)
    uiStore.addNotification({ type: 'success', message: 'Replay initiated' })
    sinceSeq.value = 0
    untilSeq.value = undefined
    replayReason.value = ''
  } catch {
    uiStore.addNotification({ type: 'error', message: 'Failed to initiate replay' })
  } finally {
    replayLoading.value = false
  }
}

function getStatusClass(status?: string): string {
  switch (status) {
    case 'accepted': return 'bg-green-100 text-green-700'
    case 'duplicate': return 'bg-yellow-100 text-yellow-700'
    case 'out_of_order': return 'bg-orange-100 text-orange-700'
    case 'too_old': return 'bg-red-100 text-red-700'
    default: return 'bg-gray-100 text-gray-600'
  }
}

function toggleEvent(id: number) {
  expandedEventId.value = expandedEventId.value === id ? null : id
}

function getStatusTitle(status?: string): string {
  switch (status) {
    case 'accepted': return 'Event was accepted and stored'
    case 'duplicate': return 'Duplicate: event with this idempotency key was already processed'
    case 'out_of_order': return 'Out of order: sequence gap detected, reconciliation queued'
    case 'too_old': return 'Too old: event was beyond the 7-day acceptance window'
    default: return 'Unknown status'
  }
}

const statusChips = [
  { label: 'All', value: '' },
  { label: 'Accepted', value: 'accepted' },
  { label: 'Duplicate', value: 'duplicate' },
  { label: 'Out of Order', value: 'out_of_order' },
  { label: 'Too Old', value: 'too_old' },
]
</script>

<template>
  <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
    <!-- Device header -->
    <div class="mb-6">
      <div class="flex items-center gap-3">
        <h1 class="text-2xl font-bold text-gray-900 font-mono">{{ deviceId }}</h1>
        <span v-if="device" class="text-sm text-gray-500 bg-gray-100 px-2 py-1 rounded">
          {{ device.kind }}
        </span>
        <span v-if="device?.label" class="text-sm text-gray-700">{{ device.label }}</span>
      </div>
      <p v-if="device" class="text-sm text-gray-400 mt-1">
        Last seq#: {{ device.last_sequence_no }} ·
        Last seen: {{ device.last_seen_at ? new Date(device.last_seen_at).toLocaleString() : 'Never' }}
      </p>
    </div>

    <!-- Tabs -->
    <div class="flex gap-1 border-b border-gray-200 mb-6">
      <button
        v-for="tab in (['events', 'replay', 'audit'] as const)"
        :key="tab"
        @click="switchTab(tab)"
        :class="[
          'min-h-[44px] px-5 text-sm font-semibold capitalize border-b-2 transition-colors',
          activeTab === tab
            ? 'border-blue-600 text-blue-600'
            : 'border-transparent text-gray-500 hover:text-gray-700'
        ]"
      >
        {{ tab === 'audit' ? 'Audit Trail' : tab.charAt(0).toUpperCase() + tab.slice(1) }}
      </button>
    </div>

    <!-- Events tab -->
    <div v-if="activeTab === 'events'">
      <!-- Status filter chips -->
      <div class="flex flex-wrap gap-2 mb-4">
        <button
          v-for="chip in statusChips"
          :key="chip.value"
          @click="eventStatusFilter = chip.value; fetchEvents(true)"
          :class="[
            'min-h-[36px] px-3 text-sm rounded-full border transition-colors',
            eventStatusFilter === chip.value
              ? 'bg-blue-600 text-white border-blue-600'
              : 'bg-white text-gray-600 border-gray-300 hover:border-blue-400'
          ]"
        >
          {{ chip.label }}
        </button>
      </div>

      <div v-if="eventsLoading" class="space-y-2">
        <div v-for="n in 8" :key="n" class="h-12 bg-gray-200 rounded-lg animate-pulse" />
      </div>

      <div v-else-if="events.length === 0" class="text-center py-12 text-gray-400">
        No events found.
      </div>

      <div v-else class="space-y-1">
        <div
          v-for="event in events"
          :key="event.id"
          class="bg-white border border-gray-200 rounded-xl overflow-hidden"
        >
          <!-- Event row -->
          <button
            @click="toggleEvent(event.id)"
            class="w-full min-h-[48px] px-4 py-3 flex items-center gap-4 hover:bg-gray-50 text-left"
          >
            <span class="text-xs font-mono text-gray-400 w-8">{{ event.sequence_no }}</span>
            <span
              :class="['text-xs font-semibold px-2 py-0.5 rounded-full shrink-0', getStatusClass(event.status)]"
              :title="getStatusTitle(event.status)"
            >
              {{ event.status ?? 'unknown' }}
            </span>
            <span
              v-if="event.buffered_by_gateway"
              class="text-xs font-semibold px-2 py-0.5 rounded-full shrink-0 bg-purple-100 text-purple-700"
              :title="event.buffered_at ? 'Originally buffered at ' + new Date(event.buffered_at).toLocaleString() : 'Event was buffered by gateway during offline period'"
            >
              buffered
            </span>
            <span class="text-sm text-gray-700 font-medium truncate flex-1">
              {{ event.event_type }}
            </span>
            <span v-if="event.is_out_of_order" class="text-xs text-orange-600 font-semibold shrink-0">
              OOO
            </span>
            <span class="text-xs text-gray-400 shrink-0">
              {{ new Date(event.occurred_at).toLocaleString() }}
            </span>
            <svg
              :class="['w-4 h-4 text-gray-400 shrink-0 transition-transform', expandedEventId === event.id ? 'rotate-180' : '']"
              fill="none" stroke="currentColor" viewBox="0 0 24 24"
            >
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
          </button>

          <!-- Payload drawer -->
          <div
            v-if="expandedEventId === event.id"
            class="border-t border-gray-100 bg-gray-50 p-4"
          >
            <div class="grid grid-cols-2 gap-4 text-xs text-gray-600 mb-3">
              <div>
                <span class="font-medium">Idempotency Key:</span>
                <span class="font-mono ml-2">{{ event.idempotency_key }}</span>
              </div>
              <div>
                <span class="font-medium">Received:</span>
                <span class="ml-2">{{ new Date(event.received_at).toLocaleString() }}</span>
              </div>
            </div>
            <div v-if="event.payload_json">
              <p class="text-xs font-medium text-gray-500 mb-1">Payload JSON:</p>
              <pre class="bg-gray-900 text-green-400 rounded-lg p-3 text-xs overflow-x-auto max-h-48">{{ JSON.stringify(event.payload_json, null, 2) }}</pre>
            </div>
            <p v-else class="text-xs text-gray-400 italic">No payload</p>
          </div>
        </div>
      </div>

      <div v-if="eventsNextCursor" class="flex justify-center mt-6">
        <button
          @click="fetchEvents(false)"
          :disabled="eventsLoadingMore"
          class="min-h-[44px] px-6 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700 disabled:opacity-50"
        >
          {{ eventsLoadingMore ? 'Loading…' : 'Load More' }}
        </button>
      </div>
    </div>

    <!-- Replay tab -->
    <div v-if="activeTab === 'replay'" class="max-w-lg">
      <h2 class="text-lg font-semibold text-gray-900 mb-4">Initiate Replay</h2>

      <div class="space-y-4 mb-6">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">
            Since Sequence # *
          </label>
          <input
            v-model.number="sinceSeq"
            type="number"
            min="0"
            class="w-full min-h-[44px] px-4 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
          />
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">
            Until Sequence # (optional)
          </label>
          <input
            v-model.number="untilSeq"
            type="number"
            min="0"
            placeholder="Leave blank for open-ended"
            class="w-full min-h-[44px] px-4 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
          />
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">
            Reason
          </label>
          <textarea
            v-model="replayReason"
            rows="3"
            placeholder="Why is this replay being initiated?"
            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm resize-none"
          />
        </div>
      </div>

      <!-- Confirmation dialog -->
      <div
        v-if="showReplayConfirm"
        class="mb-4 p-4 bg-orange-50 border border-orange-200 rounded-xl"
      >
        <p class="text-sm font-semibold text-orange-800 mb-2">Confirm Replay</p>
        <p class="text-sm text-orange-700">
          Replay events from seq# {{ sinceSeq }}
          {{ untilSeq != null ? `to ${untilSeq}` : '(open-ended)' }}?
        </p>
        <div class="flex gap-3 mt-3">
          <button
            @click="handleReplay"
            :disabled="replayLoading"
            class="min-h-[40px] px-5 bg-orange-600 text-white font-semibold rounded-lg hover:bg-orange-700 disabled:opacity-50"
          >
            {{ replayLoading ? 'Initiating…' : 'Confirm' }}
          </button>
          <button
            @click="showReplayConfirm = false"
            class="min-h-[40px] px-5 text-gray-600 rounded-lg hover:bg-gray-100"
          >
            Cancel
          </button>
        </div>
      </div>

      <button
        v-else
        @click="showReplayConfirm = true"
        class="min-h-[44px] px-6 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700"
      >
        Initiate Replay
      </button>
    </div>

    <!-- Audit Trail tab -->
    <div v-if="activeTab === 'audit'">
      <div v-if="auditsLoading" class="space-y-3">
        <div v-for="n in 5" :key="n" class="h-16 bg-gray-200 rounded-xl animate-pulse" />
      </div>

      <div v-else-if="audits.length === 0" class="text-center py-12 text-gray-400">
        No replay audits for this device.
      </div>

      <div v-else class="space-y-3">
        <div
          v-for="audit in audits"
          :key="audit.id"
          class="bg-white border border-gray-200 rounded-xl p-4"
        >
          <div class="flex items-start justify-between gap-4">
            <div>
              <div class="flex items-center gap-3 mb-1">
                <span class="text-sm font-mono text-gray-700">
                  Seq# {{ audit.since_sequence_no }}
                  {{ audit.until_sequence_no != null ? `→ ${audit.until_sequence_no}` : '→ ∞' }}
                </span>
              </div>
              <p v-if="audit.reason" class="text-sm text-gray-600">{{ audit.reason }}</p>
              <p class="text-xs text-gray-400 mt-1">
                By user #{{ audit.initiated_by }} · {{ new Date(audit.created_at).toLocaleString() }}
              </p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
