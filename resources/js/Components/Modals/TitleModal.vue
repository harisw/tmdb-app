<script setup>
import { computed, ref, watch } from 'vue'
import { usePage, Link } from '@inertiajs/vue3'
import { X, Clock, Calendar, Share2, Eye, Bookmark, Star } from 'lucide-vue-next'
import BaseModal from './BaseModal.vue'
import RatingBadge from '../RatingBadge.vue'
import ProvidersBadge from '../ProvidersBadge.vue'
import GenreBadges from '../GenreBadges.vue'
import { PROVIDER_MODE_FULL } from '../../constants/ui.js'

const props = defineProps({
    show: { type: Boolean, required: true },
    item: { type: Object, default: null },
    to: { type: [String, Object], default: 'body' },
})

const page = usePage()
defineEmits(['close'])

const BACKDROP_PLACEHOLDER = '/backdrop-placeholder.svg'
const POSTER_PLACEHOLDER = '/poster-placeholder.svg'

const currentItem = ref(props.item)

const similar = ref([])
const loadingSimilar = ref(false)
// Guards against out-of-order responses when the user clicks tiles quickly.
let requestId = 0

const baseModal = ref(null)

const backdropSrc = computed(() =>
    currentItem.value?.backdrop_path
        ? page.props.base_url.backdrop + currentItem.value.backdrop_path
        : BACKDROP_PLACEHOLDER
)

function posterSrc(title) {
    return title.poster_path ? page.props.base_url.poster + title.poster_path : POSTER_PLACEHOLDER
}

const userState = computed(() => currentItem.value?.user_state ?? null)
const hasUserState = computed(() => {
    const s = userState.value
    return !!s && (s.watched || s.to_watch || Number(s.rate) > 0)
})

function onBackdropError(e) {
    if (e.target.src.endsWith(BACKDROP_PLACEHOLDER)) return
    e.target.src = BACKDROP_PLACEHOLDER
}

function onPosterError(e) {
    if (e.target.src.endsWith(POSTER_PLACEHOLDER)) return
    e.target.src = POSTER_PLACEHOLDER
}

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

// Reset to the prop item and (re)load similars whenever a new title is opened.
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
    <BaseModal ref="baseModal" :show="show" :to="to" max-width="max-w-3xl" align="start" @close="$emit('close')">
        <template #close="{ close }">
            <button
                class="btn btn-sm btn-circle btn-neutral absolute top-3 right-3 z-10 bg-black/50 border-0 hover:bg-black/70"
                aria-label="Close"
                @click="close"
            >
                <X :size="18" :stroke-width="2.5" />
            </button>
        </template>

        <template #default>
            <div v-if="currentItem">
                <!-- Backdrop with scrim + overlaid title/meta -->
                <div class="relative">
                    <img
                        :src="backdropSrc"
                        :alt="`${currentItem.name} backdrop`"
                        class="w-full h-64 md:h-96 object-cover"
                        @error="onBackdropError"
                    />
                    <div class="absolute inset-0 bg-gradient-to-t from-licorice via-licorice/40 to-transparent"></div>

                    <div class="absolute bottom-0 inset-x-0 p-5">
                        <h2 class="text-2xl md:text-4xl font-bold drop-shadow-lg">{{ currentItem.name }}</h2>
                        <div class="mt-2 flex flex-wrap items-center gap-3 text-sm text-white/80">
                            <RatingBadge :rating="currentItem.rating" />
                            <span v-if="currentItem.release_date" class="flex items-center gap-1">
                                <Calendar :size="15" /> {{ currentItem.release_date }}
                            </span>
                            <span v-if="currentItem.runtime" class="flex items-center gap-1">
                                <Clock :size="15" /> {{ currentItem.runtime }}
                            </span>
                        </div>

                        <div v-if="hasUserState" class="mt-2 flex flex-wrap items-center gap-2 text-xs">
                            <span
                                v-if="userState.watched"
                                class="badge badge-sm gap-1 border-none bg-madder/90 text-white"
                            >
                                <Eye :size="13" /> Watched
                            </span>
                            <span
                                v-if="userState.to_watch"
                                class="badge badge-sm gap-1 border-none bg-white/15 text-white"
                            >
                                <Bookmark :size="13" /> On watchlist
                            </span>
                            <span
                                v-if="Number(userState.rate) > 0"
                                class="badge badge-sm gap-1 border-none bg-black/50 text-amber-300 tabular-nums"
                            >
                                <Star :size="13" /> Your rating {{ userState.rate }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Body -->
                <div class="p-5 space-y-5">
                    <p v-if="currentItem.overview" class="text-gray-300 leading-relaxed">
                        {{ currentItem.overview }}
                    </p>

                    <div v-if="currentItem.title_providers?.length" class="space-y-2">
                        <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-400">Where to watch</h3>
                        <ProvidersBadge :title-providers="currentItem.title_providers" :mode="PROVIDER_MODE_FULL" />
                    </div>

                    <div v-if="currentItem.genres?.length" class="space-y-2">
                        <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-400">Genres</h3>
                        <GenreBadges :genres="currentItem.genres" />
                    </div>

                    <div v-if="currentItem.keywords?.length" class="space-y-2">
                        <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-400">Tags</h3>
                        <div class="flex flex-wrap gap-1.5">
                            <Link v-for="keyword in currentItem.keywords" :key="keyword.id" :href="keyword.url">
                                <span class="badge badge-outline badge-sm px-2 bg-gray/20 text-gray-300">
                                    {{ keyword.name }}
                                </span>
                            </Link>
                        </div>
                    </div>

                    <!-- More Like This -->
                    <div v-if="loadingSimilar || similar.length" class="space-y-3">
                        <div class="flex items-center justify-between gap-3">
                            <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-400">More Like This</h3>
                            <Link
                                v-if="currentItem.slug"
                                :href="`/graph?title_slug=${currentItem.slug}`"
                                class="btn btn-xs btn-neutral gap-1 border-0 bg-white/10 hover:bg-white/20"
                            >
                                <Share2 :size="13" />
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
                                        :src="posterSrc(title)"
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
                                <p v-if="title.release_date" class="text-[11px] text-white/45">
                                    {{ title.release_date }}
                                </p>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </BaseModal>
</template>
