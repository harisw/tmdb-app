<script setup>
import RatingBadge from './RatingBadge.vue'
import { FilmIcon, TvIcon } from 'lucide-vue-next'
import ProvidersBadge from './ProvidersBadge.vue'
import GenreBadges from './GenreBadges.vue'
import TitleActionBar from './TitleActionBar.vue'
import TitleActionSheet from './TitleActionSheet.vue'
import TitleRateModal from './Modals/TitleRateModal.vue'
import useTitleActions from '../Composables/useTitleActions.js'
import { usePointerTilt } from '../Composables/usePointerTilt.js'
import { onPosterError } from '../utils/img.js'
import { badge } from '../variants/badge.js'

const props = defineProps({
    onClick: { type: Function },
    item: { type: Object, required: true },
    showGenres: { type: Boolean, default: true },
})

const GENRE_LIMIT = 2

const { state, pending, likeCount, trigger, rate, resolveRating } = useTitleActions(() => props.item)
const { target: card, onPointerMove, onPointerLeave } = usePointerTilt()
</script>

<template>
    <div class="h-full [perspective:800px]">
        <div
            ref="card"
            class="group relative flex h-full cursor-pointer flex-col overflow-hidden rounded-xl bg-licorice shadow-xl ring-1 ring-white/5 transition-transform duration-200 ease-out [transform:translateY(var(--lift,0px))_rotateX(var(--tilt-x,0deg))_rotateY(var(--tilt-y,0deg))] has-[.card-open:focus-visible]:ring-2 has-[.card-open:focus-visible]:ring-glow-red motion-reduce:transition-none"
            @pointermove="onPointerMove"
            @pointerleave="onPointerLeave"
        >
            <div class="relative overflow-hidden rounded-t-xl">
                <img
                    :src="item.poster_path"
                    :alt="`${item.name} Poster`"
                    class="aspect-[2/3] w-full object-cover transition-transform duration-200 ease-out [transform:translate(var(--shift-x,0px),var(--shift-y,0px))_scale(var(--zoom,1))] motion-reduce:transition-none"
                    @error="onPosterError"
                />

                <div class="absolute inset-x-2 top-2 flex items-center justify-between">
                    <span :class="badge({ tone: item.type === 'movie' ? 'movie' : 'tv' })">
                        <FilmIcon v-if="item.type === 'movie'" size="14" />
                        <TvIcon v-else size="14" />
                        {{ item.type === 'movie' ? 'Film' : 'TV' }}
                        <span v-if="item.release_date" class="tabular-nums opacity-70">· {{ item.release_date }}</span>
                    </span>
                    <RatingBadge :rating="item.rating" />
                </div>

                <div class="absolute inset-x-2 bottom-2 z-10 flex items-center justify-end gap-1">
                    <TitleActionBar :state="state" :pending="pending" :like-count="likeCount" @action="trigger" />
                    <ProvidersBadge
                        v-if="item.title_providers?.length"
                        :title-providers="item.title_providers.slice(0, 3)"
                    />
                </div>
            </div>

            <div class="flex flex-1 flex-col gap-2 px-3 pt-2.5 pb-3">
                <div class="flex items-start justify-between gap-2">
                    <h2 class="min-h-[2lh] text-base font-semibold leading-snug text-white">
                        <button
                            type="button"
                            class="card-open line-clamp-2 cursor-pointer text-left outline-none after:absolute after:inset-0 after:content-['']"
                            @click="props.onClick(item)"
                        >
                            {{ item.name }}
                        </button>
                    </h2>
                    <div class="relative z-10 shrink-0 md:hidden">
                        <TitleActionSheet
                            :title="item"
                            :state="state"
                            :pending="pending"
                            :like-count="likeCount"
                            @action="trigger"
                        />
                    </div>
                </div>

                <div v-if="showGenres" class="relative z-10 h-5 overflow-hidden">
                    <GenreBadges :genres="item.genres.slice(0, GENRE_LIMIT)" />
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
