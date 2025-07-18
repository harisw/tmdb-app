<template>
    <Head title="Home" />
    <AppLayout>
        <div class="p-8">
            <h1 class="text-2xl font-bold mb-6 text-madder">Hottest Movies</h1>
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-5 gap-6">
                <div v-for="item in items" :key="item.id" class="bg-white rounded shadow">
                    <div class="group [perspective:1000px] mx-auto">
                        <div class="bg-white rounded-xl shadow-xl transition-transform duration-500 transform group-hover:rotate-y-3 group-hover:-rotate-x-1 group-hover:scale-105 [transform-style:preserve-3d] overflow-hidden">

                            <img :src="poster_url + item.img_poster" alt="Movie Poster"
                                 class="w-full h-80 object-cover rounded-t-xl" />

                            <div class="p-4">
                                <h2 class="text-lg font-bold text-gray-800">{{ item.title }}</h2>
                            </div>

                        </div>
                    </div>

                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { Head } from "@inertiajs/vue3";
import AppLayout from "../Layouts/AppLayout.vue";

defineProps({
    items: Array,
    poster_url: String
})
</script>
