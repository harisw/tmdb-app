<script setup>
import { computed } from 'vue'
import { REASON_GROUPS, reasonGroupIds } from '../constants/reasons.js'
import { tv } from '../variants/tv.js'

const active = defineModel({ type: Set, required: true })

const props = defineProps({
    links: { type: Array, required: true },
})

const chip = tv({
    base: 'flex h-8 cursor-pointer items-center gap-1.5 rounded-full px-3 text-xs font-medium transition-colors focus-visible:outline-2 focus-visible:outline-glow-red disabled:cursor-not-allowed motion-reduce:transition-none',
    variants: {
        on: {
            true: 'bg-white text-licorice hover:bg-white/85',
            false: 'bg-licorice/80 text-white/55 hover:text-white',
        },
    },
})

const groups = computed(() => {
    const counts = new Map()
    for (const link of props.links) {
        for (const id of reasonGroupIds(link)) counts.set(id, (counts.get(id) ?? 0) + 1)
    }

    return REASON_GROUPS.filter((group) => counts.has(group.id)).map((group) => ({
        ...group,
        count: counts.get(group.id),
        on: active.value.has(group.id),
    }))
})

const onCount = computed(() => groups.value.filter((group) => group.on).length)

function toggle(id) {
    const next = new Set(active.value)
    if (next.has(id)) {
        next.delete(id)
    } else {
        next.add(id)
    }
    active.value = next
}
</script>

<template>
    <div v-if="groups.length > 1" role="group" aria-label="Show connections by reason" class="flex flex-wrap gap-1.5">
        <button
            v-for="group in groups"
            :key="group.id"
            type="button"
            :aria-pressed="group.on"
            :disabled="group.on && onCount === 1"
            :title="group.on && onCount === 1 ? 'At least one reason stays on' : null"
            :class="chip({ on: group.on })"
            @click="toggle(group.id)"
        >
            <component :is="group.icon" class="size-3.5" aria-hidden="true" />
            {{ group.label }}
            <span class="tabular-nums opacity-60">{{ group.count }}</span>
        </button>
    </div>
</template>
