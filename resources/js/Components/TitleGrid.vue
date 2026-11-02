<script setup>
import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'

const props = defineProps({
    title: { type: String, required: true },
    titleLogo: { type: String, default: '' },
    // `h1` by default so the standalone browse pages (genre, provider, tag…)
    // keep their page heading; pages with their own <h1> pass `h2`.
    headingTag: { type: String, default: 'h1' },
})
const img = computed(() => usePage().props.base_url)
</script>

<template>
    <div class="mb-6">
        <div class="flex items-center gap-3">
            <img v-if="titleLogo" :src="`${img.logo}/${titleLogo}`" alt="" class="h-8 w-auto object-contain rounded" />
            <component
                :is="headingTag"
                class="text-2xl 2xl:text-4xl font-bold text-white [text-shadow:0_1px_2px_rgb(0_0_0/0.4)]"
            >
                {{ title }}
            </component>
        </div>
        <slot name="meta" />
    </div>
    <div class="grid grid-cols-2 md:grid-cols-4 xl:grid-cols-5 gap-3 sm:gap-6 xl:gap-8">
        <slot />
    </div>
</template>

<style scoped></style>
