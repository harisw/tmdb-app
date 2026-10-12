<script setup>
import { ArrowBigLeft, ArrowBigRight } from 'lucide-vue-next'
import { onBeforeUnmount, onMounted, ref } from 'vue'

const carousel = ref(null)
const canScrollLeft = ref(false)
const canScrollRight = ref(false)

const prefersReducedMotion = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches

function updateEdges() {
    const el = carousel.value
    if (!el) return
    canScrollLeft.value = el.scrollLeft > 1
    canScrollRight.value = el.scrollLeft < el.scrollWidth - el.clientWidth - 1
}

function scrollByDir(dir) {
    const el = carousel.value
    if (!el) return
    el.scrollBy({
        left: dir * el.clientWidth * 0.8,
        behavior: prefersReducedMotion() ? 'auto' : 'smooth',
    })
}

onMounted(() => {
    updateEdges()
    window.addEventListener('resize', updateEdges)
})
onBeforeUnmount(() => window.removeEventListener('resize', updateEdges))
</script>

<template>
    <div class="relative w-full group/carousel">
        <div
            ref="carousel"
            class="flex gap-4 md:gap-6 overflow-x-auto scroll-smooth motion-reduce:scroll-auto no-scrollbar snap-x snap-mandatory [&>*]:snap-start [&>*]:shrink-0 pb-2"
            @scroll.passive="updateEdges"
        >
            <slot />
        </div>
        <!-- Controls: revealed on hover, and on keyboard focus so tab users can see what they're on. -->
        <button
            v-show="canScrollLeft"
            class="btn btn-circle btn-neutral shadow-lg hidden md:flex absolute left-0 top-1/2 -translate-y-1/2 -translate-x-1/2 opacity-0 transition-opacity group-hover/carousel:opacity-100 focus-visible:opacity-100"
            aria-label="Scroll left"
            @click="scrollByDir(-1)"
        >
            <ArrowBigLeft :size="26" />
        </button>
        <button
            v-show="canScrollRight"
            class="btn btn-circle btn-neutral shadow-lg hidden md:flex absolute right-0 top-1/2 -translate-y-1/2 translate-x-1/2 opacity-0 transition-opacity group-hover/carousel:opacity-100 focus-visible:opacity-100"
            aria-label="Scroll right"
            @click="scrollByDir(1)"
        >
            <ArrowBigRight :size="26" />
        </button>
    </div>
</template>

<style scoped></style>
