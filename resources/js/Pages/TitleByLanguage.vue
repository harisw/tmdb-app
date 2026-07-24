<template>
    <Head title="Find by Languages" />
    <AppLayout>
        <div>
            <h1 class="text-2xl 2xl:text-4xl font-bold text-madder [text-shadow:0_1px_2px_rgb(0_0_0/0.4)]">
                Browse by Languages
            </h1>
            <p class="mt-1.5 text-sm 2xl:text-base text-white/60">
                {{ languages.length }} languages in the library, most titles first.
            </p>

            <ChipRail
                class="mt-5 2xl:mt-7"
                :items="languages"
                item-key="code"
                count-key="titles_count"
                :active="selectedLang?.code"
                @select="itemOnClick"
            />

            <div v-if="selectedLang" class="w-full mt-10 2xl:mt-14">
                <TitleGrid :title="`${selectedLang.name} Titles`">
                    <TitleCard v-for="item in items" :key="item.id" :on-click="openModal" :item="item" />
                </TitleGrid>
                <div ref="sentinel" class="flex justify-center items-center py-4 text-white">
                    <LoadingSpinner v-if="loading" text="Loading more" :size="8" color="white" />
                    <span v-else-if="!hasMore">No more results</span>
                </div>
            </div>
            <p v-else class="mt-10 2xl:mt-14 text-sm 2xl:text-base text-white/60">Pick a language to see its titles.</p>
        </div>
    </AppLayout>
    <TitleModal :show="showModal" :item="selectedItem" @close="closeModal" />
</template>

<script setup>
import { ref, watch } from 'vue'
import { Head } from '@inertiajs/vue3'
import AppLayout from '../Layouts/AppLayout.vue'
import TitleModal from '../Components/TitleModal.vue'
import TitleCard from '../Components/TitleCard.vue'
import LoadingSpinner from '../Components/LoadingSpinner.vue'
import useInfiniteScroll from '../Composables/useInfiniteScroll.js'
import useModal from '../Composables/useModal.js'
import TitleGrid from '../Components/TitleGrid.vue'
import ChipRail from '../Components/ChipRail.vue'

const props = defineProps({
    languages: Array,
    titles: Object,
    // The language resolved from ?lang= server-side, so a shared or reloaded
    // URL lands on the same selection instead of an empty page.
    selected: { type: Object, default: null },
})

const selectedLang = ref(props.selected)

function itemOnClick(item) {
    query.value = {
        lang: item.code,
    }
    selectedLang.value = item
}

const { items, loading, sentinel, fetchItems, hasMore, query } = useInfiniteScroll({ titles: () => props.titles })
const { openModal, closeModal, selectedItem, showModal } = useModal()

watch(query, (newVal) => {
    if (newVal) fetchItems(true)
})
</script>
