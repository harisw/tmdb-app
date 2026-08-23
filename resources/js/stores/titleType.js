import { defineStore } from 'pinia'
import { ref, watch } from 'vue'

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
