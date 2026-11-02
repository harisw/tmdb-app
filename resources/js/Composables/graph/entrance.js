import { select as d3Select } from 'd3-selection'
import 'd3-transition'
import { prefersReducedMotion } from '../../utils/motion.js'

const NODE_MS = 420
const LINK_MS = 520
const STAGGER_MS = 45

const staggered = (data, index) => index * STAGGER_MS

export function igniteNodes(bodies) {
    if (prefersReducedMotion()) return

    bodies
        .attr('transform', 'scale(0)')
        .attr('opacity', 0)
        .transition()
        .delay(staggered)
        .duration(NODE_MS)
        .attr('transform', 'scale(1)')
        .attr('opacity', 1)
}

export function drawInLinks(lines) {
    if (prefersReducedMotion()) return

    lines
        .attr('pathLength', 1)
        .attr('stroke-dasharray', 1)
        .attr('stroke-dashoffset', 1)
        .transition()
        .delay(staggered)
        .duration(LINK_MS)
        .attr('stroke-dashoffset', 0)
        .on('end', function () {
            d3Select(this).attr('pathLength', null).attr('stroke-dasharray', null).attr('stroke-dashoffset', null)
        })
}
