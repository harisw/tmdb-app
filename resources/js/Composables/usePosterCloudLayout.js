import { forceCollide, forceManyBody, forceSimulation, forceX, forceY } from 'd3-force'

export const POSTER_WIDTH = 64
export const POSTER_HEIGHT = 96

const POSTER_ASPECT = POSTER_HEIGHT / POSTER_WIDTH
const COLLIDE_RADIUS = 42
const PADDING = 16
const BUBBLE_GAP = 32
const MIN_BUBBLE_RADIUS = 150
const PICK_SCALE = 0.68
const GOLDEN_ANGLE = Math.PI * (3 - Math.sqrt(5))
const SETTLE_TICKS = 300

function posterScale(id) {
    return 0.9 + ((id * 7919) % 5) * 0.05
}

export function layoutPosterCloud(titles) {
    const nodes = titles.map((title) => ({ id: title.id, scale: posterScale(title.id) }))

    forceSimulation(nodes)
        .force('collide', forceCollide((node) => COLLIDE_RADIUS * node.scale).iterations(3))
        .force('charge', forceManyBody().strength(-4))
        .force('x', forceX(0).strength(0.025))
        .force('y', forceY(0).strength(0.08))
        .stop()
        .tick(SETTLE_TICKS)

    for (const node of nodes) {
        node.y *= POSTER_ASPECT
    }

    const minX = Math.min(...nodes.map((node) => node.x)) - POSTER_WIDTH / 2 - PADDING
    const maxX = Math.max(...nodes.map((node) => node.x)) + POSTER_WIDTH / 2 + PADDING
    const minY = Math.min(...nodes.map((node) => node.y)) - POSTER_HEIGHT / 2 - PADDING
    const maxY = Math.max(...nodes.map((node) => node.y)) + POSTER_HEIGHT / 2 + PADDING

    const cloudHeight = maxY - minY
    const radius = Math.max(MIN_BUBBLE_RADIUS, cloudHeight * 0.36)
    const height = Math.max(cloudHeight, radius * 2 + PADDING)
    const offsetY = (height - cloudHeight) / 2 - minY
    const bubble = { x: maxX - minX + BUBBLE_GAP + radius, y: height / 2, r: radius }

    return {
        width: bubble.x + radius + PADDING,
        height,
        bubble,
        places: new Map(nodes.map((node) => [node.id, { x: node.x - minX, y: node.y + offsetY, scale: node.scale }])),
    }
}

export function bubbleSlot(bubble, index, capacity) {
    const spread = (bubble.r - (POSTER_HEIGHT * PICK_SCALE) / 2) / Math.sqrt(capacity)
    const angle = index * GOLDEN_ANGLE
    const distance = spread * Math.sqrt(index + 0.5)

    return {
        x: bubble.x + Math.cos(angle) * distance,
        y: bubble.y + Math.sin(angle) * distance,
        scale: PICK_SCALE,
    }
}
