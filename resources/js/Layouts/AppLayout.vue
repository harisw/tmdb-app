<script setup>
import { storeToRefs } from 'pinia'
import Navbar from '../Components/Navbar.vue'
import Toast from '../Components/Toast.vue'
import AuthModal from '../Components/AuthModal.vue'
import ProfileModal from '../Components/ProfileModal.vue'
import { useUiStore } from '../stores/ui.js'

// Modals are hosted once here (not in Navbar) so any component can open them
// through the ui store. Navbar just calls ui.openAuthModal() / openProfileModal().
const ui = useUiStore()
const { authModalOpen, authModalTab, profileModalOpen } = storeToRefs(ui)
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

        <AuthModal :is-open="authModalOpen" :initial-tab="authModalTab" @close="ui.closeAuthModal" />
        <ProfileModal :is-open="profileModalOpen" @close="ui.closeProfileModal" />
    </div>
</template>
