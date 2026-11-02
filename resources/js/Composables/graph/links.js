export const linkKey = (source, target) => [source, target].sort((a, b) => a - b).join('-')

export const endpointIds = (link) => [link.source.id ?? link.source, link.target.id ?? link.target]

export function otherEnd(link, id) {
    const [source, target] = endpointIds(link)
    return source === id ? target : source
}
