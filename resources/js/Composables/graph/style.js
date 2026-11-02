export const NODE_RADIUS = 26
export const POSTER_RADIUS = 24
export const LABEL_MAX = 22

const DIMMED_OPACITY = 0.22
const STRANDED_OPACITY = 0.4

export const COLORS = {
    highlight: 'var(--color-glow-red)',
    route: 'var(--color-tea-green)',
    ground: 'var(--color-licorice)',
    line: '#ffffff',
    busy: '#ffffff',
}

const RINGS = {
    root: { stroke: COLORS.highlight, width: 3 },
    expanded: { stroke: 'rgb(255 255 255 / 0.75)', width: 2 },
    idle: { stroke: 'rgb(255 255 255 / 0.22)', width: 1.5 },
}

export function ringFor(node) {
    if (node.root) return RINGS.root
    return node.expanded ? RINGS.expanded : RINGS.idle
}

const baseWidth = (link) => 0.75 + 1.75 * link.score

export function linkAppearance(link, { lit, onRoute }) {
    if (lit?.linkKeys.has(link.key)) {
        return { stroke: COLORS.highlight, opacity: 0.9, width: baseWidth(link) + 0.75 }
    }
    if (onRoute) {
        return { stroke: COLORS.route, opacity: lit ? 0.3 : 0.85, width: baseWidth(link) + 1 }
    }
    return { stroke: COLORS.line, opacity: lit ? 0.05 : 0.12 + 0.45 * link.score, width: baseWidth(link) }
}

export function nodeOpacity(node, { lit, connected }) {
    if (lit) return lit.nodeIds.has(node.id) ? 1 : DIMMED_OPACITY
    return connected ? 1 : STRANDED_OPACITY
}
