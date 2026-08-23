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

const baseUrl = useBaseUrl()

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

const page = usePage()
const genres = computed(() => page.props.genres)
const providers = computed(() => page.props.providers)
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
                        class="btn btn-ghost btn-circle text-white hover:bg-gray-800 lg:hidden"
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
                        <div class="dropdown">
                            <label
                                tabindex="0"
                                class="btn btn-ghost btn-sm text-white hover:text-gray-300 gap-1 2xl:text-lg 2xl:h-10"
                            >
                                Genres
                                <svg class="w-4 h-4 fill-current" viewBox="0 0 20 20">
                                    <path d="M5.25 7.5L10 12.25L14.75 7.5H5.25Z" />
                                </svg>
                            </label>
                            <ul
                                tabindex="0"
                                class="dropdown-content menu menu-sm mt-1 z-[100] p-0 shadow-lg bg-black border border-gray-800 rounded-box w-48 2xl:w-56 text-white"
                            >
                                <li v-for="genre in genres" :key="genre.id">
                                    <Link :href="genre.url" class="hover:bg-gray-800 rounded-none">{{
                                        genre.name
                                    }}</Link>
                                </li>
                                <li>
                                    <Link :href="baseUrl + '/genre/all'" class="hover:bg-gray-800 rounded-none"
                                        >See more</Link
                                    >
                                </li>
                            </ul>
                        </div>
                        <div class="dropdown">
                            <label
                                tabindex="0"
                                class="btn btn-ghost btn-sm text-white hover:text-gray-300 gap-1 2xl:text-lg 2xl:h-10"
                            >
                                Providers
                                <svg class="w-4 h-4 fill-current" viewBox="0 0 20 20">
                                    <path d="M5.25 7.5L10 12.25L14.75 7.5H5.25Z" />
                                </svg>
                            </label>
                            <ul
                                tabindex="0"
                                class="dropdown-content menu menu-sm mt-1 z-[100] p-0 shadow-lg bg-black border border-gray-800 rounded-box w-48 2xl:w-56 text-white"
                            >
                                <li v-for="provider in providers" :key="provider.id">
                                    <Link :href="provider.url" class="hover:bg-gray-800 rounded-none">{{
                                        provider.name
                                    }}</Link>
                                </li>
                                <li>
                                    <Link :href="baseUrl + '/provider/all'" class="hover:bg-gray-800 rounded-none"
                                        >See more</Link
                                    >
                                </li>
                            </ul>
                        </div>
                        <Link
                            :href="baseUrl + '/tags'"
                            class="btn btn-ghost btn-sm text-white hover:text-gray-300 2xl:text-lg 2xl:h-10"
                        >
                            Browse by Tags
                        </Link>
                        <Link
                            :href="baseUrl + '/languages'"
                            class="btn btn-ghost btn-sm text-white hover:text-gray-300 2xl:text-lg 2xl:h-10"
                        >
                            Browse by Languages
                        </Link>
                        <Link
                            :href="baseUrl + '/search-all'"
                            class="btn btn-ghost btn-sm text-white hover:text-gray-300 2xl:text-lg 2xl:h-10"
                        >
                            Find more
                        </Link>
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
                            class="btn btn-ghost btn-sm text-white hidden lg:flex"
                            @click="ui.openAuthModal('login')"
                        >
                            Login
                        </button>
                        <button
                            class="btn btn-sm bg-madder text-white border-none hover:bg-red-800 hidden lg:flex"
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
