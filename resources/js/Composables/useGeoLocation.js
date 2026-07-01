import { ref } from 'vue'

const country = ref(null)

fetch('https://ipapi.co/json/')
    .then((r) => r.json())
    .then((data) => {
        country.value = data.country_code
    })
    .catch(() => {})

export function useGeoLocation() {
    return { country }
}
