import L from 'leaflet';
import 'leaflet.markercluster';
import 'leaflet/dist/leaflet.css';
import 'leaflet.markercluster/dist/MarkerCluster.css';

// Esri's dark grey basemap: free without an API key, and it matches the site's anthracite colours
const ESRI = 'https://server.arcgisonline.com/ArcGIS/rest/services/Canvas';
const BASE_TILES = `${ESRI}/World_Dark_Gray_Base/MapServer/tile/{z}/{y}/{x}`;
const LABEL_TILES = `${ESRI}/World_Dark_Gray_Reference/MapServer/tile/{z}/{y}/{x}`;
const ATTRIBUTION = 'Tiles &copy; <a href="https://www.esri.com">Esri</a> &mdash; Esri, HERE, Garmin, &copy; OpenStreetMap contributors';

const pinIcon = L.divIcon({
    className: 'travel-pin',
    html: '<span></span>',
    iconSize: [26, 26],
    iconAnchor: [13, 13],
    popupAnchor: [0, -14],
});

function clusterIcon(cluster) {
    const count = cluster.getChildCount();
    const size = count < 10 ? 38 : count < 50 ? 46 : 54;

    return L.divIcon({
        className: 'travel-cluster',
        html: `<span>${count}</span>`,
        iconSize: [size, size],
    });
}

// Built with DOM methods instead of an HTML string, so titles can never inject markup
function popupCard(marker) {
    const card = document.createElement('a');
    card.href = marker.url;
    card.className = 'travel-popup-card';

    const image = document.createElement('img');
    image.src = marker.cover;
    image.alt = '';
    image.loading = 'lazy';

    const body = document.createElement('span');
    body.className = 'travel-popup-body';

    const place = document.createElement('span');
    place.className = 'travel-popup-place';
    place.textContent = marker.place;

    const title = document.createElement('span');
    title.className = 'travel-popup-title';
    title.textContent = marker.title;

    const meta = document.createElement('span');
    meta.className = 'travel-popup-meta';
    meta.textContent = `${marker.date} · Lees verhaal →`;

    body.append(place, title, meta);
    card.append(image, body);

    return card;
}

/**
 * Creates the map inside `element`.
 *
 * mode "full": the travel map page, with clustering and a list of stories in view.
 * mode "teaser": the small map on the homepage, without scroll zoom so the page keeps scrolling.
 */
export function createTravelMap(element, markers, { mode = 'full', onVisibleChange = () => {} } = {}) {
    const teaser = mode === 'teaser';
    const canHover = window.matchMedia('(hover: hover)').matches;

    const map = L.map(element, {
        // Small steps so all pins fit on a narrow phone screen too
        minZoom: 1,
        maxZoom: 16,
        zoomSnap: 0.25,
        // Don't scroll past the top and bottom of the world
        maxBounds: L.latLngBounds([-85, -540], [85, 540]),
        maxBoundsViscosity: 1,
        worldCopyJump: true,
        scrollWheelZoom: !teaser,
        dragging: !(teaser && L.Browser.mobile),
        zoomControl: !teaser,
        attributionControl: true,
    });

    L.tileLayer(BASE_TILES, { attribution: ATTRIBUTION, maxZoom: 16 }).addTo(map);
    L.tileLayer(LABEL_TILES, { maxZoom: 16, pane: 'shadowPane' }).addTo(map);

    const cluster = L.markerClusterGroup({
        showCoverageOnHover: false,
        maxClusterRadius: teaser ? 30 : 50,
        spiderfyOnMaxZoom: true,
        iconCreateFunction: clusterIcon,
    });

    const layers = new Map();

    markers.forEach((marker) => {
        const layer = L.marker([marker.lat, marker.lng], { icon: pinIcon, title: marker.title, riseOnHover: true });

        const popup = L.popup({
            closeButton: false,
            className: 'travel-popup',
            maxWidth: 260,
            minWidth: 260,
            autoPanPadding: [24, 24],
        }).setContent(() => popupCard(marker));

        // Hover cards don't pan the map: that would move the pin away from the pointer and close
        // the card again. Cards opened by a tap or from the list do pan, so they fit in view.
        const openCard = (pan) => {
            popup.options.autoPan = pan;
            popup.setLatLng(layer.getLatLng()).openOn(map);
        };

        let closeTimer = null;
        const keepOpen = () => clearTimeout(closeTimer);
        const closeSoon = () => {
            clearTimeout(closeTimer);
            closeTimer = setTimeout(() => map.closePopup(popup), 250);
        };

        layer.on('mouseover', () => {
            keepOpen();
            openCard(false);
        });
        layer.on('mouseout', closeSoon);

        // With a mouse the card is already open, so a click goes to the story; on touch screens
        // there is no hover, so a tap opens the card.
        layer.on('click', () => {
            if (canHover) {
                window.location.href = marker.url;
            } else {
                openCard(true);
            }
        });

        popup.on('add', () => {
            const element = popup.getElement();
            element.addEventListener('mouseenter', keepOpen);
            element.addEventListener('mouseleave', closeSoon);
        });

        layers.set(marker.id, { layer, openCard });
        cluster.addLayer(layer);
    });

    map.addLayer(cluster);

    const showAll = () => {
        if (markers.length === 0) {
            map.setView([20, 10], 2);
        } else if (markers.length === 1) {
            map.setView([markers[0].lat, markers[0].lng], 6);
        } else {
            map.fitBounds(cluster.getBounds(), { padding: [40, 40], maxZoom: 6 });
        }
    };

    const reportVisible = () => {
        const bounds = map.getBounds();
        onVisibleChange(markers.filter((marker) => bounds.contains([marker.lat, marker.lng])).map((marker) => marker.id));
    };

    map.on('moveend', reportVisible);
    showAll();
    reportVisible();

    return {
        showAll,
        // Zoom to a pin (opening its cluster if needed) and show its card
        focus(id) {
            const pin = layers.get(id);
            if (pin) {
                cluster.zoomToShowLayer(pin.layer, () => pin.openCard(true));
            }
        },
        highlight(id, active) {
            layers.get(id)?.layer.getElement()?.classList.toggle('is-active', active);
        },
        invalidateSize: () => map.invalidateSize(),
    };
}
