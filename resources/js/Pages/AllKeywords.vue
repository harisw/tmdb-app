<template>
    <Head title="Find by Keywords" />
    <AppLayout>
        <div>
            <h1 class="text-2xl 2xl:text-4xl font-bold text-madder [text-shadow:0_1px_2px_rgb(0_0_0/0.4)]">
                Browse by Tags
            </h1>
            <p class="mt-1.5 text-sm 2xl:text-base text-white/60">Most used tags first.</p>

            <ChipRail
                class="mt-5 2xl:mt-7"
                :items="tagItems"
                item-key="slug"
                :active="selectedTag?.slug"
                @select="itemOnClick"
            >
                <template #footer>
                    <div v-if="hasMoreTags" class="flex justify-center items-center pt-4 text-white">
                        <LoadingSpinner v-if="loadingTags" text="Loading more" />
                        <button
                            v-else
                            class="rounded-full border border-white/10 bg-white/5 px-4 py-1.5 2xl:px-6 2xl:py-2 text-sm 2xl:text-base text-white/90 cursor-pointer transition-colors motion-reduce:transition-none hover:border-white/25 hover:bg-white/10 hover:text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary"
                            @click="loadMoreTags"
                        >
                            Load more tags
                        </button>
                    </div>
                </template>
            </ChipRail>

            <div v-if="selectedTag" class="w-full mt-10 2xl:mt-14">
                <TitleGrid :title="`${selectedTag.name} Titles`">
                    <TitleCard v-for="item in items" :key="item.id" :on-click="openModal" :item="item" />
                </TitleGrid>
                <div ref="sentinel" class="flex justify-center items-center py-4 text-white">
                    <LoadingSpinner v-if="loading" text="Loading more" />
                    <span v-else-if="!hasMore">No more results</span>
                </div>
            </div>
            <p v-else class="mt-10 2xl:mt-14 text-sm 2xl:text-base text-white/60">Pick a tag to see its titles.</p>
        </div>
    </AppLayout>
    <TitleModal :show="showModal" :item="selectedItem" @close="closeModal" />
</template>

<script setup>
import { ref, watch } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AppLayout from '../Layouts/AppLayout.vue'
import TitleModal from '../Components/TitleModal.vue'
import TitleCard from '../Components/TitleCard.vue'
import LoadingSpinner from '../Components/LoadingSpinner.vue'
import useInfiniteScroll from '../Composables/useInfiniteScroll.js'
import useModal from '../Composables/useModal.js'
import TitleGrid from '../Components/TitleGrid.vue'
import ChipRail from '../Components/ChipRail.vue'

const props = defineProps({
    tags: Object,
    titles: Object,
    // The keyword resolved from ?tag= server-side, so a shared or reloaded URL
    // lands on the same selection instead of an empty page.
    selected: { type: Object, default: null },
})

const selectedTag = ref(props.selected)
const loadingTags = ref(false)
const hasMoreTags = ref(true)
const tagOffset = ref(15)
const tagItems = ref([...props.tags])

function itemOnClick(item) {
    query.value = {
        tag: item.slug,
    }
    selectedTag.value = item
}

function loadMoreTags() {
    if (!hasMoreTags.value) return

    loadingTags.value = true
    tagOffset.value += 35

    router.get(
        window.location.pathname,
        { tagOffset: tagOffset.value },
        {
            preserveScroll: true,
            preserveState: true,
            only: ['tags'],
            onSuccess: (page) => {
                const newTags = page.props.tags
                if (!newTags) {
                    hasMoreTags.value = false
                    return
                }

                tagItems.value.push(...newTags)
            },
            onFinish: () => (loadingTags.value = false),
        }
    )
}

const { items, loading, sentinel, fetchItems, hasMore, query } = useInfiniteScroll({ titles: () => props.titles })
const { openModal, closeModal, selectedItem, showModal } = useModal()

watch(query, (newVal) => {
    if (newVal) fetchItems(true)
})
</script>
