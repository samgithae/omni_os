<script setup lang="ts">
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import { ChevronDown } from '@lucide/vue'

interface Brand {
    id: number
    name: string
    slug: string
    color: string
}

const props = defineProps<{
    activeBrandId: number | null
    brands: Brand[]
}>()

const open = ref(false)

const activeBrand = computed(() =>
    props.activeBrandId ? props.brands.find(b => b.id === props.activeBrandId) : null
)

const activeLabel = computed(() => activeBrand.value?.name ?? 'All brands')
const activeColor = computed(() => activeBrand.value?.color ?? '#6b7280')

function switchBrand(brandId: number | null) {
    open.value = false
    router.post('/brand/switch', { brand_id: brandId }, {
        preserveScroll: true,
        preserveState: false,
    })
}
</script>

<template>
    <div class="relative inline-block text-left">
        <button
            type="button"
            @click="open = !open"
            class="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800"
        >
            <span
                class="h-3 w-3 rounded-full shrink-0"
                :style="{ backgroundColor: activeColor }"
            ></span>
            <span class="whitespace-nowrap">{{ activeLabel }}</span>
            <ChevronDown class="h-4 w-4 text-gray-400 shrink-0" />
        </button>

        <div
            v-if="open"
            @click.self="open = false"
            class="fixed inset-0 z-[9998]"
        ></div>

        <div
            v-if="open"
            class="absolute right-0 z-[9999] mt-2 w-56 origin-top-right rounded-lg border border-gray-200 bg-white shadow-lg ring-1 ring-black/5 dark:border-gray-700 dark:bg-gray-900 focus:outline-none"
        >
            <div class="py-1 flex flex-col" role="menu" aria-orientation="vertical">
                <button
                    @click="switchBrand(null)"
                    class="flex w-full items-center gap-2 px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800"
                    role="menuitem"
                >
                    <span class="h-3 w-3 rounded-full bg-gray-400 shrink-0"></span>
                    <span class="flex-1 text-left">All brands</span>
                    <svg v-if="activeBrandId === null" class="h-4 w-4 text-indigo-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                </button>
                <div class="border-t border-gray-100 dark:border-gray-700"></div>
                <button
                    v-for="brand in brands"
                    :key="brand.id"
                    @click="switchBrand(brand.id)"
                    class="flex w-full items-center gap-2 px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800"
                    role="menuitem"
                >
                    <span
                        class="h-3 w-3 rounded-full shrink-0"
                        :style="{ backgroundColor: brand.color }"
                    ></span>
                    <span class="flex-1 text-left">{{ brand.name }}</span>
                    <svg v-if="activeBrandId === brand.id" class="h-4 w-4 text-indigo-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                </button>
            </div>
        </div>
    </div>
</template>