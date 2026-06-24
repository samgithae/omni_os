@php
    $activeBrand = $activeBrandId ? $brands->firstWhere('id', $activeBrandId) : null;
    $activeLabel = $activeBrand?->name ?? 'All brands';
    $activeColor = $activeBrand?->color ?? '#6b7280';
@endphp

<div
    x-data="{ open: false }"
    @click.outside="open = false"
    class="relative"
>
    <button
        type="button"
        @click="open = !open"
        class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800"
    >
        <span
            class="h-3 w-3 rounded-full"
            style="background-color: {{ $activeColor }};"
        ></span>
        <span>{{ $activeLabel }}</span>
        <x-filament::icon
            icon="heroicon-o-chevron-down"
            class="h-4 w-4 text-gray-400"
        />
    </button>

    <div
        x-show="open"
        x-transition:enter="transition ease-out duration-100"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-75"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
        class="absolute right-0 mt-2 w-56 rounded-lg border border-gray-200 bg-white shadow-lg dark:border-gray-700 dark:bg-gray-900"
        style="z-index: 50;"
    >
        <div class="py-1 flex flex-col">
            <button
                wire:click="switchBrand(null)"
                class="flex w-full items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800"
            >
                <span class="h-3 w-3 rounded-full bg-gray-400"></span>
                <span>All brands</span>
                @if ($activeBrandId === null)
                    <x-filament::icon icon="heroicon-o-check" class="ml-auto h-4 w-4 text-indigo-600" />
                @endif
            </button>

            @foreach ($brands as $brand)
                <button
                    wire:click="switchBrand({{ $brand->id }})"
                    class="flex w-full items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800"
                >
                    <span
                        class="h-3 w-3 rounded-full"
                        style="background-color: {{ $brand->color }};"
                    ></span>
                    <span>{{ $brand->name }}</span>
                    @if ($activeBrandId === $brand->id)
                        <x-filament::icon icon="heroicon-o-check" class="ml-auto h-4 w-4 text-indigo-600" />
                    @endif
                </button>
            @endforeach
        </div>
    </div>
</div>