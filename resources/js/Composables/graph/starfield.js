import { zoomIdentity } from 'd3-zoom'
import { prefersReducedMotion } from '../../utils/motion.js'

const TILE = 512
const SEED = 20261103
const TWINKLE_SPEED = 0.0012

const STILL_LAYERS = [
    { depth: 0.15, count: 70, radius: [0.4, 0.9], alpha: [0.15, 0.4] },
    { depth: 0.35, count: 28, radius: [0.6, 1.2], alpha: [0.25, 0.55] },
]
const TWINKLING_LAYER = { depth: 0.6, count: 2, radius: [1, 1.6], alpha: [0.4, 0.9] }

function seededRandom(seed) {
    let state = seed
    return () => {
        state = (state + 0x6d2b79f5) | 0
        let t = Math.imul(state ^ (state >>> 15), 1 | state)
        t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t
        return ((t ^ (t >>> 14)) >>> 0) / 4294967296
    }
}

const between = ([min, max], amount) => min + (max - min) * amount

function scatter(random, layer) {
    const stars = Array.from({ length: layer.count }, () => ({
        x: random() * TILE,
        y: random() * TILE,
        radius: between(layer.radius, random()),
        alpha: between(layer.alpha, random()),
        phase: random() * Math.PI * 2,
    }))
    return { ...layer, stars }
}

function layerView(layer, transform) {
    return {
        offsetX: transform.x * layer.depth,
        offsetY: transform.y * layer.depth,
        scale: 1 + (transform.k - 1) * layer.depth,
    }
}

function forEachVisibleStar(layer, transform, size, paint) {
    const { offsetX, offsetY, scale } = layerView(layer, transform)
    const firstColumn = Math.floor(-offsetX / scale / TILE)
    const lastColumn = Math.floor((size.width - offsetX) / scale / TILE)
    const firstRow = Math.floor(-offsetY / scale / TILE)
    const lastRow = Math.floor((size.height - offsetY) / scale / TILE)

    for (let column = firstColumn; column <= lastColumn; column++) {
        for (let row = firstRow; row <= lastRow; row++) {
            for (const star of layer.stars) {
                const x = (column * TILE + star.x) * scale + offsetX
                const y = (row * TILE + star.y) * scale + offsetY
                paint(star, x, y)
            }
        }
    }
}

function paintStar(context, x, y, radius, alpha) {
    context.globalAlpha = alpha
    context.beginPath()
    context.arc(x, y, radius, 0, Math.PI * 2)
    context.fill()
}

export function createStarfield(canvas) {
    const random = seededRandom(SEED)
    const stillLayers = STILL_LAYERS.map((layer) => scatter(random, layer))
    const twinklingLayer = scatter(random, TWINKLING_LAYER)

    const context = canvas.getContext('2d')
    const stillSky = document.createElement('canvas')
    const stillContext = stillSky.getContext('2d')

    let size = { width: 0, height: 0 }
    let transform = zoomIdentity
    let frameId = null

    function paintStillSky() {
        stillContext.clearRect(0, 0, size.width, size.height)
        for (const layer of stillLayers) {
            forEachVisibleStar(layer, transform, size, (star, x, y) =>
                paintStar(stillContext, x, y, star.radius, star.alpha)
            )
        }
    }

    function paintFrame(time) {
        context.clearRect(0, 0, size.width, size.height)
        context.globalAlpha = 1
        context.drawImage(stillSky, 0, 0, size.width, size.height)
        forEachVisibleStar(twinklingLayer, transform, size, (star, x, y) => {
            const flicker = 0.55 + 0.45 * Math.sin(time * TWINKLE_SPEED + star.phase)
            paintStar(context, x, y, star.radius, star.alpha * flicker)
        })
    }

    function loop(time) {
        paintFrame(time)
        frameId = requestAnimationFrame(loop)
    }

    function isAnimating() {
        return frameId !== null
    }

    function draw(nextTransform = transform) {
        transform = nextTransform
        paintStillSky()
        if (!isAnimating()) paintFrame(0)
    }

    function resize() {
        const ratio = window.devicePixelRatio || 1
        size = { width: canvas.clientWidth, height: canvas.clientHeight }

        for (const [target, targetContext] of [
            [canvas, context],
            [stillSky, stillContext],
        ]) {
            target.width = Math.round(size.width * ratio)
            target.height = Math.round(size.height * ratio)
            targetContext.setTransform(ratio, 0, 0, ratio, 0, 0)
            targetContext.fillStyle = '#ffffff'
        }

        draw()
    }

    function resume() {
        if (isAnimating() || prefersReducedMotion()) return
        frameId = requestAnimationFrame(loop)
    }

    function pause() {
        cancelAnimationFrame(frameId)
        frameId = null
    }

    resize()
    resume()

    return { draw, resize, pause, resume, destroy: pause }
}
