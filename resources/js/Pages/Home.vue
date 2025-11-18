<template>
    <Head title="Home"/>
    <AppLayout>
        <div>
            <div class="flex flex-col justify-center items-center gap-5 mt-20 mb-36">
                <div class="min-h-36">
                    <RotatingHeader :texts="headlines" :interval="3500" :duration="750" tag="h2"
                                    textClass="text-5xl font-bold text-madder"/>
                </div>
                <SearchInput
                    v-model="q"
                    :debounce="400"
                    :min-length="2"
                    placeholder="Search movies..."
                    :preserve-state="true"
                    :replace="false"
                    route="search"
                    @result-on-click="openModal"
                />
            </div>

            <h1 class="text-2xl font-bold mb-6 text-madder">Hottest Movies</h1>
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-5 gap-6 auto-rows-[450px]">
                <MovieCard v-for="item in items" :key="item.id" :on-click="openModal" :item="item"
                           :poster-url="poster_url"/>

            </div>
            <div
                ref="sentinel"
                class="flex justify-center items-center py-4 text-white"
            >
                <LoadingSpinner v-if="loading" text="Loading more" size="8" color="white"/>
                <span v-else-if="!hasMore">No more results</span>
            </div>
        </div>
    </AppLayout>
    <MovieModal :show="showModal" :item="selectedItem" @close="closeModal" :backdrop_url="backdrop_url"/>
</template>

<script setup>
import {Head} from "@inertiajs/vue3";
import {ref} from 'vue';
import AppLayout from "../Layouts/AppLayout.vue";
import MovieModal from "../Components/MovieModal.vue"
import MovieCard from "../Components/MovieCard.vue";
import LoadingSpinner from "../Components/LoadingSpinner.vue";
import RotatingHeader from "../Components/RotatingHeader.vue";
import SearchInput from "../Components/SearchInput.vue";
import useInfiniteScroll from "../Composables/useInfiniteScroll.js";
import useModal from "../Composables/useModal.js";

const props = defineProps({
    movies: Object,
    poster_url: String,
    backdrop_url: String,
})

const {items, loading, sentinel, _, hasMore} = useInfiniteScroll({movies: props.movies})
const {openModal, closeModal, selectedItem, showModal} = useModal();

const headlines = [
    'Looking for some action or romance?',
    'Search with title, genre, or even some keywords',
    'Something to spice your weekend?',
    "Don't waste your time, start watching now!"
];

const q = ref('');
</script>
