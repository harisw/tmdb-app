<script setup>
import { onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { X } from 'lucide-vue-next'

const props = defineProps({
    show: { type: Boolean, required: true },
    to: { type: [String, Object], default: 'body' },
    maxWidth: { type: String, default: 'max-w-md' },
    align: { type: String, default: 'center' }, // 'center' | 'start' (tall content, e.g. scrolls from the top)
})

const emit = defineEmits(['close'])

const containerRef = ref(null)

function close() {
    emit('close')
}

// Exposed so content that swaps in-place (e.g. TitleModal's "More Like
// This") can reset scroll position without reaching into the DOM itself.
function scrollToTop() {
    if (containerRef.value) containerRef.value.scrollTop = 0
}

defineExpose({ scrollToTop })

function handleKeydown(e) {
    if (e.key === 'Escape' && props.show) close()
}

// Locks the page behind the modal, restoring whatever overflow was there
// before. Also restored on unmount so an unmount mid-transition can't leave
// the page permanently unscrollable.
let previousOverflow = ''

watch(
    () => props.show,
    (show) => {
        if (show) {
            previousOverflow = document.body.style.overflow
            document.body.style.overflow = 'hidden'
        } else {
            document.body.style.overflow = previousOverflow
        }
    }
)

onMounted(() => window.addEventListener('keydown', handleKeydown))
onBeforeUnmount(() => {
    window.removeEventListener('keydown', handleKeydown)
    if (props.show) document.body.style.overflow = previousOverflow
})
</script>

<template>
    <Teleport :to="to">
        <Transition name="modal-fade">
            <div
                v-if="show"
                ref="containerRef"
                class="fixed inset-0 z-[110] flex justify-center bg-black/70 overflow-y-auto p-4"
                :class="align === 'start' ? 'items-start' : 'items-center'"
                role="dialog"
                aria-modal="true"
                @click.self="close"
            >
                <div
                    class="modal-panel relative bg-licorice text-white rounded-2xl shadow-2xl ring-1 ring-white/10 w-full overflow-hidden"
                    :class="[maxWidth, align === 'start' ? 'my-10' : '']"
                >
                    <slot :close="close" />

                    <slot name="close" :close="close">
                        <button
                            type="button"
                            class="btn btn-xs btn-circle btn-ghost absolute top-3 right-3 z-10 border-0 text-white/50 hover:bg-white/10 hover:text-white"
                            aria-label="Close"
                            @click="close"
                        >
                            <X :size="16" />
                        </button>
                    </slot>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
/* One transition drives both the backdrop and the panel so they can't desync.
   Only opacity/transform animate, keeping the whole thing on the compositor.
   Leave is quicker than enter: dismissing should feel like getting out of the way. */
.modal-fade-enter-active {
    transition: opacity 0.2s ease-out;
}

.modal-fade-leave-active {
    transition: opacity 0.15s ease-in;
}

.modal-fade-enter-from,
.modal-fade-leave-to {
    opacity: 0;
}

.modal-fade-enter-active .modal-panel {
    transition: transform 0.2s ease-out;
}

.modal-fade-leave-active .modal-panel {
    transition: transform 0.15s ease-in;
}

.modal-fade-enter-from .modal-panel {
    transform: scale(0.97);
}

.modal-fade-leave-to .modal-panel {
    transform: scale(0.98);
}

@media (prefers-reduced-motion: reduce) {
    .modal-fade-enter-active,
    .modal-fade-leave-active,
    .modal-fade-enter-active .modal-panel,
    .modal-fade-leave-active .modal-panel {
        transition-duration: 1ms;
    }

    .modal-fade-enter-from .modal-panel,
    .modal-fade-leave-to .modal-panel {
        transform: none;
    }
}
</style>
