<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import { usePage, Link } from '@inertiajs/vue3'
import useBaseUrl from '../Composables/useBaseUrl.js'
import CsvUploadModal from '../Components/CsvUploadModal.vue'

const isGenresOpen = ref(false)
const isSettingOpen = ref(false)
const isUploadModalOpen = ref(false)
const isMobileMenuOpen = ref(false)
const baseUrl = useBaseUrl()

function toggleGenres() {
    isGenresOpen.value = !isGenresOpen.value
}

function toggleSetting() {
    isSettingOpen.value = !isSettingOpen.value
}

function toggleUploadModal() {
    isUploadModalOpen.value = true
}

function toggleMobileMenu() {
    isMobileMenuOpen.value = !isMobileMenuOpen.value
    if (!isMobileMenuOpen.value) isGenresOpen.value = false
}

function closeMobileMenu() {
    isMobileMenuOpen.value = false
    isGenresOpen.value = false
}

const page = usePage()
const genres = computed(() => page.props.genres)
const user = computed(() => page.props.auth.user)
const isAuthenticated = computed(() => !!user.value)
const menuRef = ref(null)
const settingRef = ref(null)

function handleClickOutside(ev) {
    if (menuRef.value && !menuRef.value.contains(ev.target)) {
        isGenresOpen.value = false
    }
    if (settingRef.value && !settingRef.value.contains(ev.target)) {
        isSettingOpen.value = false
    }
}

onMounted(() => {
    document.addEventListener('click', handleClickOutside)
})

onBeforeUnmount(() => {
    document.removeEventListener('click', handleClickOutside)
})
</script>

