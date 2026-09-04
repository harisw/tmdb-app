<template>
    <Head title="Explore more" />
    <AppLayout>
        <div>
            <h1 class="text-2xl 2xl:text-4xl font-bold mb-6 text-madder">Advanced Search</h1>
            <div class="flex flex-col gap-5">
                <div class="flex flex-col sm:flex-row gap-4 sm:gap-10">
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
                <div class="flex gap-2 w-full sm:w-1/2 items-center justify-center">
                    <label class="text-white font-semibold text-sm">Keywords</label>
                    <input
                        v-model="searchKey"
                        type="text"
                        placeholder="Enter query"
                        :class="field({ class: 'px-5 py-2' })"
                        aria-label="Enter query"
                    />
                    <button
                        type="button"
                        :class="button({ intent: 'primary', size: 'md', class: 'px-6' })"
                        @click="searchOnClick"
                    >
                        Search
                    </button>
                </div>
            </div>

            <div v-if="items" class="w-full mt-10">
                <h2 class="text-2xl 2xl:text-4xl font-bold mb-6 text-madder">Results</h2>
                <div
                    class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-5 xl:grid-cols-6 2xl:grid-cols-7 gap-6 auto-rows-[450px] 2xl:auto-rows-[520px]"
                >
                    <TitleCard v-for="item in items" :key="item.id" :on-click="openModal" :item="item" />
                </div>
                <div ref="sentinel" class="flex justify-center items-center py-4 text-white">
                    <LoadingSpinner v-if="loading" text="Loading more" :size="8" color="white" />
                    <div
                        v-else-if="!hasMore"
                        class="mt-8 w-full text-center rounded-xl text-red-100 py-4 px-6 animate-[fadeIn_0.9s_ease-out,scaleIn_0.4s_ease-out]"
                    >
                        <span v-if="!items" class="text-lg font-semibold tracking-wide">
                            😢 Looks like this title hasn't been made yet.
                        </span>
                        <span v-else class="text-lg font-semibold tracking-wide">
                            😢 Not what you’re looking for? Try refining your search.
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
    <TitleModal :show="showModal" :item="selectedItem" @close="closeModal" />
</template>

<script setup>
import { ref, watch } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AppLayout from '../Layouts/AppLayout.vue'
import TitleModal from '../Components/Modals/TitleModal.vue'
import TitleCard from '../Components/TitleCard.vue'
import LoadingSpinner from '../Components/LoadingSpinner.vue'
import MultiSelect from '../Components/MultiSelect.vue'
import useInfiniteScroll from '../Composables/useInfiniteScroll.js'
import useModal from '../Composables/useModal.js'
import { field } from '../variants/field.js'
import { button } from '../variants/button.js'

const props = defineProps({
    genres: Object,
    languages: Object,
    tags: Object,
    titles: Object,
})
const { items, loading, sentinel, fetchItems, hasMore, query } = useInfiniteScroll({ titles: () => props.titles })
const { openModal, closeModal, selectedItem, showModal } = useModal()

const selectedLanguages = ref([])
const selectedGenres = ref([])
const selectedTags = ref([])
const searchKey = ref('')

function searchOnClick() {
    query.value = {
        lang: selectedLanguages.value,
        gen: selectedGenres.value,
        tag: selectedTags.value,
        q: searchKey.value,
    }
    fetchItems(true)
}

// watch(query, (newVal) => {
//     if (newVal) fetchItems(true);
// });
</script>

<style scoped>
@keyframes gradient {
    0%,
    100% {
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
