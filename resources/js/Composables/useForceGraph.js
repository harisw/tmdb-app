import { forceCenter, forceCollide, forceLink, forceManyBody, forceSimulation, forceX, forceY } from 'd3-force'
import { pointer, select as d3Select } from 'd3-selection'
import { zoom, zoomIdentity } from 'd3-zoom'
import 'd3-transition'
import { prefersReducedMotion } from '../utils/motion.js'
import { fitTransform } from './graph/camera.js'
import { drawInLinks, igniteNodes } from './graph/entrance.js'
import { endpointIds, linkKey } from './graph/links.js'
import { createNodeDrag } from './graph/nodeDrag.js'
import { appendLinks, appendNodes } from './graph/shapes.js'
import { COLORS, NODE_RADIUS, linkAppearance, nodeOpacity, ringFor } from './graph/style.js'

const CAMERA_MS = 450
const ZOOM_EXTENT = [0.2, 4]
const GATHER_STRENGTH = 0.04
const SPAWN_JITTER = 24
const SETTLE_TICKS = 200

/**
 * A traversable force-directed graph rendered as SVG.
 *
 * The node/link arrays are deliberately plain (not Vue refs): d3-force mutates
 * every node with x/y/vx/vy on each tick, so wrapping them in reactivity would
 * be both wasteful and a source of update loops.
 */
