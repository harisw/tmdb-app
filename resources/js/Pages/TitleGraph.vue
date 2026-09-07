<script setup>
import { Head, Link } from '@inertiajs/vue3'
import { ref, onMounted, onBeforeUnmount, shallowRef } from 'vue'
import { Maximize2, Minimize2 } from 'lucide-vue-next'
import AppLayout from '../Layouts/AppLayout.vue'
import LoadingSpinner from '../Components/LoadingSpinner.vue'
import TitleModal from '../Components/Modals/TitleModal.vue'
import EdgeModal from '../Components/Modals/EdgeModal.vue'
import useModal from '../Composables/useModal.js'
import { createForceGraph } from '../Composables/useForceGraph.js'

const props = defineProps({
    slug: { type: String, default: null },
})
const { openModal, closeModal, selectedItem, showModal } = useModal()
let titleRequestId = 0

const svgEl = ref(null)
const container = ref(null)
const loading = ref(false)
const error = ref(null)
const focusName = ref(null)
const isFullscreen = ref(false)
const selectedLink = shallowRef(null)
const showEdgeModal = ref(false)

const expanded = new Set()
let graph = null
let resizeObserver = null

function openEdge(link) {
    selectedLink.value = link
    showEdgeModal.value = true
    graph.pause()
}

function onCloseEdgeModal() {
    showEdgeModal.value = false
    graph.resume()
}

async function fetchNeighbours(slug) {
    const { data } = await window.axios.get('/graph/neighbors', { params: { title_slug: slug } })
    return data
}

async function loadInitial(slug) {
    loading.value = true
    error.value = null
    try {
        const data = await fetchNeighbours(slug)
        graph.load(data)
        expanded.add(slug)
        focusName.value = data.node.name
        syncUrl(slug)
    } catch (e) {
        error.value = e.response?.status === 404 ? 'Title not found.' : 'Could not load the graph.'
    } finally {
        loading.value = false
    }
}

async function expandNode(node) {
    if (expanded.has(node.slug)) return
    expanded.add(node.slug)
    focusName.value = node.name
    syncUrl(node.slug)
    try {
        graph.merge(await fetchNeighbours(node.slug))
    } catch (e) {
        expanded.delete(node.slug) // allow retry
    }
}

async function openTitle(node) {
    const token = ++titleRequestId
    graph.setBusy(node.id, true)
    try {
        const { data } = await window.axios.get(`/title/${node.id}`)
        if (token !== titleRequestId) return // a newer click superseded this one
        openModal(data.title)
        graph.pause()
    } catch (e) {
        // leave the graph as it was; the node stays interactive for a retry
    } finally {
        graph.setBusy(node.id, false)
    }
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
    if (showModal.value) return
    if (e.key !== 'f' && e.key !== 'F') return
    if (e.metaKey || e.ctrlKey || e.altKey) return
    const tag = document.activeElement?.tagName
    if (tag === 'INPUT' || tag === 'TEXTAREA' || document.activeElement?.isContentEditable) return
    e.preventDefault()
    toggleFullscreen()
}

onMounted(() => {
    graph = createForceGraph(svgEl.value, {
        onSelect: openTitle,
        onExpand: expandNode,
        onLinkClick: openEdge,
    })

    resizeObserver = new ResizeObserver(() => graph?.resize())
    resizeObserver.observe(container.value)

    document.addEventListener('fullscreenchange', onFullscreenChange)
    window.addEventListener('keydown', onKeydown)

    const initial = props.slug || new URL(window.location.href).searchParams.get('title_slug')
    if (initial) {
        loadInitial(initial)
    } else {
        error.value = 'Open a title and choose “Explore graph” to start.'
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
    <Head :title="focusName ? `Graph · ${focusName}` : 'Title Graph'" />
    <AppLayout>
        <div class="flex flex-col gap-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-xl md:text-2xl font-bold text-white">Similarity Graph</h1>
                    <p class="text-sm text-white/50">
                        {{ focusName ? `Exploring around “${focusName}”` : 'Click a title to see its details' }}
                    </p>
                </div>
                <div class="flex items-center gap-3 text-xs text-white/60">
                    <span class="flex items-center gap-1.5"
                        ><span class="size-2.5 rounded-full bg-amber-500"></span> Start</span
                    >
                    <span class="flex items-center gap-1.5"
                        ><span class="size-2.5 rounded-full bg-green-500"></span> Expanded</span
                    >
                    <span class="flex items-center gap-1.5"
                        ><span class="size-2.5 rounded-full bg-gray-500"></span> Unexplored</span
                    >
                </div>
            </div>

            <div
                ref="container"
                class="relative bg-licorice overflow-hidden"
                :class="
                    isFullscreen
                        ? 'w-screen h-screen'
                        : 'w-full h-[calc(100vh-14rem)] min-h-[420px] rounded-2xl ring-1 ring-white/10'
                "
            >
                <svg ref="svgEl" class="w-full h-full block touch-none"></svg>

                <button
                    type="button"
                    class="btn btn-sm btn-circle btn-neutral absolute top-3 right-3 z-10 bg-black/50 border-0 hover:bg-black/70"
                    :aria-label="isFullscreen ? 'Exit fullscreen' : 'Enter fullscreen'"
                    :title="`${isFullscreen ? 'Exit fullscreen' : 'Fullscreen'} (F)`"
                    @click="toggleFullscreen"
                >
                    <component :is="isFullscreen ? Minimize2 : Maximize2" :size="16" />
                </button>

                <div v-if="loading" class="absolute inset-0 flex items-center justify-center bg-licorice/40">
                    <LoadingSpinner text="Loading graph" :size="8" color="white" />
                </div>

                <div
                    v-else-if="error"
                    class="absolute inset-0 flex flex-col items-center justify-center gap-3 text-center px-6"
                >
                    <p class="text-white/70">{{ error }}</p>
                    <Link href="/" class="btn btn-sm btn-neutral">Back home</Link>
                </div>

                <p
                    v-if="!loading && !error"
                    class="pointer-events-none absolute bottom-3 inset-x-0 text-center text-[11px] text-white/35"
                >
                    Drag to reposition · scroll to zoom · click for details · double-click to expand · F for fullscreen
                </p>
            </div>
        </div>
        <TitleModal
            :show="showModal"
            :item="selectedItem"
            :to="isFullscreen ? container : 'body'"
            @close="onCloseModal"
        />
        <EdgeModal
            :show="showEdgeModal"
            :link="selectedLink"
            :to="isFullscreen ? container : 'body'"
            @close="onCloseEdgeModal"
            @view="openTitle"
            @expand="expandNode"
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
