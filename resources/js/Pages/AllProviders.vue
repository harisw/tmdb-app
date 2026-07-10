<script setup>
import { computed } from 'vue'
import { Head, usePage } from '@inertiajs/vue3'
import AppLayout from '../Layouts/AppLayout.vue'
import TitleModal from '../Components/TitleModal.vue'
import Carousel from '../Components/Carousel.vue'
import TitleCard from '../Components/TitleCard.vue'
import useModal from '../Composables/useModal.js'
import SectionHeader from '../Components/SectionHeader.vue'

defineProps({
    allProviders: { type: Array },
})

const img = computed(() => usePage().props.base_url)
const { openModal, closeModal, selectedItem, showModal } = useModal()
</script>

<template>
    <Head title="Find by Provider" />
    <AppLayout>
        <div class="w-full">
            <div v-for="provider in allProviders" :key="provider.id" class="mb-15">
                <SectionHeader
                    :title="provider.name"
                    :logo="provider.img_logo ? `${img.logo}/${provider.img_logo}` : ''"
                    :href="provider.url"
                />
                <Carousel>
                    <TitleCard
                        v-for="item in provider.titles"
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
