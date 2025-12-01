<script setup>
import {ref, computed, onMounted, onBeforeUnmount} from 'vue';
import {usePage, Link} from '@inertiajs/vue3';
import useBaseUrl from "../Composables/useBaseUrl.js";
import CsvUploadModal from "../Components/CsvUploadModal.vue";

const isGenresOpen = ref(false);
const isSettingOpen = ref(false);
const isUploadModalOpen = ref(false);
const baseUrl = useBaseUrl();

function toggleGenres() {
    isGenresOpen.value = !isGenresOpen.value;
}

function toggleSetting() {
    isSettingOpen.value = !isSettingOpen.value;
}

function toggleUploadModal() {
    isUploadModalOpen.value = true;
}

const page = usePage()
const genres = computed(() => page.props.genres);
const user = computed(() => page.props.auth.user);
const isAuthenticated = computed(() => !!user.value);
const menuRef = ref(null);
const settingRef = ref(null);

function handleClickOutside(ev) {
    if (menuRef.value && !menuRef.value.contains(ev.target)) {
        isGenresOpen.value = false;
    }

    if (settingRef.value && !settingRef.value.contains(ev.target)) {
        isSettingOpen.value = false;
    }
}

onMounted(() => {
    document.addEventListener('click', handleClickOutside)
});

onBeforeUnmount(() => {
    document.removeEventListener('click', handleClickOutside)
});
</script>

<!-- resources/js/Layouts/AppLayout.vue -->
<template>
    <div class="min-h-screen bg-licorice">
        <main class="bg-licorice">
            <header class="relative">
                <nav
                    class="fixed top-0 z-100 w-full bg-black text-white px-6 py-4 flex items-center justify-between shadow-md">
                    <!-- Left: Logo + Links -->
                    <div class="flex items-center gap-6">
                        <Link :href="baseUrl" class="text-3xl font-bold text-madder">TMDB</Link>

                        <ul class="hidden md:flex gap-4 text-sm" ref="menuRef">

                            <li class="relative">
                                <button @click="toggleGenres"
                                        class="flex items-center gap-1 hover:text-gray-300 cursor-pointer">
                                    Genres
                                    <svg class="w-4 h-4 transition-transform" :class="{ 'rotate-180': isGenresOpen }"
                                         fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M5.25 7.5L10 12.25L14.75 7.5H5.25Z"/>
                                    </svg>
                                </button>

                                <!-- Dropdown menu -->
                                <ul v-if="isGenresOpen"
                                    class="absolute top-8 left-0 w-40 bg-black rounded shadow-lg text-sm z-50">
                                    <li v-for="genre in genres" :key="genre.id">
                                        <Link :href="genre.url" class="block px-4 py-2 hover:bg-gray-800">
                                            {{ genre.name }}
                                        </Link>
                                    </li>
                                    <li>
                                        <Link :href="baseUrl+'/genre/all'" class="block px-4 py-2 hover:bg-gray-800">See
                                            more
                                        </Link>
                                    </li>
                                </ul>
                            </li>
                            <li>
                                <Link :href="baseUrl+'/tags'" class="hover:text-gray-300">Browse by Tags</Link>
                            </li>
                            <li>
                                <Link :href="baseUrl+'/languages'" class="hover:text-gray-300">Browse by Languages
                                </Link>
                            </li>
                            <li>
                                <Link :href="baseUrl+'/search-all'" class="hover:text-gray-300">Find more</Link>
                            </li>
                        </ul>
                    </div>

                    <!-- Right: Profile Dropdown -->
                    <div v-if="isAuthenticated" class="relative group" ref="settingRef" @click="toggleSetting">
                        <div class="flex items-center gap-2 cursor-pointer">
                            {{ user.name }}
                            <svg class="w-4 h-4 fill-white transition-transform"
                                 :class="{ 'rotate-180': isSettingOpen }"
                                 viewBox="0 0 20 20">
                                <path d="M5.25 7.5L10 12.25L14.75 7.5H5.25Z"/>
                            </svg>
                        </div>
                        <div
                            class="absolute right-0 mt-2 w-40 bg-black text-sm rounded shadow-lg z-50"
                            v-if="isSettingOpen">
                            <span @click="toggleUploadModal"
                                  class="block px-4 py-2 hover:bg-gray-800 cursor-pointer">Upload Data</span>
                            <a href="#" class="block px-4 py-2 hover:bg-gray-800">Logout</a>
                        </div>
                    </div>
                </nav>
            </header>

            <div class="p-20">
                <slot/>
            </div>
        </main>

        <!--        <footer class="bg-white shadow p-4 text-center">-->
        <!--            My App Footer-->
        <!--        </footer>-->
    </div>
    <CsvUploadModal :is-open="isUploadModalOpen"/>
</template>
