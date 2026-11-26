<script setup>
import { computed } from 'vue'
import { Check } from 'lucide-vue-next'
import { POSTER_HEIGHT, POSTER_WIDTH, bubbleSlot, layoutPosterCloud } from '../Composables/usePosterCloudLayout.js'
import { POSTER_PLACEHOLDER, onPosterError } from '../utils/img.js'
import { tv } from '../variants/tv.js'

const props = defineProps({
    titles: { type: Array, required: true },
    picks: { type: Array, required: true },
    maxPicks: { type: Number, required: true },
    ready: { type: Boolean, default: false },
})

const emit = defineEmits(['toggle'])

const posterCard = tv({
    base: 'relative block overflow-hidden rounded-md bg-base-200 shadow-lg shadow-black/40 transition-[scale,box-shadow] duration-300 group-hover:scale-110 group-focus-visible:scale-110 motion-reduce:transition-none',
    variants: {
        picked: {
            true: 'ring-2 ring-madder',
            false: 'ring-1 ring-white/15 group-hover:ring-white/40 group-focus-visible:ring-2 group-focus-visible:ring-glow-red',
        },
    },
})

const layout = computed(() => layoutPosterCloud(props.titles))
const pickIndex = computed(() => new Map(props.picks.map((title, index) => [title.id, index])))
const full = computed(() => props.picks.length >= props.maxPicks)

const posters = computed(() => {
    const inCloud = new Set(props.titles.map((title) => title.id))
    return [...props.titles, ...props.picks.filter((title) => !inCloud.has(title.id))]
})

const stageStyle = computed(() => {
    const { width, height } = layout.value
    return {
        '--stage-ratio': `${width} / ${height}`,
        '--stage-w': `min(100cqw, calc(100cqh * ${width / height}))`,
        '--poster-w': `${(POSTER_WIDTH / width) * 100}%`,
    }
})

const bubbleStyle = computed(() => {
    const { width, height, bubble } = layout.value
    return {
        left: `${((bubble.x - bubble.r) / width) * 100}%`,
        top: `${((bubble.y - bubble.r) / height) * 100}%`,
        width: `${((bubble.r * 2) / width) * 100}%`,
    }
})

function isPicked(title) {
    return pickIndex.value.has(title.id)
}

function place(title) {
    const index = pickIndex.value.get(title.id)
    if (index === undefined) {
        return layout.value.places.get(title.id) ?? bubbleSlot(layout.value.bubble, 0, props.maxPicks)
    }
    return bubbleSlot(layout.value.bubble, index, props.maxPicks)
}

function posterStyle(title, order) {
    const { x, y, scale } = place(title)
    const index = pickIndex.value.get(title.id)
    const left = ((x - POSTER_WIDTH / 2) / POSTER_WIDTH) * 100
    const top = ((y - POSTER_HEIGHT / 2) / POSTER_HEIGHT) * 100
    return {
        '--place': `translate(${left}%, ${top}%) scale(${scale})`,
        '--z': index === undefined ? 1 : 10 + index,
        '--in-delay': `${Math.min(order * 18, 900)}ms`,
        '--drift-delay': `${-((title.id * 7919) % 6000)}ms`,
        '--drift-duration': `${6 + ((title.id * 31) % 4)}s`,
    }
}

function label(title) {
    const year = title.release_date ? `, ${title.release_date}` : ''
    return `${title.name}${year}`
}

function toggle(title) {
    if (!isPicked(title) && full.value) return
    emit('toggle', title)
}
</script>

<template>
    <div
        class="grid grid-cols-3 gap-x-3 gap-y-5 sm:grid-cols-4 md:grid-cols-6 lg:grid-cols-8 xl:relative xl:mx-auto xl:block xl:aspect-(--stage-ratio) xl:w-(--stage-w)"
        :style="stageStyle"
    >
        <div
            aria-hidden="true"
            class="pointer-events-none absolute hidden aspect-square place-items-center rounded-full transition-colors duration-500 motion-reduce:transition-none xl:grid"
            :class="ready ? 'bg-madder/15' : 'bg-white/[0.04]'"
            :style="bubbleStyle"
        >
            <p
                class="max-w-[16ch] text-center text-base leading-snug text-white/55 transition-opacity duration-300"
                :class="{ 'opacity-0': picks.length }"
            >
                Your picks gather here
            </p>
        </div>

        <button
            v-for="(title, order) in posters"
            :key="title.id"
            type="button"
            class="group relative cursor-pointer text-left outline-none hover:z-50 focus-visible:z-50 xl:absolute xl:top-0 xl:left-0 xl:z-(--z) xl:hover:z-50 xl:focus-visible:z-50 xl:w-(--poster-w) xl:[transform:var(--place)] xl:transition-transform xl:duration-700 xl:ease-[cubic-bezier(0.16,1,0.3,1)] motion-reduce:transition-none"
            :class="{ 'cursor-not-allowed opacity-35': full && !isPicked(title) }"
            :style="posterStyle(title, order)"
            :aria-pressed="isPicked(title)"
            :aria-disabled="full && !isPicked(title)"
            :aria-label="label(title)"
            @click="toggle(title)"
        >
            <span class="poster-float block">
                <span :class="posterCard({ picked: isPicked(title) })">
                    <img
                        :src="title.poster_path || POSTER_PLACEHOLDER"
                        alt=""
                        draggable="false"
                        class="aspect-[2/3] w-full object-cover"
                        @error="onPosterError"
                    />
                    <span
                        v-if="isPicked(title)"
                        class="absolute top-1.5 right-1.5 grid size-6 place-items-center rounded-full bg-madder text-white xl:hidden"
                    >
                        <Check class="size-3.5" aria-hidden="true" />
                    </span>
                </span>
                <span class="mt-1.5 block truncate text-xs text-white/75 xl:hidden">{{ title.name }}</span>
                <span
                    class="pointer-events-none absolute top-full left-1/2 mt-2 hidden -translate-x-1/2 rounded bg-licorice px-2 py-0.5 text-xs font-semibold whitespace-nowrap text-white xl:group-hover:block xl:group-focus-visible:block"
                >
                    {{ title.name }}
                </span>
            </span>
        </button>
    </div>
</template>

<style scoped>
.poster-float {
    animation: poster-in 600ms cubic-bezier(0.16, 1, 0.3, 1) var(--in-delay) both;
}

@media (min-width: 80rem) {
    .poster-float {
        animation:
            poster-in 700ms cubic-bezier(0.16, 1, 0.3, 1) var(--in-delay) both,
            poster-drift var(--drift-duration) ease-in-out var(--drift-delay) infinite alternate;
    }
}

@keyframes poster-in {
    from {
        opacity: 0;
        scale: 0.6;
    }
}

@keyframes poster-drift {
    to {
        translate: 0 -5px;
    }
}

@media (prefers-reduced-motion: reduce) {
    .poster-float {
        animation: none;
    }
}
</style>
