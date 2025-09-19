<script setup>
import {Head} from "@inertiajs/vue3";
import {ref} from "vue";
import AppLayout from "../Layouts/AppLayout.vue";
import MovieModal from "../Components/Movie-Modal.vue"
import RatingBadge from "../Components/RatingBadge.vue";
import Carousel from "../Components/Carousel.vue";

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


</script>

<template>
    <Head title="Home"/>
    <AppLayout>
        <div class="w-full">
            <div v-for="genre in allGenres" :key="genre.id" class="mb-15">
                <h1 class="text-2xl font-bold mb-6 text-madder">{{ genre.name }}</h1>
                <Carousel>
                    <div v-for="item in genre.movies" :key="item.id" class="bg-white rounded shadow ">
                        <div
                            class="w-3xs group [perspective:500px] mx-auto h-full hover:cursor-pointer overflow-hidden"
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

                                </div>

                            </div>
                        </div>

                    </div>
                </Carousel>
            </div>
        </div>
    </AppLayout>
    <MovieModal :show="showModal" :item="selectedItem" @close="closeModal" :backdrop_url="backdrop_url"/>
</template>

<style>
.no-scrollbar::-webkit-scrollbar {
    display: none;
}

.no-scrollbar {
    -ms-overflow-style: none;
    scrollbar-width: none;
}
</style>
