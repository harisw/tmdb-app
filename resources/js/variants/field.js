import { tv } from './tv.js'

/**
 * One dark, on-theme input box for SearchInput, the Auth forms, MultiSelect's
 * control and any future field. Replaces the scattered `focus:ring-indigo-400`
 * / `focus:ring-blue-500` / light-grey recipes.
 *
 * Horizontal padding stays at the call site; it varies (`pl-10` when there's
 * a leading icon, `px-3` otherwise) and isn't a real variant.
 */
export const field = tv({
    base:
        'w-full rounded-field border bg-base-200 text-base-content shadow-sm transition-colors ' +
        'placeholder:text-white/40 focus:outline-none focus:ring-2 focus:ring-madder/60',
    variants: {
        state: {
            default: 'border-white/15 hover:border-white/30',
            error: 'border-madder',
        },
        open: {
            true: 'border-white/40 ring-2 ring-madder/40',
        },
        disabled: {
            true: 'cursor-not-allowed opacity-60',
        },
    },
    defaultVariants: {
        state: 'default',
    },
})
