<script setup>
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'

const props = defineProps({
    title: String,
    logo: { type: String, default: '' },
    // When set, the whole heading becomes a link to that genre/provider's own
    // page. Left empty the heading renders as plain text, so the component stays
    // usable for sections that have nowhere to go.
    href: { type: String, default: '' },
    headingTag: { type: String, default: 'h1' },
})

const tag = computed(() => (props.href ? Link : 'div'))
</script>

<template>
    <component
        :is="tag"
        :href="href || undefined"
        :aria-label="href ? `Browse all ${title} titles` : undefined"
        class="group flex items-center gap-3 w-fit"
        :class="href && 'rounded focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-glow-red'"
    >
        <img v-if="logo" :src="logo" alt="" class="h-8 w-auto object-contain rounded" />
        <component
            :is="headingTag"
            class="text-2xl 2xl:text-4xl font-bold text-white [text-shadow:0_1px_2px_rgb(0_0_0/0.4)] transition-colors motion-reduce:transition-none group-hover:text-white/75"
        >
            {{ title }}
        </component>
        <!-- Sole affordance that the heading is clickable, so it stays visible
             rather than appearing on hover; there is no hover on touch. -->
        <svg
            v-if="href"
            aria-hidden="true"
            class="size-5 2xl:size-7 shrink-0 text-glow-red/70 transition-all motion-reduce:transition-none group-hover:translate-x-1 group-hover:text-glow-red"
            fill="none"
            stroke="currentColor"
            stroke-width="2.5"
            stroke-linecap="round"
            stroke-linejoin="round"
            viewBox="0 0 24 24"
        >
            <path d="M9 5l7 7-7 7" />
        </svg>
    </component>
</template>

<style scoped></style>
