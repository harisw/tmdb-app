export const POSTER_PLACEHOLDER = '/poster-placeholder.svg'
export const BACKDROP_PLACEHOLDER = '/backdrop-placeholder.svg'

function swap(event, fallback) {
    if (!event.target.src.endsWith(fallback)) {
        event.target.src = fallback
    }
}

export const onPosterError = (event) => swap(event, POSTER_PLACEHOLDER)
export const onBackdropError = (event) => swap(event, BACKDROP_PLACEHOLDER)
