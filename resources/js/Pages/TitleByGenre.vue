<script setup>
import { Head } from '@inertiajs/vue3'
import AppLayout from '../Layouts/AppLayout.vue'
import TitleBrowser from '../Components/TitleBrowser.vue'

defineProps({
    genre: { type: Object, required: true },
    titles: { type: Object, required: true },
    sort: { type: String, required: true },
    sorts: { type: Array, required: true },
})
</script>

<template>
    <Head :title="genre.name" />
    <AppLayout>
        <TitleBrowser :title="genre.name" :titles="titles" :sort="sort" :sorts="sorts" />
    </AppLayout>
</template>
