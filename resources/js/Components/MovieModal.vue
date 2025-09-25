<script setup>
    import { onMounted, onUnmounted } from "vue";
    import { X } from "lucide-vue-next";
    import RatingBadge from "./RatingBadge.vue";

    const props = defineProps({
        show: { type: Boolean, required: true},
        item: { type: Object, default: null },
        backdrop_url: { type: String, default: null}
    });

    const emits = defineEmits(['close']);

    function close() {
        emits('close');
    }

    function handleKey(e) {
        if (e.key === 'Escape') close()
    }

    onMounted(() => window.addEventListener("keydown", handleKey))
    onUnmounted(() => window.removeEventListener("keydown", handleKey))
</script>

<template>
    <Teleport to="body">
        <transition name="fade">
            <div
                v-if="show"
                class="fixed inset-0 z-50 flex items-start justify-center bg-black/80 overflow-y-auto mt-8"
                @click.self="close"
            >
                <div class="relative bg-zinc-900 text-white rounded-xl shadow-2xl max-w-3xl w-full my-10 overflow-hidden">
                    <button
                        class="absolute top-2 right-2 hover:cursor-pointer rounded-full bg-gray-700 p-0.5"
                        @click="close"
                    >
                        <X color="#ffffff" :stroke-width="2.5" absoluteStrokeWidth />
                    </button>

<!--                    Modal contents-->
                    <div v-if="item">
                        <!-- Large image -->
                        <div class="w-full h-72 md:h-96 bg-black">
                            <img
                                :src="backdrop_url + item.img_backdrop"
                                alt="Poster"
                                class="w-full h-full object-cover"
                            />
                        </div>
                        <div class="p-5">
                            <h2 class="text-3xl font-bold mb-3">{{item.title}}</h2>
                            <div class="flex justify-between gap-5">
                                <div class="flex flex-col gap-2 text-slate-500">
                                    <div><RatingBadge :rating="item.rating" size="sm" /> </div>
                                    <h3 class="text-lg font-semibold">{{item.release_date}} {{item.runtime}}</h3>
                                    <p class="text-gray-300 leading-relaxed">
                                        {{item.overview}}
                                    </p>
                                </div>

<!--                                Genres-->
                                <div class="flex flex-col gap-2 text-sm">
                                    <p><span class="text-slate-500">Genres: </span>
                                        <span v-for="(genre, i) in item.genres" :key="i" class="mx-0.5 hover:underline
                                        hover:cursor-pointer">
                                    {{genre.name}}<span v-if="i < item.genres.length -1">, </span>
                                </span>
                                    </p>

                                    <!-- Tags-->
                                    <p><span class="text-slate-500">Tags: </span>
                                        <span v-for="(keyword, i) in item.keywords" :key="i" class="mx-0.5 hover:underline
                                        hover:cursor-pointer">
                                    {{keyword.name}}<span v-if="i < item.keywords.length -1">, </span>
                                </span>
                                    </p>
                                </div>

                            </div>

                        </div>
                    </div>
                </div>
            </div>
        </transition>
    </Teleport>
</template>

<style scoped>
    .fade-enter-active,
    .fade-leave-active {
        transition: opacity 0.5s ease;
    }
    .fade-enter-from,
    .fade-leave-to {
        opacity: 0;
    }
</style>
