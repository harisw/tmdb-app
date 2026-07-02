<template>
    <div
        ref="root"
        class="relative w-full text-center"
        :style="containerStyle"
        aria-live="polite"
        role="status"
        @mouseenter="paused = true"
        @mouseleave="paused = false"
    >
        <!-- use dynamic tag (h1, h2...) -->
        <component :is="tag" class="relative block w-full">
            <!-- Vue transition keyed by text so it animates on change -->
            <transition name="rotating-fade" appear :css="true">
                <span
                    v-show="!!current"
                    :key="current"
                    :class="`absolute inset-x-0 top-0 break-words ${sizeClassFor(current)} ${textClass}`"
                    v-text="current"
                />
            </transition>
        </component>

        <!-- hidden copies of every headline, used only to measure the tallest one so the -->
        <!-- container height never changes as headlines with different lengths rotate in -->
        <div class="invisible absolute inset-x-0 top-0 -z-10" aria-hidden="true">
            <span
                v-for="text in texts"
                :key="text"
                ref="measureEls"
                :class="`absolute inset-x-0 top-0 break-words ${sizeClassFor(text)} ${textClass}`"
                >{{ text }}</span
            >
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch, nextTick } from 'vue'
import { toRef } from 'vue'

const props = defineProps({
    texts: {
        type: Array,
        required: true,
        validator: (v) => Array.isArray(v) && v.length > 0,
    },
    textClass: { type: String, default: '' },
    interval: { type: Number, default: 3000 },
    duration: { type: Number, default: 600 },
    tag: { type: String, default: 'h2' },
})

const texts = toRef(props, 'texts')
const interval = toRef(props, 'interval')
const duration = toRef(props, 'duration')

const index = ref(0)
const paused = ref(false)
let timer = null

const current = computed(() => texts.value[index.value] ?? '')

function advance() {
    index.value = (index.value + 1) % texts.value.length
}

// longer headlines get a smaller size tier so they don't wrap excessively or overflow
const SIZE_TIERS = [
    { maxLength: 35, class: 'text-3xl md:text-5xl 2xl:text-7xl' },
    { maxLength: 55, class: 'text-2xl md:text-4xl 2xl:text-6xl' },
    { maxLength: Infinity, class: 'text-xl md:text-3xl 2xl:text-5xl' },
]

function sizeClassFor(text) {
    const length = text?.length ?? 0
    return (SIZE_TIERS.find((tier) => length <= tier.maxLength) ?? SIZE_TIERS.at(-1)).class
}

onMounted(() => {
    if ((texts.value?.length ?? 0) < 2) return

    timer = setInterval(() => {
        if (!paused.value) advance()
    }, interval.value)
})

onUnmounted(() => {
    if (timer) clearInterval(timer)
})

watch(interval, (newVal) => {
    if (timer) clearInterval(timer)
    if ((texts.value.length ?? 0) < 2) return

    timer = setInterval(() => {
        if (!paused.value) advance()
    }, newVal)
})

// reserve enough height for the tallest headline so swapping text never shifts
// surrounding layout (e.g. the search box below) up or down
const root = ref(null)
const measureEls = ref([])
const heights = ref([])

function updateHeights() {
    heights.value = measureEls.value.map((el) => el?.offsetHeight ?? 0)
}

const maxHeight = computed(() => Math.max(0, ...heights.value))
const containerStyle = computed(() => (maxHeight.value ? { minHeight: `${maxHeight.value}px` } : {}))

let resizeObserver = null

onMounted(() => {
    if (root.value) root.value.style.setProperty('--duration', `${duration.value}ms`)

    nextTick(() => {
        updateHeights()
        resizeObserver = new ResizeObserver(() => updateHeights())
        measureEls.value.forEach((el) => el && resizeObserver.observe(el))
    })
})

onUnmounted(() => {
    resizeObserver?.disconnect()
})

watch(duration, (newVal) => {
    root.value?.style.setProperty('--duration', `${newVal}ms`)
})
</script>

<style scoped>
.rotating-fade-enter-active,
.rotating-fade-leave-active {
    transition:
        opacity var(--duration, 600ms) ease,
        transform var(--duration, 600ms) ease;
    will-change: opacity, transform;
}

.rotating-fade-enter-from {
    opacity: 0;
    transform: translateY(6px) scale(0.995);
}

.rotating-fade-enter-to {
    opacity: 1;
    transform: translateY(0) scale(1);
}

.rotating-fade-leave-from {
    opacity: 1;
    transform: translateY(0) scale(1);
}

.rotating-fade-leave-to {
    opacity: 0;
    transform: translateY(-6px) scale(0.995);
}
</style>
