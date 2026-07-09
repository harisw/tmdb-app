<script setup>
import { computed } from 'vue'
import { Head, usePage } from '@inertiajs/vue3'
import AppLayout from '../Layouts/AppLayout.vue'
import TitleModal from '../Components/TitleModal.vue'
import TitleCard from '../Components/TitleCard.vue'
import LoadingSpinner from '../Components/LoadingSpinner.vue'
import useInfiniteScroll from '../Composables/useInfiniteScroll.js'
import useModal from '../Composables/useModal.js'
import TitleGrid from '../Components/TitleGrid.vue'

const props = defineProps({
    provider: Object,
    titles: Object,
})

const img = computed(() => usePage().props.base_url)
const { items, loading, sentinel, hasMore } = useInfiniteScroll({ titles: () => props.titles })
const { openModal, closeModal, selectedItem, showModal } = useModal()
</script>

<template>
    <Head :title="`${provider.name} Titles`" />
    <AppLayout>
        <div>
            <TitleGrid :title="`${provider.name} Titles`" :title-logo="provider.img_logo">
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
