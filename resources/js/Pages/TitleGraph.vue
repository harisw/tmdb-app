<script setup>
import { Head } from '@inertiajs/vue3'
import { computed, onBeforeUnmount, onMounted, reactive, ref, shallowRef } from 'vue'
import { Maximize2, Minimize2, RotateCw, Scan } from 'lucide-vue-next'
import AppLayout from '../Layouts/AppLayout.vue'
import SearchInput from '../Components/SearchInput.vue'
import GraphInspector from '../Components/GraphInspector.vue'
import TitleModal from '../Components/Modals/TitleModal.vue'
import useModal from '../Composables/useModal.js'
import { createForceGraph, linkKey } from '../Composables/useForceGraph.js'
import { onPosterError } from '../utils/img.js'
import { button } from '../variants/button.js'

const props = defineProps({
    slug: { type: String, default: null },
    starts: { type: Array, default: () => [] },
})
const { openModal, closeModal, selectedItem, showModal } = useModal()
let titleRequestId = 0

const svgEl = ref(null)
const container = ref(null)
const canvas = ref(null)
const loading = ref(false)
const error = ref(null)
const lastSlug = ref(null)
const isFullscreen = ref(false)
const busyId = ref(null)
const searchQuery = ref('')

const known = reactive(new Map())
const linksByKey = reactive(new Map())
const discoveredVia = new Map()
const trailIds = ref([])
const rootId = ref(null)
const selected = shallowRef(null)

let graph = null
let resizeObserver = null

const root = computed(() => known.get(rootId.value) ?? null)
const trail = computed(() => trailIds.value.map((id) => known.get(id)).filter(Boolean))
const isEmpty = computed(() => !loading.value && !error.value && !rootId.value)

const selection = computed(() => {
    if (!selected.value) return null
    if (selected.value.kind === 'link') {
        const link = linksByKey.get(selected.value.key)
        if (!link) return null
        return { kind: 'link', link, source: known.get(link.source), target: known.get(link.target) }
    }
    const node = known.get(selected.value.id)
    if (!node) return null
    const via = discoveredVia.get(node.id)
    const relation = via ? { link: via, other: known.get(via.source === node.id ? via.target : via.source) } : null
    return { kind: 'node', node, relation, link: relation?.link ?? null, expanded: trailIds.value.includes(node.id) }
})

function resetState() {
    known.clear()
    linksByKey.clear()
    discoveredVia.clear()
    trailIds.value = []
    rootId.value = null
    selected.value = null
}

function absorb(data) {
    if (!known.has(data.node.id)) known.set(data.node.id, data.node)
    for (const link of data.links) {
        const key = linkKey(link.source, link.target)
        linksByKey.set(key, { ...link, key })
        const neighbourId = link.source === data.node.id ? link.target : link.source
        if (!known.has(neighbourId)) {
            discoveredVia.set(neighbourId, { ...link, key })
        }
    }
    for (const node of data.nodes) {
        if (!known.has(node.id)) known.set(node.id, node)
    }
    if (!trailIds.value.includes(data.node.id)) trailIds.value = [...trailIds.value, data.node.id]
}

async function fetchNeighbours(slug) {
    const { data } = await window.axios.get('/graph/neighbors', { params: { title_slug: slug } })
    return data
}

async function loadInitial(slug) {
    loading.value = true
    error.value = null
    lastSlug.value = slug
    try {
        const data = await fetchNeighbours(slug)
        resetState()
        absorb(data)
        rootId.value = data.node.id
        graph.load(data)
        syncUrl(slug)
    } catch (e) {
        error.value = e.response?.status === 404 ? 'missing' : 'failed'
    } finally {
        loading.value = false
    }
}

async function expandNode(node) {
    if (trailIds.value.includes(node.id) || busyId.value === node.id) return
    busyId.value = node.id
    graph.setBusy(node.id, true)
    try {
        const data = await fetchNeighbours(node.slug)
        absorb(data)
        graph.merge(data)
        syncUrl(node.slug)
    } catch {
        // the node stays unexpanded so it can be retried
    } finally {
        graph.setBusy(node.id, false)
        busyId.value = null
    }
}

async function openTitle(node) {
    const token = ++titleRequestId
    busyId.value = node.id
    graph.setBusy(node.id, true)
    try {
        const { data } = await window.axios.get(`/title/${node.id}`)
        if (token !== titleRequestId) return
        openModal(data.title)
        graph.pause()
    } catch {
        // leave the graph as it was; the node stays interactive for a retry
    } finally {
        graph.setBusy(node.id, false)
        if (token === titleRequestId) busyId.value = null
    }
}

