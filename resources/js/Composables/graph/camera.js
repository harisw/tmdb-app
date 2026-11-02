import { zoomIdentity } from 'd3-zoom'
import { NODE_RADIUS } from './style.js'

const FIT_PADDING = 48
const LABEL_ROOM = 20
const MAX_FIT_SCALE = 1.5

export function fitTransform(nodes, { width, height, scaleExtent: [minScale, maxScale] }) {
    const xs = nodes.map((node) => node.x)
    const ys = nodes.map((node) => node.y)
    const minX = Math.min(...xs) - NODE_RADIUS
    const maxX = Math.max(...xs) + NODE_RADIUS
    const minY = Math.min(...ys) - NODE_RADIUS
    const maxY = Math.max(...ys) + NODE_RADIUS + LABEL_ROOM

    const fittingScale = Math.min(
        (width - FIT_PADDING * 2) / (maxX - minX),
        (height - FIT_PADDING * 2) / (maxY - minY),
        MAX_FIT_SCALE
    )
    const scale = Math.min(maxScale, Math.max(minScale, fittingScale))

    return zoomIdentity
        .translate(width / 2, height / 2)
        .scale(scale)
        .translate(-(minX + maxX) / 2, -(minY + maxY) / 2)
}
