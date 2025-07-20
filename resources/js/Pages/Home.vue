<template>
    <Head title="Home" />
    <AppLayout>
        <div class="p-8">
            <h1 class="text-2xl font-bold mb-6 text-madder">Hottest Movies</h1>
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-5 gap-6 auto-rows-[450px]">
                <div v-for="item in items" :key="item.id" class="bg-white rounded shadow">
                    <div class="group [perspective:500px] mx-auto h-full">
                        <div class="bg-white rounded-xl shadow-xl transition-transform duration-500 transform group-hover:rotate-y-3
                        group-hover:-rotate-x-1 group-hover:scale-105 [transform-style:preserve-3d] overflow-hidden h-full">

                            <img :src="poster_url + item.img_poster" alt="Movie Poster"
                                 class="w-full h-90 object-cover rounded-t-xl" />

                            <div class="p-2">
                                <h2 class="text-lg font-bold text-gray-800">{{ item.title }}</h2>
                                <div class="flex flex-row gap-2 text-xs">
                                    <span v-for="genre in item.genres.slice(0, 3)" :key="genre.id" class="py-0.5 px-1
                                     font-semibold bg-tea-green rounded">
                                        {{genre.name}}
                                    </span>
                                </div>
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
