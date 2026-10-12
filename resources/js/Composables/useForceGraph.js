import { forceSimulation, forceManyBody, forceLink, forceCenter, forceCollide } from 'd3-force'
import { select } from 'd3-selection'
import { zoom, zoomIdentity } from 'd3-zoom'
import { drag } from 'd3-drag'
import { POSTER_PLACEHOLDER } from '../utils/img.js'

const NODE_R = 26
const POSTER_R = 24
const TAP_SLOP = 4
const DOUBLE_TAP_MS = 260

/**
 * A traversable force-directed graph rendered as SVG.
 *
 * The node/link arrays are deliberately plain (not Vue refs): d3-force mutates
 * every node with x/y/vx/vy on each tick, so wrapping them in reactivity would
 * be both wasteful and a source of update loops.
 */
export function createForceGraph(svgEl, { onSelect, onExpand, onLinkClick }) {
    const nodes = []
    const links = []
    const nodeById = new Map()
    const linkKeys = new Set()

    let width = svgEl.clientWidth || 800
    let height = svgEl.clientHeight || 600
    let pendingTap = null

    const svg = select(svgEl)
    const root = svg.append('g')
    const linkLayer = root.append('g')
    const nodeLayer = root.append('g')

    const linkKey = (s, t) => [s, t].sort((a, b) => a - b).join('-')

    const simulation = forceSimulation(nodes)
        .force('charge', forceManyBody().strength(-420))
        .force(
            'link',
            forceLink(links)
                .id((d) => d.id)
                .distance((l) => 70 + 120 * (1 - (l.score ?? 0.5)))
        )
        .force('center', forceCenter(width / 2, height / 2))
        .force('collide', forceCollide(NODE_R + 8))
        .on('tick', ticked)

    const zoomBehavior = zoom()
        .scaleExtent([0.2, 4])
        .on('zoom', (event) => root.attr('transform', event.transform))
    svg.call(zoomBehavior).on('dblclick.zoom', null)

    function ticked() {
        linkLayer
            .selectAll('g.link')
            .selectAll('line')
            .attr('x1', (d) => d.source.x)
            .attr('y1', (d) => d.source.y)
            .attr('x2', (d) => d.target.x)
            .attr('y2', (d) => d.target.y)

        nodeLayer.selectAll('g.node').attr('transform', (d) => `translate(${d.x},${d.y})`)
    }

    function posterSrc(d) {
        return d.poster_path || POSTER_PLACEHOLDER
    }

    function dragBehavior() {
        return drag()
            .on('start', (event, d) => {
                if (!event.active) simulation.alphaTarget(0.3).restart()
                d.fx = d.x
                d.fy = d.y
                d._sx = event.x
                d._sy = event.y
            })
            .on('drag', (event, d) => {
                d.fx = event.x
                d.fy = event.y
            })
            .on('end', (event, d) => {
                if (!event.active) simulation.alphaTarget(0)
                d.fx = null
                d.fy = null
                if (Math.hypot(event.x - d._sx, event.y - d._sy) >= TAP_SLOP) return

                if (pendingTap) {
                    const sameNode = pendingTap.id === d.id
                    clearTimeout(pendingTap.timer)
                    pendingTap = null
                    if (sameNode) {
                        onExpand(d)
                        return
                    }
                }

                pendingTap = {
                    id: d.id,
                    timer: setTimeout(() => {
                        pendingTap = null
                        onSelect(d)
                    }, DOUBLE_TAP_MS),
                }
            })
    }

    function update() {
        // Links
        linkLayer
            .selectAll('g.link')
            .data(links, (d) => {
                return d.key
            })
            .join((enter) => {
                const g = enter.append('g').attr('class', 'link')

                g.append('line')
                    .attr('class', 'link-hit')
                    .attr('stroke', 'transparent')
                    .attr('stroke-width', 16)
                    .style('cursor', onLinkClick ? 'pointer' : null)
                    .on('click', (ev, d) => {
                        ev.stopPropagation()
                        onLinkClick?.(d)
                    })

                g.append('line')
                    .attr('class', 'link-visible')
                    .attr('stroke', '#ffffff')
                    .attr('stroke-linecap', 'round')
                    .style('pointer-events', 'none')

                return g
            })
            .select('line.link-visible')
            .attr('stroke-opacity', (d) => 0.15 + 0.5 * d.score)
            .attr('stroke-width', (d) => 2 * d.score)
            .attr('stroke-dasharray', (d) => (d.score < 0.6 ? 8 : null))

        // Nodes
        const node = nodeLayer
            .selectAll('g.node')
            .data(nodes, (d) => d.id)
            .join((enter) => {
                const g = enter.append('g').attr('class', 'node').style('cursor', 'pointer').call(dragBehavior())

                g.append('clipPath')
                    .attr('id', (d) => `clip-${d.id}`)
                    .append('circle')
                    .attr('r', POSTER_R)

                g.append('circle')
                    .attr('class', 'ring')
                    .attr('r', NODE_R)
                    .attr('fill', '#1a1420')
                    .attr('stroke-width', 2)

                g.append('image')
                    .attr('x', -POSTER_R)
                    .attr('y', -POSTER_R)
                    .attr('width', POSTER_R * 2)
                    .attr('height', POSTER_R * 2)
                    .attr('preserveAspectRatio', 'xMidYMid slice')
                    .attr('clip-path', (d) => `url(#clip-${d.id})`)
                    .attr('href', posterSrc)
                    .on('error', function () {
                        select(this).attr('href', POSTER_PLACEHOLDER)
                    })

                g.append('text')
                    .attr('y', NODE_R + 14)
                    .attr('text-anchor', 'middle')
                    .attr('fill', '#e5e7eb')
                    .attr('font-size', 11)
                    .attr('paint-order', 'stroke')
                    .attr('stroke', '#0f0b12')
                    .attr('stroke-width', 3)
                    .text((d) => (d.name.length > 22 ? d.name.slice(0, 21) + '…' : d.name))

                g.append('title').text((d) => d.name)

                return g
            })

        applyNodeStyle(node)
        simulation.nodes(nodes)
        simulation.force('link').links(links)
        simulation.alpha(0.7).restart()
    }

    function applyNodeStyle(sel) {
        sel.select('circle.ring')
            .attr('class', (d) => (d.busy ? 'ring node-busy' : 'ring'))
            .attr('stroke', (d) => (d.busy ? '#e5e7eb' : d.expanded ? '#22c55e' : d.root ? '#f59e0b' : '#6b7280'))
            .attr('stroke-dasharray', (d) => (d.busy ? '4 4' : null))

        sel.select('image').attr('opacity', (d) => (d.busy ? 0.35 : 1))
    }

    function addNode(raw, extra = {}) {
        const existing = nodeById.get(raw.id)
        if (existing) {
            Object.assign(existing, extra)
            return existing
        }
        const node = { ...raw, ...extra, x: width / 2, y: height / 2 }
        nodeById.set(node.id, node)
        nodes.push(node)
        return node
    }

    function addLink(l) {
        const key = linkKey(l.source, l.target)
        if (linkKeys.has(key)) return
        linkKeys.add(key)
        links.push({ ...l, key })
    }

    /** Seed the graph from scratch. */
    function load({ node, nodes: neighbours, links: edges }) {
        nodes.length = 0
        links.length = 0
        nodeById.clear()
        linkKeys.clear()
        merge({ node, nodes: neighbours, links: edges }, true)
        centerOnRoot()
    }

    /** Add one title's neighbourhood to the existing graph. */
    function merge({ node, nodes: neighbours, links: edges }, isRoot = false) {
        addNode(node, { root: isRoot ? true : nodeById.get(node.id)?.root, expanded: true })
        neighbours.forEach((n) => addNode(n))
        edges.forEach(addLink)
        update()
    }

    function centerOnRoot() {
        svg.call(zoomBehavior.transform, zoomIdentity)
    }

    function resize() {
        width = svgEl.clientWidth || width
        height = svgEl.clientHeight || height
        simulation.force('center', forceCenter(width / 2, height / 2))
        simulation.alpha(0.4).restart()
    }

    function setBusy(id, isBusy) {
        const node = nodeById.get(id)
        if (!node) return
        node.busy = isBusy
        applyNodeStyle(nodeLayer.selectAll('g.node').filter((d) => d.id === id))
    }

    function pause() {
        simulation.stop()
    }

    function resume() {
        simulation.alpha(0.1).restart()
    }

    function destroy() {
        if (pendingTap) clearTimeout(pendingTap.timer)
        pendingTap = null
        simulation.stop()
        svg.on('.zoom', null)
        root.remove()
    }

    return { load, merge, resize, destroy, setBusy, pause, resume }
}
