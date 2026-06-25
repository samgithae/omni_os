<script setup lang="ts">
import { ref } from 'vue'
import { Head, router, Link } from '@inertiajs/vue3'
import { Bot, ArrowLeft, Save, Loader, Key, ExternalLink, Trash2, Upload, FileText, Circle } from '@lucide/vue'
import { dashboard } from '@/routes'

defineOptions({
  layout: {
    breadcrumbs: [
      { title: 'Dashboard', href: dashboard() },
      { title: 'Agents', href: '/agents' },
      { title: 'Edit Agent', href: '' },
    ],
  },
})

interface AgentDocument {
  id: number
  label: string
  file_path: string
  url: string | null
  mime_type: string | null
  size_bytes: number | null
}

const props = defineProps<{
  agent: {
    id: number
    codename: string
    display_name: string
    role: string | null
    description: string | null
    avatar_url: string | null
    function_area: string | null
    status: string
    is_active: boolean
    sort_order: number
    token_last_four: string | null
    last_active_at: string | null
    actions_this_week: number
    documents: AgentDocument[]
  }
}>()

const form = ref({
  codename: props.agent.codename,
  display_name: props.agent.display_name,
  role: props.agent.role || '',
  function_area: props.agent.function_area || '',
  description: props.agent.description || '',
  status: props.agent.status,
  is_active: props.agent.is_active,
  sort_order: props.agent.sort_order,
})

const avatarFile = ref<File | null>(null)
const avatarPreview = ref<string | null>(null)
const saving = ref(false)
const errors = ref<Record<string, string>>({})
const flashMessage = ref<string | null>(null)

// Token management
const showToken = ref(false)
const newToken = ref<string | null>(null)
const generatingToken = ref(false)
const tokenCopied = ref(false)

// Document upload
const newDocLabel = ref('')
const newDocFile = ref<File | null>(null)
const uploadingDoc = ref(false)
const documents = ref<AgentDocument[]>([...props.agent.documents])
const docErrors = ref<string | null>(null)

function onAvatarSelected(event: Event) {
  const target = event.target as HTMLInputElement
  if (target.files && target.files[0]) {
    avatarFile.value = target.files[0]
    avatarPreview.value = URL.createObjectURL(target.files[0])
  }
}

function removeAvatar() {
  avatarFile.value = null
  avatarPreview.value = null
}

function submit() {
  saving.value = true
  errors.value = {}
  flashMessage.value = null

  const payload = new FormData()
  payload.append('_method', 'PUT')
  payload.append('codename', form.value.codename)
  payload.append('display_name', form.value.display_name)
  payload.append('role', form.value.role)
  payload.append('function_area', form.value.function_area)
  payload.append('description', form.value.description)
  payload.append('status', form.value.status)
  payload.append('is_active', form.value.is_active ? '1' : '0')
  payload.append('sort_order', String(form.value.sort_order))
  if (avatarFile.value) {
    payload.append('avatar', avatarFile.value)
  }

  router.post(`/agents/${props.agent.id}`, payload, {
    headers: { 'Content-Type': 'multipart/form-data' },
    preserveScroll: true,
    onSuccess: () => {
      flashMessage.value = 'Agent updated successfully.'
      setTimeout(() => { flashMessage.value = null }, 3000)
    },
    onError: (errs) => {
      errors.value = errs as Record<string, string>
    },
    onFinish: () => {
      saving.value = false
    },
  })
}

function generateToken() {
  if (!confirm('Generate a new token? The current token will be invalidated immediately.')) return
  generatingToken.value = true
  showToken.value = true
  newToken.value = null
  tokenCopied.value = false

  fetch(`/agents/${props.agent.id}/token`, { method: 'POST' })
    .then(r => r.json())
    .then(data => {
      newToken.value = data.token
    })
    .catch(() => {
      alert('Failed to generate token.')
    })
    .finally(() => {
      generatingToken.value = false
    })
}

function copyToken() {
  if (newToken.value) {
    navigator.clipboard.writeText(newToken.value)
    tokenCopied.value = true
    setTimeout(() => { tokenCopied.value = false }, 3000)
  }
}

function onDocFileSelected(event: Event) {
  const target = event.target as HTMLInputElement
  if (target.files && target.files[0]) {
    newDocFile.value = target.files[0]
  }
}

