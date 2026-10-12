<script setup>
import { ref, watch } from 'vue'
import { Link } from '@inertiajs/vue3'
import { X, Clock, Calendar, Waypoints } from 'lucide-vue-next'
import BaseModal from './BaseModal.vue'
import RatingBadge from '../RatingBadge.vue'
import ProvidersBadge from '../ProvidersBadge.vue'
import GenreBadges from '../GenreBadges.vue'
import { button } from '../../variants/button.js'
import { PROVIDER_MODE_FULL } from '../../constants/ui.js'
import { onBackdropError, onPosterError } from '../../utils/img.js'
import TitleModalActions from './TitleModalActions.vue'

const props = defineProps({
    show: { type: Boolean, required: true },
    item: { type: Object, default: null },
    to: { type: [String, Object], default: 'body' },
})

defineEmits(['close'])

const SECTION_LABEL = 'text-xs font-semibold uppercase tracking-wide text-white/60'

const currentItem = ref(props.item)

const similar = ref([])
const loadingSimilar = ref(false)
// Guards against out-of-order responses when the user clicks tiles quickly.
let requestId = 0

const baseModal = ref(null)

async function fetchSimilar(title) {
    if (!title?.id) return
    const token = ++requestId
    loadingSimilar.value = true
    similar.value = []
    try {
        const { data } = await window.axios.get(`/title/${title.id}/similar`)
        if (token !== requestId) return // a newer request superseded this one
        similar.value = data.titles ?? []
    } catch (e) {
        if (token === requestId) similar.value = []
    } finally {
        if (token === requestId) loadingSimilar.value = false
    }
}

function selectSimilar(title) {
    currentItem.value = title
    baseModal.value?.scrollToTop()
    fetchSimilar(title)
}

watch(
    () => [props.show, props.item?.id],
    ([show]) => {
        if (show && props.item) {
            currentItem.value = props.item
            fetchSimilar(props.item)
        } else if (!show) {
            similar.value = []
            requestId++ // invalidate any in-flight request
        }
    },
    { immediate: true }
)
</script>

<template>
    <BaseModal
        ref="baseModal"
        :show="show"
        :to="to"
        max-width="max-w-3xl"
        align="start"
        labelledby="title-modal-heading"
        @close="$emit('close')"
    >
        <template #close="{ close }">
            <button
                type="button"
                :class="
                    button({
                        intent: 'soft',
                        size: 'sm',
                        circle: true,
                        class: 'absolute top-3 right-3 z-10 bg-black/50 hover:bg-black/70',
                    })
                "
                aria-label="Close"
                @click="close"
            >
                <X :size="18" :stroke-width="2.5" />
            </button>
        </template>

        <template #default>
            <div v-if="currentItem">
                <div class="relative">
                    <img
                        :src="currentItem.backdrop_path"
                        :alt="`${currentItem.name} backdrop`"
                        class="w-full h-64 md:h-96 object-cover"
                        @error="onBackdropError"
                    />
                    <div class="absolute inset-0 bg-gradient-to-t from-licorice via-licorice/40 to-transparent"></div>

                    <div class="absolute bottom-0 inset-x-0 p-5">
                        <h2 id="title-modal-heading" class="text-2xl md:text-4xl font-bold drop-shadow-lg">
                            {{ currentItem.name }}
                        </h2>
                        <div class="mt-2 flex flex-wrap items-center gap-3 text-sm text-white/80">
                            <RatingBadge :rating="currentItem.rating" />
                            <span v-if="currentItem.release_date" class="flex items-center gap-1">
                                <Calendar :size="15" /> {{ currentItem.release_date }}
                            </span>
                            <span v-if="currentItem.runtime" class="flex items-center gap-1">
                                <Clock :size="15" /> {{ currentItem.runtime }}
                            </span>
                        </div>

                        <TitleModalActions :item="currentItem" />
                    </div>
                </div>

                <div class="p-5 space-y-5">
                    <p v-if="currentItem.overview" class="max-w-prose leading-relaxed text-white/80">
                        {{ currentItem.overview }}
                    </p>

                    <div v-if="currentItem.title_providers?.length" class="space-y-2">
                        <h3 :class="SECTION_LABEL">Where to watch</h3>
                        <ProvidersBadge :title-providers="currentItem.title_providers" :mode="PROVIDER_MODE_FULL" />
                    </div>

                    <div v-if="currentItem.genres?.length" class="space-y-2">
                        <h3 :class="SECTION_LABEL">Genres</h3>
                        <GenreBadges :genres="currentItem.genres" />
                    </div>

                    <div v-if="currentItem.keywords?.length" class="space-y-2">
                        <h3 :class="SECTION_LABEL">Tags</h3>
                        <div class="flex flex-wrap gap-1.5">
                            <Link v-for="keyword in currentItem.keywords" :key="keyword.id" :href="keyword.url">
                                <span class="badge badge-outline badge-sm px-2 text-white/65">
                                    {{ keyword.name }}
                                </span>
                            </Link>
                        </div>
                    </div>

                    <div v-if="loadingSimilar || similar.length" class="space-y-3">
                        <div class="flex items-center justify-between gap-3">
                            <h3 :class="SECTION_LABEL">More Like This</h3>
                            <Link
                                v-if="currentItem.slug"
                                :href="`/graph?title_slug=${currentItem.slug}`"
                                :class="button({ intent: 'soft', size: 'xs', class: 'gap-1' })"
                            >
                                <Waypoints :size="13" />
                                Explore graph
                            </Link>
                        </div>

                        <div
                            v-if="loadingSimilar"
                            class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-5 lg:grid-cols-6 gap-3"
                        >
                            <div v-for="n in 6" :key="n" class="aspect-[2/3] rounded-lg bg-white/5 animate-pulse"></div>
                        </div>

                        <div v-else class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-5 lg:grid-cols-6 gap-3">
                            <button
                                v-for="title in similar.slice(0, 6)"
                                :key="title.id"
                                type="button"
                                class="group text-left focus:outline-none hover:cursor-pointer"
                                @click="selectSimilar(title)"
                            >
                                <div
                                    class="relative overflow-hidden rounded-lg ring-1 ring-white/10 transition-transform duration-300 group-hover:scale-105 group-focus-visible:ring-2 group-focus-visible:ring-white/60"
                                >
                                    <img
                                        :src="title.poster_path"
                                        :alt="`${title.name} poster`"
                                        loading="lazy"
                                        class="w-full aspect-[2/3] object-cover"
                                        @error="onPosterError"
                                    />
                                    <div class="absolute top-1.5 right-1.5">
                                        <RatingBadge :rating="title.rating" />
                                    </div>
                                </div>
                                <p class="mt-1.5 text-xs font-medium text-white/90 line-clamp-1">
                                    {{ title.name }}
                                </p>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </BaseModal>
</template>
