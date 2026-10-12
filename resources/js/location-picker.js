import L from 'leaflet';
import leafletCss from 'leaflet/dist/leaflet.css?inline';

// Loaded by URL from the admin form, so the CSS is added here instead of through a stylesheet
function addStyles() {
    if (document.getElementById('leaflet-css')) {
        return;
    }

    const style = document.createElement('style');
    style.id = 'leaflet-css';
    style.textContent = leafletCss;
    document.head.append(style);
}

const pinIcon = () => L.divIcon({
    className: '',
    html: '<span style="display:block;width:26px;height:26px;border-radius:9999px;background:#9DB894;border:3px solid #16191C;box-shadow:0 0 0 2px rgba(157,184,148,.45),0 6px 14px rgba(0,0,0,.4);cursor:grab"></span>',
    iconSize: [26, 26],
    iconAnchor: [13, 13],
});

/**
 * A map to place one pin by hand: click the map or drag the pin.
 *
 * position(): the current [lat, lng] from the form, or null
 * onPick(lat, lng): called when the pin is placed or moved by hand
 */
export function mountLocationPicker(element, { position, onPick }) {
    addStyles();

    const map = L.map(element, { minZoom: 1, maxZoom: 18, worldCopyJump: true });

    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
    }).addTo(map);

    let marker = null;

    const place = (latlng) => {
        if (! marker) {
            marker = L.marker(latlng, { icon: pinIcon(), draggable: true, autoPan: true }).addTo(map);
            marker.on('dragend', () => {
                const { lat, lng } = marker.getLatLng().wrap();
                onPick(lat, lng);
            });
        } else {
            marker.setLatLng(latlng);
        }
    };

    map.on('click', (event) => {
        const { lat, lng } = event.latlng.wrap();
        place([lat, lng]);
        onPick(lat, lng);
    });

    // Follow changes from the form (place lookup, typed coordinates, "remove pin")
    const sync = () => {
        const current = position();

        if (! current || current.some(Number.isNaN)) {
            marker?.remove();
            marker = null;
            return;
        }

        const isNew = ! marker || ! marker.getLatLng().equals(current, 1e-6);
        place(current);

        if (isNew && ! map.getBounds().pad(-0.2).contains(current)) {
            map.setView(current, Math.max(map.getZoom(), 9));
        }
    };

    const start = position();
    if (start && ! start.some(Number.isNaN)) {
        map.setView(start, 10);
    } else {
        map.setView([25, 15], 2);
    }
    sync();

    // Redraw when the map changes size, e.g. when its form section is opened again
    new ResizeObserver(() => map.invalidateSize()).observe(element);

    return { sync };
}
