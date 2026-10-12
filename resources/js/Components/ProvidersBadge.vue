<script setup>
import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { PROVIDER_MODE_THUMBNAIL } from '../constants/ui.js'

const props = defineProps({
    titleProviders: { type: Array },
    mode: { type: String, default: PROVIDER_MODE_THUMBNAIL },
})

const base_url = computed(() => usePage().props.base_url)
</script>

<template>
    <span v-if="mode === PROVIDER_MODE_THUMBNAIL" class="inline-flex bg-white/90 rounded-md p-0.5 shadow-sm">
        <img
            v-for="tp in titleProviders"
            :key="tp.provider.id"
            :src="`${base_url.logo}/${tp.provider.img_logo}`"
            :alt="tp.provider.name"
            class="h-6 w-6 object-contain rounded"
        />
    </span>
    <div v-else class="flex rounded-md p-0.5 shadow-sm w-full flex-wrap gap-2">
        <template v-for="tp in titleProviders" :key="tp.provider.id">
            <a :href="tp.stream_link ? tp.stream_link : tp.tmdb_link" target="_blank">
                <img
                    :src="`${base_url.logo}/${tp.provider.img_logo}`"
                    :alt="tp.provider.name"
                    class="h-12 w-12 object-contain rounded-xl"
                />
            </a>
        </template>
    </div>
</template>

<style scoped></style>
