<script setup>
import { onMounted, onUnmounted } from 'vue'
import { usePage, Link } from '@inertiajs/vue3'
import { X, Clock, Calendar } from 'lucide-vue-next'
import RatingBadge from './RatingBadge.vue'
import ProvidersBadge from './ProvidersBadge.vue'
import GenreBadges from './GenreBadges.vue'

const props = defineProps({
    show: { type: Boolean, required: true },
    item: { type: Object, default: null },
})

const page = usePage()
const emits = defineEmits(['close'])

function close() {
    emits('close')
}

function handleKey(e) {
    if (e.key === 'Escape') close()
}

onMounted(() => window.addEventListener('keydown', handleKey))
onUnmounted(() => window.removeEventListener('keydown', handleKey))
</script>

<template>
    <Teleport to="body">
        <transition name="fade">
            <div
                v-if="show"
                class="fixed inset-0 z-50 flex items-start justify-center bg-black/80 overflow-y-auto p-4"
                @click.self="close"
            >
                <div
                    class="relative bg-licorice text-white rounded-2xl shadow-2xl ring-1 ring-white/10 max-w-3xl w-full my-10 overflow-hidden"
                >
                    <button
                        class="btn btn-sm btn-circle btn-neutral absolute top-3 right-3 z-10 bg-black/50 border-0 hover:bg-black/70"
                        aria-label="Close"
                        @click="close"
                    >
                        <X :size="18" :stroke-width="2.5" />
                    </button>

                    <div v-if="item">
                        <!-- Backdrop with scrim + overlaid title/meta -->
                        <div class="relative">
                            <img
                                :src="page.props.base_url.backdrop + item.backdrop_path"
                                :alt="`${item.name} backdrop`"
                                class="w-full h-64 md:h-96 object-cover"
                            />
                            <div
                                class="absolute inset-0 bg-gradient-to-t from-licorice via-licorice/40 to-transparent"
                            ></div>

                            <div class="absolute bottom-0 inset-x-0 p-5">
                                <h2 class="text-2xl md:text-4xl font-bold drop-shadow-lg">{{ item.name }}</h2>
                                <div class="mt-2 flex flex-wrap items-center gap-3 text-sm text-white/80">
                                    <RatingBadge :rating="item.rating" />
                                    <span v-if="item.release_date" class="flex items-center gap-1">
                                        <Calendar :size="15" /> {{ item.release_date }}
                                    </span>
                                    <span v-if="item.runtime" class="flex items-center gap-1">
                                        <Clock :size="15" /> {{ item.runtime }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Body -->
                        <div class="p-5 space-y-5">
                            <p v-if="item.overview" class="text-gray-300 leading-relaxed">
                                {{ item.overview }}
                            </p>

                            <div v-if="item.title_providers?.length" class="space-y-2">
                                <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-400">
                                    Where to watch
                                </h3>
                                <ProvidersBadge :title-providers="item.title_providers" />
                            </div>

                            <div v-if="item.genres?.length" class="space-y-2">
                                <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-400">Genres</h3>
                                <GenreBadges :genres="item.genres" />
                            </div>

                            <div v-if="item.keywords?.length" class="space-y-2">
                                <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-400">Tags</h3>
                                <div class="flex flex-wrap gap-1.5">
                                    <Link v-for="keyword in item.keywords" :key="keyword.id" :href="keyword.url">
                                        <span class="badge badge-outline badge-sm px-2 bg-gray/20 text-gray-300">
                                            {{ keyword.name }}
                                        </span>
                                    </Link>
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
    transition: opacity 0.3s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
</style>
