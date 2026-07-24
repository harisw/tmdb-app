<script setup>
defineProps({
    items: { type: Array, default: () => [] },
    // Field identifying an item, matched against `active` to mark the selection.
    itemKey: { type: String, required: true },
    // Field holding the title count. The count is dropped when it is 0 or
    // missing rather than rendering "0", which reads as a broken page.
    countKey: { type: String, default: 'count' },
    active: { type: [String, Number], default: null },
})

defineEmits(['select'])

const numberFormat = new Intl.NumberFormat()
const formatCount = (count) => numberFormat.format(count)
</script>

<template>
    <!-- The rail sits on its own panel so the chips read as one control rather
         than loose text floating on the ambient background. -->
    <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-3 sm:p-4 2xl:p-6">
        <ul class="flex flex-wrap gap-2 2xl:gap-3">
            <li v-for="item in items" :key="item.id">
                <button
                    type="button"
                    :aria-pressed="item[itemKey] === active"
                    class="group flex items-center gap-2 2xl:gap-2.5 rounded-full border px-3 py-1.5 2xl:px-4 2xl:py-2 cursor-pointer transition-colors motion-reduce:transition-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary"
                    :class="
                        item[itemKey] === active
                            ? 'border-madder bg-madder/20 text-white shadow-[0_0_24px_-6px] shadow-madder'
                            : 'border-white/10 bg-white/5 text-white/90 hover:border-white/25 hover:bg-white/10 hover:text-white'
                    "
                    @click="$emit('select', item)"
                >
                    <span class="text-sm 2xl:text-lg leading-none">{{ item.name }}</span>
                    <span
                        v-if="item[countKey]"
                        class="text-xs 2xl:text-sm tabular-nums text-white/55 transition-colors motion-reduce:transition-none group-hover:text-white/80"
                    >
                        {{ formatCount(item[countKey]) }}
                    </span>
                </button>
            </li>
        </ul>
        <slot name="footer" />
    </div>
</template>
