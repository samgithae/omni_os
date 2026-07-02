<script setup lang="ts">
import { ref } from 'vue';
import { Head, router, Link } from '@inertiajs/vue3';
import {
  Building2,
  Settings,
  Mail,
  Send,
  Plus,
  Trash2,
  Save,
  Check,
  X,
  Globe,
  MessageSquare,
  Smartphone,
} from '@lucide/vue';
import { dashboard } from '@/routes';

defineOptions({
layout: {
  breadcrumbs: [
    { title: 'Dashboard', href: dashboard() },
    { title: 'Brand Settings', href: '/brands' },
  ],
},
});

interface BrandData {
  id: number;
  name: string;
  slug: string;
  description: string | null;
  primary_market: string | null;
  primary_kpi: string | null;
  brand_voice: string | null;
  color: string | null;
  is_active: boolean;
  sender_name: string | null;
  sender_emails: string[];
  settings: Record<string, any>;
  elementor_mcp_enabled?: boolean;
  elementor_mcp_endpoint?: string | null;
  elementor_mcp_auth?: string | null;
  codex_api_key?: string | null;
}

const props = defineProps<{
  brand: BrandData;
  brands: BrandData[];
}>();

const activeTab = ref<'email' | 'social' | 'general' | 'mcp'>('email');
const successMessage = ref<string | null>(null);
const errorMessage = ref<string | null>(null);
const saving = ref(false);

// Sender emails
const senderEmails = ref<string[]>([...props.brand.sender_emails]);
const newEmail = ref('');
const senderName = ref(props.brand.sender_name || '');

// Settings (social credentials etc)
const settings = ref<Record<string, any>>({ ...props.brand.settings });

// MCP Configuration
const mcpEnabled = ref(props.brand.elementor_mcp_enabled ?? false);
const mcpEndpoint = ref(props.brand.elementor_mcp_endpoint || '');
const mcpAuth = ref(props.brand.elementor_mcp_auth || '');
const codexApiKey = ref(props.brand.codex_api_key || '');

// Tab icons
const tabIcon: Record<string, any> = {
  email: Mail,
  social: Globe,
  general: Settings,
  mcp: Settings,
};

function addEmail() {
  const email = newEmail.value.trim().toLowerCase();
  if (!email) return;
  if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
    errorMessage.value = 'Invalid email format';
    setTimeout(() => {
      errorMessage.value = null;
    }, 3000);
    return;
  }
  if (senderEmails.value.includes(email)) {
    errorMessage.value = 'Email already in list';
    setTimeout(() => {
      errorMessage.value = null;
    }, 3000);
    return;
  }
  senderEmails.value.push(email);
  newEmail.value = '';
}

function removeEmail(idx: number) {
  senderEmails.value.splice(idx, 1);
}

function saveSettings() {
  saving.value = true;
  successMessage.value = null;
  errorMessage.value = null;

  router.put(
    `/brands/${props.brand.slug}/settings`,
    {
      sender_name: senderName.value,
      sender_emails: senderEmails.value,
      settings: settings.value,
      codex_api_key: codexApiKey.value || undefined,
      elementor_mcp_endpoint: mcpEndpoint.value || undefined,
      elementor_mcp_auth: mcpAuth.value || undefined,
      elementor_mcp_enabled: mcpEnabled.value,
    },
    {
      preserveScroll: true,
      preserveState: true,
      onSuccess: () => {
        successMessage.value = 'Settings saved successfully';
        setTimeout(() => {
          successMessage.value = null;
        }, 3000);
      },
      onError: (errors) => {
        errorMessage.value = Object.values(errors).flat().join(', ');
      },
      onFinish: () => {
        saving.value = false;
      },
    },
  );
}

const brandColors: Record<string, string> = {
  hudutech: '#1a56db',
  ujuziplus: '#059669',
  phantomflix: '#7c3aed',
  'phantom-tutors': '#dc2626',
};
</script>

