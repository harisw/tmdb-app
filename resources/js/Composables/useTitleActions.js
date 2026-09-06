import { computed, ref, watch } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { useUiStore } from '../stores/ui.js'

/**
 * All the behaviour behind a title's Like / To watch / Mark watched controls:
 * optimistic local state, the POST to /user/title/event, and the "rate it now"
 * hand-off for ratable actions. Presentation is split across TitleActionBar
 * (desktop hover) and TitleActionSheet (mobile drawer).
 *
 * Call this ONCE per card (in TitleCard) and pass state / trigger down to both
 * views, so the two controls always agree. Calling it in each view instead would
 * give every view its own state.
 *
 * Pass the reactive `props.item` object straight through; do not destructure it,
 * or the id watch below stops firing when a card is recycled in a v-for.
 */
const STATE_KEY = { like: 'liked', watchlist: 'to_watch', watched: 'watched' }
const blank = () => ({ liked: false, to_watch: false, watched: false, rate: null })
const countFormat = new Intl.NumberFormat('en', { notation: 'compact', maximumFractionDigits: 1 })

export default function useTitleActions(title) {
    const page = usePage()
    const ui = useUiStore()
    const isAuthenticated = computed(() => !!page.props.auth?.user)
    const actions = computed(() => page.props.enums.TitleAction)

    const state = ref({ ...blank(), ...(title.user_state ?? {}) })
    watch(
        () => title.id,
        () => (state.value = { ...blank(), ...(title.user_state ?? {}) })
    )

    const pending = ref(false)
    const rate = ref({ show: false, context: 'watched', eventType: null, patch: null, settled: true })

    const likeCount = computed(() => {
        const n = title.likes_count ?? 0
        return n ? countFormat.format(n).toLowerCase() : null
    })

    function trigger(key) {
        if (!isAuthenticated.value) {
            return ui.openAuthModal('login')
        }
        if (pending.value) {
            return
        }

        const isOn = state.value[STATE_KEY[key]]
        const action = actions.value[key]
        const eventType = isOn ? action.off : action.on
        const patch = { [STATE_KEY[key]]: !isOn }

        if (!isOn && action.ratable) {
            state.value = { ...state.value, ...patch } // optimistic toggle now; rating follows
            rate.value = { show: true, context: key, eventType, patch, settled: false }
            return
        }
        send(eventType, null, patch)
    }

    function resolveRating(value) {
        if (rate.value.settled) {
            return
        }
        const { eventType, patch } = rate.value
        rate.value = { ...rate.value, show: false, settled: true }
        send(eventType, value, { ...patch, ...(value !== null ? { rate: value } : {}) })
    }

    async function send(type, value, optimistic) {
        const snapshot = state.value
        state.value = { ...state.value, ...optimistic }
        pending.value = true
        try {
            const { data } = await window.axios.post('/user/title/event', {
                title_id: title.id,
                type,
                value,
            })
            state.value = { ...state.value, ...data.data }
            title.user_state = state.value
        } catch (e) {
            state.value = snapshot
            if (e.response?.status === 401) {
                ui.openAuthModal('login')
            }
        } finally {
            pending.value = false
        }
    }

    return { state, pending, isAuthenticated, likeCount, trigger, rate, resolveRating }
}
