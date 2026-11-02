<script setup>
import { computed, ref, watch } from 'vue'
import TitleModal from './Modals/TitleModal.vue'
import TitleCard from './TitleCard.vue'
import TitleGrid from './TitleGrid.vue'
import LoadingSpinner from './LoadingSpinner.vue'
import useInfiniteScroll from '../Composables/useInfiniteScroll.js'
import useModal from '../Composables/useModal.js'
import { tv } from '../variants/tv.js'

const props = defineProps({
    title: { type: String, required: true },
    logo: { type: String, default: '' },
    titles: { type: Object, required: true },
    sort: { type: String, required: true },
    sorts: { type: Array, required: true },
})

const sortOption = tv({
    base: 'h-8 flex-1 cursor-pointer whitespace-nowrap rounded-full px-2 text-[13px] sm:flex-none sm:px-3 sm:text-sm transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-glow-red motion-reduce:transition-none',
    variants: {
        selected: {
            true: 'bg-white font-medium text-licorice',
            false: 'text-white/70 hover:bg-white/10 hover:text-white',
        },
    },
})

const numberFormat = new Intl.NumberFormat()

const { items, loading, sentinel, fetchItems, hasMore, query } = useInfiniteScroll({ titles: () => props.titles })
const { openModal, closeModal, selectedItem, showModal } = useModal()

const activeSort = ref(props.sort)
query.value = { sort: activeSort.value }

const total = computed(() => props.titles.meta?.total ?? items.value.length)
const totalLabel = computed(() => `${numberFormat.format(total.value)} ${total.value === 1 ? 'title' : 'titles'}`)

watch(activeSort, (sort) => {
    query.value = { sort }
    fetchItems(true)
})
</script>

<template>
    <div>
        <TitleGrid :title="title" :title-logo="logo">
            <template #meta>
                <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
                    <p class="text-sm 2xl:text-base tabular-nums text-white/60">{{ totalLabel }}</p>
                    <div
                        role="group"
                        aria-label="Sort titles"
                        class="flex w-full gap-1 rounded-full bg-white/[0.04] p-1 sm:w-auto"
                    >
                        <button
                            v-for="option in sorts"
                            :key="option.value"
                            type="button"
                            :aria-pressed="option.value === activeSort"
                            :class="sortOption({ selected: option.value === activeSort })"
                            @click="activeSort = option.value"
                        >
                            {{ option.label }}
                        </button>
                    </div>
                </div>
            </template>
            <TitleCard v-for="item in items" :key="item.id" :on-click="openModal" :item="item" />
        </TitleGrid>
        <div ref="sentinel" class="flex justify-center items-center py-4 text-white">
            <LoadingSpinner v-if="loading" text="Loading more" :size="8" color="white" />
            <span v-else-if="!items.length" class="text-white/60">No titles here yet.</span>
            <span v-else-if="!hasMore">No more results</span>
        </div>
        <TitleModal :show="showModal" :item="selectedItem" @close="closeModal" />
    </div>
</template>
