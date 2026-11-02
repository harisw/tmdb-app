import { select as d3Select } from 'd3-selection'
import { POSTER_PLACEHOLDER } from '../../utils/img.js'
import { COLORS, LABEL_MAX, NODE_RADIUS, POSTER_RADIUS } from './style.js'

const LINK_HIT_WIDTH = 16

const truncate = (name) => (name.length > LABEL_MAX ? name.slice(0, LABEL_MAX - 1) + '…' : name)

function haloText(selection) {
    return selection
        .attr('text-anchor', 'middle')
        .attr('fill', 'rgb(255 255 255 / 0.85)')
        .attr('font-size', 12)
        .attr('font-weight', 500)
        .attr('paint-order', 'stroke')
        .style('stroke', COLORS.ground)
        .attr('stroke-width', 4)
        .attr('stroke-linejoin', 'round')
        .style('pointer-events', 'none')
}

export function appendLinks(enter, { onHover, onClick }) {
    const link = enter.append('g').attr('class', 'link')

    link.append('line').attr('class', 'link-visible').attr('stroke-linecap', 'round').style('pointer-events', 'none')

    link.append('line')
        .attr('class', 'link-hit')
        .attr('stroke', 'transparent')
        .attr('stroke-width', LINK_HIT_WIDTH)
        .style('cursor', 'pointer')
        .on('mouseenter mousemove', (event, data) => onHover(data, event))
        .on('mouseleave', () => onHover(null))
        .on('click', (event, data) => {
            event.stopPropagation()
            onClick(data)
        })

    return link
}

export function appendNodes(enter, { drag, onHover, onActivate }) {
    const node = enter
        .append('g')
        .attr('class', 'node')
        .attr('tabindex', 0)
        .attr('role', 'button')
        .attr('aria-label', (data) => [data.name, data.year].filter(Boolean).join(', '))
        .style('cursor', 'pointer')
        .style('outline', 'none')
        .style('transition', 'opacity 200ms ease-out')
        .call(drag)
        .on('mouseenter focus', (event, data) => onHover(data))
        .on('mouseleave blur', () => onHover(null))
        .on('keydown', (event, data) => {
            if (event.key !== 'Enter' && event.key !== ' ') return
            event.preventDefault()
            onActivate(data)
        })

    const body = node.append('g').attr('class', 'node-body')

    body.append('clipPath')
        .attr('id', (data) => `clip-${data.id}`)
        .append('circle')
        .attr('r', POSTER_RADIUS)

    body.append('circle')
        .attr('class', 'halo')
        .attr('r', NODE_RADIUS + 6)
        .attr('fill', 'none')
        .style('stroke', COLORS.highlight)
        .attr('stroke-width', 2)
        .attr('opacity', 0)

    body.append('circle').attr('class', 'ring').attr('r', NODE_RADIUS).style('fill', COLORS.ground)

    body.append('image')
        .attr('x', -POSTER_RADIUS)
        .attr('y', -POSTER_RADIUS)
        .attr('width', POSTER_RADIUS * 2)
        .attr('height', POSTER_RADIUS * 2)
        .attr('preserveAspectRatio', 'xMidYMid slice')
        .attr('clip-path', (data) => `url(#clip-${data.id})`)
        .attr('href', (data) => data.poster_path || POSTER_PLACEHOLDER)
        .on('error', function () {
            d3Select(this).attr('href', POSTER_PLACEHOLDER)
        })

    body.append('text')
        .attr('y', NODE_RADIUS + 16)
        .call(haloText)
        .text((data) => truncate(data.name))

    node.append('title').text((data) => data.name)

    return node
}
