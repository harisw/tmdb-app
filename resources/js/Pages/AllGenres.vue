<template>
    <Head title="Home"/>
    <AppLayout>
        <div>
            <h1 class="text-2xl font-bold mb-6 text-madder">{{ allGenres[0].name }}</h1>
            <div
                ref="carousel"
                class="flex space-x-8 gap-10 overflow-x-auto scroll-smooth no-scrollbar"
            >
                <div v-for="item in allGenres[0].movies" :key="item.id" class="bg-white rounded shadow ">
                    <div class="w-3xs group [perspective:500px] mx-auto h-full hover:cursor-pointer"
                         @click="openModal(item)">
                        <div class="bg-white rounded-xl shadow-xl transition-transform duration-500 transform group-hover:rotate-y-3
                        group-hover:-rotate-x-1 group-hover:scale-105 [transform-style:preserve-3d] overflow-hidden h-full">

                            <img :src="poster_url + item.img_poster" alt="Movie Poster"
                                 class="w-full h-90 object-cover rounded-t-xl"/>

                            <div class="p-2">
                                <div class="flex flex-row justify-between">
                                    <h2 class="text-lg font-bold text-gray-800">{{ item.title }}</h2>
                                    <RatingBadge :rating="item.rating" size="xs"/>
                                </div>
                                <!--                                <div class="flex flex-row gap-2 text-xs">-->
                                <!--                                    <span v-for="genre in item.genres.slice(0, 3)" :key="genre.id" class="py-0.5 px-1-->
                                <!--                                     font-semibold bg-tea-green rounded">-->
                                <!--                                        {{ genre.name }}-->
                                <!--                                    </span>-->
                                <!--                                </div>-->
                            </div>

                        </div>
                    </div>

                </div>
                <!-- Controls -->
                <button
                    @click="scrollLeft"
                    class="absolute left-0 top-1/2 -translate-y-1/2 bg-black/50 text-white p-2 rounded-full hover:bg-black/70"
                >
                    ‹
                </button>
                <button
                    @click="scrollRight"
                    class="absolute right-0 top-1/2 -translate-y-1/2 bg-black/50 text-white p-2 rounded-full hover:bg-black/70"
                >
                    ›
                </button>
            </div>
        </div>
    </AppLayout>
    <MovieModal :show="showModal" :item="selectedItem" @close="closeModal" :backdrop_url="backdrop_url"/>
</template>

<script setup>
import {Head} from "@inertiajs/vue3";
import {ref, onMounted, onUnmounted} from "vue";
import AppLayout from "../Layouts/AppLayout.vue";
import MovieModal from "../Components/Movie-Modal.vue"
import RatingBadge from "../Components/RatingBadge.vue";

defineProps({
    allGenres: Array,
    poster_url: String,
    backdrop_url: String,
})

const selectedItem = ref(null);
const showModal = ref(false);

function openModal(item) {
    selectedItem.value = item;
    showModal.value = true;
}

function closeModal() {
    selectedItem.value = null;
    showModal.value = false;
}

const carousel = ref(null);
let autoplayTimer = null;

const scrollLeft = () => {
    carousel.value.scrollBy({left: -150, behavior: "smooth"})
}

const scrollRight = () => {
    carousel.value.scrollBy({left: 400, behavior: "smooth"})
}

const startAutoplay = () => {
    stopAutoplay();
    autoplayTimer = setInterval(() => {
        if (!carousel.value) return;
        const maxScroll = carousel.value.scrollWidth - carousel.value.clientWidth;

        if (carousel.value.scrollLeft >= maxScroll - 10) {
            carousel.value.scrollTo({left: 0, behavior: "smooth"});
        } else {
            scrollRight();
        }
    }, 5);
};

const stopAutoplay = () => {
    if (autoplayTimer) {
        clearInterval(autoplayTimer);
        autoplayTimer = null;
    }
}

onMounted(() => {
    startAutoplay();

    // Pause autoplay when mouse enters, resume when leaves
    carousel.value.addEventListener("mouseenter", stopAutoplay);
    carousel.value.addEventListener("mouseleave", startAutoplay);
});

onUnmounted(() => {
    stopAutoplay();
});
</script>

<style>
.no-scrollbar::-webkit-scrollbar {
    display: none;
}

.no-scrollbar {
    -ms-overflow-style: none;
    scrollbar-width: none;
}
</style>
