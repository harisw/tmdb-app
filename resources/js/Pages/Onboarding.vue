<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import { Play, Shuffle } from 'lucide-vue-next'
import FocusLayout from '../Layouts/FocusLayout.vue'
import PosterCloud from '../Components/PosterCloud.vue'
import { button } from '../variants/button.js'

const props = defineProps({
    titles: { type: Object, required: true },
    round: { type: Number, required: true },
    minPicks: { type: Number, required: true },
    maxPicks: { type: Number, required: true },
})

const picks = ref([])
const shuffling = ref(false)
const form = useForm({ title_ids: [] })

const remaining = computed(() => Math.max(props.minPicks - picks.value.length, 0))
const ready = computed(() => remaining.value === 0)
const submitLabel = computed(() => (ready.value ? 'Build my home' : `Pick ${remaining.value} more`))
const status = computed(() => {
    if (picks.value.length >= props.maxPicks) return `${picks.value.length} picked, that's the most for now`
    return `${picks.value.length} of ${props.maxPicks} picked`
})
const error = computed(() => Object.entries(form.errors).find(([key]) => key.startsWith('title_ids'))?.[1])

function toggle(title) {
    const index = picks.value.findIndex((pick) => pick.id === title.id)
    if (index >= 0) {
        picks.value.splice(index, 1)
    } else {
        picks.value.push(title)
    }
}

function showOthers() {
    router.reload({
        data: { round: props.round + 1 },
        only: ['titles', 'round'],
        preserveScroll: false,
        onStart: () => (shuffling.value = true),
        onFinish: () => (shuffling.value = false),
    })
}

function submit() {
    form.transform(() => ({ title_ids: picks.value.map((pick) => pick.id) })).post('/onboarding')
}
</script>

<template>
    <FocusLayout>
        <Head title="Pick your favourites" />
        <form class="flex flex-col pt-6 pb-28 md:pt-8 xl:h-svh xl:min-h-[44rem] xl:pb-8" @submit.prevent="submit">
            <header class="flex flex-col gap-6 xl:flex-row xl:items-end xl:justify-between">
                <div class="max-w-3xl">
                    <Link href="/" class="inline-flex items-center gap-1.5 text-madder">
                        <Play :size="14" class="fill-madder" aria-hidden="true" />
                        <span class="text-2xl font-semibold tracking-tight">Chill</span>
                    </Link>
                    <h1
                        class="mt-6 text-4xl font-bold leading-[1.05] tracking-tight text-balance text-white md:text-5xl xl:mt-5"
                    >
                        Start with what you've loved
                    </h1>
                    <p class="mt-3 max-w-[60ch] text-base leading-relaxed text-white/80 md:text-lg">
                        Pick at least {{ minPicks }} films or series you enjoyed. Your home page will open on the titles
                        closest to them, and every like after this keeps it in tune.
                    </p>
                    <Link
                        href="/"
                        :class="button({ intent: 'link', size: 'sm', class: 'mt-3 -ml-3 text-white/75 xl:hidden' })"
                    >
                        Skip for now
                    </Link>
                </div>

                <div class="hidden shrink-0 flex-col items-end gap-3 xl:flex">
                    <p class="text-sm tabular-nums text-white/60" aria-live="polite">{{ status }}</p>
                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            :disabled="shuffling"
                            :class="button({ intent: 'soft', size: 'md', class: 'gap-2' })"
                            @click="showOthers"
                        >
                            <Shuffle class="size-4" aria-hidden="true" />
                            Show me others
                        </button>
                        <Link href="/" :class="button({ intent: 'ghost', size: 'md', class: 'text-white/75' })">
                            Skip for now
                        </Link>
                        <button
                            type="submit"
                            :disabled="!ready || form.processing"
                            :class="button({ intent: 'primary', size: 'md', class: 'min-w-40 px-6' })"
                        >
                            {{ submitLabel }}
                        </button>
                    </div>
                </div>
            </header>

            <p v-if="error" role="alert" class="mt-6 text-sm text-glow-red">{{ error }}</p>

            <div
                v-if="titles.data.length"
                class="mt-8 xl:mt-6 xl:flex xl:min-h-0 xl:flex-1 xl:items-center xl:[container-type:size]"
            >
                <PosterCloud
                    :titles="titles.data"
                    :picks="picks"
                    :max-picks="maxPicks"
                    :ready="ready"
                    @toggle="toggle"
                />
            </div>
            <div v-if="titles.data.length" class="mt-8 flex justify-center xl:hidden">
                <button
                    type="button"
                    :disabled="shuffling"
                    :class="button({ intent: 'soft', size: 'md', class: 'gap-2' })"
                    @click="showOthers"
                >
                    <Shuffle class="size-4" aria-hidden="true" />
                    Show me others
                </button>
            </div>
            <p v-else class="py-16 text-center text-base text-white/70">
                There's nothing to pick from yet. Check back once the catalogue has filled in.
            </p>

            <div
                class="fixed inset-x-0 bottom-0 z-40 flex items-center justify-between gap-4 bg-base-200 px-4 py-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] md:px-10 xl:hidden"
            >
                <p class="text-sm tabular-nums text-white/75" aria-live="polite">{{ status }}</p>
                <button
                    type="submit"
                    :disabled="!ready || form.processing"
                    :class="button({ intent: 'primary', size: 'md', class: 'px-5' })"
                >
                    {{ submitLabel }}
                </button>
            </div>
        </form>
    </FocusLayout>
</template>
