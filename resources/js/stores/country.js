import { defineStore } from 'pinia'
import { ref, watch } from 'vue'

export const useCountryStore = defineStore('country', () => {
    const country = ref(localStorage.getItem('country') || '')

    // Persist across reloads. Clearing back to "All" removes the key entirely.
    watch(country, (value) => {
        value ? localStorage.setItem('country', value) : localStorage.removeItem('country')
    })

    function setCountry(value) {
        country.value = value
    }

    return { country, setCountry }
})
