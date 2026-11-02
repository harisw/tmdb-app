<script setup>
import { computed, ref } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import { Search } from 'lucide-vue-next'
import { field } from '../variants/field.js'

const COLLAPSED_COUNT = 24

const props = defineProps({
    providers: { type: Array, required: true },
})

const logoBase = computed(() => usePage().props.base_url.logo)
const numberFormat = new Intl.NumberFormat()

const filter = ref('')
const expanded = ref(false)

const normalizedFilter = computed(() => filter.value.trim().toLocaleLowerCase())
const matches = computed(() =>
    normalizedFilter.value
        ? props.providers.filter((provider) => provider.name.toLocaleLowerCase().includes(normalizedFilter.value))
        : props.providers
)
const isCollapsed = computed(() => !expanded.value && !normalizedFilter.value)
const visible = computed(() => (isCollapsed.value ? matches.value.slice(0, COLLAPSED_COUNT) : matches.value))
const canExpand = computed(() => !normalizedFilter.value && props.providers.length > COLLAPSED_COUNT)
</script>

<template>
    <p v-if="!providers.length" class="text-sm text-white/60">No streaming services have titles yet.</p>
    <div v-else>
        <label class="relative block max-w-sm">
            <span class="sr-only">Filter services by name</span>
            <Search class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-white/40" />
            <input
                v-model="filter"
                type="search"
                :placeholder="`Filter ${numberFormat.format(providers.length)} services`"
                :class="field({ class: 'h-10 pl-10 pr-3 text-sm' })"
            />
        </label>

        <ul
            v-if="visible.length"
            class="mt-5 grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6 2xl:gap-3"
        >
            <li v-for="provider in visible" :key="provider.id">
                <Link
                    :href="provider.url"
                    class="group flex items-center gap-3 rounded-xl bg-white/[0.04] p-2.5 transition-colors hover:bg-white/[0.09] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-glow-red motion-reduce:transition-none"
                >
                    <img
                        v-if="provider.img_logo"
                        :src="`${logoBase}/${provider.img_logo}`"
                        alt=""
                        width="40"
                        height="40"
                        loading="lazy"
                        class="size-10 shrink-0 rounded-lg object-cover"
                    />
                    <span
                        v-else
                        class="grid size-10 shrink-0 place-items-center rounded-lg bg-white/10 text-sm font-semibold text-white/70"
                        aria-hidden="true"
                    >
                        {{ provider.name.charAt(0) }}
                    </span>
                    <span class="min-w-0">
                        <span class="block truncate text-sm font-medium text-white">{{ provider.name }}</span>
                        <span class="block text-xs tabular-nums text-white/50">
                            {{ numberFormat.format(provider.count) }} titles
                        </span>
                    </span>
                </Link>
            </li>
        </ul>
        <p v-else class="mt-5 text-sm text-white/60">No service matches “{{ filter.trim() }}”.</p>

        <button
            v-if="canExpand"
            type="button"
            class="mt-4 cursor-pointer rounded-full bg-white/5 px-4 py-1.5 text-sm text-white/90 transition-colors hover:bg-white/10 hover:text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-glow-red motion-reduce:transition-none"
            :aria-expanded="expanded"
            @click="expanded = !expanded"
        >
            {{ expanded ? 'Show fewer' : `Show all ${numberFormat.format(providers.length)} services` }}
        </button>
    </div>
</template>
