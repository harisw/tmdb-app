<script setup>
import { computed, watch } from 'vue'
import { storeToRefs } from 'pinia'
import { usePage, Link, router } from '@inertiajs/vue3'
import { Play } from 'lucide-vue-next'
import useBaseUrl from '../Composables/useBaseUrl.js'
import AccountMenu from './AccountMenu.vue'
import CountrySwitcher from './CountrySwitcher.vue'
import { useTitleTypeStore } from '../stores/titleType.js'
import { useUiStore } from '../stores/ui.js'
import DropdownMenu from './DropdownMenu.vue'
import { button } from '../variants/button.js'

const baseUrl = useBaseUrl()
const page = usePage()

// Global modal state now lives in the ui store; the modals themselves are
// hosted in AppLayout, so Navbar only needs the "open" actions.
const ui = useUiStore()

// `storeToRefs` keeps state reactive when destructured; actions are taken
// straight off the store (they don't need it).
const titleTypeStore = useTitleTypeStore()
const { titleType } = storeToRefs(titleTypeStore)
watch(titleType, () => router.visit(window.location.pathname, { preserveState: false, preserveScroll: false }))

const typeOptions = [
    { value: '', label: 'All' },
    { value: 'movie', label: 'Film' },
    { value: 'tv', label: 'TV' },
]
const pillIndex = computed(() => typeOptions.findIndex((o) => o.value === titleType.value))

const topGenres = computed(() => [
    ...page.props.topGenres.map((genre) => ({
        url: genre.url,
        label: genre.name,
    })),
    { url: baseUrl + '/genre/all', label: 'See more' },
])

const topProviders = computed(() => [
    ...page.props.topProviders.map((provider) => ({
        url: provider.url,
        label: provider.name,
    })),
    { url: baseUrl + '/provider/all', label: 'See more' },
])

const browseByMenu = [
    { url: baseUrl + '/tags', label: 'By Tags' },
    { url: baseUrl + '/languages', label: 'By Languages' },
]

const userListBaseUrl = baseUrl + '/user/titles'
const userListMenu = [
    { url: userListBaseUrl, label: 'My Lists' },
    { url: userListBaseUrl + '/liked', label: 'Liked Titles' },
    { url: userListBaseUrl + '/watched', label: 'Watched Titles' },
    { url: userListBaseUrl + '/bookmarked', label: 'Bookmarked Titles' },
]

const user = computed(() => page.props.auth.user)
const isAuthenticated = computed(() => !!user.value)
</script>

<template>
    <header>
        <nav class="navbar fixed top-0 z-[100] bg-black text-white shadow-md min-h-16 2xl:min-h-20 px-0">
            <div class="max-w-[2200px] w-full mx-auto px-4 2xl:px-8 flex items-center">
                <!-- Start: hamburger (mobile) + logo -->
                <div class="navbar-start gap-2">
                    <label
                        for="mobile-nav-drawer"
                        aria-label="Open menu"
                        :class="button({ intent: 'ghost', circle: true, class: 'lg:hidden' })"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M4 6h16M4 12h16M4 18h16"
                            />
                        </svg>
                    </label>

                    <Link :href="baseUrl" class="flex items-center gap-1.5">
                        <Play :size="14" class="text-madder fill-madder 2xl:hidden" />
                        <Play :size="20" class="text-madder fill-madder hidden 2xl:block" />
                        <span class="text-3xl 2xl:text-5xl font-semibold text-madder tracking-tight">Chill</span>
                    </Link>

                    <div class="relative flex bg-white/10 rounded-full p-1">
                        <div
                            class="absolute inset-y-1 left-1 w-[calc((100%-8px)/3)] bg-madder rounded-full transition-transform duration-200 ease-in-out"
                            :style="{ transform: `translateX(calc(${pillIndex} * 100%))` }"
                        ></div>
                        <button
                            v-for="opt in typeOptions"
                            :key="opt.value"
                            class="relative z-10 flex-1 px-3 py-0.5 text-xs 2xl:text-sm font-medium transition-colors duration-200 cursor-pointer"
                            :class="titleType === opt.value ? 'text-white' : 'text-white/60 hover:text-white/90'"
                            @click="titleTypeStore.setTitleType(opt.value)"
                        >
                            {{ opt.label }}
                        </button>
                    </div>
                </div>

                <!-- Center: desktop nav links -->
                <div class="navbar-center hidden lg:flex">
                    <div class="flex items-center gap-1 text-sm 2xl:text-lg">
                        <DropdownMenu :submenus="topGenres" label="Genres" />
                        <DropdownMenu :submenus="topProviders" label="Providers" />
                        <DropdownMenu :submenus="browseByMenu" label="Browse by" />
                        <DropdownMenu :submenus="userListMenu" label="My List" />
                    </div>
                </div>

                <!-- End: auth / profile -->
                <div class="navbar-end gap-2">
                    <!-- Mobile gets these in the sliding sidebar's bottom bar instead
                         (see MobileSidebar.vue) so they aren't stranded next to the
                         hamburger, floating above the drawer. -->
                    <CountrySwitcher class="hidden lg:inline-block" />
                    <template v-if="!isAuthenticated">
                        <button
                            :class="button({ intent: 'link', class: 'hidden lg:flex' })"
                            @click="ui.openAuthModal('login')"
                        >
                            Login
                        </button>
                        <button
                            :class="button({ intent: 'primary', class: 'hidden lg:flex' })"
                            @click="ui.openAuthModal('register')"
                        >
                            Register
                        </button>
                    </template>
                    <AccountMenu v-if="isAuthenticated" class="hidden lg:inline-block" />
                </div>
            </div>
        </nav>
    </header>
</template>
