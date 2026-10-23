<script setup>
import { computed, ref, watch } from 'vue'
import { Link } from '@inertiajs/vue3'
import { ArrowUpRight, Shuffle, X } from 'lucide-vue-next'
import { layoutConstellation } from '../Composables/useConstellationLayout.js'
import { headlineReasons, reasonMeta } from '../constants/reasons.js'
import { POSTER_PLACEHOLDER } from '../utils/img.js'
import { button } from '../variants/button.js'

const props = defineProps({
    constellation: { type: Object, default: null },
    shuffling: { type: Boolean, default: false },
    busyId: { type: Number, default: null },
})

const emit = defineEmits(['open', 'shuffle'])

const LABEL_MAX = 22

const hoveredId = ref(null)
const pinnedId = ref(null)
const hoveredLink = ref(null)

const layout = computed(() => (props.constellation ? layoutConstellation(props.constellation) : null))
const nodeById = computed(() => new Map(layout.value?.nodes.map((node) => [node.id, node]) ?? []))
const seed = computed(() => nodeById.value.get(props.constellation?.seed))

const neighbourIds = computed(() => {
    const map = new Map()
    for (const link of layout.value?.links ?? []) {
        for (const [from, to] of [
            [link.source.id, link.target.id],
            [link.target.id, link.source.id],
        ]) {
            if (!map.has(from)) map.set(from, new Set())
            map.get(from).add(to)
        }
    }
    return map
})

const activeId = computed(() => hoveredId.value ?? pinnedId.value)
const activeNode = computed(() => nodeById.value.get(activeId.value) ?? null)

watch(
    () => props.constellation?.seed,
    () => {
        hoveredId.value = null
        pinnedId.value = null
        hoveredLink.value = null
    }
)

function isLit(node) {
    if (hoveredLink.value) {
        return node.id === hoveredLink.value.source.id || node.id === hoveredLink.value.target.id
    }
    return !activeId.value || node.id === activeId.value || neighbourIds.value.get(activeId.value)?.has(node.id)
}

function isLinkLit(link) {
    if (hoveredLink.value) return link.key === hoveredLink.value.key
    return activeId.value !== null && (link.source.id === activeId.value || link.target.id === activeId.value)
}

function relationFor(node) {
    if (!node || node.id === seed.value?.id) return null
    const touching = layout.value.links.filter((link) => link.source.id === node.id || link.target.id === node.id)
    const toSeed = touching.find((link) => link.source.id === seed.value.id || link.target.id === seed.value.id)
    const strongestInward = touching
        .filter((link) => (link.source.id === node.id ? link.target : link.source).hop < node.hop)
        .sort((a, b) => b.score - a.score)[0]
    const link = toSeed ?? strongestInward ?? touching[0]
    return link ? { link, other: link.source.id === node.id ? link.target : link.source } : null
}

const inspector = computed(() => {
    if (hoveredLink.value) {
        return {
            kind: 'link',
            link: hoveredLink.value,
            reasons: headlineReasons(hoveredLink.value.reasons, 3),
        }
    }
    if (!activeNode.value) return null
    const relation = relationFor(activeNode.value)
    return {
        kind: 'node',
        node: activeNode.value,
        relation,
        reasons: relation ? headlineReasons(relation.link.reasons, 3) : [],
        connections: neighbourIds.value.get(activeNode.value.id)?.size ?? 0,
    }
})

function label(name) {
    return name.length > LABEL_MAX ? `${name.slice(0, LABEL_MAX - 1)}…` : name
}

function showLabel(node) {
    return node.id === seed.value?.id || node.id === activeId.value
}

function togglePin(node) {
    pinnedId.value = pinnedId.value === node.id ? null : node.id
}

function nodeMeta(node) {
    return [
        node.year,
        node.type === 'movie' ? 'Film' : 'TV',
        node.rating ? `Rated ${Number(node.rating).toFixed(1)}` : null,
    ]
        .filter(Boolean)
        .join(' · ')
}

function nodeStyle(node, index) {
    const entrance = node.hop === 0 ? 0 : node.hop === 1 ? 140 + index * 35 : 420 + index * 25
    return {
        '--in-delay': `${entrance}ms`,
        '--drift-delay': `${-((node.id * 7919) % 6000)}ms`,
        '--drift-duration': `${6 + ((node.id * 31) % 4)}s`,
    }
}

function linkStyle(link) {
    const hop = Math.max(link.source.hop, link.target.hop)
    return { '--in-delay': `${hop === 1 ? 80 : 360}ms` }
}

function onPosterError(event) {
    if (event.target.getAttribute('href') !== POSTER_PLACEHOLDER) {
        event.target.setAttribute('href', POSTER_PLACEHOLDER)
    }
}

function onEscape() {
    pinnedId.value = null
    hoveredLink.value = null
}
</script>

