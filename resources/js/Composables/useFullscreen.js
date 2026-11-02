import { onBeforeUnmount, onMounted, ref } from 'vue'

export function useFullscreen(target) {
    const isFullscreen = ref(false)

    function toggle() {
        if (document.fullscreenElement) {
            document.exitFullscreen()
        } else {
            target.value?.requestFullscreen?.().catch(() => {})
        }
    }

    function sync() {
        isFullscreen.value = document.fullscreenElement === target.value
    }

    onMounted(() => document.addEventListener('fullscreenchange', sync))
    onBeforeUnmount(() => document.removeEventListener('fullscreenchange', sync))

    return { isFullscreen, toggle }
}
