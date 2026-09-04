export default function useMobileDrawer() {
    // The drawer (AppLayout.vue) is a plain checkbox toggle, so anything that
    // should dismiss it (picking a country, following a nav link, opening the
    // profile modal) just flips that checkbox directly. Harmless to call from
    // desktop-only contexts too; the checkbox exists but is never checked there.
    function closeMobileDrawer() {
        const toggle = document.getElementById('mobile-nav-drawer')
        if (toggle) toggle.checked = false
    }

    return { closeMobileDrawer }
}