function selectNode(id) {
    selected.value = { kind: 'node', id }
    graph.select(id)
}

function focusNode(id) {
    selectNode(id)
    graph.focusOn(id)
}

function selectLink(link) {
    selected.value = { kind: 'link', key: link.key }
    graph.selectLink(link.key)
}

function fitToView() {
    graph.fitToView()
}

function clearSelection() {
    selected.value = null
    graph.select(null)
}

function startFrom(title) {
    searchQuery.value = ''
    loadInitial(title.slug)
}

function onCloseModal() {
    closeModal()
    graph.resume()
}

// Reflect the focused title in the URL so the view is shareable and can land
// on any title, matching the ?title_slug= entry point.
function syncUrl(slug) {
    const url = new URL(window.location.href)
    url.searchParams.set('title_slug', slug)
    window.history.replaceState(window.history.state, '', url)
}

function toggleFullscreen() {
    if (document.fullscreenElement) {
        document.exitFullscreen()
    } else {
        container.value?.requestFullscreen?.().catch(() => {})
    }
}

// Kept in sync via the event so Esc / browser chrome exits are reflected too.
function onFullscreenChange() {
    isFullscreen.value = document.fullscreenElement === container.value
}

function onKeydown(e) {
    if (showModal.value || e.metaKey || e.ctrlKey || e.altKey) return
    const tag = document.activeElement?.tagName
    if (tag === 'INPUT' || tag === 'TEXTAREA' || document.activeElement?.isContentEditable) return
    if (e.key === 'Escape' && selected.value) {
        clearSelection()
        return
    }
    if (e.key !== 'f' && e.key !== 'F') return
    e.preventDefault()
    toggleFullscreen()
}

onMounted(() => {
    graph = createForceGraph(svgEl.value, {
        onSelect: (node) => selectNode(node.id),
        onExpand: expandNode,
        onLinkClick: selectLink,
        onBackground: clearSelection,
    })

    resizeObserver = new ResizeObserver(() => graph?.resize())
    resizeObserver.observe(canvas.value)

    document.addEventListener('fullscreenchange', onFullscreenChange)
    window.addEventListener('keydown', onKeydown)

    const initial = props.slug || new URL(window.location.href).searchParams.get('title_slug')
    if (initial) {
        loadInitial(initial)
    }
})

onBeforeUnmount(() => {
    resizeObserver?.disconnect()
    document.removeEventListener('fullscreenchange', onFullscreenChange)
    window.removeEventListener('keydown', onKeydown)
    graph?.destroy()
})
</script>

