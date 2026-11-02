<script setup>
import { Head } from '@inertiajs/vue3'
import AppLayout from '../Layouts/AppLayout.vue'
import TitleBrowser from '../Components/TitleBrowser.vue'

defineProps({
    provider: { type: Object, required: true },
    titles: { type: Object, required: true },
    sort: { type: String, required: true },
    sorts: { type: Array, required: true },
})
</script>

<template>
    <Head :title="provider.name" />
    <AppLayout>
        <TitleBrowser
            :title="provider.name"
            :logo="provider.img_logo ?? ''"
            :titles="titles"
            :sort="sort"
            :sorts="sorts"
        />
    </AppLayout>
</template>
