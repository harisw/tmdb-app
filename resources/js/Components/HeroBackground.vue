<script setup>
// Purely decorative, stateless background for the header + hero region.
//
// Positioned absolutely with negative insets that cancel AppLayout's content
// padding, so it bleeds full width and up behind the fixed navbar. Nothing
// here may create a stacking context on the root: the grain's `mix-blend-overlay`
// has to blend against the page's licorice background, which only happens while
// the root stays `z-auto`. The hero content sits above via its own `z-10`.
</script>

<template>
    <div
        aria-hidden="true"
        class="pointer-events-none absolute bottom-0 overflow-hidden -top-24 -left-4 -right-4 md:-top-28 md:-left-10 md:-right-10 lg:-left-20 lg:-right-20 2xl:-top-36 2xl:-left-28 2xl:-right-28"
    >
        <!-- Grain: backmost layer, full coverage, faint film flicker. -->
        <div
            class="absolute inset-0 opacity-60 mix-blend-overlay animate-grain-shift motion-reduce:animate-none [background-image:radial-gradient(rgba(255,255,255,0.05)_1px,transparent_1px)] [background-size:3px_3px]"
        ></div>

        <!-- Bloom A (red), near top-left, mostly clipped behind the navbar.
             Size/offset/blur scale with the viewport so the bloom stays over the
             hero on narrow screens instead of sliding off; they hit the mock's
             exact px values at the 1920px width it was tuned for. -->
        <div
            class="absolute left-[15%] top-[clamp(-140px,-7.3vw,-70px)] size-[clamp(260px,27vw,520px)] rounded-full bg-glow-red opacity-[0.32] blur-[clamp(70px,7.3vw,140px)] will-change-transform animate-glow-drift-a motion-reduce:animate-none"
        ></div>

        <!-- Bloom B (amber), near top-right. -->
        <div
            class="absolute right-[12%] top-[clamp(-100px,-5.2vw,-50px)] size-[clamp(230px,24vw,460px)] rounded-full bg-glow-amber opacity-[0.22] blur-[clamp(70px,7.3vw,140px)] will-change-transform animate-glow-drift-b motion-reduce:animate-none"
        ></div>
    </div>
</template>
