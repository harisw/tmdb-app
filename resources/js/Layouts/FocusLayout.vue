<script setup>
import AmbientBackground from '../Components/AmbientBackground.vue'
import Toast from '../Components/Toast.vue'
</script>

<template>
    <div class="min-h-svh bg-licorice">
        <main class="relative min-h-svh bg-licorice">
            <AmbientBackground />
            <div class="relative z-10 mx-auto max-w-[2200px] px-4 md:px-10 xl:px-14 2xl:px-20">
                <slot />
            </div>
        </main>
        <Toast />
    </div>
</template>
