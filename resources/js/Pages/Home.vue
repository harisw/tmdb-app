<template>
    <Head title="Home" />
    <AppLayout>
        <div>
            <div class="flex flex-col justify-center items-center gap-5 mt-6 mb-12 md:mt-20 md:mb-36">
                <div class="min-h-36 w-full">
                    <RotatingHeader
                        :texts="headlines"
                        :interval="3500"
                        :duration="750"
                        tag="h1"
                        text-class="font-bold text-madder"
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

            <TitleGrid title="Hottest Titles">
                <TitleCard v-for="item in items" :key="item.id" :on-click="openModal" :item="item" />
            </TitleGrid>
            <div ref="sentinel" class="flex justify-center items-center py-4 text-white">
                <LoadingSpinner v-if="loading" text="Loading more" :size="8" color="white" />
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
import TitleGrid from '../Components/TitleGrid.vue'

const props = defineProps({
    titles: { type: Object },
})

const { items, loading, sentinel, _, hasMore } = useInfiniteScroll({ titles: () => props.titles })
const { openModal, closeModal, selectedItem, showModal } = useModal()

const headlines = [
    'Looking for some action or romance?',
    'Search with title, genre, or even some keywords',
    'Something to spice your weekend?',
    "Don't waste your time, start watching now!",
]

const q = ref('')
</script>
