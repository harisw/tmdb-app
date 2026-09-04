<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { EllipsisVertical } from 'lucide-vue-next'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faBookmark, faEye, faHeart } from '@fortawesome/free-solid-svg-icons'
import {
    faBookmark as faBookmarkRegular,
    faEye as faEyeRegular,
    faHeart as faHeartRegular,
} from '@fortawesome/free-regular-svg-icons'
import { button } from '../variants/button.js'
import ProvidersBadge from './ProvidersBadge.vue'

const props = defineProps({
    title: { type: Object, required: true },
    state: { type: Object, required: true },
    pending: { type: Boolean, default: false },
    likeCount: { type: String, default: null },
})
const emit = defineEmits(['action'])

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

// Same lock-and-restore dance as BaseModal. When a ratable row closes the sheet
// and hands off to TitleRateModal (hosted by TitleCard), that modal's own watcher
// re-locks in the same flush; no repaint happens in between, so restoring here
// is safe.
function handleKeydown(e) {
    if (e.key === 'Escape') {
        open.value = false
    }
}

let previousOverflow = ''
watch(open, (isOpen) => {
    if (isOpen) {
        previousOverflow = document.body.style.overflow
        document.body.style.overflow = 'hidden'
        window.addEventListener('keydown', handleKeydown)
        nextTick(() => panel.value?.focus())
    } else {
        document.body.style.overflow = previousOverflow
        window.removeEventListener('keydown', handleKeydown)
    }
})

onBeforeUnmount(() => {
    window.removeEventListener('keydown', handleKeydown)
    if (open.value) {
        document.body.style.overflow = previousOverflow
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
            :class="
                button({
                    intent: 'ghost',
                    size: 'sm',
                    circle: true,
                    class: 'md:hidden shrink-0 -mr-1.5 text-white/40 hover:text-white',
                })
            "
            @click.stop="open = true"
        >
            <EllipsisVertical :size="18" />
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
                            <!--                            <ProvidersBadge-->
                            <!--                                v-if="title.title_providers?.length"-->
                            <!--                                :title-providers="title.title_providers.slice(0, 3)"-->
                            <!--                            />-->
                            <!--                            <div class="mt-1 flex items-center gap-1.5 text-xs text-white/50">-->
                            <!--                                <ProvidersBadge-->
                            <!--                                    v-if="title.title_providers?.length"-->
                            <!--                                    :title-providers="title.title_providers.slice(0, 1)"-->
                            <!--                                />-->
                            <!--                                <span v-if="title.release_date">{{ title.release_date }}</span>-->
                            <!--                            </div>-->
                        </div>
                    </div>

                    <div class="border-t border-white/5 py-1">
                        <button
                            v-for="row in rows"
                            :key="row.key"
                            type="button"
                            :disabled="pending"
                            :aria-pressed="row.active"
                            class="flex w-full items-center gap-4 rounded-lg px-2 py-3.5 text-left transition-colors active:bg-white/5 disabled:opacity-50"
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
