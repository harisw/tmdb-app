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

            <!-- The rail sits on its own panel so the chips read as one control
                 rather than loose text floating on the ambient background. -->
            <div class="mt-5 2xl:mt-7 rounded-2xl border border-white/10 bg-white/[0.03] p-3 sm:p-4 2xl:p-6">
                <ul class="flex flex-wrap gap-2 2xl:gap-3">
                    <li v-for="lang in languages" :key="lang.id">
                        <button
                            type="button"
                            :aria-pressed="isActive(lang)"
                            class="group flex items-center gap-2 2xl:gap-2.5 rounded-full border px-3 py-1.5 2xl:px-4 2xl:py-2 cursor-pointer transition-colors motion-reduce:transition-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary"
                            :class="
                                isActive(lang)
                                    ? 'border-madder bg-madder/20 text-white shadow-[0_0_24px_-6px] shadow-madder'
                                    : 'border-white/10 bg-white/5 text-white/90 hover:border-white/25 hover:bg-white/10 hover:text-white'
                            "
                            @click="itemOnClick(lang)"
                        >
                            <span class="text-sm 2xl:text-lg leading-none">{{ lang.name }}</span>
                            <span
                                v-if="lang.titles_count"
                                class="text-xs 2xl:text-sm tabular-nums text-white/55 transition-colors motion-reduce:transition-none group-hover:text-white/80"
                            >
                                {{ formatCount(lang.titles_count) }}
                            </span>
                        </button>
                    </li>
                </ul>
            </div>

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

const props = defineProps({
    languages: Array,
    titles: Object,
    // The language resolved from ?lang= server-side, so a shared or reloaded
    // URL lands on the same selection instead of an empty page.
    selected: { type: Object, default: null },
})

const selectedLang = ref(props.selected)
const numberFormat = new Intl.NumberFormat()

const isActive = (lang) => lang.code === selectedLang.value?.code
const formatCount = (count) => numberFormat.format(count)

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
