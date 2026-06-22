<script setup lang="ts">
import { ref } from 'vue'
import { Head, router, Link } from '@inertiajs/vue3'
import { Building2, Pencil } from '@lucide/vue'
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
  color: string | null; is_active: boolean; leads_count: number
}

const props = defineProps<{ brands: BrandData[] }>()
</script>

<template>
  <Head title="Brands" />
  <div>
    <div class="flex items-center border-b border-gray-200 px-4 py-3">
      <Building2 class="mr-2 h-4 w-4 text-gray-400" />
      <h1 class="text-sm font-semibold text-gray-900">Brands</h1>
      <span class="ml-2 rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-medium text-gray-500">{{ brands.length }}</span>
    </div>
    <div class="grid grid-cols-1 gap-4 p-4 md:grid-cols-2">
      <div v-for="brand in brands" :key="brand.id" class="rounded-lg border border-gray-200 p-4">
        <div class="flex items-start justify-between">
          <div class="flex items-center gap-3">
            <div class="h-8 w-8 rounded-lg" :style="{ backgroundColor: brand.color || '#6b7280' }"></div>
            <div>
              <h3 class="text-sm font-semibold text-gray-900">{{ brand.name }}</h3>
              <p class="text-xs text-gray-400">{{ brand.slug }}</p>
            </div>
          </div>
          <Link :href="`/brands/${brand.id}/edit`" class="rounded p-1 text-gray-400 hover:text-blue-600"><Pencil class="h-3.5 w-3.5" /></Link>
        </div>
        <div class="mt-3 grid grid-cols-2 gap-2 text-xs">
          <div><span class="text-gray-400">Market:</span> <span class="text-gray-600">{{ brand.primary_market || '-' }}</span></div>
          <div><span class="text-gray-400">KPI:</span> <span class="text-gray-600">{{ brand.primary_kpi || '-' }}</span></div>
          <div><span class="text-gray-400">Leads:</span> <span class="font-medium text-gray-800">{{ brand.leads_count }}</span></div>
          <div><span class="text-gray-400">Status:</span> <span :class="brand.is_active ? 'text-green-600' : 'text-gray-400'">{{ brand.is_active ? 'Active' : 'Inactive' }}</span></div>
        </div>
        <p v-if="brand.description" class="mt-2 text-xs text-gray-500">{{ brand.description }}</p>
      </div>
    </div>
  </div>
</template>
