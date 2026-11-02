<script setup>
const ORBIT_ANGLES = [0, 51, 103, 154, 206, 257, 309]
const IGNITE_STEP_MS = 285
</script>

<template>
    <div class="absolute inset-0 grid place-items-center bg-licorice/50" role="status" aria-label="Loading graph">
        <div class="relative size-64" aria-hidden="true">
            <span class="absolute inset-[39%] animate-pulse rounded-full bg-white/10"></span>
            <span
                v-for="(angle, index) in ORBIT_ANGLES"
                :key="angle"
                class="absolute left-1/2 top-1/2 size-11 animate-pulse rounded-full bg-white/[0.08]"
                :style="{
                    transform: `translate(-50%, -50%) rotate(${angle}deg) translateY(-7.5rem) rotate(-${angle}deg)`,
                    animationDelay: `${index * IGNITE_STEP_MS}ms`,
                }"
            ></span>
        </div>
    </div>
</template>
