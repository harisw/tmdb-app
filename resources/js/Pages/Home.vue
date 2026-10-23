<template>
    <Head title="Home" />
    <AppLayout>
        <div>
            <section
                class="mt-2 mb-16 grid items-center gap-10 md:mt-8 md:mb-24 lg:min-h-[calc(100svh-15rem)] lg:gap-14"
                :class="{ 'lg:grid-cols-[minmax(0,5fr)_minmax(0,7fr)]': hasConstellation }"
            >
                <div class="max-w-xl">
                    <h1
                        class="text-4xl font-bold leading-[1.05] tracking-tight text-balance text-white md:text-5xl 2xl:text-7xl"
                    >
                        Start with one story. Follow the <span class="text-glow-red">threads</span>.
                    </h1>
                    <p class="mt-5 max-w-[52ch] text-base leading-relaxed text-white/65 md:text-lg">
                        Chill links every movie and series to its closest neighbours, and every link comes with a
                        reason: shared themes, cast, crew or overall feel.
                    </p>

                    <div class="mt-8">
                        <SearchInput
                            ref="searchInput"
                            v-model="q"
                            :debounce="400"
                            :min-length="2"
                            placeholder="Search a title or describe a mood"
                            :preserve-state="true"
                            :replace="false"
                            route="search"
                            @result-on-click="openModal"
                        />
                        <div class="mt-3 flex flex-wrap items-center gap-2 text-sm">
                            <span class="text-white/50">Try</span>
                            <button
                                v-for="example in searchExamples"
                                :key="example"
                                type="button"
                                class="cursor-pointer rounded-full border border-white/15 px-3 py-1 text-white/75 transition-colors hover:border-white/30 hover:text-white focus-visible:outline-2 focus-visible:outline-glow-red motion-reduce:transition-none"
                                @click="searchInput.searchFor(example)"
                            >
                                {{ example }}
                            </button>
                            <span class="hidden text-white/40 lg:inline">
                                or press
                                <kbd
                                    class="rounded border border-white/20 px-1.5 py-0.5 font-sans text-xs text-white/70"
                                    >/</kbd
                                >
                            </span>
                        </div>
                    </div>
                </div>

                <template v-if="hasConstellation">
                    <Constellation
                        class="hidden lg:block"
                        :constellation="constellation"
                        :shuffling="shuffling"
                        :busy-id="busyId"
                        @open="openNode"
                        @shuffle="shuffle"
                    />
                    <ConstellationThread
                        class="lg:hidden"
                        :constellation="constellation"
                        :shuffling="shuffling"
                        :busy-id="busyId"
                        @open="openNode"
                        @shuffle="shuffle"
                    />
                </template>
            </section>

            <TitleGrid title="Recently added" heading-tag="h2">
                <TitleCard v-for="item in items" :key="item.id" :on-click="openModal" :item="item" />
            </TitleGrid>

            <p v-if="!items.length && !loading" class="py-16 text-center text-sm text-white/60">
                No titles to show yet. Check back soon.
            </p>

            <div ref="sentinel" class="flex justify-center items-center py-6 text-white/60 text-sm">
                <LoadingSpinner v-if="loading" text="Loading more" :size="8" color="white" />
                <span v-else-if="items.length && !hasMore">No more results</span>
            </div>
        </div>
    </AppLayout>
    <TitleModal :show="showModal" :item="selectedItem" @close="closeModal" />
</template>

<script setup>
import { Head, router } from '@inertiajs/vue3'
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import AppLayout from '../Layouts/AppLayout.vue'
import TitleModal from '../Components/Modals/TitleModal.vue'
import TitleCard from '../Components/TitleCard.vue'
import LoadingSpinner from '../Components/LoadingSpinner.vue'
import SearchInput from '../Components/SearchInput.vue'
import Constellation from '../Components/Constellation.vue'
import ConstellationThread from '../Components/ConstellationThread.vue'
import useInfiniteScroll from '../Composables/useInfiniteScroll.js'
import useModal from '../Composables/useModal.js'
import TitleGrid from '../Components/TitleGrid.vue'

const props = defineProps({
    titles: { type: Object },
    constellation: { type: Object, default: undefined },
})

const { items, loading, sentinel, hasMore } = useInfiniteScroll({ titles: () => props.titles })
const { openModal, closeModal, selectedItem, showModal } = useModal()

const searchExamples = ['slow-burn sci-fi', 'heist with a twist', 'cozy anime']

const q = ref('')
const searchInput = ref(null)
const shuffling = ref(false)
const busyId = ref(null)
let openRequestId = 0

const hasConstellation = computed(() => props.constellation !== null)

function shuffle() {
    router.reload({
        only: ['constellation'],
        data: { seed: (props.constellation.seedIndex + 1) % props.constellation.seedCount },
        preserveUrl: true,
        onStart: () => (shuffling.value = true),
        onFinish: () => (shuffling.value = false),
    })
}

async function openNode(node) {
    const token = ++openRequestId
    busyId.value = node.id
    const response = await window.axios.get(`/title/${node.id}`).catch(() => null)
    if (token !== openRequestId) return
    busyId.value = null
    if (response) {
        openModal(response.data.title)
    }
}

function focusSearchOnSlash(event) {
    if (event.key !== '/' || event.metaKey || event.ctrlKey || event.altKey || showModal.value) return
    const active = document.activeElement
    if (active?.tagName === 'INPUT' || active?.tagName === 'TEXTAREA' || active?.isContentEditable) return
    event.preventDefault()
    searchInput.value?.focus()
}

onMounted(() => window.addEventListener('keydown', focusSearchOnSlash))
onBeforeUnmount(() => window.removeEventListener('keydown', focusSearchOnSlash))
</script>
