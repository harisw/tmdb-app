<script setup>
import { computed } from 'vue'
import { reasonMeta } from '../constants/reasons.js'

const OFFSET = 14
const MAX_WIDTH = 256
const VALUE_LIMIT = 2

const props = defineProps({
    reason: { type: Object, required: true },
    x: { type: Number, required: true },
    y: { type: Number, required: true },
    boundsWidth: { type: Number, required: true },
})

const label = computed(() => reasonMeta(props.reason.type).label)

const values = computed(() => {
    const all = props.reason.values ?? []
    const remainder = all.length > VALUE_LIMIT ? ` +${all.length - VALUE_LIMIT}` : ''
    return all.length ? `${all.slice(0, VALUE_LIMIT).join(', ')}${remainder}` : null
})

const position = computed(() => {
    const opensLeft = props.x + OFFSET + MAX_WIDTH > props.boundsWidth
    return {
        top: `${props.y + OFFSET}px`,
        left: `${props.x + (opensLeft ? -OFFSET : OFFSET)}px`,
        maxWidth: `${MAX_WIDTH}px`,
        transform: opensLeft ? 'translateX(-100%)' : null,
    }
})
</script>

<template>
    <div
        class="pointer-events-none absolute z-10 rounded-md bg-base-300 px-2 py-1 text-xs leading-snug"
        :style="position"
        aria-hidden="true"
    >
        <span class="text-white/50">{{ label }}</span>
        <span v-if="values" class="text-white/75"> · {{ values }}</span>
    </div>
</template>
