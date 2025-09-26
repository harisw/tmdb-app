<template>
    <Head title="Home"/>
    <AppLayout>
        <div>
            <h1 class="text-2xl font-bold mb-6 text-madder">Browse by Languages</h1>
            <div class="flex text-white font-semibold gap-2 w-max-50">
                <div v-for="lang in languages" :key="lang.id">{{ lang.name }} ({{ lang.count }})</div>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-5 gap-6 auto-rows-[450px] w-96">

                <MovieCard v-for="item in items" :key="item.id" :on-click="openModal" :item="item"
                           :poster-url="poster_url"/>
            </div>
            <LoadingSpinner v-if="loading" text="Loading more" size="8" color="white"/>
        </div>
    </AppLayout>
    <MovieModal :show="showModal" :item="selectedItem" @close="closeModal" :backdrop_url="backdrop_url"/>
</template>

<script setup>
import {Head} from "@inertiajs/vue3";
import AppLayout from "../Layouts/AppLayout.vue";
import MovieModal from "../Components/MovieModal.vue"
import MovieCard from "../Components/MovieCard.vue";
import LoadingSpinner from "../Components/LoadingSpinner.vue";
import useInfiniteScroll from "../Composables/useInfiniteScroll.js";
import useModal from "../Composables/useModal.js";

const props = defineProps({
    languages: Object,
    movies: Object,
    poster_url: String,
    backdrop_url: String,
});

const {items, loading} = useInfiniteScroll({movies: props.movies});
const {openModal, closeModal, selectedItem, showModal} = useModal();

</script>
