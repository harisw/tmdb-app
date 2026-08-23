<script setup>
import { computed } from 'vue'
import { usePage, Link } from '@inertiajs/vue3'
import { X } from 'lucide-vue-next'
import useBaseUrl from '../Composables/useBaseUrl.js'
import useMobileDrawer from '../Composables/useMobileDrawer.js'
import AccountMenu from './AccountMenu.vue'
import CountrySwitcher from './CountrySwitcher.vue'
import { useUiStore } from '../stores/ui.js'

const baseUrl = useBaseUrl()
const ui = useUiStore()
const { closeMobileDrawer } = useMobileDrawer()

const page = usePage()
const genres = computed(() => page.props.genres)
const providers = computed(() => page.props.providers)
const isAuthenticated = computed(() => !!page.props.auth.user)

function openAuthModal(tab) {
    closeMobileDrawer()
    ui.openAuthModal(tab)
}
</script>

<template>
    <div class="flex h-full w-72 max-w-[85vw] flex-col bg-black text-white">
        <div class="flex items-center justify-between p-4 border-b border-gray-800">
            <span class="text-lg font-semibold text-madder">Menu</span>
            <label
                for="mobile-nav-drawer"
                aria-label="Close menu"
                class="btn btn-ghost btn-sm btn-circle text-white hover:bg-gray-800"
            >
                <X :size="18" />
            </label>
        </div>

        <!-- `flex-1` claims whatever height the header/footer don't use, so a
             short link list doesn't leave a slab of dead space beneath it. -->
        <ul class="menu menu-sm flex-1 overflow-y-auto p-2">
            <li>
                <details>
                    <summary>Genres</summary>
                    <ul>
                        <li v-for="genre in genres" :key="genre.id">
                            <Link :href="genre.url" @click="closeMobileDrawer">{{ genre.name }}</Link>
                        </li>
                        <li>
                            <Link :href="baseUrl + '/genre/all'" @click="closeMobileDrawer">See more</Link>
                        </li>
                    </ul>
                </details>
            </li>
            <li>
                <details>
                    <summary>Providers</summary>
                    <ul>
                        <li v-for="provider in providers" :key="provider.id">
                            <Link :href="provider.url" @click="closeMobileDrawer">{{ provider.name }}</Link>
                        </li>
                        <li>
                            <Link :href="baseUrl + '/provider/all'" @click="closeMobileDrawer">See more</Link>
                        </li>
                    </ul>
                </details>
            </li>
            <li>
                <Link :href="baseUrl + '/tags'" @click="closeMobileDrawer">Browse by Tags</Link>
            </li>
            <li>
                <Link :href="baseUrl + '/languages'" @click="closeMobileDrawer">Browse by Languages</Link>
            </li>
            <li>
                <Link :href="baseUrl + '/search-all'" @click="closeMobileDrawer">Find more</Link>
            </li>
        </ul>

        <!-- Account + country: same controls the desktop navbar shows at `lg`
             and up, just relocated here so they aren't floating over the
             drawer on small screens. Dropdowns open upward (`dropTop`) since
             this bar sits at the very bottom of the viewport. -->
        <div class="flex items-center justify-between gap-2 border-t border-gray-800 p-3">
            <AccountMenu v-if="isAuthenticated" drop-top />
            <div v-else class="flex gap-2">
                <button class="btn btn-ghost btn-sm text-white" @click="openAuthModal('login')">Login</button>
                <button
                    class="btn btn-sm bg-madder text-white border-none hover:bg-red-800"
                    @click="openAuthModal('register')"
                >
                    Register
                </button>
            </div>
            <CountrySwitcher drop-top />
        </div>
    </div>
</template>
