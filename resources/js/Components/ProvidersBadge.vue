<script setup>
import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'

const props = defineProps({
    titleProviders: { type: Array },
})

const base_url = computed(() => usePage().props.base_url)
</script>

<template>
    <span class="inline-flex bg-white/90 rounded-md p-0.5 shadow-sm">
        <img
            v-for="tp in titleProviders"
            :key="tp.provider.id"
            :src="`${base_url.logo}/${tp.provider.img_logo}`"
            :alt="tp.provider.name"
            class="h-6 w-6 object-contain rounded"
        />
    </span>
</template>

<style scoped></style>
