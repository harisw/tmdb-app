<template>
    <div class="p-8">
        <h1 class="text-2xl font-bold mb-6">Item Grid</h1>
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-6">
            <div v-for="item in items" :key="item.id" class="bg-white p-4 rounded shadow">
                <h2 class="text-lg font-semibold">{{ item.title }}</h2>
                <p class="text-gray-600">{{ item.description }}</p>
            </div>
        </div>
    </div>
</template>

<script setup>
defineProps({
    items: Array
})
</script>
