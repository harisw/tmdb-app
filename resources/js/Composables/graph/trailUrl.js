export function syncTrailUrl(trail) {
    const [root, ...expanded] = trail
    if (!root) return

    const url = new URL(window.location.href)
    url.searchParams.set('title_slug', root.slug)
    if (expanded.length) {
        url.searchParams.set('trail', expanded.map((title) => title.slug).join(','))
    } else {
        url.searchParams.delete('trail')
    }
    window.history.replaceState(window.history.state, '', url)
}
