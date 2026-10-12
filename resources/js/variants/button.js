import { tv } from './tv.js'

/**
 * The one place daisyUI's `btn` gets composed with our theme.
 *
 * - `intent` picks the look: solid madder, transparent-with-bg-on-hover, or
 *   a text link that only shifts colour on hover.
 * - `size` maps straight to daisyUI's size modifiers.
 * - `responsive` adds the 2xl bump the desktop nav uses; opt in, it isn't
 *   wanted on toolbar/modal buttons.
 * - `circle` is the icon-only square.
 *
 * Anything genuinely one-off (see TitleActionBar's 3D hover buttons) keeps
 * its own local `tv()` instead of growing a variant here.
 */
export const button = tv({
    base: 'btn border-none text-white',
    variants: {
        intent: {
            soft: 'btn-soft bg-white/10',
            primary: 'bg-madder hover:bg-madder/85',
            ghost: 'btn-ghost hover:bg-white/10',
            link: 'btn-ghost hover:text-white/70',
        },
        size: {
            xs: 'btn-xs',
            sm: 'btn-sm',
            md: '',
        },
        responsive: {
            true: '2xl:h-10 2xl:text-lg',
        },
        circle: {
            true: 'btn-circle',
        },
    },
    defaultVariants: {
        intent: 'ghost',
        size: 'sm',
    },
})
