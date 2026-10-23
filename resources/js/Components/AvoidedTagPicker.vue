<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import { Check, LoaderCircle, Search, X } from 'lucide-vue-next'
import { field } from '../variants/field.js'

const props = defineProps({
    keywords: { type: Array, required: true },
    max: { type: Number, required: true },
    results: { type: Object, default: null },
})

const MIN_QUERY_LENGTH = 2
const SEARCH_DEBOUNCE_MS = 250

const avoided = ref([])
const pending = ref(new Map())
const query = ref('')
const isOpen = ref(false)
const highlighted = ref(0)
const isSearching = ref(false)
const error = ref('')
let searchTimer = null

const numberFormat = new Intl.NumberFormat()

watch(
    () => props.keywords,
    (keywords) => {
        const changes = pending.value
        const removed = new Set([...changes].filter(([, change]) => change.action === 'remove').map(([slug]) => slug))
        const serverSlugs = new Set(keywords.map((keyword) => keyword.slug))
        const added = [...changes.values()]
            .filter((change) => change.action === 'add' && !serverSlugs.has(change.keyword.slug))
            .map((change) => change.keyword)

        avoided.value = [...keywords.filter((keyword) => !removed.has(keyword.slug)), ...added]
    },
    { immediate: true }
)

const avoidedSlugs = computed(() => new Set(avoided.value.map((keyword) => keyword.slug)))
const isFull = computed(() => avoided.value.length >= props.max)
const term = computed(() => query.value.trim())
const isQueryTooShort = computed(() => term.value.length < MIN_QUERY_LENGTH)
const options = computed(() => (props.results?.query === term.value ? props.results.items : []))
const isStale = computed(() => !isQueryTooShort.value && props.results?.query !== term.value)
const showListbox = computed(() => isOpen.value && !isQueryTooShort.value)
const activeOptionId = computed(() =>
    showListbox.value && options.value[highlighted.value]
        ? `avoid-option-${options.value[highlighted.value].slug}`
        : undefined
)

watch(term, (value) => {
    clearTimeout(searchTimer)
    highlighted.value = 0
    if (value.length < MIN_QUERY_LENGTH) {
        return
    }
    searchTimer = setTimeout(() => {
        router.reload({
            only: ['keywordResults'],
            data: { tag_query: value },
            preserveUrl: true,
            onStart: () => (isSearching.value = true),
            onFinish: () => (isSearching.value = false),
        })
    }, SEARCH_DEBOUNCE_MS)
})

onBeforeUnmount(() => clearTimeout(searchTimer))

function highlightParts(name) {
    const index = name.toLowerCase().indexOf(term.value.toLowerCase())
    if (index === -1) {
        return [{ text: name, match: false }]
    }
    const end = index + term.value.length
    return [
        { text: name.slice(0, index), match: false },
        { text: name.slice(index, end), match: true },
        { text: name.slice(end), match: false },
    ].filter((part) => part.text)
}

function persist(action, keyword, rollback) {
    error.value = ''
    pending.value.set(keyword.slug, { action, keyword })

    router.visit(`/settings/avoided-tags/${keyword.slug}`, {
        method: action === 'add' ? 'post' : 'delete',
        async: true,
        preserveScroll: true,
        preserveState: true,
        only: ['avoidedKeywords'],
        onError: (errors) => {
            rollback()
            error.value = errors.keyword ?? `Couldn't update “${keyword.name}”. Try again.`
        },
        onFinish: () => pending.value.delete(keyword.slug),
    })
}

function add(keyword) {
    if (isFull.value) {
        error.value = `You can avoid up to ${props.max} tags. Remove one to add another.`
        return
    }
    avoided.value = [...avoided.value, keyword]
    persist('add', keyword, () => (avoided.value = avoided.value.filter((k) => k.slug !== keyword.slug)))
}

function remove(keyword) {
    const index = avoided.value.findIndex((k) => k.slug === keyword.slug)
    avoided.value = avoided.value.filter((k) => k.slug !== keyword.slug)
    persist('remove', keyword, () => avoided.value.splice(index, 0, keyword))
}

function toggle(keyword) {
    avoidedSlugs.value.has(keyword.slug) ? remove(keyword) : add(keyword)
}

function choose(keyword) {
    toggle(keyword)
    query.value = ''
}

function move(step) {
    if (!showListbox.value || options.value.length === 0) {
        isOpen.value = true
        return
    }
    highlighted.value = (highlighted.value + step + options.value.length) % options.value.length
}

function chooseHighlighted() {
    const keyword = options.value[highlighted.value]
    if (showListbox.value && keyword) {
        choose(keyword)
    }
}

function close() {
    isOpen.value = false
}
</script>

