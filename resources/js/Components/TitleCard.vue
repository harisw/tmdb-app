<script setup>
import { usePage } from '@inertiajs/vue3'
import RatingBadge from './RatingBadge.vue'
import { FilmIcon, TvIcon } from 'lucide-vue-next'
import { computed } from 'vue'
import ProvidersBadge from './ProvidersBadge.vue'

const props = defineProps({
    onClick: { type: Function },
    item: { type: Object, required: true },
    showGenres: { type: Boolean, default: true },
    size: { type: String, default: '' },
})

const page = usePage()
const base_url = computed(() => page.props.base_url)
</script>

<template>
    <div class="bg-white rounded shadow">
        <div
            :class="`${size} group [perspective:500px] mx-auto h-full hover:cursor-pointer`"
            @click="props.onClick(item)"
        >
            <div
                class="relative bg-white rounded-xl shadow-xl transition-transform duration-500 transform group-hover:rotate-y-3 group-hover:-rotate-x-1 group-hover:scale-105 [transform-style:preserve-3d] overflow-x-hidden h-full"
            >
                <div class="absolute inset-x-2 top-2 flex justify-between items-center">
                    <span
                        v-if="item.type === 'movie'"
                        class="flex items-center gap-1 bg-black/60 text-white text-xs font-medium px-2 py-1 rounded-md"
                    >
                        <FilmIcon size="14" /> Movie
                    </span>
                    <span
                        v-else
                        class="flex items-center gap-1 bg-blue-900/80 text-white text-xs font-medium px-2 py-1 rounded-md"
                    >
                        <TvIcon size="14" /> TV
                    </span>
                    <RatingBadge :rating="item.rating" />
                </div>
                <div class="absolute w-full h-90">
                    <div class="absolute bottom-2 w-full flex justify-end gap-1 px-2">
                        <ProvidersBadge
                            v-if="item.title_providers?.length"
                            :titleProviders="item.title_providers.slice(0, 3)"
                        />
                    </div>
                </div>
                <img
                    :src="page.props.base_url.poster + item.poster_path"
                    :alt="`${item.name} Poster`"
                    class="w-full h-90 object-cover rounded-t-xl"
                />

                <div class="p-2 flex flex-col max-h-48">
                    <div class="flex flex-1 justify-between">
                        <h2 class="text-base font-bold text-gray-800 line-clamp-2">
                            {{ item.name }}
                        </h2>
                    </div>
                    <div v-if="showGenres" class="flex flex-2 gap-2 text-xs">
                        <span
                            v-for="genre in item.genres.slice(0, 3)"
                            :key="genre.id"
                            class="py-0.25 px-1 bg-tea-green rounded"
                        >
                            {{ genre.name }}
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped></style>
