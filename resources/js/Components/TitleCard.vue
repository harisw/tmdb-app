<script setup>
import { usePage } from '@inertiajs/vue3'
import RatingBadge from './RatingBadge.vue'
import { FilmIcon, TvIcon } from 'lucide-vue-next'
import { computed } from 'vue'
import ProvidersBadge from './ProvidersBadge.vue'
import GenreBadges from './GenreBadges.vue'
import Adult from './Icons/Adult.vue'

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
</script>

<template>
    <!-- #4a: dark card base instead of bg-white -->
    <div class="bg-licorice rounded-xl shadow">
        <div
            :class="`${size} group [perspective:500px] mx-auto h-full hover:cursor-pointer`"
            @click="props.onClick(item)"
        >
            <!-- animated card — ALL your transition / 3D classes are untouched -->
            <div
                class="relative bg-licorice/95 ring-1 ring-white/5 rounded-xl shadow-xl transition-transform duration-500 transform group-hover:rotate-y-3 group-hover:-rotate-x-1 group-hover:scale-105 [transform-style:preserve-3d] overflow-x-hidden h-full"
            >
                <!-- #3: poster + overlays wrapped in one relative container -->
                <div class="relative">
                    <img
                        :src="posterSrc"
                        :alt="`${item.name} Poster`"
                        class="w-full aspect-[2/3] object-cover rounded-t-xl"
                        @error="onPosterError"
                    />

                    <!-- top row: type badge + rating -->
                    <div class="absolute inset-x-2 top-2 flex justify-between items-center">
                        <span
                            v-if="item.type === 'movie'"
                            class="p-1 gap-1 badge rounded-md text-white text-xs font-medium bg-black/80"
                        >
                            <FilmIcon size="14" /> Film
                        </span>
                        <span v-else class="p-1 gap-1 badge rounded-md text-white text-xs font-medium bg-blue-900/80">
                            <TvIcon size="14" /> TV
                        </span>
                        <RatingBadge :rating="item.rating" />
                    </div>

                    <!-- bottom row: adult marker (left) + provider logos (right) -->
                    <div class="absolute inset-x-2 bottom-2 flex justify-end items-center gap-1">
                        <span
                            v-if="item.is_adult"
                            class="mr-auto p-1 badge rounded-full bg-black/80 text-red-500"
                            title="Adult content"
                        >
                            <Adult class="w-7 h-7" />
                        </span>
                        <ProvidersBadge
                            v-if="item.title_providers?.length"
                            :title-providers="item.title_providers.slice(0, 3)"
                        />
                    </div>
                </div>

                <!-- body -->
                <div class="p-2 flex flex-col">
                    <h2 class="text-base font-semibold text-white line-clamp-2">
                        {{ item.name }}
                    </h2>

                    <span v-if="item.release_date" class="mt-0.5 text-xs text-white/50">
                        {{ item.release_date }}
                    </span>

                    <div v-if="showGenres" class="mt-1.5 flex flex-wrap gap-1.5">
                        <GenreBadges :genres="item.genres.slice(0, 3)" />
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped></style>
