<script setup>
import { computed } from 'vue'
import { usePage, Link } from '@inertiajs/vue3'
import useMobileDrawer from '../Composables/useMobileDrawer.js'
import { menu } from '../variants/menu.js'

// `dropTop` is for the mobile sidebar's bottom bar, where the trigger sits at
// the bottom of the screen and the menu needs to open upward instead.
const props = defineProps({
    dropTop: { type: Boolean, default: false },
})

const { closeMobileDrawer } = useMobileDrawer()

const page = usePage()
const user = computed(() => page.props.auth.user)

const { content, item } = menu({ width: 'sm', pad: 'sm', offset: props.dropTop ? 'up' : 'gap' })
</script>

<template>
    <div class="dropdown dropdown-end" :class="{ 'dropdown-top': dropTop }">
        <label tabindex="0" class="cursor-pointer">
            <div class="avatar">
                <div class="w-9 2xl:w-11 rounded-full ring-2 ring-madder ring-offset-1 ring-offset-black">
                    <img :src="user.avatar_url" :alt="user.avatar_url" />
                </div>
            </div>
        </label>
        <ul tabindex="0" :class="content()">
            <li class="menu-title text-sm">@{{ user.username }}</li>
            <li>
                <Link href="/settings" :class="item({ class: 'w-full' })" @click="closeMobileDrawer">Settings</Link>
            </li>
            <li>
                <Link
                    href="/logout"
                    method="post"
                    as="button"
                    :class="item({ class: 'w-full' })"
                    @click="closeMobileDrawer"
                >
                    Logout
                </Link>
            </li>
        </ul>
    </div>
</template>
