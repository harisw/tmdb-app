<template>
    <Head title="Find by Genre" />
    <AppLayout>
        <div>
            <h1 class="text-2xl 2xl:text-4xl font-bold mb-6 text-madder">{{ genre.name }} Titles</h1>
            <div
                class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-5 xl:grid-cols-6 2xl:grid-cols-7 gap-6 auto-rows-[450px] 2xl:auto-rows-[520px]"
            >
                <TitleCard
                    v-for="item in items"
                    :key="item.id"
                    :on-click="openModal"
                    :item="item"
                />
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
import AppLayout from '../Layouts/AppLayout.vue'
import TitleModal from '../Components/TitleModal.vue'
import TitleCard from '../Components/TitleCard.vue'
import LoadingSpinner from '../Components/LoadingSpinner.vue'
import useInfiniteScroll from '../Composables/useInfiniteScroll.js'
import useModal from '../Composables/useModal.js'

const props = defineProps({
    genre: Object,
    titles: Object,
})

const { items, loading, sentinel, _, hasMore } = useInfiniteScroll({ titles: props.titles })
const { openModal, closeModal, selectedItem, showModal } = useModal()
</script>
