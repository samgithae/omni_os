<script setup lang="ts">
import { ref } from 'vue'
import { Head, router, Link } from '@inertiajs/vue3'
import { Building2, ArrowLeft, Save } from '@lucide/vue'
import { dashboard } from '@/routes'

defineOptions({
  layout: { breadcrumbs: [
    { title: 'Dashboard', href: dashboard() },
    { title: 'Brands', href: '/brands' },
  ]},
})

interface BrandData {
  id: number; name: string; slug: string; description: string | null
  primary_market: string | null; primary_kpi: string | null; brand_voice: string | null
  color: string | null; is_active: boolean
}

const props = defineProps<{ brand: BrandData }>()

const form = ref({
  name: props.brand.name, slug: props.brand.slug,
  description: props.brand.description || '', primary_market: props.brand.primary_market || '',
  primary_kpi: props.brand.primary_kpi || '', brand_voice: props.brand.brand_voice || '',
  color: props.brand.color || '#6b7280', is_active: props.brand.is_active,
})

const errors = ref<Record<string, string>>({})
const saving = ref(false)

function submit() {
  saving.value = true
  router.put(`/brands/${props.brand.id}`, form.value, {
    preserveScroll: true, preserveState: true,
    onError: (e) => { errors.value = e as Record<string, string>; saving.value = false },
    onSuccess: () => { saving.value = false },
  })
}
</script>

<template>
  <Head :title="`Edit: ${brand.name}`" />
  <div>
    <div class="flex items-center gap-2 border-b border-gray-200 px-4 py-3">
      <Link href="/brands" class="rounded p-1 text-gray-400 hover:text-gray-600"><ArrowLeft class="h-4 w-4" /></Link>
      <Building2 class="h-4 w-4 text-gray-400" />
      <h1 class="text-sm font-semibold text-gray-900">Edit: {{ brand.name }}</h1>
    </div>
    <form @submit.prevent="submit" class="mx-auto max-w-2xl space-y-4 p-6">
      <div class="grid grid-cols-2 gap-4">
        <div><label class="mb-1 block text-xs font-medium text-gray-700">Name</label><input v-model="form.name" class="w-full rounded border-gray-200 text-sm" :class="{ 'border-red-400': errors.name }" /><p v-if="errors.name" class="mt-1 text-xs text-red-500">{{ errors.name }}</p></div>
        <div><label class="mb-1 block text-xs font-medium text-gray-700">Slug</label><input v-model="form.slug" class="w-full rounded border-gray-200 text-sm" :class="{ 'border-red-400': errors.slug }" /></div>
      </div>
      <div><label class="mb-1 block text-xs font-medium text-gray-700">Description</label><textarea v-model="form.description" rows="3" class="w-full rounded border-gray-200 text-sm"></textarea></div>
      <div class="grid grid-cols-2 gap-4">
        <div><label class="mb-1 block text-xs font-medium text-gray-700">Primary Market</label><input v-model="form.primary_market" class="w-full rounded border-gray-200 text-sm" /></div>
        <div><label class="mb-1 block text-xs font-medium text-gray-700">Primary KPI</label><input v-model="form.primary_kpi" class="w-full rounded border-gray-200 text-sm" /></div>
      </div>
      <div><label class="mb-1 block text-xs font-medium text-gray-700">Brand Voice</label><textarea v-model="form.brand_voice" rows="3" class="w-full rounded border-gray-200 text-sm"></textarea></div>
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="mb-1 block text-xs font-medium text-gray-700">Color</label>
          <div class="flex items-center gap-2">
            <input type="color" v-model="form.color" class="h-8 w-8 rounded border-gray-200 cursor-pointer" />
            <input v-model="form.color" class="flex-1 rounded border-gray-200 text-xs font-mono" />
          </div>
        </div>
        <div class="flex items-end pb-2">
          <label class="flex items-center gap-2"><input type="checkbox" v-model="form.is_active" class="rounded border-gray-300 text-blue-600" /><span class="text-xs font-medium text-gray-700">Active</span></label>
        </div>
      </div>
      <div class="flex items-center justify-end gap-2 border-t border-gray-100 pt-4">
        <Link href="/brands" class="rounded px-3 py-1.5 text-xs font-medium text-gray-600 ring-1 ring-inset ring-gray-300 hover:bg-gray-50">Cancel</Link>
        <button type="submit" class="inline-flex items-center gap-1 rounded bg-blue-600 px-4 py-1.5 text-xs font-medium text-white hover:bg-blue-700 disabled:opacity-40" :disabled="saving">
          <Save class="h-3.5 w-3.5" />{{ saving ? 'Saving...' : 'Update Brand' }}
        </button>
      </div>
    </form>
  </div>
</template>