<template>
    <figure class="relative w-full" aria-labelledby="constellation-caption" @keydown.escape="onEscape">
        <figcaption id="constellation-caption" class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2">
            <p class="min-w-0 text-sm text-white/60">
                <template v-if="seed">
                    Threads from <span class="font-semibold text-white">{{ seed.name }}</span
                    >, one of the most popular titles on Chill.
                </template>
                <span v-else class="inline-block h-4 w-72 animate-pulse rounded bg-white/10"></span>
            </p>
            <div v-if="seed" class="flex shrink-0 items-center gap-1">
                <button
                    v-if="constellation.seedCount > 1"
                    type="button"
                    :disabled="shuffling"
                    :class="button({ intent: 'ghost', size: 'xs', class: 'gap-1.5 px-2.5 text-white/75' })"
                    @click="emit('shuffle')"
                >
                    <Shuffle
                        class="size-3.5"
                        :class="{ 'animate-spin motion-reduce:animate-none': shuffling }"
                        aria-hidden="true"
                    />
                    Another start
                </button>
                <Link
                    :href="`/graph?title_slug=${seed.slug}`"
                    :class="button({ intent: 'ghost', size: 'xs', class: 'gap-1 px-2.5 text-white/75' })"
                >
                    Open the full graph
                    <ArrowUpRight class="size-3.5" aria-hidden="true" />
                </Link>
            </div>
        </figcaption>

        <div class="relative mt-3 aspect-[4/3] w-full max-h-[min(68vh,640px)]">
            <div v-if="!layout" class="absolute inset-0 grid place-items-center" aria-hidden="true">
                <div class="relative size-72">
                    <span class="absolute inset-[38%] animate-pulse rounded-full bg-white/10"></span>
                    <span
                        v-for="angle in [0, 60, 120, 180, 240, 300]"
                        :key="angle"
                        class="absolute left-1/2 top-1/2 size-12 -translate-x-1/2 -translate-y-1/2 animate-pulse rounded-full bg-white/5"
                        :style="{ transform: `rotate(${angle}deg) translateY(-8.5rem) rotate(-${angle}deg)` }"
                    ></span>
                </div>
            </div>

            <svg
                v-else
                :key="constellation.seed"
                :viewBox="layout.viewBox"
                preserveAspectRatio="xMidYMid meet"
                class="constellation size-full overflow-visible transition-opacity duration-300"
                :class="{ 'opacity-40': shuffling }"
                role="group"
                :aria-label="`Titles connected to ${seed.name}`"
            >
                <defs>
                    <clipPath id="constellation-poster" clipPathUnits="objectBoundingBox">
                        <circle cx="0.5" cy="0.5" r="0.5" />
                    </clipPath>
                </defs>

                <g>
                    <g v-for="link in layout.links" :key="link.key">
                        <line
                            :x1="link.source.x"
                            :y1="link.source.y"
                            :x2="link.target.x"
                            :y2="link.target.y"
                            pathLength="1"
                            class="constellation-link transition-[stroke,opacity] duration-300"
                            :class="
                                isLinkLit(link)
                                    ? 'stroke-glow-red opacity-90'
                                    : activeId || hoveredLink
                                      ? 'stroke-white opacity-[0.06]'
                                      : 'stroke-white opacity-20'
                            "
                            :stroke-width="0.75 + link.score * 1.75"
                            :style="linkStyle(link)"
                        />
                        <line
                            :x1="link.source.x"
                            :y1="link.source.y"
                            :x2="link.target.x"
                            :y2="link.target.y"
                            stroke="transparent"
                            stroke-width="14"
                            class="cursor-help"
                            @mouseenter="hoveredLink = link"
                            @mouseleave="hoveredLink = null"
                        />
                    </g>
                </g>

                <g
                    v-for="(node, index) in layout.nodes"
                    :key="node.id"
                    :transform="`translate(${node.x} ${node.y})`"
                    role="button"
                    tabindex="0"
                    :aria-label="`${node.name}, ${neighbourIds.get(node.id)?.size ?? 0} connections`"
                    :aria-pressed="pinnedId === node.id"
                    class="group cursor-pointer outline-none transition-opacity duration-300"
                    :class="isLit(node) ? 'opacity-100' : 'opacity-25'"
                    @mouseenter="hoveredId = node.id"
                    @mouseleave="hoveredId = null"
                    @focus="hoveredId = node.id"
                    @blur="hoveredId = null"
                    @click="togglePin(node)"
                    @keydown.enter.prevent="togglePin(node)"
                    @keydown.space.prevent="togglePin(node)"
                >
                    <g class="constellation-node" :style="nodeStyle(node, index)">
                        <circle
                            :r="node.r + 6"
                            class="fill-none stroke-glow-red opacity-0 transition-opacity group-focus-visible:opacity-100"
                            stroke-width="2"
                        />
                        <circle
                            :r="node.r + 2"
                            class="fill-licorice transition-[stroke] duration-300"
                            :class="
                                node.hop === 0 || pinnedId === node.id || node.id === activeId
                                    ? 'stroke-glow-red'
                                    : 'stroke-white/25'
                            "
                            :stroke-width="node.hop === 0 ? 3 : 1.5"
                        />
                        <image
                            :href="node.poster_path"
                            :x="-node.r"
                            :y="-node.r"
                            :width="node.r * 2"
                            :height="node.r * 2"
                            preserveAspectRatio="xMidYMid slice"
                            clip-path="url(#constellation-poster)"
                            :class="{ 'animate-pulse': busyId === node.id }"
                            @error="onPosterError"
                        />
                        <text
                            v-if="showLabel(node)"
                            :y="node.r + 20"
                            text-anchor="middle"
                            class="pointer-events-none fill-white font-semibold [paint-order:stroke] stroke-licorice"
                            :class="node.hop === 0 ? 'text-[15px]' : 'text-[13px]'"
                            stroke-width="4"
                            stroke-linejoin="round"
                        >
                            {{ label(node.name) }}
                        </text>
                    </g>
                </g>
            </svg>

            <aside
                v-if="layout"
                class="absolute bottom-0 left-0 w-[19rem] max-w-full rounded-xl border border-white/10 bg-base-200/95 p-4 shadow-[0_16px_40px_-12px_rgb(0_0_0/0.7)]"
                aria-live="polite"
            >
                <template v-if="!inspector">
                    <p class="text-sm leading-relaxed text-white/60">
                        Every line is a reason. Hover a poster to see why it's here, click to pin it.
                    </p>
                </template>

                <template v-else-if="inspector.kind === 'link'">
                    <p class="text-sm font-semibold leading-snug text-white">
                        {{ inspector.link.source.name }}
                        <span class="font-normal text-white/45">and</span>
                        {{ inspector.link.target.name }}
                    </p>
                    <p class="mt-0.5 text-xs tabular-nums text-white/50">
                        {{ Math.round(inspector.link.score * 100) }}% match
                    </p>
                </template>

                <template v-else>
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-white">{{ inspector.node.name }}</p>
                            <p class="mt-0.5 text-xs tabular-nums text-white/50">{{ nodeMeta(inspector.node) }}</p>
                        </div>
                        <button
                            v-if="pinnedId === inspector.node.id"
                            type="button"
                            aria-label="Unpin"
                            class="grid size-6 shrink-0 cursor-pointer place-items-center rounded-full text-white/50 transition-colors hover:bg-white/10 hover:text-white"
                            @click="pinnedId = null"
                        >
                            <X class="size-3.5" aria-hidden="true" />
                        </button>
                    </div>
                    <p v-if="inspector.relation" class="mt-3 text-xs text-white/55">
                        Linked to
                        <span class="font-medium text-white/85">{{ inspector.relation.other.name }}</span>
                        · {{ Math.round(inspector.relation.link.score * 100) }}% match
                    </p>
                    <p v-else class="mt-3 text-xs text-white/55">
                        The starting point. Connected to {{ inspector.connections }} titles here.
                    </p>
                </template>

                <ul v-if="inspector?.reasons?.length" class="mt-2 flex flex-col gap-1.5">
                    <li
                        v-for="reason in inspector.reasons"
                        :key="reason.type"
                        class="flex items-start gap-2 text-xs leading-relaxed text-white/75"
                    >
                        <component
                            :is="reasonMeta(reason.type).icon"
                            class="mt-0.5 size-3.5 shrink-0 text-tea-green"
                            aria-hidden="true"
                        />
                        <span>
                            <span class="text-white/50">{{ reasonMeta(reason.type).label }}:</span>
                            {{ reason.values.slice(0, 3).join(', ') }}
                        </span>
                    </li>
                </ul>

                <div
                    v-if="inspector?.kind === 'node'"
                    class="mt-4 flex items-center gap-2 border-t border-white/10 pt-3"
                >
                    <template v-if="pinnedId === inspector.node.id">
                        <button
                            type="button"
                            :disabled="busyId === inspector.node.id"
                            :class="button({ intent: 'primary', size: 'xs', class: 'px-3' })"
                            @click="emit('open', inspector.node)"
                        >
                            Details
                        </button>
                        <Link
                            :href="`/graph?title_slug=${inspector.node.slug}`"
                            :class="button({ intent: 'ghost', size: 'xs', class: 'gap-1 px-2.5 text-white/75' })"
                        >
                            Explore from here
                            <ArrowUpRight class="size-3.5" aria-hidden="true" />
                        </Link>
                    </template>
                    <p v-else class="text-xs text-white/45">Click to pin and see options.</p>
                </div>
            </aside>
        </div>
    </figure>
</template>

<style scoped>
.constellation-node {
    transform-box: fill-box;
    transform-origin: center;
    animation:
        constellation-node-in 700ms cubic-bezier(0.16, 1, 0.3, 1) var(--in-delay) both,
        constellation-drift var(--drift-duration) ease-in-out var(--drift-delay) infinite alternate;
}

.constellation-link {
    stroke-dasharray: 1;
    animation: constellation-link-in 900ms cubic-bezier(0.16, 1, 0.3, 1) var(--in-delay) both;
}

@keyframes constellation-node-in {
    from {
        opacity: 0;
        scale: 0.6;
    }
}

@keyframes constellation-drift {
    to {
        translate: 0 -4px;
    }
}

@keyframes constellation-link-in {
    from {
        stroke-dashoffset: 1;
    }
}

@media (prefers-reduced-motion: reduce) {
    .constellation-node,
    .constellation-link {
        animation: none;
    }
}
</style>
