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

// How a name fits its bubble: a bit smaller, then over two lines, and only then shortened
// (the whole name always shows on hover). Returns the lines and the font size.
function fit(name, radius, fontSize) {
    const room = (size) => Math.floor((radius * 1.7) / (size * 0.55));

    for (const size of [fontSize, fontSize * 0.85, fontSize * 0.72]) {
        if (name.length <= room(size)) {
            return { lines: [name], size };
        }
    }

    const words = name.split(' ');
    if (words.length > 1) {
        const size = fontSize * 0.72;
        // Split where the two lines are most even ("Huis &" / "verbouwen")
        const splits = words.slice(1).map((_, i) => [words.slice(0, i + 1).join(' '), words.slice(i + 1).join(' ')]);
        const [first, second] = splits.sort((a, b) => Math.max(...a.map((l) => l.length)) - Math.max(...b.map((l) => l.length)))[0];
        if (Math.max(first.length, second.length) <= room(size)) {
            return { lines: [first, second], size };
        }
    }

    const size = fontSize * 0.72;

    return { lines: [`${name.slice(0, Math.max(3, room(size) - 1))}…`], size };
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

        const showCount = leaf.r > (phone ? 30 : 36);
        if (leaf.r > (phone ? 14 : 18)) {
            const { lines, size } = fit(name, leaf.r, Math.min(leaf.r / 2.6, phone ? 22 : 30));
            // Centre the name (and the count under it) in the bubble
            const top = -((lines.length - 1) * 1.05 * size) / 2 - (showCount ? size * 0.35 : 0);
            const label = el('text', { 'text-anchor': 'middle', fill: INK, 'font-size': size, class: 'font-display' });
            lines.forEach((line, n) => label.append(el('tspan', { x: 0, y: top + n * 1.05 * size, dy: '0.35em' }, line)));
            group.append(label);

            if (showCount) {
                group.append(el('text', { 'text-anchor': 'middle', y: top + (lines.length - 1) * 1.05 * size + size * 1.25, dy: '0.35em', fill: INK, opacity: 0.7, 'font-size': Math.min(leaf.r / 4.5, 15) },
                    `${count} ${count === 1 ? 'verhaal' : 'verhalen'}`));
            }
        }

        link.append(group);
        svg.append(link);
    });

    container.replaceChildren(svg);
}
