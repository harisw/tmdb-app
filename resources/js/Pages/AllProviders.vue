<script setup>
import { computed } from 'vue'
import { Head, usePage } from '@inertiajs/vue3'
import AppLayout from '../Layouts/AppLayout.vue'
import TitleModal from '../Components/Modals/TitleModal.vue'
import Carousel from '../Components/Carousel.vue'
import ProviderDirectory from '../Components/ProviderDirectory.vue'
import TitleCard from '../Components/TitleCard.vue'
import SectionHeader from '../Components/SectionHeader.vue'
import useModal from '../Composables/useModal.js'

defineProps({
    allProviders: { type: Array, required: true },
    featuredProviders: { type: Array, required: true },
})

const logoBase = computed(() => usePage().props.base_url.logo)
const { openModal, closeModal, selectedItem, showModal } = useModal()
</script>

<template>
    <Head title="Streaming services" />
    <AppLayout>
        <div class="w-full">
            <h1 class="text-2xl 2xl:text-4xl font-bold text-white [text-shadow:0_1px_2px_rgb(0_0_0/0.4)]">
                Browse by Streaming Service
            </h1>
            <p class="mt-1.5 text-sm 2xl:text-base text-white/60">
                {{ allProviders.length }} services with titles in the library, most titles first.
            </p>

            <ProviderDirectory class="mt-5 2xl:mt-7" :providers="allProviders" />

            <section v-for="provider in featuredProviders" :key="provider.id" class="mt-15" :aria-label="provider.name">
                <Carousel>
                    <template #header>
                        <SectionHeader
                            :title="provider.name"
                            :logo="provider.img_logo ? `${logoBase}/${provider.img_logo}` : ''"
                            :href="provider.url"
                            heading-tag="h2"
                        />
                    </template>
                    <TitleCard
                        v-for="item in provider.titles"
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
