import { forceSimulation, forceManyBody, forceLink, forceCenter, forceCollide } from 'd3-force'
import { select as d3Select } from 'd3-selection'
import { zoom, zoomIdentity } from 'd3-zoom'
import { drag } from 'd3-drag'
import 'd3-transition'
import { POSTER_PLACEHOLDER } from '../utils/img.js'

const NODE_R = 26
const POSTER_R = 24
const TAP_SLOP = 4
const DOUBLE_TAP_MS = 260
const LABEL_MAX = 22
const DIMMED = 0.22
const CAMERA_MS = 450
const FIT_PADDING = 48

const HUE = 'var(--color-glow-red)'
const RING = {
    root: { stroke: HUE, width: 3 },
    expanded: { stroke: 'rgb(255 255 255 / 0.75)', width: 2 },
    idle: { stroke: 'rgb(255 255 255 / 0.22)', width: 1.5 },
}

export const linkKey = (s, t) => [s, t].sort((a, b) => a - b).join('-')

/**
 * A traversable force-directed graph rendered as SVG.
 *
 * The node/link arrays are deliberately plain (not Vue refs): d3-force mutates
 * every node with x/y/vx/vy on each tick, so wrapping them in reactivity would
 * be both wasteful and a source of update loops.
 */
export function createForceGraph(svgEl, { onSelect, onExpand, onLinkClick, onBackground }) {
    const nodes = []
    const links = []
    const nodeById = new Map()
    const linkKeys = new Set()

    let width = svgEl.clientWidth || 800
    let height = svgEl.clientHeight || 600
    let pendingTap = null
    let hoveredId = null
    let hoveredLinkKey = null
    let selectedId = null
    let selectedLinkKey = null

    const svg = d3Select(svgEl)
    const root = svg.append('g')
    const linkLayer = root.append('g')
    const nodeLayer = root.append('g')

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
    svg.on('click', (event) => {
        if (event.target === svgEl) onBackground?.()
    })

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

    function endpointIds(link) {
        return [link.source.id ?? link.source, link.target.id ?? link.target]
    }

    function litState() {
        const linkKeyInFocus = hoveredLinkKey ?? selectedLinkKey
        if (linkKeyInFocus) {
            const link = links.find((l) => l.key === linkKeyInFocus)
            return { nodeIds: new Set(link ? endpointIds(link) : []), linkKeys: new Set([linkKeyInFocus]) }
        }

        const activeId = hoveredId ?? selectedId
        if (activeId === null) return null

        const nodeIds = new Set([activeId])
        const litLinks = new Set()
        for (const link of links) {
            const [source, target] = endpointIds(link)
            if (source === activeId || target === activeId) {
                nodeIds.add(source)
                nodeIds.add(target)
                litLinks.add(link.key)
            }
        }
        return { nodeIds, linkKeys: litLinks }
    }

    function applyState() {
        const lit = litState()

        linkLayer
            .selectAll('line.link-visible')
            .style('stroke', (d) => (lit?.linkKeys.has(d.key) ? HUE : '#ffffff'))
            .attr('stroke-opacity', (d) => {
                if (!lit) return 0.12 + 0.45 * d.score
                return lit.linkKeys.has(d.key) ? 0.9 : 0.05
            })
            .attr('stroke-width', (d) => 0.75 + 1.75 * d.score + (lit?.linkKeys.has(d.key) ? 0.75 : 0))

        const nodeSel = nodeLayer
            .selectAll('g.node')
            .attr('opacity', (d) => (!lit || lit.nodeIds.has(d.id) ? 1 : DIMMED))

        nodeSel
            .select('circle.ring')
            .attr('class', (d) => (d.busy ? 'ring node-busy' : 'ring'))
            .style('stroke', (d) => (d.busy ? '#ffffff' : ringFor(d).stroke))
            .attr('stroke-width', (d) => ringFor(d).width)
            .attr('stroke-dasharray', (d) => (d.busy ? '4 4' : null))

        nodeSel.select('circle.halo').attr('opacity', (d) => (d.id === selectedId ? 1 : 0))
        nodeSel.select('image').attr('opacity', (d) => (d.busy ? 0.35 : 1))
        nodeSel.attr('aria-pressed', (d) => String(d.id === selectedId))
    }

    function ringFor(d) {
        if (d.root) return RING.root
        return d.expanded ? RING.expanded : RING.idle
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
        linkLayer
            .selectAll('g.link')
            .data(links, (d) => d.key)
            .join((enter) => {
                const g = enter.append('g').attr('class', 'link')

                g.append('line')
                    .attr('class', 'link-visible')
                    .attr('stroke-linecap', 'round')
                    .style('pointer-events', 'none')

                g.append('line')
                    .attr('class', 'link-hit')
                    .attr('stroke', 'transparent')
                    .attr('stroke-width', 16)
                    .style('cursor', 'pointer')
                    .on('mouseenter', (ev, d) => {
                        hoveredLinkKey = d.key
                        applyState()
                    })
                    .on('mouseleave', () => {
                        hoveredLinkKey = null
                        applyState()
                    })
                    .on('click', (ev, d) => {
                        ev.stopPropagation()
                        onLinkClick?.(d)
                    })

                return g
            })

        nodeLayer
            .selectAll('g.node')
            .data(nodes, (d) => d.id)
            .join((enter) => {
                const g = enter
                    .append('g')
                    .attr('class', 'node')
                    .attr('tabindex', 0)
                    .attr('role', 'button')
                    .attr('aria-label', (d) => [d.name, d.year].filter(Boolean).join(', '))
                    .style('cursor', 'pointer')
                    .style('outline', 'none')
                    .style('transition', 'opacity 200ms ease-out')
                    .call(dragBehavior())
                    .on('mouseenter focus', (ev, d) => {
                        hoveredId = d.id
                        applyState()
                    })
                    .on('mouseleave blur', () => {
                        hoveredId = null
                        applyState()
                    })
                    .on('keydown', (ev, d) => {
                        if (ev.key === 'Enter' || ev.key === ' ') {
                            ev.preventDefault()
                            onSelect(d)
                        }
                    })

                g.append('clipPath')
                    .attr('id', (d) => `clip-${d.id}`)
                    .append('circle')
                    .attr('r', POSTER_R)

                g.append('circle')
                    .attr('class', 'halo')
                    .attr('r', NODE_R + 6)
                    .attr('fill', 'none')
                    .style('stroke', HUE)
                    .attr('stroke-width', 2)
                    .attr('opacity', 0)

                g.append('circle').attr('class', 'ring').attr('r', NODE_R).style('fill', 'var(--color-licorice)')

                g.append('image')
                    .attr('x', -POSTER_R)
                    .attr('y', -POSTER_R)
                    .attr('width', POSTER_R * 2)
                    .attr('height', POSTER_R * 2)
                    .attr('preserveAspectRatio', 'xMidYMid slice')
                    .attr('clip-path', (d) => `url(#clip-${d.id})`)
                    .attr('href', posterSrc)
                    .on('error', function () {
                        d3Select(this).attr('href', POSTER_PLACEHOLDER)
                    })

                g.append('text')
                    .attr('y', NODE_R + 16)
                    .attr('text-anchor', 'middle')
                    .attr('fill', 'rgb(255 255 255 / 0.85)')
                    .attr('font-size', 12)
                    .attr('font-weight', 500)
                    .attr('paint-order', 'stroke')
                    .style('stroke', 'var(--color-licorice)')
                    .attr('stroke-width', 4)
                    .attr('stroke-linejoin', 'round')
                    .style('pointer-events', 'none')
                    .text((d) => (d.name.length > LABEL_MAX ? d.name.slice(0, LABEL_MAX - 1) + '…' : d.name))

                g.append('title').text((d) => d.name)

                return g
            })

        applyState()
        simulation.nodes(nodes)
        simulation.force('link').links(links)
        simulation.alpha(0.7).restart()
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
        selectedId = null
        selectedLinkKey = null
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

    function camera() {
        const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches
        return svg
            .interrupt()
            .transition()
            .duration(reduced ? 0 : CAMERA_MS)
    }

    function focusOn(id) {
        const node = nodeById.get(id)
        if (node) camera().call(zoomBehavior.translateTo, node.x, node.y)
    }

    function fitToView() {
        if (!nodes.length) return
        const xs = nodes.map((n) => n.x)
        const ys = nodes.map((n) => n.y)
        const minX = Math.min(...xs) - NODE_R
        const maxX = Math.max(...xs) + NODE_R
        const minY = Math.min(...ys) - NODE_R
        const maxY = Math.max(...ys) + NODE_R + 20
        const [minScale, maxScale] = zoomBehavior.scaleExtent()
        const scale = Math.min(
            maxScale,
            Math.max(
                minScale,
                Math.min((width - FIT_PADDING * 2) / (maxX - minX), (height - FIT_PADDING * 2) / (maxY - minY), 1.5)
            )
        )
        const transform = zoomIdentity
            .translate(width / 2, height / 2)
            .scale(scale)
            .translate(-(minX + maxX) / 2, -(minY + maxY) / 2)
        camera().call(zoomBehavior.transform, transform)
    }

    function select(id) {
        selectedId = id
        selectedLinkKey = null
        applyState()
    }

    function selectLink(key) {
        selectedLinkKey = key
        selectedId = null
        applyState()
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
        applyState()
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
        svg.on('.zoom', null).on('click', null)
        root.remove()
    }

    return { load, merge, resize, destroy, setBusy, pause, resume, select, selectLink, focusOn, fitToView }
}
