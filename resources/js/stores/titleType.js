import { defineStore } from 'pinia'
import { ref, watch } from 'vue'

/**
 * Global, persisted "title type" filter (All / Film / TV).
 *
 * This is genuine global client state: it lives outside any single page,
 * survives Inertia navigations, and is read both in components (Navbar) and
 * outside them (the router `before` hook in app.js that appends `title_type`
 * to every GET). That combination is exactly what Pinia is for.
 *
 * Written in Pinia's "setup store" style — it's just the Composition API you
 * already use in components (`ref`, `watch`), so the mental model carries over.
 */
export const useTitleTypeStore = defineStore('titleType', () => {
    const titleType = ref(localStorage.getItem('title-type') || '')

    // Persist across reloads. Clearing back to "All" removes the key entirely.
    watch(titleType, (value) => {
        value ? localStorage.setItem('title-type', value) : localStorage.removeItem('title-type')
    })

    function setTitleType(value) {
        titleType.value = value
    }

    return { titleType, setTitleType }
})