export function createForceGraph(svgEl, { onSelect, onExpand, onLinkClick, onLinkHover, onBackground, onZoom }) {
    const nodes = []
    const links = []
    const nodeById = new Map()
    const linkKeys = new Set()

    let width = svgEl.clientWidth || 800
    let height = svgEl.clientHeight || 600
    let hoveredId = null
    let hoveredLinkKey = null
    let selectedId = null
    let selectedLinkKey = null
    let linkFilter = () => true
    let routeKeys = new Set()

    const svg = d3Select(svgEl)
    const root = svg.append('g')
    const linkLayer = root.append('g')
    const nodeLayer = root.append('g')

    const simulation = forceSimulation(nodes)
        .force('charge', forceManyBody().strength(-420))
        .force(
            'link',
            forceLink()
                .id((node) => node.id)
                .distance((link) => 70 + 120 * (1 - (link.score ?? 0.5)))
        )
        .force('collide', forceCollide(NODE_RADIUS + 8))
        .on('tick', ticked)
    gatherAround(width / 2, height / 2)

    const nodeDrag = createNodeDrag(simulation, { onTap: onSelect, onDoubleTap: onExpand })

    const zoomBehavior = zoom()
        .scaleExtent(ZOOM_EXTENT)
        .on('zoom', (event) => {
            root.attr('transform', event.transform)
            onZoom?.(event.transform)
        })
    svg.call(zoomBehavior).on('dblclick.zoom', null)
    svg.on('click', (event) => {
        if (event.target === svgEl) onBackground?.()
    })

    function gatherAround(x, y) {
        simulation
            .force('center', forceCenter(x, y))
            .force('x', forceX(x).strength(GATHER_STRENGTH))
            .force('y', forceY(y).strength(GATHER_STRENGTH))
    }

    function reheat(alpha) {
        simulation.alpha(Math.max(simulation.alpha(), alpha)).restart()
    }

    function ticked() {
        linkLayer
            .selectAll('g.link')
            .selectAll('line')
            .attr('x1', (link) => link.source.x)
            .attr('y1', (link) => link.source.y)
            .attr('x2', (link) => link.target.x)
            .attr('y2', (link) => link.target.y)

        nodeLayer.selectAll('g.node').attr('transform', (node) => `translate(${node.x},${node.y})`)
    }

    const isShown = (link) => routeKeys.has(link.key) || linkFilter(link)

    function litState(shownLinks) {
        const focusedLinkKey = hoveredLinkKey ?? selectedLinkKey
        if (focusedLinkKey) {
            const link = shownLinks.find((candidate) => candidate.key === focusedLinkKey)
            return { nodeIds: new Set(link ? endpointIds(link) : []), linkKeys: new Set([focusedLinkKey]) }
        }

        const focusedId = hoveredId ?? selectedId
        if (focusedId === null) return null

        const nodeIds = new Set([focusedId])
        const litLinkKeys = new Set()
        for (const link of shownLinks) {
            const ends = endpointIds(link)
            if (!ends.includes(focusedId)) continue
            ends.forEach((id) => nodeIds.add(id))
            litLinkKeys.add(link.key)
        }
        return { nodeIds, linkKeys: litLinkKeys }
    }

    function applyState() {
        const shownLinks = links.filter(isShown)
        const lit = litState(shownLinks)
        const connectedIds = new Set(shownLinks.flatMap(endpointIds))

        linkLayer.selectAll('g.link').style('display', (link) => (isShown(link) ? null : 'none'))
        linkLayer.selectAll('line.link-visible').each(function (link) {
            const look = linkAppearance(link, { lit, onRoute: routeKeys.has(link.key) })
            d3Select(this)
                .style('stroke', look.stroke)
                .attr('stroke-opacity', look.opacity)
                .attr('stroke-width', look.width)
        })

        const nodeSelection = nodeLayer
            .selectAll('g.node')
            .attr('opacity', (node) => nodeOpacity(node, { lit, connected: node.root || connectedIds.has(node.id) }))
            .attr('aria-pressed', (node) => String(node.id === selectedId))

        nodeSelection
            .select('circle.ring')
            .attr('class', (node) => (node.busy ? 'ring node-busy' : 'ring'))
            .style('stroke', (node) => (node.busy ? COLORS.busy : ringFor(node).stroke))
            .attr('stroke-width', (node) => ringFor(node).width)
            .attr('stroke-dasharray', (node) => (node.busy ? '4 4' : null))
        nodeSelection.select('circle.halo').attr('opacity', (node) => (node.id === selectedId ? 1 : 0))
        nodeSelection.select('image').attr('opacity', (node) => (node.busy ? 0.35 : 1))
    }

    function syncLinkForce(alpha) {
        simulation.force('link').links(links.filter(isShown))
        reheat(alpha)
    }

    function hoverNode(node) {
        hoveredId = node?.id ?? null
        applyState()
    }

    function hoverLink(link, event) {
        const key = link?.key ?? null
        if (key !== hoveredLinkKey) {
            hoveredLinkKey = key
            applyState()
        }
        onLinkHover?.(link, link ? pointer(event, svgEl) : null)
    }

    function render() {
        linkLayer
            .selectAll('g.link')
            .data(links, (link) => link.key)
            .join((enter) => {
                const entered = appendLinks(enter, { onHover: hoverLink, onClick: (link) => onLinkClick?.(link) })
                drawInLinks(entered.select('line.link-visible'))
                return entered
            })

        nodeLayer
            .selectAll('g.node')
            .data(nodes, (node) => node.id)
            .join((enter) => {
                const entered = appendNodes(enter, {
                    drag: nodeDrag.behavior,
                    onHover: hoverNode,
                    onActivate: onSelect,
                })
                igniteNodes(entered.select('g.node-body'))
                return entered
            })

        simulation.nodes(nodes)
        applyState()
        syncLinkForce(0.7)
    }

    function addNode(raw, extra = {}, origin = { x: width / 2, y: height / 2 }) {
        const existing = nodeById.get(raw.id)
        if (existing) {
            Object.assign(existing, extra)
            return existing
        }

        const node = {
            ...raw,
            ...extra,
            x: origin.x + (Math.random() - 0.5) * SPAWN_JITTER,
            y: origin.y + (Math.random() - 0.5) * SPAWN_JITTER,
        }
        nodeById.set(node.id, node)
        nodes.push(node)
        return node
    }

    function addLink(raw) {
        const key = linkKey(raw.source, raw.target)
        if (linkKeys.has(key)) return
        linkKeys.add(key)
        links.push({ ...raw, key })
    }

    function clear() {
        nodes.length = 0
        links.length = 0
        nodeById.clear()
        linkKeys.clear()
        selectedId = null
        selectedLinkKey = null
        routeKeys = new Set()
    }

    function camera() {
        return svg
            .interrupt()
            .transition()
            .duration(prefersReducedMotion() ? 0 : CAMERA_MS)
    }

    /** Seed the graph from scratch with the starting title's neighbourhood. */
    function load(expansion) {
        clear()
        merge(expansion, true)
        svg.call(zoomBehavior.transform, zoomIdentity)
    }

    /** Add one title's neighbourhood; new neighbours spawn from that title. */
    function merge({ node, nodes: neighbours, links: edges }, isRoot = false) {
        const parent = addNode(node, isRoot ? { root: true, expanded: true } : { expanded: true })
        neighbours.forEach((neighbour) => addNode(neighbour, {}, parent))
        edges.forEach(addLink)
        render()
    }

    function settleAndFit() {
        simulation.tick(SETTLE_TICKS)
        ticked()
        fitToView()
    }

    function setLinkFilter(predicate) {
        linkFilter = predicate
        applyState()
        syncLinkForce(0.5)
    }

    function setRoute(keys) {
        const shownBefore = links.filter(isShown).length
        routeKeys = new Set(keys)
        applyState()
        if (links.filter(isShown).length !== shownBefore) syncLinkForce(0.3)
    }

    function focusOn(id) {
        const node = nodeById.get(id)
        if (node) camera().call(zoomBehavior.translateTo, node.x, node.y)
    }

    function fitToView() {
        if (!nodes.length) return
        camera().call(zoomBehavior.transform, fitTransform(nodes, { width, height, scaleExtent: ZOOM_EXTENT }))
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

    function setBusy(id, isBusy) {
        const node = nodeById.get(id)
        if (!node) return
        node.busy = isBusy
        applyState()
    }

    function resize() {
        width = svgEl.clientWidth || width
        height = svgEl.clientHeight || height
        gatherAround(width / 2, height / 2)
        simulation.alpha(0.4).restart()
    }

    function pause() {
        simulation.stop()
    }

    function resume() {
        simulation.alpha(0.1).restart()
    }

    function destroy() {
        nodeDrag.cancel()
        simulation.stop()
        svg.on('.zoom', null).on('click', null)
        root.remove()
    }

    return {
        load,
        merge,
        settleAndFit,
        setLinkFilter,
        setRoute,
        select,
        selectLink,
        focusOn,
        fitToView,
        setBusy,
        resize,
        pause,
        resume,
        destroy,
    }
}
