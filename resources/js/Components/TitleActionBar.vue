<script setup>
import { faBookmark, faEye, faHeart } from '@fortawesome/free-solid-svg-icons'
import {
    faBookmark as faBookmarkRegular,
    faEye as faEyeRegular,
    faHeart as faHeartRegular,
} from '@fortawesome/free-regular-svg-icons'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { tv } from '../variants/tv.js'

defineProps({
    state: { type: Object, required: true },
    pending: { type: Boolean, default: false },
    likeCount: { type: String, default: null },
})
const emit = defineEmits(['action'])

const actionBtn = tv({
    base: `btn btn-xs relative h-7 rounded-md border-none bg-transparent text-white shadow-none transition-transform
    duration-300 ease-out will-change-transform hover:z-10 hover:bg-black hover:-translate-y-1 hover:scale-125
    hover:rotate-x-12 hover:shadow-xl hover:shadow-black/60 active:scale-95`,
    variants: {
        withGap: { true: 'gap-1 ' },
        active: { true: 'text-madder' },
    },
})
</script>

<template>
    <div
        class="mr-auto hidden md:flex items-center gap-1 rounded-lg bg-black/70 p-0.5 backdrop-blur-sm [perspective:500px] [transform-style:preserve-3d] translate-y-8 opacity-0 pointer-events-none transition-all duration-300 ease-out group-hover:translate-y-0 group-hover:opacity-100 group-hover:pointer-events-auto"
        @click.stop
    >
        <button
            type="button"
            :aria-label="state.liked ? 'Unlike' : 'Like'"
            :aria-pressed="state.liked"
            :disabled="pending"
            :class="actionBtn({ withGap: !!likeCount, active: state.liked })"
            @click="emit('action', 'like')"
        >
            <FontAwesomeIcon :icon="state.liked ? faHeart : faHeartRegular" />
            <span v-if="likeCount" class="text-xs tabular-nums">{{ likeCount }}</span>
        </button>
        <button
            type="button"
            :aria-label="state.to_watch ? 'Remove from watchlist' : 'Add to watchlist'"
            :aria-pressed="state.to_watch"
            :disabled="pending"
            :class="actionBtn({ active: state.to_watch })"
            @click="emit('action', 'watchlist')"
        >
            <FontAwesomeIcon :icon="state.to_watch ? faBookmark : faBookmarkRegular" />
        </button>
        <button
            type="button"
            :aria-label="state.watched ? 'Mark as unwatched' : 'Mark as watched'"
            :aria-pressed="state.watched"
            :disabled="pending"
            :class="actionBtn({ active: state.watched })"
            @click="emit('action', 'watched')"
        >
            <FontAwesomeIcon :icon="state.watched ? faEye : faEyeRegular" />
        </button>
    </div>
</template>

<style scoped></style>
