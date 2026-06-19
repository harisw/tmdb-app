<script setup>
import { ref, watch } from 'vue'
import { usePage } from '@inertiajs/vue3'

const page = usePage()
const visible = ref(false)
const message = ref('')

watch(
    () => page.props.flash?.message,
    (val) => {
        if (val) {
            message.value = val
            visible.value = true
            setTimeout(() => (visible.value = false), 4000)
        }
    },
    { immediate: true }
)
</script>

<template>
    <Transition
        enter-active-class="transition ease-out duration-300"
        enter-from-class="opacity-0 translate-y-4"
        enter-to-class="opacity-100 translate-y-0"
        leave-active-class="transition ease-in duration-200"
        leave-from-class="opacity-100 translate-y-0"
        leave-to-class="opacity-0 translate-y-4"
    >
        <div v-if="visible" class="toast !bottom-20 !right-20">
            <div class="alert alert-soft">
                <span>{{ message }}</span>
                <button class="btn btn-ghost btn-xs" @click="visible = false">✕</button>
            </div>
        </div>
    </Transition>
</template>
