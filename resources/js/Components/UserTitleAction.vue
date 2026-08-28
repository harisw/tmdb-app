<script setup>
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'

import { faBookmark, faEye, faHeart } from '@fortawesome/free-regular-svg-icons'
import { computed } from 'vue'
import { tv } from '../variants/tv.js'

const actionBtn = tv({
    base: `btn btn-xs relative h-7 rounded-md border-none bg-transparent text-white shadow-none transition-transform
    duration-300 ease-out will-change-transform hover:z-10 hover:bg-black hover:-translate-y-1 hover:scale-125
    hover:rotate-x-12 hover:shadow-xl hover:shadow-black/60 active:scale-95`,
    variants: { withGap: { true: 'gap-1 ' } },
})
const props = defineProps({
    title: { type: Object, required: true },
})

const countFormat = new Intl.NumberFormat('en', { notation: 'compact', maximumFractionDigits: 1 })

const likeCount = computed(() => {
    const n = props.title.likes_count ?? 0
    return n ? countFormat.format(n).toLowerCase() : null
})

function onLike() {}

function onBookmark() {}

function onMarkWatched() {}
</script>

<template>
    <div
        class="mr-auto flex items-center gap-1 rounded-lg bg-black/70 p-0.5 backdrop-blur-sm [perspective:500px] [transform-style:preserve-3d] translate-y-8 opacity-0 pointer-events-none transition-all duration-300 ease-out group-hover:translate-y-0 group-hover:opacity-100 group-hover:pointer-events-auto"
        @click.stop
    >
        <button type="button" aria-label="Like" :class="actionBtn({ withGap: !!likeCount })" @click="onLike">
            <FontAwesomeIcon :icon="faHeart" />
            <span v-if="likeCount" class="text-xs tabular-nums">{{ likeCount }}</span>
        </button>
        <button type="button" aria-label="Add to watchlist" :class="actionBtn()" @click="onBookmark">
            <FontAwesomeIcon :icon="faBookmark" />
        </button>
        <button type="button" aria-label="Mark as watched" :class="actionBtn()" @click="onMarkWatched">
            <FontAwesomeIcon :icon="faEye" />
        </button>
    </div>
</template>

<style scoped></style>
