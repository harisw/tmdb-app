<template>
    <Head title="Find by keyword" />
    <AppLayout>
        <div>
            <TitleGrid :title="`Titles tagged ${keyword.name}`">
                <TitleCard v-for="item in items" :key="item.id" :on-click="openModal" :item="item" />
            </TitleGrid>
            <div ref="sentinel" class="flex justify-center items-center py-4 text-white">
                <LoadingSpinner v-if="loading" text="Loading more" size="{8}" color="white" />
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
import TitleGrid from '../Components/TitleGrid.vue'

const props = defineProps({
    keyword: Object,
    titles: Object,
})

const { items, loading, sentinel, _, hasMore } = useInfiniteScroll({ titles: props.titles })
const { openModal, closeModal, selectedItem, showModal } = useModal()
</script>
