import { hierarchy, pack } from 'd3-hierarchy';

// The site's teal and green, with the warm sand and rose of the photos
const COLOURS = ['#5BB5AA', '#9DB894', '#D9B98C', '#D9967E'];
const INK = '#16191C';
const SVG = 'http://www.w3.org/2000/svg';

function el(name, attributes = {}, text = null) {
    const node = document.createElementNS(SVG, name);
    Object.entries(attributes).forEach(([key, value]) => node.setAttribute(key, value));
    if (text !== null) node.textContent = text;

    return node;
}

// A name that doesn't fit its bubble is shortened; the whole name shows on hover (title)
function fit(name, radius, fontSize) {
    const room = Math.floor((radius * 1.75) / (fontSize * 0.55));

    return name.length <= room ? name : `${name.slice(0, Math.max(3, room - 1))}…`;
}

/**
 * Draws the subjects as packed bubbles (area = number of stories), each a link to its stories.
 */
export function drawBubbles(container, subjects) {
    const width = container.clientWidth;
    const height = width < 500 ? width : 360;
    const phone = width < 500;
    const hasActive = subjects.some((subject) => subject.active);

    const root = pack()
        .size([width, height])
        .padding(phone ? 4 : 7)(hierarchy({ children: subjects }).sum((subject) => subject.count));

    const svg = el('svg', { width, height, viewBox: `0 0 ${width} ${height}`, class: 'block mx-auto overflow-visible' });

    root.leaves().forEach((leaf, i) => {
        const { name, count, url, active } = leaf.data;
        const link = el('a', { href: url, class: 'subject-bubble', 'aria-label': `${name}: ${count} ${count === 1 ? 'verhaal' : 'verhalen'}` });
        const group = el('g', { transform: `translate(${leaf.x},${leaf.y})`, opacity: hasActive && !active ? 0.45 : 1 });

        group.append(el('title', {}, `${name} · ${count} ${count === 1 ? 'verhaal' : 'verhalen'}`));
        group.append(el('circle', {
            r: leaf.r,
            fill: COLOURS[i % COLOURS.length],
            'fill-opacity': 0.92 - (i % 3) * 0.12,
            stroke: active ? '#ECE6DC' : 'none',
            'stroke-width': active ? 3 : 0,
        }));

        if (leaf.r > (phone ? 14 : 18)) {
            const fontSize = Math.min(leaf.r / 2.6, phone ? 22 : 30);
            group.append(el('text', { 'text-anchor': 'middle', dy: '0.1em', fill: INK, 'font-size': fontSize, class: 'font-display' }, fit(name, leaf.r, fontSize)));
        }
        if (leaf.r > (phone ? 30 : 36)) {
            group.append(el('text', { 'text-anchor': 'middle', dy: '1.6em', fill: INK, opacity: 0.7, 'font-size': Math.min(leaf.r / 4.5, 15) },
                `${count} ${count === 1 ? 'verhaal' : 'verhalen'}`));
        }

        link.append(group);
        svg.append(link);
    });

    container.replaceChildren(svg);
}
