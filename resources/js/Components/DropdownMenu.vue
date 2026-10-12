<script setup>
import { Link } from '@inertiajs/vue3'
import { menu } from '../variants/menu.js'
import { button } from '../variants/button.js'

defineProps({
    label: { type: String, required: true },
    submenus: { type: Array, required: true },
})

const { content, item } = menu({ width: 'md', pad: 'none', offset: 'top' })
</script>

<template>
    <div class="dropdown">
        <label tabindex="0" :class="button({ intent: 'link', responsive: true, class: 'gap-1' })">
            {{ label }}
            <svg class="w-4 h-4 fill-current" viewBox="0 0 20 20">
                <path d="M5.25 7.5L10 12.25L14.75 7.5H5.25Z" />
            </svg>
        </label>
        <ul tabindex="0" :class="content()">
            <li v-for="submenu in submenus" :key="submenu.url">
                <Link :href="submenu.url" :class="item()">{{ submenu.label }}</Link>
            </li>
        </ul>
    </div>
</template>