<template>
    <section aria-labelledby="avoided-tags-heading" class="pt-10 border-t border-white/10">
        <div class="flex items-baseline justify-between gap-4">
            <h2 id="avoided-tags-heading" class="text-lg font-semibold text-white">Avoided tags</h2>
            <span class="text-sm tabular-nums" :class="isFull ? 'text-red-300' : 'text-white/45'">
                {{ avoided.length }} / {{ max }}
            </span>
        </div>
        <p class="mt-1.5 max-w-[60ch] text-sm leading-relaxed text-white/55">
            Titles carrying any of these tags stay out of home, browse, search and similar titles. Your own lists and
            links you open directly still show them.
        </p>

        <div class="relative mt-6">
            <label for="avoid-tag-search" class="sr-only">Search tags to avoid</label>
            <Search class="absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-white/40 pointer-events-none" />
            <input
                id="avoid-tag-search"
                v-model="query"
                type="text"
                role="combobox"
                autocomplete="off"
                maxlength="50"
                aria-autocomplete="list"
                aria-controls="avoid-tag-listbox"
                :aria-expanded="showListbox"
                :aria-activedescendant="activeOptionId"
                :placeholder="isFull ? 'Limit reached — remove a tag to add more' : 'Search tags, e.g. zombie'"
                :disabled="isFull"
                :class="field({ open: showListbox, disabled: isFull, class: 'h-11 pl-10 pr-10 text-sm caret-madder' })"
                @focus="isOpen = true"
                @blur="close"
                @input="isOpen = true"
                @keydown.down.prevent="move(1)"
                @keydown.up.prevent="move(-1)"
                @keydown.enter.prevent="chooseHighlighted"
                @keydown.escape="close"
            />
            <LoaderCircle
                v-if="isSearching || isStale"
                aria-hidden="true"
                class="absolute right-3.5 top-1/2 size-4 -translate-y-1/2 text-white/50 animate-spin motion-reduce:animate-none"
            />

            <Transition
                enter-active-class="transition duration-150 ease-out motion-reduce:transition-none"
                enter-from-class="opacity-0 -translate-y-1"
                leave-active-class="transition duration-100 ease-in motion-reduce:transition-none"
                leave-to-class="opacity-0"
            >
                <div
                    v-if="showListbox"
                    class="absolute inset-x-0 z-30 mt-2 overflow-hidden rounded-field border border-white/15 bg-base-200 shadow-[0_16px_40px_-12px_rgb(0_0_0/0.7)]"
                >
                    <ul
                        id="avoid-tag-listbox"
                        role="listbox"
                        aria-label="Matching tags"
                        class="max-h-72 overflow-y-auto py-1.5"
                    >
                        <li
                            v-for="(keyword, index) in options"
                            :id="`avoid-option-${keyword.slug}`"
                            :key="keyword.slug"
                            role="option"
                            :aria-selected="avoidedSlugs.has(keyword.slug)"
                            class="flex cursor-pointer items-center gap-3 px-3.5 py-2.5 text-sm transition-colors motion-reduce:transition-none"
                            :class="index === highlighted ? 'bg-white/10' : ''"
                            @mousedown.prevent="choose(keyword)"
                            @mouseenter="highlighted = index"
                        >
                            <span class="min-w-0 flex-1 truncate text-white/70">
                                <template v-for="(part, i) in highlightParts(keyword.name)" :key="i">
                                    <span v-if="part.match" class="font-semibold text-white">{{ part.text }}</span>
                                    <template v-else>{{ part.text }}</template>
                                </template>
                            </span>
                            <span
                                v-if="avoidedSlugs.has(keyword.slug)"
                                class="inline-flex shrink-0 items-center gap-1 text-xs font-medium text-tea-green"
                            >
                                <Check class="size-3.5" aria-hidden="true" />
                                Avoided
                            </span>
                            <span v-else class="shrink-0 text-xs tabular-nums text-white/40">
                                {{ numberFormat.format(keyword.count) }} titles
                            </span>
                        </li>
                    </ul>
                    <p v-if="isStale && options.length === 0" class="px-3.5 py-3 text-sm text-white/50">Searching…</p>
                    <p v-else-if="options.length === 0" class="px-3.5 py-3 text-sm text-white/50">
                        No tag matches “{{ term }}”.
                    </p>
                </div>
            </Transition>
        </div>

        <p v-if="error" role="alert" class="mt-2 text-sm text-red-300">{{ error }}</p>

        <TransitionGroup
            v-if="avoided.length"
            tag="ul"
            aria-label="Avoided tags"
            class="relative mt-5 flex flex-wrap gap-2"
            enter-active-class="transition duration-200 ease-out motion-reduce:transition-none"
            enter-from-class="opacity-0 scale-90"
            leave-active-class="absolute transition duration-150 ease-in motion-reduce:transition-none"
            leave-to-class="opacity-0 scale-90"
            move-class="transition-transform duration-200 ease-out motion-reduce:transition-none"
        >
            <li
                v-for="keyword in avoided"
                :key="keyword.slug"
                class="inline-flex max-w-full items-center gap-1 rounded-full border border-madder/50 bg-madder/15 py-1 pl-3 pr-1 text-sm text-white transition-opacity"
                :class="{ 'opacity-60': pending.has(keyword.slug) }"
            >
                <span class="min-w-0 truncate" :title="keyword.name">{{ keyword.name }}</span>
                <button
                    type="button"
                    class="grid size-6 shrink-0 cursor-pointer place-items-center rounded-full text-white/60 transition-colors hover:bg-white/15 hover:text-white focus-visible:outline-2 focus-visible:outline-madder motion-reduce:transition-none"
                    :aria-label="`Stop avoiding ${keyword.name}`"
                    @click="remove(keyword)"
                >
                    <X class="size-3.5" aria-hidden="true" />
                </button>
            </li>
        </TransitionGroup>
        <p
            v-else
            class="mt-5 rounded-xl border border-dashed border-white/15 px-4 py-5 text-sm leading-relaxed text-white/50"
        >
            Nothing avoided yet. Add a tag and every title carrying it drops out of your feed.
        </p>
    </section>
</template>