<template>
    <div class="min-h-screen bg-licorice">
        <main class="bg-licorice">
            <header class="relative">
                <nav class="fixed top-0 z-100 w-full bg-black text-white shadow-md">
                    <!-- Main nav row -->
                    <div class="max-w-[2200px] mx-auto px-6 py-4 2xl:py-6 flex items-center justify-between">
                        <!-- Left: Logo + Desktop links -->
                        <div class="flex items-center gap-6 2xl:gap-10">
                            <Link :href="baseUrl" class="text-3xl 2xl:text-5xl font-bold text-madder">Chill</Link>

                            <ul ref="menuRef" class="hidden md:flex gap-4 2xl:gap-8 text-sm 2xl:text-lg">
                                <li class="relative">
                                    <button
                                        class="flex items-center gap-1 hover:text-gray-300 cursor-pointer"
                                        @click="toggleGenres"
                                    >
                                        Genres
                                        <svg
                                            class="w-4 h-4 transition-transform"
                                            :class="{ 'rotate-180': isGenresOpen }"
                                            fill="currentColor"
                                            viewBox="0 0 20 20"
                                        >
                                            <path d="M5.25 7.5L10 12.25L14.75 7.5H5.25Z" />
                                        </svg>
                                    </button>
                                    <ul
                                        v-if="isGenresOpen"
                                        class="absolute top-8 2xl:top-12 left-0 w-40 2xl:w-56 bg-black rounded shadow-lg text-sm 2xl:text-lg z-50"
                                    >
                                        <li v-for="genre in genres" :key="genre.id">
                                            <Link :href="genre.url" class="block px-4 py-2 hover:bg-gray-800">
                                                {{ genre.name }}
                                            </Link>
                                        </li>
                                        <li>
                                            <Link
                                                :href="baseUrl + '/genre/all'"
                                                class="block px-4 py-2 hover:bg-gray-800"
                                            >
                                                See more
                                            </Link>
                                        </li>
                                    </ul>
                                </li>
                                <li>
                                    <Link :href="baseUrl + '/tags'" class="hover:text-gray-300">Browse by Tags</Link>
                                </li>
                                <li>
                                    <Link :href="baseUrl + '/languages'" class="hover:text-gray-300"
                                        >Browse by Languages</Link
                                    >
                                </li>
                                <li>
                                    <Link :href="baseUrl + '/search-all'" class="hover:text-gray-300">Find more</Link>
                                </li>
                            </ul>
                        </div>

                        <!-- Right: Profile (desktop) + Hamburger (mobile) -->
                        <div class="flex items-center gap-4">
                            <div
                                v-if="isAuthenticated"
                                ref="settingRef"
                                class="hidden md:block relative text-sm 2xl:text-lg"
                                @click="toggleSetting"
                            >
                                <div class="flex items-center gap-2 cursor-pointer">
                                    {{ user.name }}
                                    <svg
                                        class="w-4 h-4 2xl:w-6 2xl:h-6 fill-white transition-transform"
                                        :class="{ 'rotate-180': isSettingOpen }"
                                        viewBox="0 0 20 20"
                                    >
                                        <path d="M5.25 7.5L10 12.25L14.75 7.5H5.25Z" />
                                    </svg>
                                </div>
                                <div
                                    v-if="isSettingOpen"
                                    class="absolute right-0 mt-2 w-40 2xl:w-56 bg-black text-sm 2xl:text-lg rounded shadow-lg z-50"
                                >
                                    <span
                                        class="block px-4 py-2 hover:bg-gray-800 cursor-pointer"
                                        @click="toggleUploadModal"
                                        >Upload Data</span
                                    >
                                    <a href="#" class="block px-4 py-2 hover:bg-gray-800">Logout</a>
                                </div>
                            </div>

                            <!-- Hamburger button -->
                            <button
                                class="md:hidden p-2 rounded text-white hover:text-gray-300 cursor-pointer"
                                aria-label="Toggle menu"
                                @click="toggleMobileMenu"
                            >
                                <svg
                                    v-if="!isMobileMenuOpen"
                                    class="w-6 h-6"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M4 6h16M4 12h16M4 18h16"
                                    />
                                </svg>
                                <svg v-else class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M6 18L18 6M6 6l12 12"
                                    />
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- Mobile menu dropdown -->
                    <div
                        v-if="isMobileMenuOpen"
                        class="md:hidden border-t border-gray-800 px-6 py-4 flex flex-col gap-4 text-sm max-w-[2200px] mx-auto"
                    >
                        <!-- Genres accordion -->
                        <div>
                            <button
                                class="flex items-center gap-1 w-full hover:text-gray-300 cursor-pointer"
                                @click="toggleGenres"
                            >
                                Genres
                                <svg
                                    class="w-4 h-4 transition-transform"
                                    :class="{ 'rotate-180': isGenresOpen }"
                                    fill="currentColor"
                                    viewBox="0 0 20 20"
                                >
                                    <path d="M5.25 7.5L10 12.25L14.75 7.5H5.25Z" />
                                </svg>
                            </button>
                            <ul v-if="isGenresOpen" class="pl-4 mt-2 flex flex-col gap-1">
                                <li v-for="genre in genres" :key="genre.id">
                                    <Link
                                        :href="genre.url"
                                        class="block py-1 hover:text-gray-300"
                                        @click="closeMobileMenu"
                                        >{{ genre.name }}</Link
                                    >
                                </li>
                                <li>
                                    <Link
                                        :href="baseUrl + '/genre/all'"
                                        class="block py-1 hover:text-gray-300"
                                        @click="closeMobileMenu"
                                        >See more</Link
                                    >
                                </li>
                            </ul>
                        </div>
                        <Link :href="baseUrl + '/tags'" class="hover:text-gray-300" @click="closeMobileMenu"
                            >Browse by Tags</Link
                        >
                        <Link :href="baseUrl + '/languages'" class="hover:text-gray-300" @click="closeMobileMenu"
                            >Browse by Languages</Link
                        >
                        <Link :href="baseUrl + '/search-all'" class="hover:text-gray-300" @click="closeMobileMenu"
                            >Find more</Link
                        >

                        <!-- Profile links on mobile -->
                        <template v-if="isAuthenticated">
                            <hr class="border-gray-800" />
                            <span
                                class="cursor-pointer hover:text-gray-300"
                                @click="
                                    () => {
                                        toggleUploadModal()
                                        closeMobileMenu()
                                    }
                                "
                                >Upload Data</span
                            >
                            <a href="#" class="hover:text-gray-300">Logout</a>
                        </template>
                    </div>
                </nav>
            </header>

            <div class="max-w-[2200px] mx-auto px-4 pt-24 pb-8 md:px-10 md:pt-28 lg:px-20 2xl:px-28 2xl:pt-36">
                <slot />
            </div>
        </main>
    </div>
    <CsvUploadModal :is-open="isUploadModalOpen" />
</template>
