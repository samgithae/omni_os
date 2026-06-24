<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue'
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
const dropdownRef = ref<HTMLElement | null>(null)

const activeBrand = computed(() =>
    props.activeBrandId ? props.brands.find(b => b.id === props.activeBrandId) : null
)

const activeLabel = computed(() => activeBrand.value?.name ?? 'All brands')
const activeColor = computed(() => activeBrand.value?.color ?? '#6b7280')

function toggle() {
    open.value = !open.value
}

function switchBrand(brandId: number | null) {
    open.value = false
    router.post('/brand/switch', { brand_id: brandId }, {
        preserveScroll: true,
        preserveState: false,
    })
}

function handleClickOutside(e: MouseEvent) {
    if (dropdownRef.value && !dropdownRef.value.contains(e.target as Node)) {
        open.value = false
    }
}

onMounted(() => document.addEventListener('click', handleClickOutside))
onUnmounted(() => document.removeEventListener('click', handleClickOutside))
</script>

<template>
    <div ref="dropdownRef" class="relative">
        <button
            type="button"
            @click="toggle"
            class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800"
        >
            <span
                class="h-3 w-3 rounded-full"
                :style="{ backgroundColor: activeColor }"
            ></span>
            <span>{{ activeLabel }}</span>
            <ChevronDown class="h-4 w-4 text-gray-400" />
        </button>

        <div
            v-if="open"
            class="absolute right-0 mt-2 w-56 rounded-lg border border-gray-200 bg-white shadow-lg dark:border-gray-700 dark:bg-gray-900"
            style="z-index: 9999;"
        >
            <div class="py-1 flex flex-col">
                <button
                    @click="switchBrand(null)"
                    class="flex w-full items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800"
                >
                    <span class="h-3 w-3 rounded-full bg-gray-400"></span>
                    <span>All brands</span>
                    <svg v-if="activeBrandId === null" class="ml-auto h-4 w-4 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                </button>

                <button
                    v-for="brand in brands"
                    :key="brand.id"
                    @click="switchBrand(brand.id)"
                    class="flex w-full items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800"
                >
                    <span
                        class="h-3 w-3 rounded-full"
                        :style="{ backgroundColor: brand.color }"
                    ></span>
                    <span>{{ brand.name }}</span>
                    <svg v-if="activeBrandId === brand.id" class="ml-auto h-4 w-4 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                </button>
            </div>
        </div>
    </div>
</template>