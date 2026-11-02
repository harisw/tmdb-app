<script setup>
import { computed } from 'vue'
import { ArrowLeftRight, X } from 'lucide-vue-next'
import { reasonMeta } from '../constants/reasons.js'
import { onPosterError } from '../utils/img.js'
import { button } from '../variants/button.js'

const props = defineProps({
    selection: { type: Object, default: null },
    trail: { type: Array, required: true },
    rootId: { type: Number, default: null },
    busyId: { type: Number, default: null },
})

const emit = defineEmits(['select', 'expand', 'details', 'clear'])

const reasons = computed(() => props.selection?.link?.reasons ?? [])
const topWeight = computed(() => Math.max(...reasons.value.map((reason) => reason.weight), 1))

function meta(node) {
    return [
        node.year,
        node.type === 'movie' ? 'Film' : 'TV',
        node.rating ? `Rated ${Number(node.rating).toFixed(1)}` : null,
    ]
        .filter(Boolean)
        .join(' · ')
}

function strength(score) {
    if (score >= 0.6) return 'strong'
    if (score >= 0.4) return 'moderate'
    return 'weak'
}

function barWidth(weight) {
    return `${Math.max(4, Math.min(100, (weight / topWeight.value) * 100))}%`
}
</script>

<template>
    <aside class="flex min-h-0 flex-col overflow-y-auto rounded-2xl bg-base-200 p-5" aria-live="polite">
        <template v-if="!selection">
            <h2 class="text-base font-semibold text-white">Your trail</h2>
            <ol v-if="trail.length" class="mt-3 flex flex-col gap-1">
                <li v-for="node in trail" :key="node.id">
                    <button
                        type="button"
                        class="flex w-full cursor-pointer items-center gap-3 rounded-lg p-1.5 text-left transition-colors hover:bg-white/[0.06] focus-visible:outline-2 focus-visible:outline-glow-red"
                        @click="emit('select', node.id)"
                    >
                        <img
                            :src="node.poster_path"
                            :alt="''"
                            width="32"
                            height="48"
                            class="aspect-[2/3] w-8 shrink-0 rounded object-cover ring-offset-2 ring-offset-base-200"
                            :class="node.id === rootId ? 'ring-2 ring-glow-red' : ''"
                            @error="onPosterError"
                        />
                        <span class="min-w-0">
                            <span class="block truncate text-sm font-medium text-white">{{ node.name }}</span>
                            <span class="block text-xs text-white/50">{{ meta(node) }}</span>
                        </span>
                    </button>
                </li>
            </ol>
            <p v-else class="mt-2 text-sm leading-relaxed text-white/60">
                Titles you expand collect here, so you can jump back to any of them.
            </p>

            <h2 class="mt-8 text-base font-semibold text-white">Reading the graph</h2>
            <dl class="mt-3 flex flex-col gap-3 text-sm">
                <div class="flex items-center gap-3">
                    <dt class="shrink-0">
                        <svg width="24" height="24" viewBox="0 0 24 24" aria-hidden="true">
                            <circle cx="12" cy="12" r="9" class="fill-none stroke-glow-red" stroke-width="3" />
                        </svg>
                    </dt>
                    <dd class="text-white/75">Where you started</dd>
                </div>
                <div class="flex items-center gap-3">
                    <dt class="shrink-0">
                        <svg width="24" height="24" viewBox="0 0 24 24" aria-hidden="true">
                            <circle cx="12" cy="12" r="9" class="fill-none stroke-white/75" stroke-width="2" />
                        </svg>
                    </dt>
                    <dd class="text-white/75">Expanded, its neighbours are shown</dd>
                </div>
                <div class="flex items-center gap-3">
                    <dt class="shrink-0">
                        <svg width="24" height="24" viewBox="0 0 24 24" aria-hidden="true">
                            <circle cx="12" cy="12" r="9" class="fill-none stroke-white/25" stroke-width="1.5" />
                        </svg>
                    </dt>
                    <dd class="text-white/75">Not expanded yet</dd>
                </div>
                <div class="flex items-center gap-3">
                    <dt class="shrink-0">
                        <svg width="24" height="24" viewBox="0 0 24 24" aria-hidden="true">
                            <line x1="2" y1="16" x2="22" y2="8" class="stroke-white/60" stroke-width="2.5" />
                        </svg>
                    </dt>
                    <dd class="text-white/75">Brighter, thicker lines are stronger matches</dd>
                </div>
                <div class="flex items-center gap-3">
                    <dt class="shrink-0">
                        <svg width="24" height="24" viewBox="0 0 24 24" aria-hidden="true">
                            <line x1="2" y1="16" x2="22" y2="8" class="stroke-tea-green" stroke-width="3" />
                        </svg>
                    </dt>
                    <dd class="text-white/75">Your route to each title you expanded</dd>
                </div>
            </dl>

            <p class="mt-6 text-sm leading-relaxed text-white/60">
                Click a poster to see why it's here, double-click to add its neighbours. Hover a line for its main
                reason. Drag to move, scroll to zoom,
                <kbd class="rounded border border-white/20 px-1 text-xs text-white/75">F</kbd> for fullscreen.
            </p>
        </template>

        <template v-else-if="selection.kind === 'node'">
            <div class="flex items-start gap-4">
                <img
                    :src="selection.node.poster_path"
                    :alt="`${selection.node.name} poster`"
                    width="64"
                    height="96"
                    class="aspect-[2/3] w-16 shrink-0 rounded-md object-cover"
                    :class="{ 'animate-pulse': busyId === selection.node.id }"
                    @error="onPosterError"
                />
                <div class="min-w-0 flex-1">
                    <h2 class="text-lg font-semibold leading-snug text-white">{{ selection.node.name }}</h2>
                    <p class="mt-1 text-sm tabular-nums text-white/60">{{ meta(selection.node) }}</p>
                </div>
                <button
                    type="button"
                    aria-label="Close"
                    class="grid size-8 shrink-0 cursor-pointer place-items-center rounded-full text-white/60 transition-colors hover:bg-white/10 hover:text-white"
                    @click="emit('clear')"
                >
                    <X class="size-4" aria-hidden="true" />
                </button>
            </div>

            <div class="mt-5 flex flex-wrap items-center gap-2">
                <button
                    v-if="!selection.expanded"
                    type="button"
                    :disabled="busyId === selection.node.id"
                    class="h-9 cursor-pointer rounded-full bg-white px-4 text-sm font-semibold text-black transition-colors hover:bg-white/85 disabled:cursor-wait disabled:opacity-60"
                    @click="emit('expand', selection.node)"
                >
                    Expand neighbours
                </button>
                <button
                    type="button"
                    :disabled="busyId === selection.node.id"
                    :class="button({ intent: 'soft', size: 'sm', class: 'h-9 rounded-full px-4' })"
                    @click="emit('details', selection.node)"
                >
                    Details
                </button>
            </div>

            <p v-if="selection.expanded && selection.node.id !== rootId" class="mt-3 text-sm text-white/55">
                Its neighbours are on the graph.
            </p>

            <template v-if="selection.relation">
                <p class="mt-6 text-sm text-white/60">
                    Found through
                    <button
                        type="button"
                        class="cursor-pointer font-medium text-white underline decoration-white/30 underline-offset-4 hover:decoration-white"
                        @click="emit('select', selection.relation.other.id)"
                    >
                        {{ selection.relation.other.name }}
                    </button>
                    · {{ Math.round(selection.relation.link.score * 100) }}% match
                </p>
            </template>
            <p v-else-if="selection.node.id === rootId" class="mt-6 text-sm text-white/60">
                This is where your exploration started.
            </p>
        </template>

        <template v-else-if="selection.kind === 'link'">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <button
                        type="button"
                        class="block max-w-full cursor-pointer truncate text-left text-base font-semibold text-white hover:underline"
                        @click="emit('select', selection.source.id)"
                    >
                        {{ selection.source.name }}
                    </button>
                    <span class="my-1 flex items-center gap-1.5 text-xs text-white/45">
                        <ArrowLeftRight class="size-3.5 rotate-90" aria-hidden="true" />
                        {{ Math.round(selection.link.score * 100) }}% match, {{ strength(selection.link.score) }}
                    </span>
                    <button
                        type="button"
                        class="block max-w-full cursor-pointer truncate text-left text-base font-semibold text-white hover:underline"
                        @click="emit('select', selection.target.id)"
                    >
                        {{ selection.target.name }}
                    </button>
                </div>
                <button
                    type="button"
                    aria-label="Close"
                    class="grid size-8 shrink-0 cursor-pointer place-items-center rounded-full text-white/60 transition-colors hover:bg-white/10 hover:text-white"
                    @click="emit('clear')"
                >
                    <X class="size-4" aria-hidden="true" />
                </button>
            </div>
        </template>

        <section v-if="reasons.length" class="mt-6" aria-label="Why they're connected">
            <h3 class="text-sm font-semibold text-white">Why they're connected</h3>
            <ul class="mt-3 flex flex-col gap-4">
                <li v-for="reason in reasons" :key="reason.type">
                    <div class="flex items-center gap-2 text-sm text-white/85">
                        <component
                            :is="reasonMeta(reason.type).icon"
                            class="size-4 shrink-0 text-tea-green"
                            aria-hidden="true"
                        />
                        {{ reasonMeta(reason.type).label }}
                    </div>
                    <div class="mt-2 h-1 overflow-hidden rounded-full bg-base-300">
                        <div class="h-full rounded-full bg-glow-red" :style="{ width: barWidth(reason.weight) }"></div>
                    </div>
                    <div v-if="reason.values?.length" class="mt-2 flex flex-wrap gap-1.5">
                        <span
                            v-for="value in reason.values.slice(0, 8)"
                            :key="value"
                            class="rounded-md bg-base-300 px-2 py-0.5 text-xs text-white/75"
                        >
                            {{ value }}
                        </span>
                        <span v-if="reason.values.length > 8" class="px-1 py-0.5 text-xs text-white/50">
                            +{{ reason.values.length - 8 }} more
                        </span>
                    </div>
                </li>
            </ul>
        </section>
    </aside>
</template>
