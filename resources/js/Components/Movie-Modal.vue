<script setup>
    import { onMounted, onUnmounted } from "vue";

    const props = defineProps({
        show: { type: Boolean, required: true},
        item: { type: Object, default: null }
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
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
                @click.self="close"
            >
                <div class="bg-white rounded-2xl shadow-xl max-w-lg w-full p-6 relative">
                    <button
                        class="absolute top-3 right-3 text-gray-400 hover:text-gray-600"
                        @click="close"
                    >
                        X
                    </button>

<!--                    Modal contents-->
                    <div v-if="item">
                        <h2 class="text-xl font-bold mb-2">{{item.title}}</h2>
                    </div>
                </div>
            </div>
        </transition>
    </Teleport>
</template>

<style scoped>
    .fade-enter-active,
    .fade-leave-active {
        transition: opacity 0.3s;
    }
    .fade-enter-from,
    .fade-leave-to {
        opacity: 0;
    }
</style>