<template>
    <Head :title="root ? `Graph · ${root.name}` : 'Graph'" />
    <AppLayout>
        <div class="flex flex-col gap-5">
            <header class="flex flex-wrap items-end justify-between gap-x-6 gap-y-4">
                <div class="min-w-0">
                    <h1 class="truncate text-2xl font-semibold text-white">
                        {{ root ? root.name : 'Explore the graph' }}
                    </h1>
                    <p class="mt-1 text-sm tabular-nums text-white/60">
                        <template v-if="root">
                            {{ known.size }} titles · {{ linksByKey.size }} links · {{ trailIds.length }} expanded
                        </template>
                        <template v-else>Every line between two titles comes with its reasons.</template>
                    </p>
                </div>
                <div class="flex w-full items-center gap-2 sm:w-auto">
                    <SearchInput
                        v-model="searchQuery"
                        :debounce="400"
                        :min-length="2"
                        placeholder="Start from another title"
                        :preserve-state="true"
                        :replace="false"
                        route="search"
                        class="sm:w-80"
                        @result-on-click="startFrom"
                    />
                </div>
            </header>

            <div
                ref="container"
                class="grid gap-3 lg:grid-cols-[minmax(0,1fr)_21rem]"
                :class="isFullscreen ? 'h-screen w-screen bg-licorice p-3 lg:grid-rows-1' : ''"
            >
                <div
                    ref="canvas"
                    class="relative overflow-hidden rounded-2xl bg-base-200/60"
                    :class="isFullscreen ? 'h-full min-h-[50vh]' : 'h-[60svh] min-h-[420px] lg:h-[calc(100svh-15rem)]'"
                >
                    <svg
                        ref="svgEl"
                        class="block size-full touch-none"
                        role="group"
                        :aria-label="root ? `Titles connected to ${root.name}` : 'Similarity graph'"
                    ></svg>

                    <div class="absolute top-3 right-3 flex gap-1.5">
                        <button
                            v-if="root"
                            type="button"
                            :class="button({ intent: 'ghost', size: 'sm', circle: true, class: 'bg-licorice/80' })"
                            aria-label="Fit graph to view"
                            title="Fit to view"
                            @click="fitToView"
                        >
                            <Scan :size="16" />
                        </button>
                        <button
                            type="button"
                            :class="button({ intent: 'ghost', size: 'sm', circle: true, class: 'bg-licorice/80' })"
                            :aria-label="isFullscreen ? 'Exit fullscreen' : 'Enter fullscreen'"
                            :title="`${isFullscreen ? 'Exit fullscreen' : 'Fullscreen'} (F)`"
                            @click="toggleFullscreen"
                        >
                            <component :is="isFullscreen ? Minimize2 : Maximize2" :size="16" />
                        </button>
                    </div>

                    <div
                        v-if="loading"
                        class="absolute inset-0 grid place-items-center bg-licorice/50"
                        role="status"
                        aria-label="Loading graph"
                    >
                        <div class="relative size-64" aria-hidden="true">
                            <span class="absolute inset-[39%] animate-pulse rounded-full bg-white/10"></span>
                            <span
                                v-for="angle in [0, 51, 103, 154, 206, 257, 309]"
                                :key="angle"
                                class="absolute left-1/2 top-1/2 size-11 animate-pulse rounded-full bg-white/[0.06]"
                                :style="{
                                    transform: `translate(-50%, -50%) rotate(${angle}deg) translateY(-7.5rem) rotate(-${angle}deg)`,
                                }"
                            ></span>
                        </div>
                    </div>

                    <div
                        v-else-if="error"
                        class="absolute inset-0 flex flex-col items-center justify-center gap-4 px-6 text-center"
                    >
                        <p class="max-w-sm text-base text-white/80">
                            {{
                                error === 'missing'
                                    ? 'That title isn’t in the graph. Search for another one above.'
                                    : 'The graph didn’t load. Check your connection and try again.'
                            }}
                        </p>
                        <button
                            v-if="error === 'failed'"
                            type="button"
                            class="flex h-9 cursor-pointer items-center gap-2 rounded-full bg-white px-4 text-sm font-semibold text-black hover:bg-white/85"
                            @click="loadInitial(lastSlug)"
                        >
                            <RotateCw class="size-4" aria-hidden="true" />
                            Try again
                        </button>
                    </div>

                    <div
                        v-else-if="isEmpty"
                        class="absolute inset-0 flex flex-col items-center justify-center gap-6 px-6 text-center"
                    >
                        <div>
                            <h2 class="text-xl font-semibold text-white">Pick a title to start from</h2>
                            <p class="mt-1.5 text-sm text-white/60">
                                {{
                                    starts.length
                                        ? 'Or search for any title above.'
                                        : 'Search for any title above, or open one and choose “Explore more”.'
                                }}
                            </p>
                        </div>
                        <ul v-if="starts.length" class="flex flex-wrap justify-center gap-4">
                            <li v-for="start in starts" :key="start.id">
                                <button
                                    type="button"
                                    class="group flex w-24 cursor-pointer flex-col items-center gap-2 rounded-xl p-1 focus-visible:outline-2 focus-visible:outline-glow-red"
                                    @click="loadInitial(start.slug)"
                                >
                                    <img
                                        :src="start.poster_path"
                                        :alt="''"
                                        width="80"
                                        height="120"
                                        class="aspect-[2/3] w-20 rounded-lg object-cover transition-transform duration-200 group-hover:-translate-y-1 motion-reduce:transition-none"
                                        @error="onPosterError"
                                    />
                                    <span class="line-clamp-2 text-xs font-medium text-white/85">{{ start.name }}</span>
                                </button>
                            </li>
                        </ul>
                    </div>
                </div>

                <GraphInspector
                    :class="isFullscreen ? 'min-h-0' : 'lg:max-h-[calc(100svh-15rem)]'"
                    :selection="selection"
                    :trail="trail"
                    :root-id="rootId"
                    :busy-id="busyId"
                    @select="focusNode"
                    @expand="expandNode"
                    @details="openTitle"
                    @clear="clearSelection"
                />
            </div>
        </div>
        <TitleModal
            :show="showModal"
            :item="selectedItem"
            :to="isFullscreen ? container : 'body'"
            @close="onCloseModal"
        />
    </AppLayout>
</template>

<!-- Deliberately not scoped: scoped styles match on a data-v attribute stamped
     onto Vue-rendered elements, but every element inside the SVG is created by
     d3, so a scoped rule would silently never apply. -->
<style>
.node-busy {
    animation: ring-spin 1s linear infinite;
}

@keyframes ring-spin {
    to {
        stroke-dashoffset: -16;
    }
}
</style>
