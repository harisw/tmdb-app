import { computed, reactive, ref } from 'vue'
import { linkKey, otherEnd } from './graph/links.js'

export function useGraphExploration() {
    const titles = reactive(new Map())
    const links = reactive(new Map())
    const discoveredVia = reactive(new Map())
    const trailIds = ref([])

    const rootId = computed(() => trailIds.value[0] ?? null)
    const root = computed(() => titles.get(rootId.value) ?? null)
    const trail = computed(() => trailIds.value.map((id) => titles.get(id)).filter(Boolean))
    const routeKeys = computed(() => new Set(trailIds.value.map((id) => discoveredVia.get(id)?.key).filter(Boolean)))

    const isExpanded = (id) => trailIds.value.includes(id)

    function reset() {
        titles.clear()
        links.clear()
        discoveredVia.clear()
        trailIds.value = []
    }

    function absorb({ node, nodes, links: edges }) {
        if (!titles.has(node.id)) titles.set(node.id, node)

        for (const edge of edges) {
            const link = { ...edge, key: linkKey(edge.source, edge.target) }
            links.set(link.key, link)

            const neighbourId = otherEnd(link, node.id)
            if (!titles.has(neighbourId)) discoveredVia.set(neighbourId, link)
        }

        for (const neighbour of nodes) {
            if (!titles.has(neighbour.id)) titles.set(neighbour.id, neighbour)
        }

        if (!isExpanded(node.id)) trailIds.value = [...trailIds.value, node.id]
    }

    function describe(selected) {
        if (!selected) return null

        if (selected.kind === 'link') {
            const link = links.get(selected.key)
            if (!link) return null
            return { kind: 'link', link, source: titles.get(link.source), target: titles.get(link.target) }
        }

        const node = titles.get(selected.id)
        if (!node) return null

        const via = discoveredVia.get(node.id)
        const relation = via ? { link: via, other: titles.get(otherEnd(via, node.id)) } : null
        return { kind: 'node', node, relation, link: via ?? null, expanded: isExpanded(node.id) }
    }

    return { titles, links, rootId, root, trail, routeKeys, isExpanded, reset, absorb, describe }
}
