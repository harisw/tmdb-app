<script setup>
import { computed } from 'vue'
import BaseModal from './BaseModal.vue'
import { ArrowLeftRight, Info, Waypoints } from 'lucide-vue-next'
import { reasonMeta } from '../../constants/reasons.js'

const props = defineProps({
    show: { type: Boolean, required: true },
    link: { type: Object, default: null },
    to: { type: [String, Object], default: 'body' },
})

const emit = defineEmits(['close', 'view', 'expand'])

const reasons = computed(() => props.link?.reasons ?? [])
const topWeight = computed(() => reasons.value[0]?.weight || 1)

function barWidth(weight) {
    return `${Math.min(100, (weight / topWeight.value) * 100)}%`
}

const matchPct = computed(() => Math.round(props.link?.score * 100))

const strength = computed(() => {
    const score = props.link?.score ?? 0
    if (score >= 0.6) return 'strong'
    if (score >= 0.4) return 'moderate'
    return 'weak'
})

function viewTarget() {
    emit('view', props.link.target)
    emit('close')
}

function expandTarget() {
    emit('expand', props.link.target)
    emit('close')
}
</script>

<template>
    <BaseModal :show="show" :to="to" max-width="max-w-lg" align="start" @close="$emit('close')">
        <template #default>
            <div v-if="link" class="p-5 pr-9 space-y-5">
                <div class="flex flex-col sm:flex-row items-center gap-2 sm:gap-3">
                    <span
                        class="w-full sm:flex-1 min-w-0 rounded-2xl bg-white/10 px-4 py-2 text-center text-sm sm:text-base font-semibold leading-snug text-white break-words"
                    >
                        {{ link.source.name }}
                    </span>
                    <ArrowLeftRight :size="16" class="text-white/40 shrink-0 rotate-90 sm:rotate-0" />
                    <span
                        class="w-full sm:flex-1 min-w-0 rounded-2xl bg-white/10 px-4 py-2 text-center text-sm sm:text-base font-semibold leading-snug text-white break-words"
                    >
                        {{ link.target.name }}
                    </span>
                </div>

                <div class="flex items-center gap-3">
                    <span
                        class="badge badge-info badge-sm shrink-0 bg-blue-500/20 text-blue-300 border-0 font-semibold"
                    >
                        {{ matchPct }}% match
                    </span>
                    <div class="flex-1 min-w-0 h-2 rounded-full bg-white/10 overflow-hidden">
                        <div class="h-full rounded-full bg-blue-500" :style="{ width: `${matchPct}%` }" />
                    </div>
                    <span class="text-xs text-white/50 capitalize shrink-0">{{ strength }}</span>
                </div>

                <div class="space-y-4">
                    <div v-for="reason in reasons" :key="reason.type" class="space-y-2 sm:space-y-5">
                        <div class="flex items-center gap-2">
                            <span
                                class="flex items-center justify-center size-7 rounded-lg"
                                :class="reasonMeta(reason.type).iconBg"
                            >
                                <component :is="reasonMeta(reason.type).icon" :size="15" />
                            </span>
                            <span class="text-sm font-semibold text-white">{{ reasonMeta(reason.type).label }}</span>
                        </div>

                        <div class="flex flex-wrap gap-1.5 pl-9">
                            <span
                                v-for="value in reason.values"
                                :key="value"
                                class="badge badge-outline badge-sm px-2 bg-white/5 text-gray-300 border-white/10"
                            >
                                {{ value }}
                            </span>
                        </div>

                        <div class="flex items-center gap-3 pl-9">
                            <div class="flex-1 min-w-0 h-1.5 rounded-full bg-white/10 overflow-hidden">
                                <div
                                    class="h-full rounded-full"
                                    :class="reasonMeta(reason.type).bar"
                                    :style="{ width: barWidth(reason.weight) }"
                                />
                            </div>
                            <span class="text-xs text-white/40 tabular-nums shrink-0"
                                >{{ reason.weight.toFixed(2) }} pts</span
                            >
                        </div>
                    </div>
                </div>

                <div class="flex flex-col sm:flex-row gap-2 pt-1">
                    <button
                        type="button"
                        class="btn btn-sm sm:flex-1 h-auto min-h-9 py-2 flex-wrap whitespace-normal text-center leading-snug bg-white/5 hover:bg-white/10 border-white/10 text-white gap-1.5"
                        @click="viewTarget"
                    >
                        <Info :size="14" class="shrink-0" />
                        <span>View {{ link.target.name }}</span>
                    </button>
                    <button
                        type="button"
                        class="btn btn-sm sm:flex-1 h-auto min-h-9 py-2 flex-wrap whitespace-normal text-center leading-snug bg-white/5 hover:bg-white/10 border-white/10 text-white gap-1.5"
                        @click="expandTarget"
                    >
                        <Waypoints :size="14" class="shrink-0" />
                        <span>Expand neighbours</span>
                    </button>
                </div>
            </div>
        </template>
    </BaseModal>
</template>

<style scoped></style>
