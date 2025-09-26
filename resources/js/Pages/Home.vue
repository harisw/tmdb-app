<template>
    <Head title="Home"/>
    <AppLayout>
        <div>
            <h1 class="text-2xl font-bold mb-6 text-madder">Hottest Movies</h1>
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-5 gap-6 auto-rows-[450px]">
                <MovieCard v-for="item in items" :key="item.id" :on-click="openModal" :item="item"
                           :poster-url="poster_url"/>

            </div>
            <LoadingSpinner v-if="loading" text="Loading more" size="8" color="white"/>
        </div>
    </AppLayout>
    <MovieModal :show="showModal" :item="selectedItem" @close="closeModal" :backdrop_url="backdrop_url"/>
</template>

<script setup>
import {Head, router} from "@inertiajs/vue3";
import {onBeforeUnmount, onMounted, ref} from "vue";
import AppLayout from "../Layouts/AppLayout.vue";
import MovieModal from "../Components/MovieModal.vue"
import MovieCard from "../Components/MovieCard.vue";
import useInfiniteScroll from "../Composables/useInfiniteScroll.js";
import LoadingSpinner from "../Components/LoadingSpinner.vue";

const props = defineProps({
    movies: Object,
    poster_url: String,
    backdrop_url: String,
})

const {items, loading} = useInfiniteScroll({movies: props.movies})

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
