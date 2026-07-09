<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'

const props = defineProps({
    text: { type: String, default: 'Loading' },
    size: { type: [Number, String], default: 8 },
    color: { type: String, default: 'white' },
})

// Tailwind can't generate classes built at runtime (`h-${size}`, `border-${color}`),
// so drive size + color with inline styles instead. `size` is in Tailwind's
// spacing scale (1 unit = 0.25rem), matching the old `h-8`/`w-8` intent.
const dimension = computed(() => `${(Number(props.size) || 8) * 0.25}rem`)
const spinnerStyle = computed(() => ({
    width: dimension.value,
    height: dimension.value,
    borderColor: props.color,
    borderTopColor: 'transparent',
}))

const dots = ref('')
let interval

onMounted(() => {
    let count = 0
    interval = setInterval(() => {
        count = (count + 1) % 4
        dots.value = '.'.repeat(count)
    }, 500)
})

onUnmounted(() => {
    clearInterval(interval)
})
</script>

<template>
    <div class="text-center py-4">
        <div class="animate-spin rounded-full border-2 border-solid mx-auto" :style="spinnerStyle"></div>
        <p class="mt-2" :style="{ color }">{{ text }}{{ dots }}</p>
    </div>
</template>

<style scoped></style>
