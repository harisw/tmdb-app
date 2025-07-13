import './bootstrap';
import { createApp, h } from "vue";
import { createInertiaApp } from "@inertiajs/inertia-vue3";
import '../css/app.css';

createInertiaApp({
    title: title => `TMDB - ${title}`,
    resolve: name => import(`./Pages/${name}.vue`),
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el)
    },
})
