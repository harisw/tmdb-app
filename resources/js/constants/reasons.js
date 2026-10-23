import { Brain, Film, Tag, User, Users } from 'lucide-vue-next'

const REASON_META = {
    shared_keywords: {
        label: 'Shared keywords',
        icon: Tag,
        iconBg: 'bg-violet-500/20 text-violet-400',
        bar: 'bg-violet-500',
    },
    semantic_similarity: {
        label: 'Similar overall feel',
        icon: Brain,
        iconBg: 'bg-emerald-500/20 text-emerald-400',
        bar: 'bg-emerald-500',
    },
    shared_casts: { label: 'Shared cast', icon: User, iconBg: 'bg-amber-500/20 text-amber-400', bar: 'bg-amber-500' },
    shared_genres: { label: 'Shared genres', icon: Film, iconBg: 'bg-blue-500/20 text-blue-400', bar: 'bg-blue-500' },
}

export function reasonMeta(type) {
    return (
        REASON_META[type] ?? {
            label: type.replace(/^shared_/, 'Shared ').replace(/_/g, ' '),
            icon: Users,
            iconBg: 'bg-gray-500/20 text-gray-400',
            bar: 'bg-gray-500',
        }
    )
}

export function headlineReasons(reasons, limit = 2) {
    const specific = reasons.filter((reason) => reason.type !== 'semantic_similarity' && reason.values?.length)
    return (specific.length ? specific : reasons).slice(0, limit)
}
