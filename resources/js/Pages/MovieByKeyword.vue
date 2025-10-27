<template>
    <Head title="Home"/>
    <AppLayout>
        <div>
            <h1 class="text-2xl font-bold mb-6 text-madder">Browse by Tags</h1>
            <div class="flex flex-wrap text-white gap-2 w-full">
                <div v-for="tag in tagItems" :key="tag.id"
                     class="hover:cursor-pointer hover:underline hover:font-semibold hover:text-gray-300"
                     @click="itemOnClick(tag)">
                    {{ tag.name }} ({{ tag.count }}),
                </div>
            </div>
            <div
                class="flex justify-center items-center py-4 text-white"
                v-if="hasMoreTags"
            >
                <LoadingSpinner v-if="loadingTags" text="Loading more" size="8" color="white"/>
                <button v-else @click="loadMoreTags"
                        class="bg-madder px-4 py-2 rounded-lg hover:cursor-pointer text-xs">Load
                    more Tags
                </button>
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
    tags: Object,
    movies: Object,
    poster_url: String,
    backdrop_url: String,
});

const selectedTag = ref(null);
const loadingTags = ref(false);
const hasMoreTags = ref(true);
const tagOffset = ref(15);
const tagItems = ref([...props.tags])

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
