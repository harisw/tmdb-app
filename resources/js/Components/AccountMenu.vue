<script setup>
import { computed } from 'vue'
import { usePage, Link } from '@inertiajs/vue3'
import useMobileDrawer from '../Composables/useMobileDrawer.js'
import { useUiStore } from '../stores/ui.js'

// `dropTop` is for the mobile sidebar's bottom bar, where the trigger sits at
// the bottom of the screen and the menu needs to open upward instead.
defineProps({
    dropTop: { type: Boolean, default: false },
})

const ui = useUiStore()
const { closeMobileDrawer } = useMobileDrawer()

const page = usePage()
const user = computed(() => page.props.auth.user)

function openProfileModal() {
    closeMobileDrawer()
    ui.openProfileModal()
}
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
        <ul
            tabindex="0"
            class="dropdown-content menu menu-sm z-[100] p-2 shadow-lg bg-black border border-gray-800 rounded-box w-44 2xl:w-48 text-white"
            :class="dropTop ? 'mb-3' : 'mt-3'"
        >
            <li class="menu-title text-sm">@{{ user.username }}</li>
            <li>
                <button class="w-full hover:bg-gray-800" @click="openProfileModal">Update Profile</button>
            </li>
            <li>
                <Link
                    href="/logout"
                    method="post"
                    as="button"
                    class="w-full hover:bg-gray-800"
                    @click="closeMobileDrawer"
                >
                    Logout
                </Link>
            </li>
        </ul>
    </div>
</template>
