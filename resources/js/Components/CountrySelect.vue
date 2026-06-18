<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import { usePage } from '@inertiajs/vue3'

const model = defineModel({ type: String, default: '' })

const countries = usePage().props.countries
const isOpen = ref(false)
const dropdownRef = ref(null)

const selected = computed(() => countries.find((c) => c.iso2 === model.value))

function select(iso2) {
    model.value = iso2
    isOpen.value = false
}

function handleClickOutside(e) {
    if (dropdownRef.value && !dropdownRef.value.contains(e.target)) {
        isOpen.value = false
    }
}

onMounted(() => document.addEventListener('click', handleClickOutside))
onBeforeUnmount(() => document.removeEventListener('click', handleClickOutside))
</script>

<template>
    <div ref="dropdownRef" class="relative">
        <button
            type="button"
            class="w-full flex items-center justify-between gap-2 px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm focus:outline-none focus:border-madder transition-colors"
            :class="selected ? 'text-white' : 'text-gray-500'"
            @click="isOpen = !isOpen"
        >
            <span class="flex items-center gap-2">
                <span v-if="selected" :class="`fi fi-${selected.iso2.toLowerCase()} fis`"></span>
                {{ selected?.name ?? 'Choose a country' }}
            </span>
            <svg
                class="w-4 h-4 shrink-0 transition-transform text-gray-400"
                :class="{ 'rotate-180': isOpen }"
                fill="currentColor"
                viewBox="0 0 20 20"
            >
                <path d="M5.25 7.5L10 12.25L14.75 7.5H5.25Z" />
            </svg>
        </button>

        <ul
            v-if="isOpen"
            class="absolute z-50 w-full mt-1 bg-zinc-800 border border-white/10 rounded-lg max-h-52 overflow-y-auto shadow-xl"
        >
            <li v-for="c in countries" :key="c.iso2">
                <button
                    type="button"
                    class="w-full flex items-center gap-2.5 px-3 py-2 text-sm text-white hover:bg-white/10 text-left transition-colors"
                    :class="{ 'bg-white/5': c.iso2 === model }"
                    @click="select(c.iso2)"
                >
                    <span :class="`fi fi-${c.iso2.toLowerCase()} fis`"></span>
                    {{ c.name }}
                </button>
            </li>
        </ul>
    </div>
</template>
