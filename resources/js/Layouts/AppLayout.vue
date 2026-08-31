<script setup>
import { storeToRefs } from 'pinia'
import AmbientBackground from '../Components/AmbientBackground.vue'
import MobileSidebar from '../Components/MobileSidebar.vue'
import Navbar from '../Components/Navbar.vue'
import Toast from '../Components/Toast.vue'
import AuthModal from '../Components/Modals/AuthModal.vue'
import ProfileModal from '../Components/Modals/ProfileModal.vue'
import { useUiStore } from '../stores/ui.js'

// Modals are hosted once here (not in Navbar) so any component can open them
// through the ui store. Navbar just calls ui.openAuthModal() / openProfileModal().
const ui = useUiStore()
const { authModalOpen, authModalTab, profileModalOpen } = storeToRefs(ui)
</script>

<template>
    <div class="drawer">
        <input id="mobile-nav-drawer" type="checkbox" class="drawer-toggle" />

        <div class="drawer-content min-h-screen bg-licorice">
            <main class="relative min-h-screen bg-licorice">
                <AmbientBackground />
                <Navbar />
                <div
                    class="relative z-10 max-w-[2200px] mx-auto px-4 pt-24 pb-14 md:px-10 md:pt-28 lg:px-20 2xl:px-28 2xl:pt-36"
                >
                    <slot />
                </div>
            </main>
            <Toast />

            <AuthModal :show="authModalOpen" :initial-tab="authModalTab" @close="ui.closeAuthModal" />
            <ProfileModal :show="profileModalOpen" @close="ui.closeProfileModal" />
        </div>

        <!-- Mobile-only sliding sidebar (Navbar's hamburger, `lg:hidden`, toggles
             the checkbox above via its `for`). Nothing opens it at `lg` and up. -->
        <div class="drawer-side z-[105]">
            <label for="mobile-nav-drawer" aria-label="Close menu" class="drawer-overlay"></label>
            <MobileSidebar />
        </div>
    </div>
</template>
