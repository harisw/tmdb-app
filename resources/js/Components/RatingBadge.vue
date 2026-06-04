<script setup>
import {Star} from "lucide-vue-next";

const props = defineProps({
    rating: { type: [Number, String], required: true },
})
</script>

<template>
                    <span
                        v-if="typeof rating === 'number' && rating > 0"
                        class="flex items-center gap-1 bg-black/60 text-yellow-500 text-xs font-medium px-2 py-1 rounded-full"
                    >
                        <Star absoluteStrokeWidth :size="16" />
                        {{ rating.toFixed(1) }}
                    </span>
</template>
