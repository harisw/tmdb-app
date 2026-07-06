<script setup>
import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'

const props = defineProps({
    title: { type: String, required: true },
    titleLogo: { type: String, default: '' },
})
const img = computed(() => usePage().props.base_url)
</script>

<template>
    <div class="flex items-center gap-3 mb-6">
        <img
            v-if="titleLogo"
            :src="`${img.logo}/${titleLogo}`"
            :alt="title"
            class="h-8 w-auto object-contain rounded"
        />
        <h1 class="text-2xl 2xl:text-4xl font-bold text-madder [text-shadow:0_1px_2px_rgb(0_0_0/0.4)]">
            {{ title }}
        </h1>
    </div>
    <div class="grid grid-cols-2 md:grid-cols-4 xl:grid-cols-5 gap-3 sm:gap-6 xl:gap-8">
        <slot />
    </div>
</template>

<style scoped></style>
