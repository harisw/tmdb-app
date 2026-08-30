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

    const items = ref(resource?.data || [])
    const page = ref(1)
    const hasMore = ref(true)
    const query = ref({})
    // const nextPageUrl = ref(resource.next_page_url);
    const loading = ref(false)

    // The reset watcher below reacts to the prop changing. But *our own*
    // pagination fetches also change the prop, and those are already applied by
    // hand in fetchItems (append). This flag marks a prop change as ours so the
    // watcher skips it — otherwise appending page 2 would immediately be reset
    // back to just page 2's rows (a visible "snap"). Object-identity checks are
    // unreliable here because the watcher sees the reactive props proxy on the
    // next tick, not the object captured synchronously in onSuccess.
    let internalUpdate = false

    const setupObserver = () => {
        if (observer.value) observer.value.disconnect()

        observer.value = new IntersectionObserver((entries) => {
            const entry = entries[0]
            if (entry.isIntersecting && items.value.length > 0 && hasMore.value && !loading.value) {
                loadMore()
            }
        })
    }

    async function fetchItems(reset = false) {
        if (loading.value || (!hasMore.value && !reset)) return
        loading.value = true

        if (reset) {
            page.value = 1
        }

        let params = { page: page.value }

        if (query.value) {
            params = { ...params, ...query.value }
        }

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
                hasMore.value = !!res.links?.next
            },
            onFinish: () => (loading.value = false),
        })
    }

    async function loadMore() {
        page.value++
        await fetchItems()
    }

    // const loadMore = () => {
    //     if (!nextPageUrl.value || loading.value) return;
    //
    //     loading.value = true;
    //     router.get(
    //         nextPageUrl.value,
    //         {},
    //         {
    //             preserveScroll: true,
    //             preserveState: true,
    //             replace: true,
    //             onSuccess: (page) => {
    //                 const newResource = page.props[propKey];
    //                 items.value.push(...newResource.data);
    //                 nextPageUrl.value = newResource.next_page_url;
    //                 loading.value = false;
    //             },
    //             onFinish: () => {
    //                 loading.value = false;
    //             },
    //         }
    //     );
    // };
    //
    // // const handleScroll = () => {
    // //     if (loading.value || !nextPageUrl.value) return;
    // //
    // //     const scrollPos = window.innerHeight + window.scrollY;
    // //     const bottomPos = document.body.offsetHeight - threshold;
    // //
    // //     if (scrollPos >= bottomPos) {
    // //         loadMore();
    // //     }
    // // };
    // //
    // // onMounted(() => {
    // //     window.addEventListener("scroll", handleScroll);
    // // });
    // //
    // // onUnmounted(() => {
    // //     window.removeEventListener("scroll", handleScroll);
    // // });

    onMounted(setupObserver)
    onUnmounted(() => observer.value?.disconnect())

    watch(sentinel, (newVal) => {
        if (newVal && observer.value) observer.value.observe(newVal)
    })

    // When Inertia replaces the prop, rebuild the list — but only if the change
    // came from outside our own pagination (e.g. router.reload() after toggling
    // the adult filter). Our own fetches set internalUpdate and are skipped.
    watch(getResource, (res) => {
        if (internalUpdate) {
            internalUpdate = false
            return
        }
        items.value = res?.data || []
        page.value = 1
        hasMore.value = !!res?.links?.next
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
