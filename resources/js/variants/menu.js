import { tv } from './tv.js'

export const menu = tv({
    slots: {
        content:
            'dropdown-content menu menu-sm z-[100] shadow-lg bg-black ' +
            'border border-gray-800 rounded-box text-white',
        item: 'hover:bg-gray-800 rounded-none',
    },
    variants: {
        width: {
            sm: { content: 'w-44 2xl:w-48' },
            md: { content: 'w-48 2xl:w-56' },
            lg: { content: 'w-56 max-w-[70vw] max-h-72 overflow-y-scroll' },
        },
        offset: { top: { content: 'mt-1' }, gap: { content: 'mt-3' }, up: { content: 'mb-3' } },
        pad: { none: { content: 'p-0' }, sm: { content: 'p-2' } },
    },
    defaultVariants: { width: 'md', offset: 'gap', pad: 'sm' },
})
