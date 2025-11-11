<template>
    <div class="relative w-full max-w-md text-white">
        <label class="sr-only">Search</label>
        <input
            v-model="query"
            @input="onInput"
            @keydown.escape="clear"
            type="search"
            :placeholder="placeholder"
            class="w-full pl-10 pr-10 py-2 rounded-lg border focus:outline-none focus:ring-2 focus:ring-offset-1 focus:ring-indigo-400 shadow-sm"
            :aria-label="placeholder"
        />

        <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24"
                 stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"/>
            </svg>
        </span>

        <!-- clear button -->
        <button
            v-if="query"
            @click="clear"
            class="absolute right-3 top-1/2 -translate-y-1/2 p-1 rounded focus:outline-none focus:ring-2 focus:ring-indigo-400
            hover:cursor-pointer"
            aria-label="Clear search"
        >
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24"
                 stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>

        <!-- floating suggestion list -->
        <ul
            v-if="showResults && results.length"
            class="absolute z-50 left-0 mt-1 w-full bg-white border border-gray-200 rounded-lg
            shadow-md text-black"
        >
            <li
                v-for="(item, i) in results"
                :key="i"
                @click="itemOnClick(item)"
                class="px-3 py-2 cursor-pointer hover:bg-indigo-50"
            >
                {{ item.title }}
            </li>
            <li
                v-if="hasMore"
                @click="itemOnClick(item)"
                class="px-3 py-2 cursor-pointer hover:bg-indigo-50 bg-color-licorice rounded-lg
                 hover:rounded-xl"
            >
                Find more
            </li>
        </ul>
        <!--        <LoadingSpinner v-if="loading" class="absolute right-10 top-1/2 -translate-y-1/2"/>-->
    </div>
</template>

<script setup>

import {ref, watch} from 'vue';

const props = defineProps({
    modelValue: {type: String, default: ''},
    paramName: {type: String, default: 'q'},
    debounce: {type: Number, default: 400},
    placeholder: {type: String, default: 'Search...'},
    minLength: {type: Number, default: 0},
    replace: {type: Boolean, default: true},
    preserveState: {type: Boolean, default: false},
    route: {type: String, default: ''}
})

const emit = defineEmits(['update:modelValue', 'search', 'resultOnClick']);

const query = ref(props.modelValue);
const loading = ref(false);
const showResults = ref(false);
const results = ref([]);
const hasMore = ref(false);
let timer = null;

async function doSearch(q) {
    if (props.minLength && q.length < props.minLength) {
        emit('search', '')
        return
    }

    loading.value = true;

    const params = {[props.paramName]: q};

    const url = props.route || window.location.pathname;
    results.value = []
    hasMore.value = false;
    try {
        const resp = await fetch(`${url}?q=${params.q}`);
        if (!resp.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        const data = await resp.json()
        // console.log("data: ", data)
        showResults.value = true
        hasMore.value = data.hasMore
        results.value = data.results
    } catch (e) {
        console.log("Error: ", e.message);
    } finally {
        loading.value = false;
    }

    emit('search', q)
}

function itemOnClick(movie) {
    emit('resultOnClick', movie)
}

function debounceSearch(q) {
    clearTimeout(timer)
    timer = setTimeout(() => doSearch(q), props.debounce)
}

function onInput(e) {
    emit('update:modelValue', query.value)
    debounceSearch(query.value)
}

function clear() {
    query.value = ''
    emit('update:modelValue', '')
    debounceSearch('')
}

watch(() => props.modelValue, (v) => {
    if (v !== query.value) query.value = v
})

// optionally perform an initial search on mount if there's already a query
// onMounted(() => {
//     if (query.value && query.value.length >= props.minLength) {
// // run search immediately without waiting for debounce
//         doSearch(query.value)
//     }
// })
</script>

<style scoped>

</style>
