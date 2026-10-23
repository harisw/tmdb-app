<script setup>
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import { ArrowUpRight, Shuffle } from 'lucide-vue-next'
import { headlineReasons, reasonMeta } from '../constants/reasons.js'
import { onPosterError } from '../utils/img.js'
import { button } from '../variants/button.js'

const props = defineProps({
    constellation: { type: Object, default: null },
    shuffling: { type: Boolean, default: false },
    busyId: { type: Number, default: null },
})

const emit = defineEmits(['open', 'shuffle'])

const seed = computed(() => props.constellation?.nodes.find((node) => node.id === props.constellation.seed))

const threads = computed(() => {
    if (!seed.value) return []
    const nodeById = new Map(props.constellation.nodes.map((node) => [node.id, node]))
    return props.constellation.links
        .filter((link) => link.source === seed.value.id || link.target === seed.value.id)
        .sort((a, b) => b.score - a.score)
        .map((link) => ({
            node: nodeById.get(link.source === seed.value.id ? link.target : link.source),
            score: link.score,
            reasons: headlineReasons(link.reasons),
        }))
})
</script>

<template>
    <section aria-labelledby="thread-heading" class="w-full">
        <div v-if="!seed" class="space-y-3" aria-hidden="true">
            <div class="h-24 animate-pulse rounded-xl bg-white/5"></div>
            <div v-for="row in 4" :key="row" class="h-16 animate-pulse rounded-xl bg-white/[0.03]"></div>
        </div>

        <template v-else>
            <div class="flex items-center justify-between gap-3">
                <h2 id="thread-heading" class="text-sm text-white/60">
                    Threads from <span class="font-semibold text-white">{{ seed.name }}</span>
                </h2>
                <button
                    v-if="constellation.seedCount > 1"
                    type="button"
                    :disabled="shuffling"
                    :class="button({ intent: 'ghost', size: 'xs', class: 'shrink-0 gap-1.5 px-2.5 text-white/75' })"
                    @click="emit('shuffle')"
                >
                    <Shuffle
                        class="size-3.5"
                        :class="{ 'animate-spin motion-reduce:animate-none': shuffling }"
                        aria-hidden="true"
                    />
                    Another start
                </button>
            </div>

            <button
                type="button"
                class="mt-3 flex w-full cursor-pointer items-center gap-4 rounded-xl border border-glow-red/40 bg-white/[0.04] p-3 text-left transition-colors hover:bg-white/[0.07] focus-visible:outline-2 focus-visible:outline-glow-red"
                :class="{ 'opacity-60': shuffling }"
                @click="emit('open', seed)"
            >
                <img
                    :src="seed.poster_path"
                    :alt="`${seed.name} poster`"
                    width="64"
                    height="96"
                    class="aspect-[2/3] w-16 shrink-0 rounded-lg object-cover"
                    :class="{ 'animate-pulse': busyId === seed.id }"
                    @error="onPosterError"
                />
                <span class="min-w-0">
                    <span class="block truncate text-base font-semibold text-white">{{ seed.name }}</span>
                    <span class="mt-0.5 block text-xs text-white/50">
                        {{ seed.year }} · {{ seed.type === 'movie' ? 'Film' : 'TV' }} · {{ threads.length }} threads
                    </span>
                </span>
            </button>

            <ol
                class="relative mt-2 ml-8 border-l border-white/15 pl-0 transition-opacity"
                :class="{ 'opacity-60': shuffling }"
            >
                <li v-for="thread in threads" :key="thread.node.id" class="relative">
                    <span
                        class="absolute -left-px top-1/2 h-px w-4 -translate-y-1/2 bg-white/15"
                        aria-hidden="true"
                    ></span>
                    <button
                        type="button"
                        class="ml-4 flex w-[calc(100%-1rem)] cursor-pointer items-center gap-3 rounded-xl p-2 text-left transition-colors hover:bg-white/[0.05] focus-visible:outline-2 focus-visible:outline-glow-red"
                        @click="emit('open', thread.node)"
                    >
                        <img
                            :src="thread.node.poster_path"
                            :alt="`${thread.node.name} poster`"
                            width="40"
                            height="60"
                            loading="lazy"
                            class="aspect-[2/3] w-10 shrink-0 rounded-md object-cover"
                            :class="{ 'animate-pulse': busyId === thread.node.id }"
                            @error="onPosterError"
                        />
                        <span class="min-w-0 flex-1">
                            <span class="flex items-baseline justify-between gap-2">
                                <span class="truncate text-sm font-medium text-white">{{ thread.node.name }}</span>
                                <span class="shrink-0 text-xs tabular-nums text-white/45">
                                    {{ Math.round(thread.score * 100) }}%
                                </span>
                            </span>
                            <span
                                v-for="reason in thread.reasons"
                                :key="reason.type"
                                class="mt-0.5 flex items-center gap-1.5 text-xs text-white/55"
                            >
                                <component
                                    :is="reasonMeta(reason.type).icon"
                                    class="size-3 shrink-0 text-tea-green"
                                    aria-hidden="true"
                                />
                                <span class="truncate">{{ reason.values.slice(0, 3).join(', ') }}</span>
                            </span>
                        </span>
                    </button>
                </li>
            </ol>

            <Link
                :href="`/graph?title_slug=${seed.slug}`"
                :class="button({ intent: 'ghost', size: 'sm', class: 'mt-3 ml-8 gap-1 px-3 text-white/75' })"
            >
                Open the full graph
                <ArrowUpRight class="size-4" aria-hidden="true" />
            </Link>
        </template>
    </section>
</template>
