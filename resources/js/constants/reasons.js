import { Brain, Film, Tag, User, Users } from 'lucide-vue-next'

const REASON_META = {
    shared_keywords: {
        label: 'Shared keywords',
        icon: Tag,
    },
    semantic_similarity: {
        label: 'Similar overall feel',
        icon: Brain,
    },
    shared_casts: { label: 'Shared cast', icon: User },
    shared_genres: { label: 'Shared genres', icon: Film },
}

export function reasonMeta(type) {
    return (
        REASON_META[type] ?? {
            label: type.replace(/^shared_/, 'Shared ').replace(/_/g, ' '),
            icon: Users,
        }
    )
}

export function headlineReasons(reasons, limit = 2) {
    const specific = reasons.filter((reason) => reason.type !== 'semantic_similarity' && reason.values?.length)
    return (specific.length ? specific : reasons).slice(0, limit)
}

export const REASON_GROUPS = [
    { id: 'feel', label: 'Feel', icon: Brain, types: ['semantic_similarity'] },
    { id: 'genres', label: 'Genres', icon: Film, types: ['shared_genres'] },
    { id: 'keywords', label: 'Keywords', icon: Tag, types: ['shared_keywords'] },
    { id: 'cast', label: 'Cast', icon: User, types: ['shared_casts'] },
    {
        id: 'crew',
        label: 'Crew',
        icon: Users,
        types: ['shared_director', 'shared_producer', 'shared_composer', 'shared_writer', 'shared_art_director'],
    },
]

const GROUP_BY_TYPE = new Map(REASON_GROUPS.flatMap((group) => group.types.map((type) => [type, group.id])))

export function reasonGroupIds(link) {
    return new Set((link.reasons ?? []).map((reason) => GROUP_BY_TYPE.get(reason.type)).filter(Boolean))
}

export function linkMatchesGroups(link, activeGroupIds) {
    const groupIds = reasonGroupIds(link)
    return groupIds.size === 0 || [...groupIds].some((id) => activeGroupIds.has(id))
}

export function linkHeadline(link, activeGroupIds) {
    const reasons = link.reasons ?? []
    const relevant = reasons.filter((reason) => activeGroupIds.has(GROUP_BY_TYPE.get(reason.type)))
    const [headline] = headlineReasons(relevant.length ? relevant : reasons, 1)
    return headline ?? null
}