function uploadDocument() {
  if (!newDocLabel.value.trim() || !newDocFile.value) {
    docErrors.value = 'Label and file are required.'
    return
  }
  uploadingDoc.value = true
  docErrors.value = null

  const fd = new FormData()
  fd.append('label', newDocLabel.value.trim())
  fd.append('file', newDocFile.value)

  fetch(`/agents/${props.agent.id}/documents`, { method: 'POST', body: fd })
    .then(r => r.json())
    .then(doc => {
      documents.value.push(doc)
      newDocLabel.value = ''
      newDocFile.value = null
      ;(document.getElementById('doc-file-input') as HTMLInputElement).value = ''
    })
    .catch(() => {
      docErrors.value = 'Upload failed.'
    })
    .finally(() => {
      uploadingDoc.value = false
    })
}

function deleteDocument(doc: AgentDocument) {
  if (!confirm(`Delete "${doc.label}"?`)) return
  fetch(`/agents/${props.agent.id}/documents/${doc.id}`, { method: 'DELETE' })
    .then(() => {
      documents.value = documents.value.filter(d => d.id !== doc.id)
    })
    .catch(() => {
      alert('Delete failed.')
    })
}

function formatFileSize(bytes: number | null): string {
  if (!bytes) return '—'
  if (bytes < 1024) return `${bytes} B`
  if (bytes < 1048576) return `${(bytes / 1024).toFixed(1)} KB`
  return `${(bytes / 1048576).toFixed(1)} MB`
}

function initialsAvatar(name: string): string {
  return name.split(' ').map(s => s.charAt(0)).join('').toUpperCase().slice(0, 2)
}
</script>

