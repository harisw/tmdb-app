<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { Plus } from 'lucide-vue-next'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faBookmark, faEye, faHeart } from '@fortawesome/free-solid-svg-icons'
import {
    faBookmark as faBookmarkRegular,
    faEye as faEyeRegular,
    faHeart as faHeartRegular,
} from '@fortawesome/free-regular-svg-icons'
import { tv } from '../variants/tv.js'

const props = defineProps({
    title: { type: Object, required: true },
    state: { type: Object, required: true },
    pending: { type: Boolean, default: false },
    likeCount: { type: String, default: null },
})
const emit = defineEmits(['action'])

// Bespoke trigger: a friendly "add this" affordance — a soft circular well that's
// discoverable at rest on touch (no hover to reveal it), warming to madder on
// press so the tap feels like it belongs to Chill. One-off look, not a `button()`
// recipe case (cf. TitleActionBar's actionBtn).
const triggerBtn = tv({
    base: `grid size-8 shrink-0 -mr-1.5 place-items-center rounded-full md:hidden text-white/60
    bg-white/5 ring-1 ring-inset ring-white/10 transition duration-200 ease-out
    hover:bg-white/10 hover:text-white hover:ring-white/20
    active:scale-90 active:bg-madder active:text-white active:ring-madder
    focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-madder
    motion-reduce:transition-none motion-reduce:active:scale-100`,
})

const page = usePage()
const POSTER_PLACEHOLDER = '/poster-placeholder.svg'
const posterSrc = computed(() =>
    props.title.poster_path ? page.props.base_url.poster + props.title.poster_path : POSTER_PLACEHOLDER
)

const rows = computed(() => [
    {
        key: 'like',
        label: props.state.liked ? 'Liked' : 'Like',
        icon: props.state.liked ? faHeart : faHeartRegular,
        active: props.state.liked,
        count: props.likeCount,
    },
    {
        key: 'watchlist',
        label: props.state.to_watch ? 'On your list' : 'To watch',
        icon: props.state.to_watch ? faBookmark : faBookmarkRegular,
        active: props.state.to_watch,
        count: null,
    },
    {
        key: 'watched',
        label: props.state.watched ? 'Watched' : 'Mark watched',
        icon: props.state.watched ? faEye : faEyeRegular,
        active: props.state.watched,
        count: null,
    },
])

const open = ref(false)
const panel = ref(null)
let lastFocused = null

const FOCUSABLE = 'a[href],button:not([disabled]),[tabindex]:not([tabindex="-1"])'

function focusables() {
    if (!panel.value) return []
    return [...panel.value.querySelectorAll(FOCUSABLE)].filter((n) => n.getClientRects().length > 0)
}

// Same lock-and-restore dance as BaseModal: Escape closes, Tab stays inside the
// sheet, and focus returns to the trigger on close. When a ratable row closes the
// sheet and hands off to TitleRateModal (hosted by TitleCard), that modal's own
// watcher re-locks in the same flush; no repaint happens in between, so restoring
// here is safe.
function handleKeydown(e) {
    if (!open.value) return

    if (e.key === 'Escape') {
        open.value = false
        return
    }

    if (e.key !== 'Tab') return

    const nodes = focusables()
    if (nodes.length === 0) {
        e.preventDefault()
        panel.value?.focus()
        return
    }

    const first = nodes[0]
    const last = nodes[nodes.length - 1]
    const active = document.activeElement
    const inside = panel.value?.contains(active)

    if (e.shiftKey && (active === first || !inside)) {
        e.preventDefault()
        last.focus()
    } else if (!e.shiftKey && (active === last || !inside)) {
        e.preventDefault()
        first.focus()
    }
}

let previousOverflow = ''
watch(open, (isOpen) => {
    if (isOpen) {
        lastFocused = document.activeElement
        previousOverflow = document.body.style.overflow
        document.body.style.overflow = 'hidden'
        window.addEventListener('keydown', handleKeydown)
        nextTick(() => panel.value?.focus())
    } else {
        document.body.style.overflow = previousOverflow
        window.removeEventListener('keydown', handleKeydown)
        lastFocused?.focus?.()
        lastFocused = null
    }
})

onBeforeUnmount(() => {
    window.removeEventListener('keydown', handleKeydown)
    if (open.value) {
        document.body.style.overflow = previousOverflow
        lastFocused?.focus?.()
    }
})

function onRow(key) {
    emit('action', key)
    open.value = false
}
</script>

<template>
    <div class="contents">
        <button
            type="button"
            aria-haspopup="dialog"
            :aria-expanded="open"
            :aria-label="`Actions for ${title.name}`"
            :class="triggerBtn()"
            @click.stop="open = true"
        >
            <Plus :size="20" :stroke-width="3" />
        </button>

        <Teleport to="body">
            <Transition
                enter-active-class="transition-opacity duration-200 ease-out"
                leave-active-class="transition-opacity duration-200 ease-in"
                enter-from-class="opacity-0"
                leave-to-class="opacity-0"
            >
                <div v-if="open" class="fixed inset-0 z-[100] bg-black/70" @click="open = false" />
            </Transition>

            <Transition
                enter-active-class="transition-transform duration-300 ease-out motion-reduce:transition-none"
                leave-active-class="transition-transform duration-200 ease-in motion-reduce:transition-none"
                enter-from-class="translate-y-full motion-reduce:translate-y-0"
                leave-to-class="translate-y-full motion-reduce:translate-y-0"
            >
                <div
                    v-if="open"
                    ref="panel"
                    role="dialog"
                    aria-modal="true"
                    :aria-label="`Actions for ${title.name}`"
                    tabindex="-1"
                    class="fixed inset-x-0 bottom-0 z-[100] rounded-t-2xl bg-licorice text-white shadow-2xl ring-1 ring-white/10 px-4 pb-[calc(env(safe-area-inset-bottom)+0.75rem)] pt-2.5 focus:outline-none"
                    @click.stop
                >
                    <div class="mx-auto h-1 w-9 rounded-full bg-white/15" />

                    <div class="flex items-center gap-3 py-4">
                        <img
                            :src="posterSrc"
                            :alt="`${title.name} poster`"
                            class="w-11 shrink-0 aspect-[2/3] rounded-md object-cover ring-1 ring-white/10"
                        />
                        <div class="min-w-0">
                            <p class="truncate font-semibold leading-tight">{{ title.name }}</p>
                            <span v-if="title.release_date" class="text-xs text-white/50">{{
                                title.release_date
                            }}</span>
                        </div>
                    </div>

                    <div class="border-t border-white/5 py-1">
                        <button
                            v-for="row in rows"
                            :key="row.key"
                            type="button"
                            :disabled="pending"
                            :aria-pressed="row.active"
                            class="flex w-full items-center gap-4 rounded-lg px-2 py-3.5 text-left transition-colors hover:bg-white/5 active:bg-white/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-madder disabled:opacity-50"
                            :class="row.active ? 'text-madder' : 'text-white'"
                            @click="onRow(row.key)"
                        >
                            <FontAwesomeIcon :icon="row.icon" class="w-5 text-center text-lg" />
                            <span class="text-[15px] font-medium">{{ row.label }}</span>
                            <span v-if="row.count" class="ml-auto text-sm tabular-nums text-white/40">
                                {{ row.count }}
                            </span>
                        </button>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </div>
</template>

<style scoped></style>