<template>
  <Head :title="brand.name + ' Settings'" />
  <div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto p-6">
    <!-- Page Header -->
    <div class="flex items-center justify-between">
      <div class="flex items-center gap-3">
        <div
          class="h-8 w-1 rounded-full"
          :style="{
            backgroundColor:
              brand.color || brandColors[brand.slug] || '#999',
          }"
        />
        <div>
          <h1 class="text-2xl font-bold tracking-tight">
            {{ brand.name }}
          </h1>
          <p class="text-sm text-muted-foreground">
            Brand settings & channel configuration
          </p>
        </div>
      </div>
      <div class="flex items-center gap-2">
        <!-- Brand switcher -->
        <select
          class="rounded-md border border-gray-200 bg-white px-2.5 py-1.5 text-xs font-medium text-gray-700 outline-none focus:border-blue-300"
          @change="
            router.get(
              '/brands/' +
              ($event.target as HTMLSelectElement).value +
              '/settings',
            )
          "
        >
          <option
            v-for="b in brands"
            :key="b.slug"
            :value="b.slug"
            :selected="b.slug === brand.slug"
          >
            {{ b.name }}
          </option>
        </select>
        <a
          :href="`/admin/brands/${brand.id}/edit`"
          class="rounded-md border px-3 py-1.5 text-xs text-gray-600 hover:bg-gray-50"
        >Filament editor →</a
        >
      </div>
    </div>

    <!-- Success / Error -->
    <div
      v-if="successMessage"
      class="flex items-center gap-2 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700"
    >
      <Check class="h-4 w-4" /> {{ successMessage }}
    </div>
    <div
      v-if="errorMessage"
      class="flex items-center gap-2 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
    >
      <X class="h-4 w-4" /> {{ errorMessage }}
    </div>

    <!-- Tabs -->
    <div class="flex gap-2 border-b border-gray-200">
      <button
        v-for="tab in ['email', 'social', 'general', 'mcp'] as const"
        :key="tab"
        class="flex items-center gap-2 border-b-2 px-4 py-3 text-sm font-medium transition-colors"
        :class="
          activeTab === tab
            ? 'border-blue-600 text-blue-700'
            : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700'
        "
        @click="activeTab = tab"
      >
        <component :is="tabIcon[tab]" class="h-4 w-4" />
        <span class="capitalize">{{ tab === 'mcp' ? 'MCP Elementor' : tab === 'email' ? 'Email Sending' : tab === 'social' ? 'Social Channels' : 'General' }}</span>
      </button>
    </div>

    <!-- MCP TAB -->
    <div v-if="activeTab === 'mcp'" class="space-y-6">
      <div class="rounded-xl border bg-card p-5 shadow-sm">
        <div class="mb-4 flex items-center gap-2">
          <Settings class="h-4 w-4 text-purple-500" />
          <h3 class="text-sm font-semibold">Elementor MCP Integration</h3>
          <div class="ml-auto">
            <label class="flex items-center gap-2 text-xs text-muted-foreground">
              <input
                v-model="mcpEnabled"
                type="checkbox"
                class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
              />
              Enable Elementor MCP Management
            </label>
          </div>
        </div>
        
        <p class="mb-4 text-sm text-muted-foreground">
          Configure Elementor MCP server for AI-powered page management of this brand. 
          Each brand can have its own WordPress site with Elementor MCP server.
        </p>
        
        <!-- Codex API Key -->
        <div class="mb-5 rounded-xl border border-blue-200 bg-blue-50 p-4">
          <div class="mb-2 flex items-center gap-2">
            <Smartphone class="h-4 w-4 text-blue-600" />
            <h4 class="text-sm font-medium text-blue-800">Codex Configuration</h4>
          </div>
          <label class="mb-2 block text-xs font-medium text-gray-600">Codex API Key</label>
          <input
            v-model="codexApiKey"
            type="password"
            placeholder="Enter your Codex API key"
            class="w-full max-w-md rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-blue-300 focus:ring-1 focus:ring-blue-200"
          />
          <p class="mt-2 text-xs text-gray-500">
            Found in ~/.codex/config.toml
          </p>
        </div>
        
        <!-- Elementor MCP Server -->
        <div class="mb-5 space-y-4">
          <label class="block text-xs font-medium text-gray-600">
            Elementor MCP Server Endpoint
            <span class="ml-2 text-blue-600 text-xs">HTTPS required</span>
            <input
              v-model="mcpEndpoint"
              type="url"
              placeholder="https://your-brand-site.com/wp-json/mcp/emcp-tools-server"
              class="mt-1 w-full max-w-2xl rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-blue-300 focus:ring-1 focus:ring-blue-200"
            />
          </label>
          
          <p class="text-xs text-gray-500">
            The MCP endpoint for this brand's Elementor WordPress installation.
            Example: https://omni.hudutech.co.ke/wp-json/mcp/emcp-tools-server
          </p>
          
          <!-- MCP Authentication -->
          <div class="rounded-lg border border-gray-200 p-4">
            <label class="block text-xs font-medium text-gray-600 mb-2">Authentication (Basic Auth)</label>
            <textarea
              v-model="mcpAuth"
              rows="3"
              placeholder="username:application_password (Base64 encoded)

