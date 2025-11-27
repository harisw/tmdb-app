import './bootstrap';
import {createApp, h} from "vue";
import {createInertiaApp} from "@inertiajs/vue3";
import '../css/app.css';
import {resolvePageComponent} from "laravel-vite-plugin/inertia-helpers";

createInertiaApp({
    title: title => `TMDB - ${title}`,
    resolve: name => resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue')),
    setup({el, App, props, plugin}) {
        const app = createApp({render: () => h(App, props)});

        app.use(plugin);

        app.config.globalProperties.$base_url = import.meta.env.VITE_APP_URL || window.location.origin;

        app.mount(el);
    },
})
