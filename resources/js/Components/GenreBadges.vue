<script setup>
import { Link } from '@inertiajs/vue3'
import { badge } from '../variants/badge.js'

const props = defineProps({
    genres: { type: Array, default: () => [] },
})
</script>

<template>
    <div class="flex flex-wrap gap-1.5">
        <Link v-for="genre in genres" :key="genre.id" :href="genre.url" :class="badge({ tone: 'genre' })" @click.stop>
            {{ genre.name }}
        </Link>
    </div>
</template>
