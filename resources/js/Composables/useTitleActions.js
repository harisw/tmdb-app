import { computed, ref, toValue, watch } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { useUiStore } from '../stores/ui.js'

const STATE_KEY = { like: 'liked', watchlist: 'to_watch', watched: 'watched' }
const blank = () => ({ liked: false, to_watch: false, watched: false, rate: null })
const countFormat = new Intl.NumberFormat('en', { notation: 'compact', maximumFractionDigits: 1 })
const idleRate = () => ({ show: false, context: 'watched', eventType: null, patch: null, settled: true })
export default function useTitleActions(src) {
    const page = usePage()
    const ui = useUiStore()
    const isAuthenticated = computed(() => !!page.props.auth?.user)
    const actions = computed(() => page.props.enums.TitleAction)

    const title = computed(() => toValue(src))

    const seedState = () => ({ ...blank(), ...(title.value?.user_state ?? {}) })

    const state = ref(blank())
    const pending = ref(false)
    const rate = ref(idleRate())

    watch(
        () => title.value?.id,
        () => {
            state.value = seedState()
            rate.value = idleRate()
            pending.value = false
        },
        { immediate: true }
    )

    const likeCount = computed(() => {
        const n = title.value?.likes_count ?? 0
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
        const target = title.value
        if (!target) {
            return
        }
        const snapshot = state.value
        state.value = { ...state.value, ...optimistic }
        pending.value = true
        try {
            const { data } = await window.axios.post('/user/title/event', {
                title_id: target.id,
                type,
                value,
            })
            state.value = { ...state.value, ...data.data }
            target.user_state = state.value
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
