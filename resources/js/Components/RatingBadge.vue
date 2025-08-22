<script setup>
const props = defineProps({
    rating: { type: [Number, String], required: true },
    size: { type: String, default: "sm" }, // "xs" | "sm" | "md"
})

const sizes = {
    xs: {
        wrap: "px-1.5 py-0.5 text-[10px]",
        icon: "h-3 w-3",
    },
    sm: {
        wrap: "px-2 py-0.5 text-xs",
        icon: "h-3.5 w-3.5",
    },
    md: {
        wrap: "px-2.5 py-1 text-sm",
        icon: "h-4 w-4",
    },
}
</script>

<template>
  <span
      class="inline-flex items-center gap-1 rounded-full bg-yellow-100/90 font-medium text-yellow-900 ring-1 ring-yellow-300 shadow-sm"
      :class="sizes[size].wrap"
      :aria-label="`Rating: ${rating}`"
  >
    <svg
        :class="sizes[size].icon + ' text-yellow-500'"
        viewBox="0 0 20 20"
        fill="currentColor"
        aria-hidden="true"
    >
      <path
          d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.967 0 1.371 1.24.588 1.81l-2.802 2.036a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118L10 13.347l-2.386 1.927c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L3.98 8.72c-.783-.57-.379-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"
      />
    </svg>
    <span>{{ typeof rating === "number" ? rating.toFixed(1) : rating }}</span>
  </span>
</template>
