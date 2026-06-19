<script setup>
import { ref, computed } from 'vue'
import { usePage, Link } from '@inertiajs/vue3'
import useBaseUrl from '../Composables/useBaseUrl.js'
import AuthModal from '../Components/AuthModal.vue'
import ProfileModal from '../Components/ProfileModal.vue'
import Toast from '../Components/Toast.vue'

const isAuthModalOpen = ref(false)
const authModalTab = ref('login')
const isProfileModalOpen = ref(false)
const baseUrl = useBaseUrl()

function openAuthModal(tab) {
    authModalTab.value = tab
    isAuthModalOpen.value = true
}

const page = usePage()
const genres = computed(() => page.props.genres)
const providers = computed(() => page.props.providers)
const user = computed(() => page.props.auth.user)
const isAuthenticated = computed(() => !!user.value)
</script>

<template>
    <div class="min-h-screen bg-licorice">
        <main class="bg-licorice">
            <header>
                <nav class="navbar fixed top-0 z-[100] bg-black text-white shadow-md min-h-16 2xl:min-h-20 px-0">
                    <div class="max-w-[2200px] w-full mx-auto px-4 2xl:px-8 flex items-center">
                        <!-- Start: hamburger (mobile) + logo -->
                        <div class="navbar-start gap-2">
                            <div class="dropdown lg:hidden">
                                <label tabindex="0" class="btn btn-ghost btn-circle text-white hover:bg-gray-800">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M4 6h16M4 12h16M4 18h16"
                                        />
                                    </svg>
                                </label>
                                <ul
                                    tabindex="0"
                                    class="menu menu-sm dropdown-content mt-3 z-[100] p-2 shadow-lg bg-black border border-gray-800 rounded-box w-56 text-white"
                                >
                                    <li>
                                        <details>
                                            <summary>Genres</summary>
                                            <ul>
                                                <li v-for="genre in genres" :key="genre.id">
                                                    <Link :href="genre.url">{{ genre.name }}</Link>
                                                </li>
                                                <li>
                                                    <Link :href="baseUrl + '/genre/all'">See more</Link>
                                                </li>
                                            </ul>
                                        </details>
                                    </li>
                                    <li>
                                        <Link :href="baseUrl + '/tags'">Browse by Tags</Link>
                                    </li>
                                    <li>
                                        <Link :href="baseUrl + '/languages'">Browse by Languages</Link>
                                    </li>
                                    <li>
                                        <Link :href="baseUrl + '/search-all'">Find more</Link>
                                    </li>
                                    <template v-if="isAuthenticated">
                                        <div class="divider my-1 border-gray-800"></div>
                                        <li>
                                            <Link href="/logout" method="post" as="button" class="w-full">Logout</Link>
                                        </li>
                                    </template>
                                    <template v-else>
                                        <div class="divider my-1 border-gray-800"></div>
                                        <li>
                                            <button class="w-full text-left" @click="openAuthModal('login')">
                                                Login
                                            </button>
                                        </li>
                                        <li>
                                            <button class="w-full text-left" @click="openAuthModal('register')">
                                                Register
                                            </button>
                                        </li>
                                    </template>
                                </ul>
                            </div>

                            <Link :href="baseUrl" class="text-3xl 2xl:text-5xl font-bold text-madder">Chill</Link>
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
                                            <Link :href="genre.url" class="hover:bg-gray-800 rounded-none"
                                                >{{ genre.name }}
                                            </Link>
                                        </li>
                                        <li>
                                            <Link :href="baseUrl + '/genre/all'" class="hover:bg-gray-800 rounded-none"
                                                >See more
                                            </Link>
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
                            <template v-if="!isAuthenticated">
                                <button
                                    class="btn btn-ghost btn-sm text-white hidden lg:flex"
                                    @click="openAuthModal('login')"
                                >
                                    Login
                                </button>
                                <button
                                    class="btn btn-sm bg-madder text-white border-none hover:bg-red-800 hidden lg:flex"
                                    @click="openAuthModal('register')"
                                >
                                    Register
                                </button>
                            </template>
                            <div v-if="isAuthenticated" class="dropdown dropdown-end">
                                <label tabindex="0" class="cursor-pointer">
                                    <div class="avatar">
                                        <div
                                            class="w-9 2xl:w-11 rounded-full ring-2 ring-madder ring-offset-1 ring-offset-black"
                                        >
                                            <img :src="user.avatar_url" :alt="user.avatar_url" />
                                        </div>
                                    </div>
                                </label>
                                <ul
                                    tabindex="0"
                                    class="dropdown-content menu menu-sm mt-3 z-[100] p-2 shadow-lg bg-black border border-gray-800 rounded-box w-44 2xl:w-48 text-white"
                                >
                                    <li class="menu-title text-sm">@{{ user.username }}</li>
                                    <li>
                                        <button class="w-full hover:bg-gray-800" @click="isProfileModalOpen = true">
                                            Update Profile
                                        </button>
                                    </li>
                                    <li>
                                        <Link href="/logout" method="post" as="button" class="w-full hover:bg-gray-800"
                                            >Logout
                                        </Link>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </nav>
            </header>

            <div class="max-w-[2200px] mx-auto px-4 pt-24 pb-14 md:px-10 md:pt-28 lg:px-20 2xl:px-28 2xl:pt-36">
                <slot />
            </div>
        </main>

        <AuthModal :is-open="isAuthModalOpen" :initial-tab="authModalTab" @close="isAuthModalOpen = false" />
        <ProfileModal :is-open="isProfileModalOpen" @close="isProfileModalOpen = false" />
        <Toast />
    </div>
</template>
