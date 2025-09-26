import { ref, onMounted, onUnmounted } from "vue";
import { router } from "@inertiajs/vue3";

export default function useInfiniteScroll(initialData, options = {}) {
    // Detect the key dynamically (products, users, posts, etc.)
    const propKey = options.key || Object.keys(initialData)[0] || "data";
    const resource = initialData[propKey] || initialData;

    const items = ref(resource.data || []);
    const nextPageUrl = ref(resource.next_page_url);
    const loading = ref(false);

    const threshold = options.threshold || 200; // px before bottom

    const loadMore = () => {
        if (!nextPageUrl.value || loading.value) return;

        loading.value = true;
        router.get(
            nextPageUrl.value,
            {},
            {
                preserveScroll: true,
                preserveState: true,
                replace: true,
                onSuccess: (page) => {
                    const newResource = page.props[propKey];
                    items.value.push(...newResource.data);
                    nextPageUrl.value = newResource.next_page_url;
                    loading.value = false;
                },
                onFinish: () => {
                    loading.value = false;
                },
            }
        );
    };

    const handleScroll = () => {
        if (loading.value || !nextPageUrl.value) return;

        const scrollPos = window.innerHeight + window.scrollY;
        const bottomPos = document.body.offsetHeight - threshold;

        if (scrollPos >= bottomPos) {
            loadMore();
        }
    };

    onMounted(() => {
        window.addEventListener("scroll", handleScroll);
    });

    onUnmounted(() => {
        window.removeEventListener("scroll", handleScroll);
    });

    return {
        items,
        loading,
        loadMore,
        nextPageUrl,
    };
}
