<script setup>
import { usePage } from '@inertiajs/vue3'
import RatingBadge from './RatingBadge.vue'
import { FilmIcon, TvIcon } from 'lucide-vue-next'
import { computed } from 'vue'
import ProvidersBadge from './ProvidersBadge.vue'
import GenreBadges from './GenreBadges.vue'
import TitleActionBar from './TitleActionBar.vue'
import TitleActionSheet from './TitleActionSheet.vue'
import TitleRateModal from './Modals/TitleRateModal.vue'
import useTitleActions from '../Composables/useTitleActions.js'
import { badge } from '../variants/badge.js'

const props = defineProps({
    onClick: { type: Function },
    item: { type: Object, required: true },
    showGenres: { type: Boolean, default: true },
    size: { type: String, default: '' },
})

const page = usePage()
const base_url = computed(() => page.props.base_url)

const POSTER_PLACEHOLDER = '/poster-placeholder.svg'

const posterSrc = computed(() =>
    props.item.poster_path ? base_url.value.poster + props.item.poster_path : POSTER_PLACEHOLDER
)

function onPosterError(e) {
    if (e.target.src.endsWith(POSTER_PLACEHOLDER)) return
    e.target.src = POSTER_PLACEHOLDER
}

// One instance per card, shared by the desktop bar and the mobile sheet so both
// controls reflect the same like / watchlist / watched state.
const { state, pending, likeCount, trigger, rate, resolveRating } = useTitleActions(props.item)
</script>

<template>
    <!-- #4a: dark card base instead of bg-white -->
    <div class="bg-licorice rounded-xl shadow">
        <div
            :class="`${size} group [perspective:500px] mx-auto h-full hover:cursor-pointer`"
            @click="props.onClick(item)"
        >
            <!-- animated card: ALL your transition / 3D classes are untouched -->
            <div
                class="relative bg-licorice/95 ring-1 ring-white/5 rounded-xl shadow-xl transition-transform duration-500 transform group-hover:rotate-y-3 group-hover:-rotate-x-1 group-hover:scale-105 [transform-style:preserve-3d] overflow-x-hidden h-full"
            >
                <!-- #3: poster + overlays wrapped in one relative container.
                     overflow-hidden masks the action group while it slides up. -->
                <div class="relative overflow-hidden rounded-t-xl">
                    <img
                        :src="posterSrc"
                        :alt="`${item.name} Poster`"
                        class="w-full aspect-[2/3] object-cover rounded-t-xl"
                        @error="onPosterError"
                    />

                    <!-- top row: type badge + rating -->
                    <div class="absolute inset-x-2 top-2 flex justify-between items-center">
                        <span v-if="item.type === 'movie'" :class="badge({ tone: 'movie' })">
                            <FilmIcon size="14" /> Film
                        </span>
                        <span v-else :class="badge({ tone: 'tv' })"> <TvIcon size="14" /> TV </span>
                        <RatingBadge :rating="item.rating" />
                    </div>

                    <!-- bottom row: action group (left) + provider logos (right) -->
                    <div class="absolute inset-x-2 bottom-2 flex justify-end items-center gap-1">
                        <TitleActionBar :state="state" :pending="pending" :like-count="likeCount" @action="trigger" />
                        <ProvidersBadge
                            v-if="item.title_providers?.length"
                            :title-providers="item.title_providers.slice(0, 3)"
                        />
                    </div>
                </div>

                <!-- body -->
                <div class="p-2 flex flex-col">
                    <h2 class="hidden md:block text-base font-semibold text-white line-clamp-2">
                        {{ item.name }}
                    </h2>
                    <div class="md:hidden flex flex-row items-start justify-between gap-2">
                        <h2 class="text-base font-semibold text-white line-clamp-2">
                            {{ item.name }}
                        </h2>
                        <TitleActionSheet
                            :title="item"
                            :state="state"
                            :pending="pending"
                            :like-count="likeCount"
                            @action="trigger"
                        />
                    </div>
                    <span v-if="item.release_date" class="mt-0.5 text-xs text-white/50">
                        {{ item.release_date }}
                    </span>

                    <div v-if="showGenres" class="mt-1.5 flex flex-wrap gap-1.5">
                        <GenreBadges :genres="item.genres.slice(0, 3)" />
                    </div>
                </div>
            </div>
        </div>

        <TitleRateModal
            :show="rate.show"
            :title-name="item.name"
            :context="rate.context"
            @confirm="resolveRating"
            @close="resolveRating(null)"
        />
    </div>
</template>

<style scoped></style>
