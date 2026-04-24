<script setup>
import { Head } from '@inertiajs/vue3'
import AppLayout from '../Layouts/AppLayout.vue'
import TitleModal from '../Components/TitleModal.vue'
import Carousel from '../Components/Carousel.vue'
import TitleCard from '../Components/TitleCard.vue'
import useModal from '../Composables/useModal.js'

defineProps({
    allGenres: Array,
})
const { openModal, closeModal, selectedItem, showModal } = useModal()
</script>

<template>
    <Head title="Find all Genres" />
    <AppLayout>
        <div class="w-full">
            <div v-for="genre in allGenres" :key="genre.id" class="mb-15">
                <h1 class="text-2xl 2xl:text-4xl font-bold mb-6 text-madder">{{ genre.name }}</h1>
                <Carousel>
                    <TitleCard
                        v-for="item in genre.titles"
                        :key="item.id"
                        :on-click="openModal"
                        :item="item"
                        :show-genres="false"
                        size="w-3xs"
                    />
                </Carousel>
            </div>
        </div>
    </AppLayout>
    <TitleModal :show="showModal" :item="selectedItem" @close="closeModal" />
</template>

<style>
.no-scrollbar::-webkit-scrollbar {
    display: none;
}

.no-scrollbar {
    -ms-overflow-style: none;
    scrollbar-width: none;
}
</style>