Example:
Sam:xxxx-xxxx-xxxx-xxxx"
              class="w-full max-w-2xl rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none font-mono focus:border-blue-300 focus:ring-1 focus:ring-blue-200"
              spellcheck="false"
            ></textarea>
            <p class="mt-2 text-xs text-gray-500">
              Generate with: <code class="bg-gray-100 px-1 rounded">echo -n "username:password" | base64</code>
            </p>
          </div>
        </div>
      </div>
    </div>

    <!-- EMAIL TAB -->
    <div v-if="activeTab === 'email'" class="space-y-6">
      <div class="rounded-xl border bg-card p-5 shadow-sm">
        <div class="mb-4 flex items-center gap-2">
          <Send class="h-4 w-4 text-blue-500" />
          <h3 class="text-sm font-semibold">Sender Name</h3>
        </div>
        <p class="mb-3 text-xs text-muted-foreground">
          This name appears as the sender on all outgoing emails for this brand.
        </p>
        <input
          v-model="senderName"
          type="text"
          placeholder="e.g. UjuziPlus Team"
          class="w-full max-w-md rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-blue-300 focus:ring-1 focus:ring-blue-200"
        />
      </div>

      <div class="rounded-xl border bg-card p-5 shadow-sm">
        <div class="mb-4 flex items-center gap-2">
          <Mail class="h-4 w-4 text-emerald-500" />
          <h3 class="text-sm font-semibold">Sender Emails</h3>
          <span class="ml-auto text-xs text-muted-foreground"
            >{{ senderEmails.length }} addresses</span
          >
        </div>
        <p class="mb-3 text-xs text-muted-foreground">
          Emails are rotated randomly to improve deliverability and avoid spam flags.
        </p>

        <!-- Add new email -->
        <div class="mb-4 flex items-center gap-2">
          <input
            v-model="newEmail"
            type="email"
            placeholder="Add sender email..."
            class="max-w-md flex-1 rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-blue-300 focus:ring-1 focus:ring-blue-200"
            @keydown.enter.prevent="addEmail"
          />
          <button
            @click="addEmail"
            :disabled="!newEmail.trim()"
            class="inline-flex items-center gap-1.5 rounded-lg bg-blue-600 px-3 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-40"
          >
            <Plus class="h-4 w-4" /> Add
          </button>
        </div>

        <!-- Email list -->
        <div class="space-y-1.5">
          <div
            v-for="(email, idx) in senderEmails"
            :key="idx"
            class="flex items-center justify-between rounded-lg border border-gray-100 px-3 py-2 text-sm hover:bg-gray-50"
          >
            <div class="flex items-center gap-2">
              <Mail class="h-3.5 w-3.5 text-gray-400" />
              <span class="font-medium text-gray-700">{{ email }}</span>
            </div>
            <button
              @click="removeEmail(idx)"
              class="rounded p-1 text-gray-400 hover:bg-red-50 hover:text-red-600"
              title="Remove"
            >
              <Trash2 class="h-3.5 w-3.5" />
            </button>
          </div>
          <div
            v-if="senderEmails.length === 0"
            class="py-6 text-center text-sm text-muted-foreground"
          >
            No sender emails configured. Add at least one above.
          </div>
        </div>
      </div>
    </div>

    <!-- SOCIAL TAB contents continue here naturally -->

    <!-- Save Button -->
    <div
      class="sticky bottom-0 mt-auto flex items-center justify-end gap-3 rounded-xl border bg-card p-4 shadow-lg"
    >
      <button
        @click="saveSettings"
        :disabled="saving"
        class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-6 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50"
      >
        <Save class="h-4 w-4" /> {{ saving ? 'Saving...' : 'Save Settings' }}
      </button>
    </div>
  </div>
</template>
