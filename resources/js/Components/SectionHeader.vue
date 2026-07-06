<script setup>
defineProps({ title: String, logo: { type: String, default: '' } })
</script>
<template>
    <div class="flex items-center gap-3 mb-6">
        <img v-if="logo" :src="logo" :alt="title" class="h-8 w-auto object-contain rounded" />
        <h1 class="text-2xl 2xl:text-4xl font-bold text-primary [text-shadow:0_1px_2px_rgb(0_0_0/0.4)]">
            {{ title }}
        </h1>
    </div>
</template>

<style scoped></style>
