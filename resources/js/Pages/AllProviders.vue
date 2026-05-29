<script setup>
import { computed } from 'vue'
import { Head, usePage } from '@inertiajs/vue3'
import AppLayout from '../Layouts/AppLayout.vue'
import TitleModal from '../Components/TitleModal.vue'
import Carousel from '../Components/Carousel.vue'
import TitleCard from '../Components/TitleCard.vue'
import useModal from '../Composables/useModal.js'

defineProps({
    allProviders: Array,
})

const img = computed(() => usePage().props.img)
const { openModal, closeModal, selectedItem, showModal } = useModal()
</script>

<template>
    <Head title="Find by Provider" />
    <AppLayout>
        <div class="w-full">
            <div v-for="provider in allProviders" :key="provider.id" class="mb-15">
                <div class="flex items-center gap-3 mb-6">
                    <img
                        v-if="provider.img_logo"
                        :src="`${img.logo}/${provider.img_logo}`"
                        :alt="provider.name"
                        class="h-8 w-auto object-contain rounded"
                    />
                    <h1 class="text-2xl 2xl:text-4xl font-bold text-madder">{{ provider.name }}</h1>
                </div>
                <Carousel>
                    <TitleCard
                        v-for="item in provider.titles"
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
