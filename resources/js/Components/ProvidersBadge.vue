<script setup>
import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'

const props = defineProps({
    titleProviders: { type: Array },
})

const base_url = computed(() => usePage().props.base_url)
</script>

<template>
    <img
        v-for="tp in titleProviders"
        :key="tp.provider.id"
        :src="`${base_url.logo}/${tp.provider.img_logo}`"
        :alt="tp.provider.name"
        class="h-8 w-auto object-contain rounded"
    />
</template>

<style scoped></style>
