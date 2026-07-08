import { ref, onMounted, onUnmounted, watch } from 'vue'
import { router } from '@inertiajs/vue3'

export default function useInfiniteScroll(source, options = {}) {
    // Detect the key dynamically (products, users, posts, etc.)
    const propKey = options.key || Object.keys(source)[0] || 'data'
    // The value may be a reactive getter (`() => props.titles`) or a plain
    // snapshot. Only the getter form reacts to the prop being replaced by
    // Inertia (e.g. router.reload() after a filter change).
    const getResource = () => {
        const value = source[propKey] ?? source
        return typeof value === 'function' ? value() : value
    }
    const resource = getResource()

    const observer = ref(null)
    const sentinel = ref(null)

    const items = ref(resource.data || [])
    const page = ref(1)
    const hasMore = ref(true)
    const query = ref({})
    // const nextPageUrl = ref(resource.next_page_url);
    const loading = ref(false)

    // Track the resource object currently reflected in `items` so we can tell
    // our own pagination fetches (handled in fetchItems) apart from external
    // prop replacements that should reset the list.
    let syncedResource = resource

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
                hasMore.value = !!res.next_page_url
                // Mark this resource as already applied so the reset watcher
                // below ignores the prop change from our own pagination.
                syncedResource = res
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

    // When Inertia replaces the prop from outside our own pagination (e.g.
    // router.reload() after toggling the adult filter), rebuild the list.
    watch(getResource, (res) => {
        if (!res || res === syncedResource) return
        items.value = res.data || []
        page.value = 1
        hasMore.value = !!res.next_page_url
        syncedResource = res
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
