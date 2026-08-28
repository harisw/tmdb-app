import { tv } from './tv.js'

/**
 * Replaces three hand-rolled badge styles: RatingBadge's pill, TitleCard's
 * movie/tv type chips, and GenreBadges' daisyUI soft badge.
 */
export const badge = tv({
    base: 'badge border-none text-xs font-medium gap-1',
    variants: {
        tone: {
            rating: 'rounded-full bg-black/60 text-amber-400 tabular-nums px-2 py-1',
            movie: 'rounded-md bg-black/80 text-white p-1',
            tv: 'rounded-md bg-blue-900/80 text-white p-1',
            genre: 'badge-soft badge-secondary badge-sm px-2',
        },
    },
    defaultVariants: {
        tone: 'genre',
    },
})
