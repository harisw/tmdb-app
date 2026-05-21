<template>
    <Head title="Home" />
    <AppLayout>
        <div>
            <div class="flex flex-col justify-center items-center gap-5 mt-6 mb-12 md:mt-20 md:mb-36">
                <div class="min-h-36">
                    <RotatingHeader
                        :texts="headlines"
                        :interval="3500"
                        :duration="750"
                        tag="h1"
                        text-class="text-3xl md:text-5xl 2xl:text-7xl font-bold text-madder"
                    />
                </div>
                <SearchInput
                    v-model="q"
                    :debounce="400"
                    :min-length="2"
                    placeholder="Search titles..."
                    :preserve-state="true"
                    :replace="false"
                    route="search"
                    @result-on-click="openModal"
                />
            </div>

            <h2 class="text-2xl 2xl:text-4xl font-bold mb-6 text-madder">Hottest Titles</h2>
            <div
                class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-5 xl:grid-cols-6 2xl:grid-cols-7 gap-6 auto-rows-[450px] 2xl:auto-rows-[520px]"
            >
                <TitleCard v-for="item in items" :key="item.id" :on-click="openModal" :item="item" />
            </div>
            <div ref="sentinel" class="flex justify-center items-center py-4 text-white">
                <LoadingSpinner v-if="loading" text="Loading more" size="8" color="white" />
                <span v-else-if="!hasMore">No more results</span>
            </div>
        </div>
    </AppLayout>
    <TitleModal :show="showModal" :item="selectedItem" @close="closeModal" />
</template>

<script setup>
import { Head } from '@inertiajs/vue3'
import { ref } from 'vue'
import AppLayout from '../Layouts/AppLayout.vue'
import TitleModal from '../Components/TitleModal.vue'
import TitleCard from '../Components/TitleCard.vue'
import LoadingSpinner from '../Components/LoadingSpinner.vue'
import RotatingHeader from '../Components/RotatingHeader.vue'
import SearchInput from '../Components/SearchInput.vue'
import useInfiniteScroll from '../Composables/useInfiniteScroll.js'
import useModal from '../Composables/useModal.js'

const props = defineProps({
    titles: Object,
})

const { items, loading, sentinel, _, hasMore } = useInfiniteScroll({ titles: props.titles })
const { openModal, closeModal, selectedItem, showModal } = useModal()

const headlines = [
    'Looking for some action or romance?',
    'Search with title, genre, or even some keywords',
    'Something to spice your weekend?',
    "Don't waste your time, start watching now!",
]

const q = ref('')
</script>
