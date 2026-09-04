<script setup>
import { ref, watch } from 'vue'
import { Star } from 'lucide-vue-next'
import BaseModal from './BaseModal.vue'
import { button } from '../../variants/button.js'

const props = defineProps({
    show: { type: Boolean, required: true },
    titleName: { type: String, default: '' },
    context: { type: String, default: 'watched' }, // 'like' | 'watched'
})
const emit = defineEmits(['confirm', 'close']) // confirm payload: number | null

const rating = ref(0)
const hover = ref(0)

watch(
    () => props.show,
    (open) => {
        if (open) {
            rating.value = 0
            hover.value = 0
        }
    }
)

const HINT = {
    like: 'You liked it. How many stars?',
    watched: 'Rate it now, or skip.',
}
</script>

<template>
    <BaseModal :show="show" max-width="max-w-sm" labelledby="rate-modal-heading" @close="emit('close')">
        <div class="space-y-5 p-6 text-center">
            <div class="space-y-1">
                <h3 id="rate-modal-heading" class="text-lg font-semibold">Rate this title</h3>
                <p v-if="titleName" class="line-clamp-1 text-sm text-white/60">{{ titleName }}</p>
            </div>

            <div class="flex flex-col gap-2">
                <div class="flex justify-center gap-1" @mouseleave="hover = 0">
                    <button
                        v-for="n in 10"
                        :key="n"
                        type="button"
                        :aria-label="`${n} of 10 stars`"
                        :aria-pressed="rating === n"
                        class="rounded p-1 transition-transform duration-150 hover:scale-125 active:scale-95 focus:outline-none focus-visible:ring-2 focus-visible:ring-madder"
                        @mouseenter="hover = n"
                        @focus="hover = n"
                        @blur="hover = 0"
                        @click="rating = rating === n ? 0 : n"
                    >
                        <Star
                            :size="22"
                            :stroke-width="1.5"
                            :class="
                                n <= (hover || rating) ? 'fill-madder text-madder' : 'fill-transparent text-white/25'
                            "
                        />
                    </button>
                </div>
                <p class="text-xs text-white/60">{{ hover || rating }} out of 10</p>
            </div>
            <p class="text-xs text-white/40">{{ HINT[context] ?? HINT.watched }}</p>

            <div class="flex items-center justify-center gap-2">
                <button type="button" :class="button({ intent: 'ghost', size: 'sm' })" @click="emit('confirm', null)">
                    Skip
                </button>
                <button
                    type="button"
                    :disabled="!rating"
                    :class="button({ intent: 'primary', size: 'sm' })"
                    @click="emit('confirm', rating)"
                >
                    Save rating
                </button>
            </div>
        </div>
    </BaseModal>
</template>
