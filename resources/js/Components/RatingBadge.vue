<script setup>
import { Star } from 'lucide-vue-next'
import { badge } from '../variants/badge.js'

const props = defineProps({
    rating: { type: [Number, String], required: true },
})
</script>

<template>
    <span v-if="typeof rating === 'number' && rating > 0" :class="badge({ tone: 'rating' })">
        <Star absolute-stroke-width :size="16" />
        {{ rating.toFixed(1) }}
    </span>
</template>
