<template>

    <div
        class="inline-block"
        @mouseenter="paused = true"
        @mouseleave="paused = false"
        aria-live="polite"
        role="status"
    >
        <!-- use dynamic tag (h1, h2...) -->
        <component :is="tag" class="relative inline-block">
            <!-- Vue transition keyed by text so it animates on change -->
            <transition
                name="rotating-fade"
                appear
                :css="true"
            >
                <span
                    :key="current"
                    :class="`inline-block ${textClass}`"
                    v-text="current"
                    v-show="!!current"
                />
            </transition>
        </component>

    </div>
</template>

<style scoped>
.rotating-fade-enter-active,
.rotating-fade-leave-active {
    transition: opacity var(--duration, 600ms) ease, transform var(--duration, 600ms) ease;
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

<script setup>
import {ref, computed, onMounted, onUnmounted, watch} from "vue";
import {toRef} from 'vue';


const props = defineProps({
    texts: {
        type: Array,
        required: true,
        validator: (v) => Array.isArray(v) && v.length > 0
    },
    textClass: {type: String, default: ''},
    interval: {type: Number, default: 3000},
    duration: {type: Number, default: 600},
    tag: {type: String, default: 'h2'}
});

const texts = toRef(props, 'texts');
const interval = toRef(props, 'interval');
const duration = toRef(props, 'duration');

const index = ref(0);
const paused = ref(false);
let timer = null;

const current = computed(() => texts.value[index.value] ?? '');

function advance() {
    index.value = (index.value + 1) % texts.value.length
}

onMounted(() => {
    if ((texts.value?.length ?? 0) < 2) return;

    timer = setInterval(() => {
        if (!paused.value) advance()
    }, interval.value)
})

onUnmounted(() => {
    if (timer) clearInterval(timer);
});

watch(interval, (newVal) => {
    if (timer) clearInterval(timer);
    if ((texts.value.length ?? 0) < 2) return;

    timer = setInterval(() => {
        if (!paused.value) advance()
    }, newVal)
})

// set CSS variable for duration dynamically so prop duration works
// (script placed after template/style so DOM exists)
import {watchEffect} from 'vue'

const el = (/* istanbul ignore next */) => document ? document.currentScript?.ownerDocument?.querySelector('div.inline-block') : null
watchEffect(() => {
    // set --duration on the component root; if not found, skip silently
    const root = document?.querySelector('div.inline-block')
    if (root) root.style.setProperty('--duration', `${duration.value}ms`)
})
</script>
