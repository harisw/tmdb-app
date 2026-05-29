<script setup>

import {computed} from "vue";
import {usePage} from "@inertiajs/vue3";

const props = defineProps({
    title: { type: String, required: true},
    titleLogo: { type: String}
})
const img = computed(() => usePage().props.img)
</script>

<template>
    <div class="flex items-center gap-3 mb-6">
        <img
            v-if="titleLogo"
            :src="`${img.logo}/${titleLogo}`"
            :alt="title"
            class="h-8 w-auto object-contain rounded"
        />
        <h1 class="text-2xl 2xl:text-4xl font-bold text-madder">{{ title }}</h1>
    </div>
    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-5 gap-10 auto-rows-[450px] 2xl:auto-rows-[520px]">
        <slot />
    </div>
</template>

<style scoped>

</style>
