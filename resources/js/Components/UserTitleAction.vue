<script setup>
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import {
    faBookmark as faBookmarkRegular,
    faEye as faEyeRegular,
    faHeart as faHeartRegular,
} from '@fortawesome/free-regular-svg-icons'
import { faBookmark, faEye, faHeart } from '@fortawesome/free-solid-svg-icons'
import { computed, reactive, ref, watch } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { tv } from '../variants/tv.js'
import { useUiStore } from '../stores/ui.js'
import TitleRateModal from './Modals/TitleRateModal.vue'

const props = defineProps({
    title: { type: Object, required: true },
})

const actionBtn = tv({
    base: `btn btn-xs relative h-7 rounded-md border-none bg-transparent text-white shadow-none transition-transform
    duration-300 ease-out will-change-transform hover:z-10 hover:bg-black hover:-translate-y-1 hover:scale-125
    hover:rotate-x-12 hover:shadow-xl hover:shadow-black/60 active:scale-95`,
    variants: {
        withGap: { true: 'gap-1 ' },
        active: { true: 'text-madder' },
    },
})

const page = usePage()
const ui = useUiStore()
const isAuthenticated = computed(() => !!page.props.auth?.user)
const actions = computed(() => page.props.enums.TitleAction)

const STATE_KEY = { like: 'liked', watchlist: 'to_watch', watched: 'watched' }

const blank = () => ({ liked: false, to_watch: false, watched: false, rate: null })
const state = ref({ ...blank(), ...(props.title.user_state ?? {}) })
watch(
    () => props.title.id,
    () => (state.value = { ...blank(), ...(props.title.user_state ?? {}) })
)

const pending = ref(false)
const rate = reactive({ show: false, context: 'watched', eventType: null, patch: null, settled: true })

const countFormat = new Intl.NumberFormat('en', { notation: 'compact', maximumFractionDigits: 1 })
const likeCount = computed(() => {
    const n = props.title.likes_count ?? 0
    return n ? countFormat.format(n).toLowerCase() : null
})

function trigger(key) {
    if (!isAuthenticated.value) return ui.openAuthModal('login')
    if (pending.value) return

    const isOn = state.value[STATE_KEY[key]]
    const action = actions.value[key]
    const eventType = isOn ? action.off : action.on
    const patch = { [STATE_KEY[key]]: !isOn }

    if (!isOn && action.ratable) {
        state.value = { ...state.value, ...patch } // optimistic toggle now; rating follows
        Object.assign(rate, { show: true, context: key, eventType, patch, settled: false })
        return
    }
    send(eventType, null, patch)
}

function resolveRating(value) {
    if (rate.settled) return
    rate.settled = true
    rate.show = false
    send(rate.eventType, value, { ...rate.patch, ...(value !== null ? { rate: value } : {}) })
}

async function send(type, value, optimistic) {
    const snapshot = state.value
    state.value = { ...state.value, ...optimistic }
    pending.value = true
    try {
        const { data } = await window.axios.post('/user/title/event', {
            title_id: props.title.id,
            type,
            value,
        })
        state.value = { ...state.value, ...data.data }
    } catch (e) {
        state.value = snapshot
        if (e.response?.status === 401) ui.openAuthModal('login')
    } finally {
        pending.value = false
    }
}
</script>

<template>
    <div
        class="mr-auto flex items-center gap-1 rounded-lg bg-black/70 p-0.5 backdrop-blur-sm [perspective:500px] [transform-style:preserve-3d] translate-y-8 opacity-0 pointer-events-none transition-all duration-300 ease-out group-hover:translate-y-0 group-hover:opacity-100 group-hover:pointer-events-auto"
        @click.stop
    >
        <button
            type="button"
            :aria-label="state.liked ? 'Unlike' : 'Like'"
            :aria-pressed="state.liked"
            :disabled="pending"
            :class="actionBtn({ withGap: !!likeCount, active: state.liked })"
            @click="trigger('like')"
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
            @click="trigger('watchlist')"
        >
            <FontAwesomeIcon :icon="state.to_watch ? faBookmark : faBookmarkRegular" />
        </button>
        <button
            type="button"
            :aria-label="state.watched ? 'Mark as unwatched' : 'Mark as watched'"
            :aria-pressed="state.watched"
            :disabled="pending"
            :class="actionBtn({ active: state.watched })"
            @click="trigger('watched')"
        >
            <FontAwesomeIcon :icon="state.watched ? faEye : faEyeRegular" />
        </button>

        <TitleRateModal
            :show="rate.show"
            :title-name="title.name"
            :context="rate.context"
            @confirm="resolveRating"
            @close="resolveRating(null)"
        />
    </div>
</template>

<style scoped></style>
