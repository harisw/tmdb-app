<script setup>
import { ArrowBigLeft, ArrowBigRight } from 'lucide-vue-next'
import { ref } from 'vue'

const carousel = ref(null)

const scrollByDir = (dir) => {
    const amount = carousel.value.clientWidth * 0.8
    carousel.value.scrollBy({ left: dir * amount, behavior: 'smooth' })
}
</script>

<template>
    <div class="relative w-full group/carousel">
        <div
            ref="carousel"
            class="flex gap-4 md:gap-6 overflow-x-auto scroll-smooth no-scrollbar snap-x snap-mandatory [&>*]:snap-start [&>*]:shrink-0 pb-2"
        >
            <slot />
        </div>
        <!-- Controls -->
        <button
            class="btn btn-circle btn-neutral shadow-lg hidden md:flex absolute left-0 top-1/2 -translate-y-1/2 -translate-x-1/2 opacity-0 group-hover/carousel:opacity-100 transition-opacity"
            aria-label="Scroll left"
            @click="scrollByDir(-1)"
        >
            <ArrowBigLeft :size="26" />
        </button>
        <button
            class="btn btn-circle btn-neutral shadow-lg hidden md:flex absolute right-0 top-1/2 -translate-y-1/2 translate-x-1/2 opacity-0 group-hover/carousel:opacity-100 transition-opacity"
            aria-label="Scroll right"
            @click="scrollByDir(1)"
        >
            <ArrowBigRight :size="26" />
        </button>
    </div>
</template>

<style scoped></style>
