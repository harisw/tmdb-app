<script setup>

import {ref, onMounted, onUnmounted} from "vue";

const props = defineProps({
    text: {type: String, default: "Loading"},
    size: {type: Number, default: 8},
    color: {type: String, default: "white"}
});

const dots = ref("")
let interval;

onMounted(() => {
    let count = 0;
    interval = setInterval(() => {
        count = (count + 1) % 4;
        dots.value = ".".repeat(count);
    }, 500)
});

onUnmounted(() => {
    clearInterval(interval);
});
</script>

<template>
    <div class="text-center py-4">
        <div class="animate-spin rounded-full border-b-2 mx-auto" :class="`h-${size} w-${size} border-${color}`">
        </div>
        <p class="mt-2" :class="`text-${color}`">{{ text }}{{ dots }}</p>
    </div>
</template>

<style scoped>

</style>
