<script setup>
import { Link } from '@inertiajs/vue3'
import { badge } from '../variants/badge.js'

const props = defineProps({
    genres: { type: Array, default: () => [] },
})
</script>

<template>
    <div class="flex flex-wrap gap-1.5">
        <Link v-for="genre in genres" :key="genre.id" :href="genre.url" @click.stop>
            <span :class="badge({ tone: 'genre' })">
                {{ genre.name }}
            </span>
        </Link>
    </div>
</template>
