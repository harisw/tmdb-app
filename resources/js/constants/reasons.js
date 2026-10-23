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
