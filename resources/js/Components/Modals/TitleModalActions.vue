<script setup>
import { computed } from 'vue'
import { Star } from 'lucide-vue-next'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faBookmark, faEye, faHeart } from '@fortawesome/free-solid-svg-icons'
import {
    faBookmark as faBookmarkRegular,
    faEye as faEyeRegular,
    faHeart as faHeartRegular,
} from '@fortawesome/free-regular-svg-icons'
import { button } from '../../variants/button.js'
import useTitleActions from '../../Composables/useTitleActions.js'
import TitleRateModal from './TitleRateModal.vue'

const props = defineProps({
    item: { type: Object, required: true },
})

const { state, pending, trigger, rate, resolveRating } = useTitleActions(() => props.item)

const ACTIONS = [
    {
        key: 'like',
        flag: 'liked',
        off: 'Like',
        on: '',
        solid: faHeart,
        hollow: faHeartRegular,
        tint: 'bg-madder/90 text-white',
    },
    {
        key: 'watched',
        flag: 'watched',
        off: 'Add to Watched',
        on: 'Watched',
        solid: faEye,
        hollow: faEyeRegular,
        tint: 'bg-madder/90 text-white',
    },
    {
        key: 'watchlist',
        flag: 'to_watch',
        off: 'Add to Watchlist',
        on: 'On watchlist',
        solid: faBookmark,
        hollow: faBookmarkRegular,
        tint: 'bg-white/15 text-white',
    },
]

const rows = computed(() => ACTIONS.map((a) => ({ ...a, active: !!state.value[a.flag] })))
</script>

<template>
    <div class="mt-2 flex flex-wrap items-center gap-2 text-xs">
        <span
            v-if="Number(state.rate) > 0"
            class="badge badge-sm gap-1 border-none bg-black/50 text-amber-400 tabular-nums"
        >
            <Star :size="13" /> Your rating {{ state.rate }}
        </span>

        <template v-for="row in rows" :key="row.key">
            <span v-if="row.active" class="badge badge-sm gap-1 border-none" :class="row.tint">
                <FontAwesomeIcon :icon="row.solid" />
                <template v-if="row.on">{{ row.on }}</template>
            </span>
            <button
                v-else
                type="button"
                :disabled="pending"
                :class="button({ intent: 'ghost', size: 'xs' })"
                @click="trigger(row.key)"
            >
                <FontAwesomeIcon :icon="row.hollow" /> {{ row.off }}
            </button>
        </template>

        <TitleRateModal
            :show="rate.show"
            :title-name="item.name"
            :context="rate.context"
            @confirm="resolveRating"
            @close="resolveRating(null)"
        />
    </div>
</template>
