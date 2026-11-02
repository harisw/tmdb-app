import { drag } from 'd3-drag'

const TAP_SLOP = 4
const DOUBLE_TAP_MS = 260

export function createNodeDrag(simulation, { onTap, onDoubleTap }) {
    let pendingTap = null

    function cancel() {
        if (pendingTap) clearTimeout(pendingTap.timer)
        pendingTap = null
    }

    function handleTap(node) {
        const isSecondTap = pendingTap?.id === node.id
        cancel()

        if (isSecondTap) {
            onDoubleTap(node)
            return
        }

        pendingTap = {
            id: node.id,
            timer: setTimeout(() => {
                pendingTap = null
                onTap(node)
            }, DOUBLE_TAP_MS),
        }
    }

    const behavior = drag()
        .on('start', (event, node) => {
            if (!event.active) simulation.alphaTarget(0.3).restart()
            node.fx = node.x
            node.fy = node.y
            node.dragOrigin = [event.x, event.y]
        })
        .on('drag', (event, node) => {
            node.fx = event.x
            node.fy = event.y
        })
        .on('end', (event, node) => {
            if (!event.active) simulation.alphaTarget(0)
            node.fx = null
            node.fy = null

            const [startX, startY] = node.dragOrigin
            if (Math.hypot(event.x - startX, event.y - startY) < TAP_SLOP) handleTap(node)
        })

    return { behavior, cancel }
}
