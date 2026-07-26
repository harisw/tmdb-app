import { forceSimulation, forceManyBody, forceLink, forceCenter, forceCollide } from 'd3-force'
import { select } from 'd3-selection'
import { zoom, zoomIdentity } from 'd3-zoom'
import { drag } from 'd3-drag'

const NODE_R = 26
const POSTER_R = 24

/**
 * A traversable force-directed graph rendered as SVG.
 *
 * The node/link arrays are deliberately plain (not Vue refs): d3-force mutates
 * every node with x/y/vx/vy on each tick, so wrapping them in reactivity would
 * be both wasteful and a source of update loops.
 *
 * @param {SVGSVGElement} svgEl
 * @param {object} opts
 * @param {string} opts.posterBase   base URL to prefix poster_path with
 * @param {string} opts.placeholder  fallback poster src
 * @param {(node) => void} opts.onSelect  called when a node is clicked (not dragged)
 */
export function createForceGraph(svgEl, { posterBase, placeholder, onSelect }) {
    const nodes = []
    const links = []
    const nodeById = new Map()
    const linkKeys = new Set()

    let width = svgEl.clientWidth || 800
    let height = svgEl.clientHeight || 600

    const svg = select(svgEl)
    const root = svg.append('g')
    const linkLayer = root.append('g').attr('stroke', '#ffffff')
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
    svg.call(zoomBehavior)

    function ticked() {
        linkLayer
            .selectAll('line')
            .attr('x1', (d) => d.source.x)
            .attr('y1', (d) => d.source.y)
            .attr('x2', (d) => d.target.x)
            .attr('y2', (d) => d.target.y)

        nodeLayer.selectAll('g.node').attr('transform', (d) => `translate(${d.x},${d.y})`)
    }

    function posterSrc(d) {
        return d.poster_path ? posterBase + d.poster_path : placeholder
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
                const moved = Math.hypot(event.x - d._sx, event.y - d._sy)
                if (moved < 4) onSelect(d)
            })
    }

    function update() {
        // Links
        linkLayer
            .selectAll('line')
            .data(links, (d) => d.key)
            .join((enter) => enter.append('line').attr('stroke-linecap', 'round'))
            .attr('stroke-opacity', (d) => 0.15 + 0.5 * (d.score ?? 0.5))
            .attr('stroke-width', (d) => 1 + 3 * (d.score ?? 0.5))

        // Nodes
        nodeLayer
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
                        select(this).attr('href', placeholder)
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
            .select('circle.ring')
            .attr('stroke', (d) => (d.expanded ? '#22c55e' : d.root ? '#f59e0b' : '#6b7280'))

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

    function destroy() {
        simulation.stop()
        svg.on('.zoom', null)
        root.remove()
    }

    return { load, merge, resize, destroy }
}
