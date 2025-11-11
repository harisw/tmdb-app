<template>
    <Head title="Home"/>
    <AppLayout>
        <div>
            <h1 class="text-2xl font-bold mb-6 text-madder">Search</h1>
            <div class="flex gap-10">

            </div>

            <div v-if="items && selectedTag" class="w-full mt-10">
                <h1 class="text-2xl font-bold mb-6 text-madder">{{ selectedTag.name }} Movies</h1>
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
        </div>
    </AppLayout>
    <MovieModal :show="showModal" :item="selectedItem" @close="closeModal" :backdrop_url="backdrop_url"/>
</template>

<script setup>
import {ref, watch} from "vue";
import {Head, router} from "@inertiajs/vue3";
import AppLayout from "../Layouts/AppLayout.vue";
import MovieModal from "../Components/MovieModal.vue"
import MovieCard from "../Components/MovieCard.vue";
import LoadingSpinner from "../Components/LoadingSpinner.vue";
import useInfiniteScroll from "../Composables/useInfiniteScroll.js";
import useModal from "../Composables/useModal.js";

const props = defineProps({
    genres: Object,
    languages: Object,
    tags: Object,
    movies: Object,
    poster_url: String,
    backdrop_url: String,
});

function itemOnClick(item) {
    query.value = {
        tag: item.slug
    };
    selectedTag.value = item;
}

function loadMoreTags() {
    if (!hasMoreTags.value) return;

    loadingTags.value = true;
    tagOffset.value += 35;

    router.get(
        window.location.pathname,
        {tagOffset: tagOffset.value},
        {
            preserveScroll: true,
            preserveState: true,
            only: ['tags'],
            onSuccess: (page) => {
                const newTags = page.props.tags;
                if (!newTags) {
                    hasMoreTags.value = false;
                    return;
                }

                tagItems.value.push(...newTags);
            },
            onFinish: () => (loadingTags.value = false),
        }
    )
}


const {items, loading, sentinel, fetchItems, hasMore, query} = useInfiniteScroll({movies: props.movies});
const {openModal, closeModal, selectedItem, showModal} = useModal();

watch(query, (newVal) => {
    if (newVal) fetchItems(true);
});
</script>
