<script setup>
import { ref, watch } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AppLayout from '../../Layouts/AppLayout.vue'
import SectionHeader from '../../Components/SectionHeader.vue'

const props = defineProps({
    flags: { type: Array, required: true },
})

// Local mirror of the server state so the switch reacts on click instead of
// sitting still until the round trip lands. Re-synced from props on every
// response, so a rejected write snaps back to the truth.
const state = ref({})
const saving = ref({})

watch(
    () => props.flags,
    (flags) => {
        state.value = Object.fromEntries(flags.map((flag) => [flag.key, flag.active]))
    },
    { immediate: true, deep: true }
)

function toggle(flag) {
    const previous = state.value[flag.key]
    const next = !previous

    state.value[flag.key] = next
    saving.value[flag.key] = true

    router.patch(
        `/admin/feature-flags/${flag.key}`,
        { active: next },
        {
            preserveScroll: true,
            onError: () => (state.value[flag.key] = previous),
            onFinish: () => (saving.value[flag.key] = false),
        }
    )
}
</script>

<template>
    <Head title="Feature Flags" />
    <AppLayout>
        <div class="w-full max-w-3xl">
            <SectionHeader title="Feature Flags" />
            <p class="-mt-2 mb-8 text-sm text-white/50 leading-relaxed">
                Runtime kill switches for scheduled work. A change applies from the next scheduler tick onward. Anything
                already queued still runs to completion.
            </p>

            <ul class="flex flex-col gap-3">
                <li
                    v-for="flag in flags"
                    :key="flag.key"
                    class="flex items-start gap-4 p-4 sm:p-5 bg-white/5 border border-white/10 rounded-xl transition-colors motion-reduce:transition-none hover:border-white/20"
                >
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2.5">
                            <h2 class="text-base font-medium text-white">{{ flag.label }}</h2>
                            <span
                                class="px-2 py-0.5 rounded-full border text-[11px] font-medium"
                                :class="
                                    state[flag.key]
                                        ? 'text-tea-green border-tea-green/30 bg-tea-green/10'
                                        : 'text-white/50 border-white/10 bg-white/5'
                                "
                            >
                                {{ state[flag.key] ? 'Active' : 'Disabled' }}
                            </span>
                        </div>
                        <p class="mt-1.5 text-sm text-white/50 leading-relaxed">
                            {{ flag.description }}
                        </p>
                        <code class="mt-2 inline-block text-[11px] text-white/40">{{ flag.key }}</code>
                    </div>

                    <input
                        type="checkbox"
                        class="toggle toggle-sm mt-1 shrink-0"
                        :checked="state[flag.key]"
                        :disabled="saving[flag.key]"
                        :aria-label="`Toggle ${flag.label}`"
                        @change="toggle(flag)"
                    />
                </li>
            </ul>
        </div>
    </AppLayout>
</template>
