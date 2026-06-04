import { ref, onMounted } from 'vue'

export function useGeoLocation() {
    const country = ref(null)
    const loading = ref(true)

    onMounted(async () => {
        try {
            const data = await fetch('https://ipapi.co/json/').then((r) => r.json())
            country.value = data.country_code
        } finally {
            loading.value = false
        }
    })
    return { country, loading }
}
