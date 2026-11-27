import { ref, onMounted, onUnmounted, watch } from 'vue'
import { router } from '@inertiajs/vue3'

export default function useInfiniteScroll(source, options = {}) {
    // Detect the key dynamically (products, users, posts, etc.)
    const propKey = options.key || Object.keys(source)[0] || 'data'
    // The value is a reactive getter (`() => props.titles`), so reading it
    // tracks the prop and reacts when Inertia replaces it (e.g. router.reload()
    // after a filter change). It may be null (pages that only load titles once
    // a language/tag is picked), so every read must be null-safe.
    const getResource = () => {
        const value = source[propKey]
        const resolved = typeof value === 'function' ? value() : value
        return resolved ?? null
    }
    const resource = getResource()

    const observer = ref(null)
    const sentinel = ref(null)

    const nextParams = (res) => {
        const next = res?.links?.next
        return next ? Object.fromEntries(new URL(next, window.location.origin).searchParams) : null
    }

    const items = ref(resource?.data || [])
    const next = ref(nextParams(resource))
    const hasMore = ref(next.value !== null)
    const query = ref({})
    const loading = ref(false)

    // The reset watcher below reacts to the prop changing. But *our own*
    // pagination fetches also change the prop, and those are already applied by
    // hand in fetchItems (append). This flag marks a prop change as ours so the
    // watcher skips it; otherwise appending page 2 would immediately be reset
    // back to just page 2's rows (a visible "snap"). Object-identity checks are
    // unreliable here because the watcher sees the reactive props proxy on the
    // next tick, not the object captured synchronously in onSuccess.
    let internalUpdate = false

    const setupObserver = () => {
        if (observer.value) observer.value.disconnect()

        observer.value = new IntersectionObserver((entries) => {
            const entry = entries[0]
            if (entry.isIntersecting && items.value.length > 0 && hasMore.value && !loading.value) {
                fetchItems()
            }
        })
    }

    async function fetchItems(reset = false) {
        if (loading.value || (!hasMore.value && !reset)) return
        loading.value = true

        const params = reset ? { ...query.value } : { ...query.value, ...next.value }

        // This navigation replaces the prop; tell the watcher the resulting
        // change is ours so it doesn't reset the list we're about to update.
        internalUpdate = true

        router.get(window.location.pathname, params, {
            preserveScroll: true,
            preserveState: true,
            replace: !reset, // keep history cleaner
            onSuccess: (page) => {
                const res = page.props[propKey]
                const data = res?.data
                if (!res || !data) return
                if (reset) items.value = data
                else items.value.push(...data)
                next.value = nextParams(res)
                hasMore.value = next.value !== null
            },
            onFinish: () => (loading.value = false),
        })
    }

    onMounted(setupObserver)
    onUnmounted(() => observer.value?.disconnect())

    watch(sentinel, (newVal) => {
        if (newVal && observer.value) observer.value.observe(newVal)
    })

    // When Inertia replaces the prop, rebuild the list, but only if the change
    // came from outside our own pagination (e.g. router.reload()). Our own
    // fetches set internalUpdate and are skipped.
    watch(getResource, (res) => {
        if (internalUpdate) {
            internalUpdate = false
            return
        }
        items.value = res?.data || []
        next.value = nextParams(res)
        hasMore.value = next.value !== null
    })

    return {
        items,
        loading,
        sentinel,
        fetchItems,
        hasMore,
        query,
    }
}
