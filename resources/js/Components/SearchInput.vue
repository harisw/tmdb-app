<template>
    <div class="relative w-full max-w-md">
        <label :for="inputId" class="sr-only">{{ placeholder }}</label>
        <input
            :id="inputId"
            ref="inputEl"
            v-model="query"
            type="search"
            :aria-label="placeholder"
            :placeholder="placeholder"
            :class="field({ open: showPanel, class: 'pl-10 pr-10 py-2' })"
            @input="onInput"
            @focus="onFocus"
            @keydown.escape="onEscape"
            @keydown.enter="searchNow"
        />

        <SearchIcon class="pointer-events-none absolute left-3 top-1/2 size-5 -translate-y-1/2 text-white/40" />

        <!-- <button
            v-if="query"
            type="button"
            class="absolute right-2.5 top-1/2 grid size-6 -translate-y-1/2 place-items-center rounded-full text-white/50 transition-colors hover:bg-white/10 hover:text-white focus:outline-none focus:ring-2 focus:ring-madder/60"
            aria-label="Clear search"
            @click="clear"
        >
            <XIcon class="size-4" />
        </button> -->

        <!-- results / status popover -->
        <div
            v-if="showPanel"
            class="absolute left-0 z-50 mt-2 w-full overflow-hidden rounded-field border border-white/10 bg-base-200 text-base-content shadow-xl"
        >
            <ul v-if="results.length" class="max-h-80 overflow-y-auto py-1">
                <li v-for="item in results" :key="item.id">
                    <button
                        type="button"
                        class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm transition-colors hover:bg-white/5 focus:bg-white/5 focus:outline-none"
                        @click="itemOnClick(item)"
                    >
                        <span class="truncate">{{ item.name }}</span>
                        <span v-if="releaseYear(item)" class="ml-auto shrink-0 text-xs text-white/40">
                            {{ releaseYear(item) }}
                        </span>
                    </button>
                </li>
                <li v-if="hasMore" class="border-t border-white/10">
                    <Link
                        :href="`${baseUrl}/search-all?q=${encodeURIComponent(query)}`"
                        class="block px-3 py-2 text-sm font-medium text-white underline decoration-madder decoration-2 underline-offset-4 transition-colors hover:bg-white/5"
                    >
                        See all results
                    </Link>
                </li>
            </ul>

            <p v-else-if="loading" class="flex items-center gap-2 px-3 py-3 text-sm text-base-content/70">
                <span
                    class="size-3.5 animate-spin rounded-full border-2 border-white/30 border-t-transparent"
                    aria-hidden="true"
                />
                Searching…
            </p>

            <p v-else-if="error" class="px-3 py-3 text-sm text-base-content/70">
                Search is unavailable right now. Try again in a moment.
            </p>

            <p v-else class="px-3 py-3 text-sm text-base-content/70">No titles match “{{ query }}”.</p>
        </div>
    </div>
</template>

<script setup>
import { computed, ref, useId, watch } from 'vue'
import { Link } from '@inertiajs/vue3'
import { Search as SearchIcon, X as XIcon } from 'lucide-vue-next'
import useBaseUrl from '../Composables/useBaseUrl.js'
import { field } from '../variants/field.js'

const props = defineProps({
    modelValue: { type: String, default: '' },
    paramName: { type: String, default: 'q' },
    debounce: { type: Number, default: 400 },
    pauseDebounce: { type: Number, default: 1000 },
    placeholder: { type: String, default: 'Search...' },
    minLength: { type: Number, default: 0 },
    replace: { type: Boolean, default: true },
    preserveState: { type: Boolean, default: false },
    route: { type: String, default: '' },
})

const emit = defineEmits(['update:modelValue', 'search', 'resultOnClick'])

const baseUrl = useBaseUrl()
const inputId = useId()

const inputEl = ref(null)
const query = ref(props.modelValue)
const loading = ref(false)
const error = ref(false)
const showResults = ref(false)
const results = ref([])
const hasMore = ref(false)
let timer = null
let lastSearched = null

const CHARACTER_EDITS = ['insertText', 'deleteContentBackward', 'deleteContentForward']

const showPanel = computed(() => showResults.value && query.value.length >= props.minLength)

function releaseYear(item) {
    const year = String(item.release_date ?? '').slice(0, 4)
    return /^\d{4}$/.test(year) ? year : null
}

async function doSearch(q) {
    if (props.minLength && q.length < props.minLength) {
        showResults.value = false
        results.value = []
        hasMore.value = false
        error.value = false
        emit('search', '')
        return
    }

    loading.value = true
    error.value = false
    showResults.value = true
    results.value = []
    hasMore.value = false

    const url = props.route || window.location.pathname

    try {
        const resp = await fetch(`${url}?${props.paramName}=${encodeURIComponent(q)}`)
        if (!resp.ok) {
            throw new Error(`Search request failed: ${resp.status}`)
        }
        const data = await resp.json()
        results.value = data.results ?? []
        hasMore.value = Boolean(data.hasMore)
    } catch {
        error.value = true
    } finally {
        loading.value = false
    }

    emit('search', q)
}

function debounceSearch(text, delay) {
    const q = text.trim()
    clearTimeout(timer)
    if (q === lastSearched) return
    timer = setTimeout(() => {
        lastSearched = q
        doSearch(q)
    }, delay)
}

function searchNow() {
    clearTimeout(timer)
    lastSearched = query.value.trim()
    doSearch(lastSearched)
}

function shouldSearch(text, inputType) {
    const onFirstWord = !/\S\s+\S/.test(text)
    const finishedWord = /\S\s$/.test(text)
    return onFirstWord || finishedWord || !CHARACTER_EDITS.includes(inputType)
}

function onInput(event) {
    emit('update:modelValue', query.value)
    debounceSearch(query.value, shouldSearch(query.value, event.inputType) ? props.debounce : props.pauseDebounce)
}

function onFocus() {
    if (results.value.length || error.value) showResults.value = true
}

function itemOnClick(title) {
    showResults.value = false
    emit('resultOnClick', title)
}

function onEscape() {
    if (showPanel.value) {
        showResults.value = false
        return
    }
    clear()
}

function clear() {
    clearTimeout(timer)
    lastSearched = null
    query.value = ''
    showResults.value = false
    results.value = []
    hasMore.value = false
    error.value = false
    emit('update:modelValue', '')
    emit('search', '')
}

function focus() {
    inputEl.value?.focus()
}

function searchFor(text) {
    query.value = text
    emit('update:modelValue', text)
    focus()
    searchNow()
}

defineExpose({ focus, searchFor })

watch(
    () => props.modelValue,
    (v) => {
        if (v !== query.value) query.value = v
    }
)
</script>

<style scoped></style>
