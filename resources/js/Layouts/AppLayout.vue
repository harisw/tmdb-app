<script setup>
import Navbar from '../Components/Navbar.vue'
import Toast from '../Components/Toast.vue'
</script>

<template>
    <div class="min-h-screen bg-licorice">
        <main class="bg-licorice">
            <Navbar />
            <div class="max-w-[2200px] mx-auto px-4 pt-24 pb-14 md:px-10 md:pt-28 lg:px-20 2xl:px-28 2xl:pt-36">
                <slot />
            </div>
        </main>
        <Toast />
    </div>
</template>
