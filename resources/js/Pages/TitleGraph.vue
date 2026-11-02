<script setup>
import { Head } from '@inertiajs/vue3'
import { computed, onBeforeUnmount, onMounted, ref, shallowRef, watch } from 'vue'
import { Maximize2, Minimize2, RotateCw, Scan } from 'lucide-vue-next'
import AppLayout from '../Layouts/AppLayout.vue'
import SearchInput from '../Components/SearchInput.vue'
import GraphInspector from '../Components/GraphInspector.vue'
import GraphLinkTooltip from '../Components/GraphLinkTooltip.vue'
import GraphLoading from '../Components/GraphLoading.vue'
import GraphReasonFilter from '../Components/GraphReasonFilter.vue'
import TitleModal from '../Components/Modals/TitleModal.vue'
import useModal from '../Composables/useModal.js'
import { createForceGraph } from '../Composables/useForceGraph.js'
import { useFullscreen } from '../Composables/useFullscreen.js'
import { useGraphExploration } from '../Composables/useGraphExploration.js'
import { createStarfield } from '../Composables/graph/starfield.js'
import { syncTrailUrl } from '../Composables/graph/trailUrl.js'
import { REASON_GROUPS, linkHeadline, linkMatchesGroups } from '../constants/reasons.js'
import { onPosterError } from '../utils/img.js'
import { button } from '../variants/button.js'

const props = defineProps({
    slug: { type: String, default: null },
    trail: { type: Array, default: () => [] },
    starts: { type: Array, default: () => [] },
})
const { openModal, closeModal, selectedItem, showModal } = useModal()
const exploration = useGraphExploration()
const { titles, links, root, rootId, trail: trailTitles, routeKeys } = exploration

const svgEl = ref(null)
const skyEl = ref(null)
const container = ref(null)
const canvas = ref(null)
const loading = ref(false)
const error = ref(null)
const lastRequest = ref(null)
const busyId = ref(null)
const searchQuery = ref('')
const selected = shallowRef(null)
const hoveredLink = shallowRef(null)
const activeReasonGroups = ref(new Set(REASON_GROUPS.map((group) => group.id)))

const { isFullscreen, toggle: toggleFullscreen } = useFullscreen(container)

let graph = null
let starfield = null
let resizeObserver = null
let titleRequestId = 0

const linkList = computed(() => [...links.values()])
const selection = computed(() => exploration.describe(selected.value))
const isEmpty = computed(() => !loading.value && !error.value && !rootId.value)
const hoveredReason = computed(() =>
    hoveredLink.value ? linkHeadline(hoveredLink.value.link, activeReasonGroups.value) : null
)

watch(activeReasonGroups, (active) => graph?.setLinkFilter((link) => linkMatchesGroups(link, active)))
watch(routeKeys, (keys) => graph?.setRoute(keys))

async function fetchNeighbours(slug) {
    const { data } = await window.axios.get('/graph/neighbors', { params: { title_slug: slug } })
    return data
}

async function loadInitial(rootSlug, trailSlugs = []) {
    loading.value = true
    error.value = null
    lastRequest.value = { rootSlug, trailSlugs }
    try {
        const [start, ...steps] = await Promise.allSettled([rootSlug, ...trailSlugs].map(fetchNeighbours))
        if (start.status === 'rejected') throw start.reason

        const expansions = steps.filter((step) => step.status === 'fulfilled').map((step) => step.value)
        selected.value = null
        hoveredLink.value = null
        exploration.reset()
        exploration.absorb(start.value)
        graph.load(start.value)
        for (const expansion of expansions) {
            exploration.absorb(expansion)
            graph.merge(expansion)
        }
        if (expansions.length) graph.settleAndFit()
        syncTrailUrl(trailTitles.value)
    } catch (e) {
        error.value = e.response?.status === 404 ? 'missing' : 'failed'
    } finally {
        loading.value = false
    }
}

async function withBusyNode(node, work) {
    busyId.value = node.id
    graph.setBusy(node.id, true)
    try {
        return await work()
    } finally {
        graph.setBusy(node.id, false)
        if (busyId.value === node.id) busyId.value = null
    }
}

async function expandNode(node) {
    if (exploration.isExpanded(node.id) || busyId.value === node.id) return
    await withBusyNode(node, async () => {
        const data = await fetchNeighbours(node.slug)
        exploration.absorb(data)
        graph.merge(data)
        syncTrailUrl(trailTitles.value)
    }).catch(() => {})
}

async function openTitle(node) {
    const token = ++titleRequestId
    await withBusyNode(node, async () => {
        const { data } = await window.axios.get(`/title/${node.id}`)
        if (token !== titleRequestId) return
        openModal(data.title)
        graph.pause()
        starfield.pause()
    }).catch(() => {})
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

function retry() {
    loadInitial(lastRequest.value.rootSlug, lastRequest.value.trailSlugs)
}

function onCloseModal() {
    closeModal()
    graph.resume()
    starfield.resume()
}

function onLinkHover(link, point) {
    hoveredLink.value = link ? { link, x: point[0], y: point[1] } : null
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
    starfield = createStarfield(skyEl.value)
    graph = createForceGraph(svgEl.value, {
        onSelect: (node) => selectNode(node.id),
        onExpand: expandNode,
        onLinkClick: selectLink,
        onBackground: clearSelection,
        onZoom: (transform) => starfield.draw(transform),
        onLinkHover,
    })

    resizeObserver = new ResizeObserver(() => {
        starfield.resize()
        graph.resize()
    })
    resizeObserver.observe(canvas.value)
    window.addEventListener('keydown', onKeydown)

    if (props.slug) loadInitial(props.slug, props.trail)
})

onBeforeUnmount(() => {
    resizeObserver?.disconnect()
    window.removeEventListener('keydown', onKeydown)
    graph?.destroy()
    starfield?.destroy()
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
                            {{ titles.size }} titles · {{ links.size }} links · {{ trailTitles.length }} expanded
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
                    <canvas
                        ref="skyEl"
                        class="pointer-events-none absolute inset-0 size-full"
                        aria-hidden="true"
                    ></canvas>
                    <svg
                        ref="svgEl"
                        class="relative block size-full touch-none"
                        role="group"
                        :aria-label="root ? `Titles connected to ${root.name}` : 'Similarity graph'"
                    ></svg>

                    <GraphLinkTooltip
                        v-if="hoveredReason"
                        :reason="hoveredReason"
                        :x="hoveredLink.x"
                        :y="hoveredLink.y"
                        :bounds-width="canvas?.clientWidth ?? 0"
                    />

                    <GraphReasonFilter
                        v-if="root"
                        v-model="activeReasonGroups"
                        :links="linkList"
                        class="absolute top-3 right-24 left-3"
                    />

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

                    <GraphLoading v-if="loading" />

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
                            @click="retry"
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
                    :trail="trailTitles"
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
