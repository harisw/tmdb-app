import { forceCollide, forceLink, forceManyBody, forceSimulation, forceX, forceY } from 'd3-force'

const NODE_RADIUS = [46, 30, 22]
const LABEL_ROOM = 28
const PADDING = 24
const SETTLE_TICKS = 300

export function linkKey(link) {
    const source = link.source.id ?? link.source
    const target = link.target.id ?? link.target
    return source < target ? `${source}-${target}` : `${target}-${source}`
}

export function layoutConstellation({ nodes, links, seed }) {
    const placed = nodes.map((node) => ({
        ...node,
        r: NODE_RADIUS[node.hop] ?? NODE_RADIUS.at(-1),
        ...(node.id === seed ? { fx: 0, fy: 0 } : {}),
    }))
    const edges = links.map((link) => ({ ...link }))

    forceSimulation(placed)
        .force(
            'link',
            forceLink(edges)
                .id((node) => node.id)
                .distance((link) => 80 + 120 * (1 - link.score))
                .strength(0.7)
        )
        .force('charge', forceManyBody().strength(-600))
        .force(
            'collide',
            forceCollide((node) => node.r + 14)
        )
        .force('x', forceX(0).strength(0.02))
        .force('y', forceY(0).strength(0.14))
        .stop()
        .tick(SETTLE_TICKS)

    const minX = Math.min(...placed.map((node) => node.x - node.r)) - PADDING
    const maxX = Math.max(...placed.map((node) => node.x + node.r)) + PADDING
    const minY = Math.min(...placed.map((node) => node.y - node.r)) - PADDING
    const maxY = Math.max(...placed.map((node) => node.y + node.r + LABEL_ROOM)) + PADDING

    return {
        nodes: placed,
        links: edges.map((edge) => ({ ...edge, key: linkKey(edge) })),
        viewBox: `${minX} ${minY} ${maxX - minX} ${maxY - minY}`,
    }
}
