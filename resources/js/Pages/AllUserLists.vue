<script setup>
import { Head } from '@inertiajs/vue3'
import AppLayout from '../Layouts/AppLayout.vue'
import TitleModal from '../Components/Modals/TitleModal.vue'
import Carousel from '../Components/Carousel.vue'
import TitleCard from '../Components/TitleCard.vue'
import useModal from '../Composables/useModal.js'
import SectionHeader from '../Components/SectionHeader.vue'

defineProps({
    lists: { type: Array },
})
const { openModal, closeModal, selectedItem, showModal } = useModal()
</script>

<template>
    <Head title="User lists" />
    <AppLayout>
        <div class="w-full">
            <div v-for="list in lists" :key="list.value" class="mb-15">
                <SectionHeader :title="list.name" :href="list.url" />
                <Carousel>
                    <TitleCard
                        v-for="item in list.titles"
                        :key="item.id"
                        :on-click="openModal"
                        :item="item"
                        :show-genres="false"
                        size="w-36 sm:w-44 lg:w-3xs"
                    />
                </Carousel>
            </div>
        </div>
    </AppLayout>
    <TitleModal :show="showModal" :item="selectedItem" @close="closeModal" />
</template>
