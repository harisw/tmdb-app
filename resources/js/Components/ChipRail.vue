<script setup>
import { Link } from '@inertiajs/vue3'

const props = defineProps({
    items: { type: Array, default: () => [] },
    // Field identifying an item, matched against `active` to mark the selection.
    itemKey: { type: String, required: true },
    // Field holding the title count. The count is dropped when it is 0 or
    // missing rather than rendering "0", which reads as a broken page.
    countKey: { type: String, default: 'count' },
    active: { type: [String, Number], default: null },
    hrefKey: { type: String, default: null },
    swipeOnPhone: { type: Boolean, default: false },
})

const emit = defineEmits(['select'])

function chipAttributes(item) {
    return props.hrefKey
        ? { href: item[props.hrefKey] }
        : { type: 'button', 'aria-pressed': item[props.itemKey] === props.active }
}

function onChipClick(item) {
    if (!props.hrefKey) emit('select', item)
}

const numberFormat = new Intl.NumberFormat()
const formatCount = (count) => numberFormat.format(count)
</script>

<template>
    <!-- The rail sits on its own panel so the chips read as one control rather
         than loose text floating on the ambient background. -->
    <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-3 sm:p-4 2xl:p-6">
        <ul
            class="flex gap-2 2xl:gap-3"
            :class="
                swipeOnPhone ? 'no-scrollbar -mx-1 overflow-x-auto px-1 max-sm:flex-nowrap sm:flex-wrap' : 'flex-wrap'
            "
        >
            <li v-for="item in items" :key="item.id" class="shrink-0">
                <component
                    :is="hrefKey ? Link : 'button'"
                    v-bind="chipAttributes(item)"
                    class="group flex items-center gap-2 2xl:gap-2.5 rounded-full border px-3 py-1.5 2xl:px-4 2xl:py-2 cursor-pointer transition-colors motion-reduce:transition-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary"
                    :class="
                        item[itemKey] === active
                            ? 'border-madder bg-madder/20 text-white shadow-[0_0_24px_-6px] shadow-madder'
                            : 'border-white/10 bg-white/5 text-white/90 hover:border-white/25 hover:bg-white/10 hover:text-white'
                    "
                    @click="onChipClick(item)"
                >
                    <span class="text-sm 2xl:text-lg leading-none">{{ item.name }}</span>
                    <span
                        v-if="item[countKey]"
                        class="text-xs 2xl:text-sm tabular-nums text-white/55 transition-colors motion-reduce:transition-none group-hover:text-white/80"
                    >
                        {{ formatCount(item[countKey]) }}
                    </span>
                </component>
            </li>
        </ul>
        <slot name="footer" />
    </div>
</template>
