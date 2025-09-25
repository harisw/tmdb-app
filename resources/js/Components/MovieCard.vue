<script setup>

import RatingBadge from "./RatingBadge.vue";

const props = defineProps({
    onClick: {type: Function},
    posterUrl: {type: String},
    item: {type: Object, required: true},
    showGenres: {type: Boolean, default: true},
    size: {type: String, default: ''}
});
</script>

<template>
    <div class="bg-white rounded shadow">
        <div :class="`${size} group [perspective:500px] mx-auto h-full hover:cursor-pointer`"
             @click="props.onClick(item)"
        >
            <div class="bg-white rounded-xl shadow-xl transition-transform duration-500 transform group-hover:rotate-y-3
                        group-hover:-rotate-x-1 group-hover:scale-105 [transform-style:preserve-3d] overflow-x-hidden h-full">

                <img :src="posterUrl + item.img_poster" alt="Movie Poster"
                     class="w-full h-90 object-cover rounded-t-xl"/>

                <div class="p-2 flex flex-col max-h-48">
                    <div class="flex flex-1 justify-between">
                        <h2 class="text-md font-bold text-gray-800 line-clamp-2">
                            {{ item.title }}
                        </h2>
                        <RatingBadge :rating="item.rating" size="xs"/>
                    </div>
                    <div v-if="showGenres" class="flex flex-2 gap-2 text-xs">
                        <span v-for="genre in item.genres.slice(0, 3)" :key="genre.id" class="py-0.25 px-1
                        bg-tea-green rounded">
                            {{ genre.name }}
                        </span>
                    </div>
                </div>

            </div>
        </div>

    </div>
</template>

<style scoped>

</style>
