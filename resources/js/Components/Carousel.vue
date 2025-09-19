<script setup>

import {ArrowBigLeft, ArrowBigRight} from "lucide-vue-next";
import {ref} from "vue";

const carousel = ref(null);
const scrollLeft = () => {
    carousel.value.scrollBy({left: -150, behavior: "smooth"})
}

const scrollRight = () => {
    carousel.value.scrollBy({left: 400, behavior: "smooth"})
}
</script>

<template>
    <div class="relative w-full">
        <div
            ref="carousel"
            class="flex space-x-8 gap-10 overflow-x-auto scroll-smooth no-scrollbar"
        >
            <slot/>


        </div>
        <!-- Controls -->
        <button
            @click="scrollLeft"
            class="cursor-pointer absolute left-0 top-1/2 -translate-x-5/6 -translate-y-1/2 bg-black/50 text-white p-2 rounded-full hover:bg-black/70"
        >
            <ArrowBigLeft size="46"/>
        </button>
        <button
            @click="scrollRight"
            class="cursor-pointer absolute right-0 top-1/2 translate-x-5/6 -translate-y-1/2 bg-black/50 text-white p-2 rounded-full hover:bg-black/70"
        >
            <ArrowBigRight size="46"/>
        </button>
    </div>
</template>

<style scoped>

</style>
