<script setup>
import { ChevronLeft, ChevronRight } from 'lucide-vue-next'
import { onBeforeUnmount, onMounted, ref } from 'vue'
import { prefersReducedMotion } from '../utils/motion.js'
import { button } from '../variants/button.js'

const SCROLL_PAGE = 0.8

const track = ref(null)
const canScrollLeft = ref(false)
const canScrollRight = ref(false)

let resizeObserver = null

function updateEdges() {
    const el = track.value
    if (!el) return
    canScrollLeft.value = el.scrollLeft > 1
    canScrollRight.value = el.scrollLeft < el.scrollWidth - el.clientWidth - 1
}

function scrollByPage(direction) {
    track.value?.scrollBy({
        left: direction * track.value.clientWidth * SCROLL_PAGE,
        behavior: prefersReducedMotion() ? 'auto' : 'smooth',
    })
}

const arrow = (enabled) =>
    button({ intent: 'ghost', size: 'sm', circle: true, class: enabled ? 'bg-white/5' : 'bg-white/5 opacity-30' })

onMounted(() => {
    updateEdges()
    resizeObserver = new ResizeObserver(updateEdges)
    resizeObserver.observe(track.value)
})
onBeforeUnmount(() => resizeObserver?.disconnect())
</script>

<template>
    <div class="w-full">
        <div class="mb-6 flex items-center justify-between gap-4">
            <slot name="header" />
            <div v-if="canScrollLeft || canScrollRight" class="hidden shrink-0 gap-1.5 md:flex">
                <button
                    type="button"
                    :class="arrow(canScrollLeft)"
                    :disabled="!canScrollLeft"
                    aria-label="Scroll left"
                    @click="scrollByPage(-1)"
                >
                    <ChevronLeft class="size-4" aria-hidden="true" />
                </button>
                <button
                    type="button"
                    :class="arrow(canScrollRight)"
                    :disabled="!canScrollRight"
                    aria-label="Scroll right"
                    @click="scrollByPage(1)"
                >
                    <ChevronRight class="size-4" aria-hidden="true" />
                </button>
            </div>
        </div>
        <div
            ref="track"
            class="no-scrollbar -mx-(--page-gutter) flex snap-x snap-proximity scroll-px-(--page-gutter) gap-4 overflow-x-auto scroll-smooth px-(--page-gutter) pt-1 pb-2 motion-reduce:scroll-auto md:gap-6 *:shrink-0 *:snap-start *:basis-[42%] sm:*:basis-[29%] lg:*:basis-[21%] 2xl:*:basis-[17%]"
            @scroll.passive="updateEdges"
        >
            <slot />
        </div>
    </div>
</template>
