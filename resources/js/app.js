import './bootstrap'
import { createApp, h } from 'vue'
import { createPinia } from 'pinia'
import { createInertiaApp, router, usePage } from '@inertiajs/vue3'
import '../css/app.css'
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers'
import { useTitleTypeStore } from './stores/titleType.js'
import { useCountryStore } from './stores/country.js'

// Create the Pinia instance up front so it can be used both inside components
// (via app.use below) and outside them (in the router hook here). Passing the
// instance explicitly — useTitleTypeStore(pinia) — is how you reach a store
// from code that runs outside a component's setup().
const pinia = createPinia()

router.on('before', (event) => {
    if (event.detail.visit.method === 'get') {
        const { titleType } = useTitleTypeStore(pinia)
        const p = event.detail.visit.url.searchParams
        titleType ? p.set('title_type', titleType) : p.delete('title_type')

        // Server prefers the authenticated user's stored country
        // (Title::detailRelations()), so the query param only matters for
        // guests — sending it while logged in would just be a stale echo of
        // whatever was last picked before login.
        const isGuest = !usePage().props.auth?.user
        const { country } = useCountryStore(pinia)
        isGuest && country ? p.set('country', country) : p.delete('country')
    }
})

createInertiaApp({
    title: (title) => `Chill - ${title}`,
    resolve: (name) => resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        const app = createApp({ render: () => h(App, props) })

        app.use(plugin)
        app.use(pinia)

        app.config.globalProperties.$base_url = import.meta.env.VITE_APP_URL || window.location.origin

        app.mount(el)
    },
})
