<template>
    <Head title="Find by Keywords" />
    <AppLayout>
        <div>
            <h1 class="text-2xl 2xl:text-4xl font-bold mb-6 text-madder">Browse by Tags</h1>
            <div class="flex flex-wrap text-white gap-3 2xl:gap-5 w-full">
                <div
                    v-for="tag in tagItems"
                    :key="tag.id"
                    class="text-sm 2xl:text-xl hover:cursor-pointer hover:underline hover:font-semibold hover:text-gray-300"
                    @click="itemOnClick(tag)"
                >
                    {{ tag.name }} ({{ tag.count }}),
                </div>
            </div>
            <div v-if="hasMoreTags" class="flex justify-center items-center py-4 text-white">
                <LoadingSpinner v-if="loadingTags" text="Loading more" />
                <button
                    v-else
                    class="bg-madder px-4 py-2 2xl:px-6 2xl:py-3 rounded-lg hover:cursor-pointer text-xs 2xl:text-base"
                    @click="loadMoreTags"
                >
                    Load more Tags
                </button>
            </div>
            <div v-if="items && selectedTag" class="w-full mt-10">
                <TitleGrid :title="`${selectedTag.name} Titles`">
                    <TitleCard v-for="item in items" :key="item.id" :on-click="openModal" :item="item" />
                </TitleGrid>
                <div ref="sentinel" class="flex justify-center items-center py-4 text-white">
                    <LoadingSpinner v-if="loading" text="Loading more" />
                    <span v-else-if="!hasMore">No more results</span>
                </div>
            </div>
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

const props = defineProps({
    tags: Object,
    titles: Object,
})

const selectedTag = ref(null)
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
