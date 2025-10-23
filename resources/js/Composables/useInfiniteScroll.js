import {ref, onMounted, onUnmounted, watch} from "vue";
import {router} from "@inertiajs/vue3";

export default function useInfiniteScroll(initialData, options = {}) {
    // Detect the key dynamically (products, users, posts, etc.)
    const propKey = options.key || Object.keys(initialData)[0] || "data";
    const resource = initialData[propKey] || initialData;

    const observer = ref(null);
    const sentinel = ref(null);

    const items = ref(resource.data || []);
    const page = ref(1)
    const hasMore = ref(true)
    const query = ref({})
    // const nextPageUrl = ref(resource.next_page_url);
    const loading = ref(false);

    const setupObserver = () => {
        if (observer.value) observer.value.disconnect()

        observer.value = new IntersectionObserver((entries) => {
            const entry = entries[0];
            if (entry.isIntersecting && hasMore.value && !loading.value) {
                loadMore();
            }
        })
    }

    async function fetchItems(reset = false) {
        if (loading.value || (!hasMore.value && !reset)) return
        loading.value = true

        if (reset) {
            page.value = 1;
        }

        let params = {page: page.value};

        if (query.value) {
            params = {...params, ...query.value};
        }

        router.get(
            window.location.pathname,
            params,
            {
                preserveScroll: true,
                preserveState: true,
                replace: !reset, // keep history cleaner
                onSuccess: (page) => {
                    const data = page.props.movies.data
                    if (!page.props.movies) return;
                    if (reset) items.value = data
                    else items.value.push(...data)
                    hasMore.value = !!page.props.movies.next_page_url
                },
                onFinish: () => (loading.value = false),
            }
        )
    }

    async function loadMore() {
        page.value++;
        await fetchItems();
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
    });

    return {
        items,
        loading,
        sentinel,
        fetchItems,
        hasMore,
        query
    };
}
