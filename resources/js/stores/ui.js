import { defineStore } from 'pinia'
import { ref } from 'vue'

/**
 * Global UI state for app-level modals.
 *
 * Modal visibility used to live as local refs in Navbar.vue, which meant only
 * Navbar could open them. Lifting it into a store lets *any* component trigger
 * the auth or profile modal (e.g. a "log in to continue" button on a page)
 * without prop-drilling or event buses. The modals themselves are hosted once
 * in AppLayout.vue.
 */
export const useUiStore = defineStore('ui', () => {
    const authModalOpen = ref(false)
    const authModalTab = ref('login')
    const profileModalOpen = ref(false)

    function openAuthModal(tab = 'login') {
        authModalTab.value = tab
        authModalOpen.value = true
    }

    function closeAuthModal() {
        authModalOpen.value = false
    }

    function openProfileModal() {
        profileModalOpen.value = true
    }

    function closeProfileModal() {
        profileModalOpen.value = false
    }

    return {
        authModalOpen,
        authModalTab,
        profileModalOpen,
        openAuthModal,
        closeAuthModal,
        openProfileModal,
        closeProfileModal,
    }
})