<template>
  <Head title="Edit Agent" />

  <div class="mx-auto max-w-2xl pb-16">
    <!-- Header -->
    <div class="flex items-center gap-3 border-b border-gray-200 px-4 py-3">
      <Link href="/agents" class="text-gray-400 hover:text-gray-600">
        <ArrowLeft class="h-4 w-4" />
      </Link>
      <Bot class="h-4 w-4 text-gray-400" />
      <h1 class="text-sm font-semibold text-gray-900">Edit {{ agent.display_name }}</h1>
    </div>

    <!-- Flash message -->
    <div v-if="flashMessage" class="mx-4 mt-3 rounded-md bg-green-50 px-3 py-2 text-xs text-green-700">
      {{ flashMessage }}
    </div>

    <!-- Token reveal modal -->
    <Transition name="fade">
      <div v-if="showToken" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 px-4">
        <div class="w-full max-w-lg rounded-xl bg-white p-6 shadow-2xl">
          <h3 class="mb-2 text-sm font-semibold text-gray-900">New Token Generated</h3>
          <div v-if="generatingToken" class="flex items-center justify-center py-6">
            <Loader class="h-6 w-6 animate-spin text-blue-600" />
          </div>
          <div v-else>
            <div class="mb-2 rounded-lg bg-amber-50 px-4 py-3 text-xs text-amber-800">
              <strong>Store this now — it will not be shown again.</strong>
              The previous token is invalidated immediately.
            </div>
            <div class="relative mb-4">
              <pre class="overflow-x-auto rounded-lg bg-gray-50 px-4 py-3 font-mono text-xs text-gray-800">{{ newToken }}</pre>
            </div>
            <div class="flex items-center gap-2">
              <button
                @click="copyToken"
                class="inline-flex items-center gap-1.5 rounded-lg bg-blue-600 px-4 py-2 text-xs font-medium text-white hover:bg-blue-700"
              >
                {{ tokenCopied ? 'Copied!' : 'Copy to Clipboard' }}
              </button>
              <button
                @click="showToken = false"
                class="text-xs font-medium text-gray-500 hover:text-gray-700"
              >Close</button>
            </div>
          </div>
        </div>
      </div>
    </Transition>

    <!-- Form -->
    <form @submit.prevent="submit" class="space-y-4 px-4 pt-4">
      <!-- Avatar -->
      <div>
        <label class="block text-xs font-medium text-gray-700 mb-1">Avatar</label>
        <div class="flex items-center gap-4">
          <div class="h-14 w-14 shrink-0 overflow-hidden rounded-full bg-gray-200">
            <img
              v-if="avatarPreview || agent.avatar_url"
              :src="avatarPreview || agent.avatar_url || ''"
              :alt="agent.display_name"
              class="h-full w-full object-cover"
            />
            <div v-else class="flex h-full w-full items-center justify-center text-sm font-bold text-gray-500">
              {{ initialsAvatar(agent.display_name) }}
            </div>
          </div>
          <div class="flex items-center gap-2">
            <label class="cursor-pointer rounded-lg bg-white px-3 py-1.5 text-xs font-medium text-blue-600 ring-1 ring-inset ring-gray-200 hover:bg-blue-50">
              <Upload class="mr-1 inline h-3 w-3" />
              Upload
              <input type="file" accept="image/*" class="hidden" @change="onAvatarSelected" />
            </label>
            <button
              v-if="avatarPreview || agent.avatar_url"
              type="button"
              @click="removeAvatar"
              class="text-[10px] text-red-500 hover:text-red-700"
            >Remove</button>
          </div>
        </div>
      </div>

      <!-- Codename -->
      <div>
        <label class="block text-xs font-medium text-gray-700">Codename</label>
        <input
          v-model="form.codename"
          type="text"
          class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-xs outline-none focus:border-blue-400 focus:ring-1 focus:ring-blue-300"
        />
        <p v-if="errors.codename" class="mt-1 text-[10px] text-red-500">{{ errors.codename }}</p>
      </div>

      <!-- Display Name -->
      <div>
        <label class="block text-xs font-medium text-gray-700">Display Name</label>
        <input
          v-model="form.display_name"
          type="text"
          class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-xs outline-none focus:border-blue-400 focus:ring-1 focus:ring-blue-300"
        />
        <p v-if="errors.display_name" class="mt-1 text-[10px] text-red-500">{{ errors.display_name }}</p>
      </div>

      <!-- Role + Function Area -->
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-xs font-medium text-gray-700">Role</label>
          <input v-model="form.role" type="text" class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-xs outline-none focus:border-blue-400 focus:ring-1 focus:ring-blue-300" />
        </div>
        <div>
          <label class="block text-xs font-medium text-gray-700">Function Area</label>
          <select v-model="form.function_area" class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-xs outline-none focus:border-blue-400 focus:ring-1 focus:ring-blue-300">
            <option value="">Select...</option>
            <option value="orchestration">Orchestration</option>
            <option value="mining">Mining</option>
            <option value="enrichment">Enrichment</option>
            <option value="drafting">Drafting</option>
            <option value="triage">Triage</option>
            <option value="research">Research</option>
          </select>
        </div>
      </div>

      <!-- Description -->
      <div>
        <label class="block text-xs font-medium text-gray-700">Description</label>
        <textarea v-model="form.description" rows="3" class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-xs outline-none focus:border-blue-400 focus:ring-1 focus:ring-blue-300 resize-none"></textarea>
      </div>

      <!-- Status + Sort + Active -->
      <div class="grid grid-cols-3 gap-4">
        <div>
          <label class="block text-xs font-medium text-gray-700">Status</label>
          <select v-model="form.status" class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-xs outline-none focus:border-blue-400 focus:ring-1 focus:ring-blue-300">
            <option value="active">Active</option>
            <option value="paused">Paused</option>
            <option value="maintenance">Maintenance</option>
          </select>
        </div>
        <div>
          <label class="block text-xs font-medium text-gray-700">Sort Order</label>
          <input v-model.number="form.sort_order" type="number" min="0" class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-xs outline-none focus:border-blue-400 focus:ring-1 focus:ring-blue-300" />
        </div>
        <div class="flex items-end pb-2">
          <label class="flex items-center gap-2 text-xs text-gray-700 cursor-pointer">
            <input v-model="form.is_active" type="checkbox" class="h-4 w-4 rounded border-gray-300" />
            Enabled
          </label>
        </div>
      </div>

      <!-- Save / Cancel -->
      <div class="flex items-center gap-3 border-t border-gray-100 pt-4">
        <button
          type="submit"
          :disabled="saving"
          class="inline-flex items-center gap-1.5 rounded-lg bg-blue-600 px-4 py-2 text-xs font-medium text-white hover:bg-blue-700 disabled:opacity-50"
        >
          <Save v-if="!saving" class="h-3.5 w-3.5" />
          <Loader v-else class="h-3.5 w-3.5 animate-spin" />
          {{ saving ? 'Saving...' : 'Save Changes' }}
        </button>
        <Link href="/agents" class="text-xs font-medium text-gray-500 hover:text-gray-700">Cancel</Link>
      </div>
    </form>

    <!-- Token Section -->
    <div class="mt-6 border-t border-gray-200 px-4 pt-4">
      <h2 class="mb-3 flex items-center gap-2 text-xs font-semibold text-gray-700">
        <Key class="h-3.5 w-3.5" />
        API Token
      </h2>
      <div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-xs text-gray-600">
              Token:
              <span v-if="agent.token_last_four" class="font-mono font-medium text-gray-900">…{{ agent.token_last_four }}</span>
              <span v-else class="text-gray-400 italic">Not generated yet</span>
            </p>
            <p v-if="agent.last_active_at" class="mt-0.5 text-[10px] text-gray-400">Last active {{ agent.last_active_at }}</p>
          </div>
          <button
            @click="generateToken"
            :disabled="generatingToken"
            class="inline-flex items-center gap-1.5 rounded-lg bg-amber-100 px-3 py-1.5 text-xs font-medium text-amber-700 hover:bg-amber-200 disabled:opacity-50"
          >
            <Loader v-if="generatingToken" class="h-3 w-3 animate-spin" />
            <Key v-else class="h-3 w-3" />
            {{ agent.token_last_four ? 'Regenerate' : 'Generate' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Documents Section -->
    <div class="mt-6 border-t border-gray-200 px-4 pt-4">
      <h2 class="mb-3 flex items-center gap-2 text-xs font-semibold text-gray-700">
        <FileText class="h-3.5 w-3.5" />
        Attached Documents
      </h2>

      <!-- Document list -->
      <div v-if="documents.length > 0" class="mb-3 space-y-1">
        <div
          v-for="doc in documents"
          :key="doc.id"
          class="flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-3 py-2"
        >
          <FileText class="h-4 w-4 shrink-0 text-gray-400" />
          <div class="min-w-0 flex-1">
            <p class="text-xs font-medium text-gray-700 truncate">{{ doc.label }}</p>
            <p class="text-[10px] text-gray-400">{{ formatFileSize(doc.size_bytes) }}</p>
          </div>
          <a
            v-if="doc.url"
            :href="doc.url"
            target="_blank"
            class="shrink-0 rounded px-2 py-1 text-[10px] text-blue-600 hover:bg-blue-50"
          >
            <ExternalLink class="h-3 w-3" />
          </a>
          <button
            @click="deleteDocument(doc)"
            class="shrink-0 rounded px-2 py-1 text-[10px] text-red-500 hover:bg-red-50"
          >
            <Trash2 class="h-3 w-3" />
          </button>
        </div>
      </div>
      <p v-else class="mb-3 text-[11px] text-gray-400 italic">No documents attached.</p>

      <!-- Upload form -->
      <div class="flex items-end gap-2">
        <div class="flex-1 space-y-1">
          <input
            v-model="newDocLabel"
            type="text"
            placeholder="Document label..."
            class="block w-full rounded-lg border border-gray-300 px-3 py-1.5 text-xs outline-none focus:border-blue-400 focus:ring-1 focus:ring-blue-300"
          />
          <input
            id="doc-file-input"
            type="file"
            class="block w-full text-xs text-gray-500 file:mr-2 file:rounded file:border-0 file:bg-blue-50 file:px-2 file:py-1 file:text-[10px] file:font-medium file:text-blue-700 hover:file:bg-blue-100"
            @change="onDocFileSelected"
          />
        </div>
        <button
          @click="uploadDocument"
          :disabled="uploadingDoc || !newDocLabel.trim() || !newDocFile"
          class="inline-flex items-center gap-1 rounded-lg bg-blue-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-blue-700 disabled:opacity-50"
        >
          <Upload v-if="!uploadingDoc" class="h-3 w-3" />
          <Loader v-else class="h-3 w-3 animate-spin" />
          Upload
        </button>
      </div>
      <p v-if="docErrors" class="mt-1 text-[10px] text-red-500">{{ docErrors }}</p>
    </div>
  </div>
</template>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity 0.15s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>
