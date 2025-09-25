<script setup>
import {Head} from "@inertiajs/vue3";
import {ref} from "vue";
import AppLayout from "../Layouts/AppLayout.vue";
import MovieModal from "../Components/MovieModal.vue"
import Carousel from "../Components/Carousel.vue";
import MovieCard from "../Components/MovieCard.vue";

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
                    <MovieCard v-for="item in genre.movies" :key="item.id" :on-click="openModal" :item="item"
                               :poster-url="poster_url" :show-genres="false" size="w-3xs"/>
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
