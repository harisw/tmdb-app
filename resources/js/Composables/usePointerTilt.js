import { onBeforeUnmount, ref } from 'vue'
import { prefersReducedMotion } from '../utils/motion.js'

const MAX_TILT_DEG = 6
const LIFT_PX = 6
const PARALLAX_PX = 6
const HOVER_ZOOM = 1.08

const REST = { x: 0, y: 0, lift: 0, zoom: 1 }

export function usePointerTilt() {
    const target = ref(null)
    let frameId = null
    let pending = REST

    function write({ x, y, lift, zoom }) {
        const style = target.value?.style
        if (!style) return
        style.setProperty('--tilt-x', `${-y * 2 * MAX_TILT_DEG}deg`)
        style.setProperty('--tilt-y', `${x * 2 * MAX_TILT_DEG}deg`)
        style.setProperty('--shift-x', `${-x * 2 * PARALLAX_PX}px`)
        style.setProperty('--shift-y', `${-y * 2 * PARALLAX_PX}px`)
        style.setProperty('--lift', `${-lift}px`)
        style.setProperty('--zoom', String(zoom))
    }

    function schedule(values) {
        pending = values
        if (frameId !== null) return
        frameId = requestAnimationFrame(() => {
            frameId = null
            write(pending)
        })
    }

    function onPointerMove(event) {
        if (event.pointerType !== 'mouse' || !target.value) return
        if (prefersReducedMotion()) {
            schedule({ ...REST, lift: LIFT_PX })
            return
        }

        const bounds = target.value.getBoundingClientRect()
        schedule({
            x: (event.clientX - bounds.left) / bounds.width - 0.5,
            y: (event.clientY - bounds.top) / bounds.height - 0.5,
            lift: LIFT_PX,
            zoom: HOVER_ZOOM,
        })
    }

    function onPointerLeave() {
        schedule(REST)
    }

    onBeforeUnmount(() => {
        if (frameId !== null) cancelAnimationFrame(frameId)
    })

    return { target, onPointerMove, onPointerLeave }
}
