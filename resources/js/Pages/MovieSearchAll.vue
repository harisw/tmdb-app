<template>
    <Head title="Home"/>
    <AppLayout>
        <div>
            <h1 class="text-2xl font-bold mb-6 text-madder">Advanced Search</h1>
            <div class="flex flex-col gap-5">
                <div class="flex gap-10">
                    <MultiSelect
                        v-model="selectedGenres"
                        :options="genres"
                        track-by="slug"
                        display-by="name"
                        label="Genre"
                        placeholder="Select Genres..."
                    />

                    <MultiSelect
                        v-model="selectedLanguages"
                        :options="languages"
                        track-by="code"
                        display-by="name"
                        label="Language"
                        placeholder="Select Language..."
                    />

                    <MultiSelect
                        v-model="selectedTags"
                        :options="tags"
                        track-by="slug"
                        display-by="name"
                        label="Popular Tags"
                        placeholder="Select Tags..."
                    />
                </div>
                <div class="flex gap-2 w-1/2 items-center justify-center">
                    <label class="text-white font-semibold text-sm">Keywords</label>
                    <input
                        v-model="searchKey"
                        type="text"
                        placeholder="Enter query"
                        class="w-full px-5 py-2 rounded-lg border bg-white focus:outline-none focus:ring-2 focus:ring-offset-1 focus:ring-indigo-400 shadow-sm"
                        aria-label="Enter query"
                    />
                    <button type="button"
                            @click="searchOnClick"
                            class="hover:cursor-pointer relative group pl-4 pr-8 py-2
                        bg-gradient-to-br from-[#A31621]/20 to-[#8B1219]/20 backdrop-blur-xl
                        text-white font-bold text-lg rounded-2xl border-2 border-[#A31621]/30
                        shadow-[0_0_30px_rgba(163,22,33,0.2),0_12px_0_rgba(163,22,33,0.2),0_15px_40px_rgba(163,22,33,0.3)]
                        hover:shadow-[0_0_40px_rgba(163,22,33,0.4),0_6px_0_rgba(163,22,33,0.3),0_10px_40px_rgba(163,22,33,0.5)]
                        hover:translate-y-[6px] hover:from-[#A31621]/30 hover:to-[#8B1219]/30
                        active:shadow-[0_0_20px_rgba(163,22,33,0.5),0_0px_0_rgba(163,22,33,0.4)]
                        active:translate-y-[12px] transition-all duration-200 overflow-hidden
                    ">
                        Search
                    </button>
                </div>
            </div>


            <div v-if="items" class="w-full mt-46">
                <h1 class="text-2xl font-bold mb-6 text-madder">Results</h1>
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-5 gap-6 auto-rows-[450px]">
                    <MovieCard v-for="item in items" :key="item.id" :on-click="openModal" :item="item"
                               :poster-url="poster_url"/>
                </div>
                <div
                    ref="sentinel"
                    class="flex justify-center items-center py-4 text-white"
                >
                    <LoadingSpinner v-if="loading" text="Loading more" :size=8 color="white"/>
                    <div
                        v-else-if="!hasMore"
                        class="mt-8 w-full text-center rounded-xl text-red-100 py-4 px-6
                        animate-[fadeIn_0.9s_ease-out,scaleIn_0.4s_ease-out]">
                        <span v-if="!items"
                              class="text-lg font-semibold tracking-wide">
                            😢 Looks like this movie hasn't been made yet.
                        </span>
                        <span v-else
                              class="text-lg font-semibold tracking-wide">
                            😢 Not what you’re looking for? Try refining your search.
                        </span>
                    </div>
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
import MultiSelect from "../Components/MultiSelect.vue";
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
const {items, loading, sentinel, fetchItems, hasMore, query} = useInfiniteScroll({movies: props.movies});
const {openModal, closeModal, selectedItem, showModal} = useModal();

const selectedLanguages = ref([])
const selectedGenres = ref([])
const selectedTags = ref([])
const searchKey = ref('')

function searchOnClick() {
    query.value = {
        lang: selectedLanguages.value,
        gen: selectedGenres.value,
        tag: selectedTags.value,
        q: searchKey.value
    };
    console.log("query: ", query.value);
    fetchItems(true);
}

// watch(query, (newVal) => {
//     if (newVal) fetchItems(true);
// });
</script>

<style scoped>
@keyframes gradient {
    0%, 100% {
        background-position: 0% 50%;
    }
    50% {
        background-position: 100% 50%;
    }
}

@keyframes fadeIn {
    from {
        opacity: 0;
    }
    to {
        opacity: 1;
    }
}

@keyframes scaleIn {
    from {
        transform: scale(0.5);
    }
    to {
        transform: scale(1);
    }
}
</style>
