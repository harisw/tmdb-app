<template>
    <Head title="Find by Languages" />
    <AppLayout>
        <div>
            <h1 class="text-2xl 2xl:text-4xl font-bold mb-6 text-madder">Browse by Languages</h1>
            <div class="flex flex-wrap text-white gap-3 2xl:gap-5 w-full">
                <div
                    v-for="lang in languages"
                    :key="lang.id"
                    class="text-sm 2xl:text-xl hover:cursor-pointer hover:underline hover:font-semibold hover:text-gray-300"
                    @click="itemOnClick(lang)"
                >
                    >{{ lang.name }} ({{ lang.count }})
                </div>
            </div>
            <div v-if="items && selectedLang" class="w-full mt-10">
                <h1 class="text-2xl 2xl:text-4xl font-bold mb-6 text-madder">{{ selectedLang.name }} Movies</h1>
                <div
                    class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-5 xl:grid-cols-6 2xl:grid-cols-7 gap-6 auto-rows-[450px] 2xl:auto-rows-[520px]"
                >
                    <MovieCard
                        v-for="item in items"
                        :key="item.id"
                        :on-click="openModal"
                        :item="item"
                        :poster-url="poster_url"
                    />
                </div>
                <div ref="sentinel" class="flex justify-center items-center py-4 text-white">
                    <LoadingSpinner v-if="loading" text="Loading more" size="8" color="white" />
                    <span v-else-if="!hasMore">No more results</span>
                </div>
            </div>
        </div>
    </AppLayout>
    <MovieModal :show="showModal" :item="selectedItem" :backdrop_url="backdrop_url" @close="closeModal" />
</template>

<script setup>
import { ref, watch } from 'vue'
import { Head } from '@inertiajs/vue3'
import AppLayout from '../Layouts/AppLayout.vue'
import MovieModal from '../Components/MovieModal.vue'
import MovieCard from '../Components/MovieCard.vue'
import LoadingSpinner from '../Components/LoadingSpinner.vue'
import useInfiniteScroll from '../Composables/useInfiniteScroll.js'
import useModal from '../Composables/useModal.js'

const props = defineProps({
    languages: Object,
    movies: Object,
    poster_url: String,
    backdrop_url: String,
})

const selectedLang = ref(null)

function itemOnClick(item) {
    query.value = {
        lang: item.code,
    }
    selectedLang.value = item
}

const { items, loading, sentinel, fetchItems, hasMore, query } = useInfiniteScroll({ movies: props.movies })
const { openModal, closeModal, selectedItem, showModal } = useModal()

watch(query, (newVal) => {
    if (newVal) fetchItems(true)
})
</script>
