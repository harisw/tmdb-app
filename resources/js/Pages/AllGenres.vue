<script setup>
import { Head } from '@inertiajs/vue3'
import AppLayout from '../Layouts/AppLayout.vue'
import TitleModal from '../Components/Modals/TitleModal.vue'
import Carousel from '../Components/Carousel.vue'
import ChipRail from '../Components/ChipRail.vue'
import TitleCard from '../Components/TitleCard.vue'
import SectionHeader from '../Components/SectionHeader.vue'
import useModal from '../Composables/useModal.js'

defineProps({
    allGenres: { type: Array, required: true },
})
const { openModal, closeModal, selectedItem, showModal } = useModal()
</script>

<template>
    <Head title="Genres" />
    <AppLayout>
        <div class="w-full">
            <h1 class="text-2xl 2xl:text-4xl font-bold text-white [text-shadow:0_1px_2px_rgb(0_0_0/0.4)]">
                Browse by Genre
            </h1>
            <p class="mt-1.5 text-sm 2xl:text-base text-white/60">
                {{ allGenres.length }} genres, most titles first. Each row shows its most popular titles.
            </p>

            <ChipRail
                class="mt-5 2xl:mt-7"
                :items="allGenres"
                item-key="slug"
                count-key="count"
                href-key="url"
                swipe-on-phone
            />

            <section v-for="genre in allGenres" :key="genre.id" class="mt-15" :aria-label="genre.name">
                <Carousel>
                    <template #header>
                        <SectionHeader :title="genre.name" :href="genre.url" heading-tag="h2" />
                    </template>
                    <TitleCard
                        v-for="item in genre.titles"
                        :key="item.id"
                        :on-click="openModal"
                        :item="item"
                        :show-genres="false"
                    />
                </Carousel>
            </section>
        </div>
    </AppLayout>
    <TitleModal :show="showModal" :item="selectedItem" @close="closeModal" />
</template>
