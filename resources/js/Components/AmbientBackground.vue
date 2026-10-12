<script setup>
// Purely decorative, stateless background for the whole page. Mounted once by
// AppLayout, so every route gets it.
//
// Pinned to `main` with inset-0: full-bleed, and the full document height. The
// blooms sit at the top and scroll away; grain and the side lights follow the
// content all the way down.
//
// Nothing here may create a stacking context on the root: the grain's
// `mix-blend-overlay` has to blend against the page's licorice background, which
// only happens while the root stays `z-auto`. That also rules out `position:
// fixed` on this wrapper; `fixed` always forms a stacking context. Page content
// sits above via AppLayout's `z-10`.
</script>

<template>
    <div aria-hidden="true" class="pointer-events-none absolute inset-0 overflow-hidden">
        <!-- Grain: backmost layer, full coverage, faint film flicker. -->
        <div
            class="absolute inset-0 opacity-60 mix-blend-overlay animate-grain-shift motion-reduce:animate-none [background-image:radial-gradient(rgba(255,255,255,0.05)_1px,transparent_1px)] [background-size:3px_3px]"
        ></div>

        <!-- Side lights: ambient spill from the left and right edges. Flat
             gradients rather than blurred ellipses, because these run the full
             document height and blur() over a several-thousand-pixel box is
             ruinous. The mask keeps them from ending on a hard edge at the
             bottom of a long page. -->
        <div
            class="absolute inset-y-0 left-0 w-[22vw] max-w-[380px] opacity-[0.12] animate-side-glow-a motion-reduce:animate-none [background-image:linear-gradient(to_right,var(--color-glow-red),transparent_75%)] [mask-image:linear-gradient(to_bottom,transparent,black_10%,black_88%,transparent)]"
        ></div>
        <div
            class="absolute inset-y-0 right-0 w-[22vw] max-w-[380px] opacity-[0.10] animate-side-glow-b motion-reduce:animate-none [background-image:linear-gradient(to_left,var(--color-glow-amber),transparent_75%)] [mask-image:linear-gradient(to_bottom,transparent,black_10%,black_88%,transparent)]"
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
