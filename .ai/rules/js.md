---
paths:
  - 'resources/js/**/*.{vue,js}'
---

# Js

## Tailwind class recipes use tailwind-variants in resources/js/variants
Shared, repeated Tailwind class strings are expressed as `tailwind-variants` recipes in `resources/js/variants/*.js` (`button`, `menu`, `badge`, `field`, ...).

- Import the configured instance: `import { tv } from '../variants/tv.js'` (or import a specific recipe like `button`). Never import from `tailwind-variants` directly — `tv.js` wraps `createTV` so twMergeConfig stays central.
- A recipe returns a string (or slot fns); use it in `:class`. Per-instance layout goes in the recipe's `class:` key so tailwind-merge resolves collisions, e.g. `button({ intent: 'primary', class: 'hidden lg:flex' })`.
- One-off styling with no reuse can stay a local `tv()` in the component's `<script setup>` (see UserTitleAction.vue). Don't grow a shared recipe for a single bespoke case (YAGNI).
- Don't add new raw multi-class `:class` ternaries for variant logic — add a variant to the relevant recipe instead.
- Use theme tokens (madder, licorice, base-*, rounded-field) not off-theme literals (indigo-*, blue-500, light greys).
