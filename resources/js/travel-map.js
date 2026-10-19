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
 * Shows the story card on hover (mouse) or tap (touch) and returns a function to open it.
 */
function attachCard(map, layer, marker, canHover) {
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

    return openCard;
}

function addBaseTiles(map) {
    L.tileLayer(BASE_TILES, { attribution: ATTRIBUTION, maxZoom: 16 }).addTo(map);
    L.tileLayer(LABEL_TILES, { maxZoom: 16, pane: 'shadowPane' }).addTo(map);
}

/**
 * On a phone the map doesn't take one-finger swipes (those scroll the page), so someone swiping on it
 * gets a short hint that two fingers move the map. Two fingers pan and zoom (Leaflet's touch zoom).
 */
function twoFingerHint(map) {
    if (!L.Browser.mobile) return;

    const container = map.getContainer();
    const hint = L.DomUtil.create('div', 'map-hint', container);
    hint.textContent = 'Gebruik twee vingers om de kaart te bewegen';
    let start = null;
    let timer;

    container.addEventListener('touchstart', (event) => {
        start = event.touches.length === 1 ? event.touches[0].clientY : null;
        if (event.touches.length > 1) hint.classList.remove('is-visible');
    }, { passive: true });

    container.addEventListener('touchmove', (event) => {
        if (start === null || event.touches.length !== 1 || Math.abs(event.touches[0].clientY - start) < 12) return;
        hint.classList.add('is-visible');
        clearTimeout(timer);
        timer = setTimeout(() => hint.classList.remove('is-visible'), 1600);
    }, { passive: true });
}

/**
 * Creates the map inside `element`.
 *
 * mode "full": the travel map page, with clustering and a list of stories in view.
 * mode "teaser": the small map on the homepage, without scroll zoom so the page keeps scrolling.
 */
export function createTravelMap(element, markers, { mode = 'full', trip = null, planned = [], onVisibleChange = () => {} } = {}) {
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
        // On phones one finger keeps scrolling the page; two fingers move and zoom the map
        dragging: !L.Browser.mobile,
        zoomControl: !teaser,
        attributionControl: true,
    });

    addBaseTiles(map);
    if (!teaser) twoFingerHint(map);

    const cluster = L.markerClusterGroup({
        showCoverageOnHover: false,
        maxClusterRadius: teaser ? 30 : 50,
        spiderfyOnMaxZoom: true,
        iconCreateFunction: clusterIcon,
    });

    const layers = new Map();

    markers.forEach((marker) => {
        const layer = L.marker([marker.lat, marker.lng], { icon: pinIcon, title: marker.title, riseOnHover: true });

        layers.set(marker.id, { layer, openCard: attachCard(map, layer, marker, canHover) });
    });

    map.addLayer(cluster);

    // Trips still to come: their planned route as a quiet dashed line with open circles for the stops.
    const NAME_FROM_ZOOM = 3;

    planned.forEach((plannedTrip) => {
        const stops = plannedTrip.stops.map((stop) => [stop.lat, stop.lng]);
        const line = L.polyline(stops, { className: 'planned-route', interactive: false }).addTo(map);
        // An invisible, wider line on top: the dashed one is only 3 pixels, too thin to hover
        const grip = L.polyline(stops, { className: 'planned-route-grip', weight: 16, opacity: 0, interactive: !teaser }).addTo(map);
        const parts = [grip];

        plannedTrip.stops.forEach((stop) => {
            parts.push(L.circleMarker([stop.lat, stop.lng], { radius: 5, className: 'planned-stop', interactive: !teaser }).addTo(map));
        });

        if (teaser) {
            return;
        }

        parts.forEach((part) => {
            part.bindTooltip(plannedTrip.name, { sticky: true, direction: 'top', offset: [0, -6], className: 'planned-label' });
            // On the world map the name stays away; zoomed in, hovering shows it
            part.on('tooltipopen', () => {
                if (map.getZoom() < NAME_FROM_ZOOM) part.closeTooltip();
            });
            part.on('click', () => map.fitBounds(line.getBounds(), { padding: [60, 60], maxZoom: 8 }));
        });
    });

    // The pins currently on the map: all stories, or the stories of one trip
    let shown = markers;
    let route = null;

    // The stops of the trips still to come: with all stories in view, they are kept in view too
    const plannedPoints = planned.flatMap((plannedTrip) => plannedTrip.stops.map((stop) => [stop.lat, stop.lng]));
    let showingOneTrip = false;

    const showAll = () => {
        const points = [
            ...shown.map((marker) => [marker.lat, marker.lng]),
            ...(showingOneTrip ? [] : plannedPoints),
        ];

        if (points.length === 0) {
            map.setView([20, 10], 2);
        } else if (points.length === 1) {
            map.setView(points[0], 6);
        } else {
            map.fitBounds(L.latLngBounds(points), { padding: [40, 40], maxZoom: 7 });
        }
    };

    const reportVisible = () => {
        const bounds = map.getBounds();
        onVisibleChange(shown.filter((marker) => bounds.contains([marker.lat, marker.lng])).map((marker) => marker.id));
    };

    // Show only one trip (or all stories with null), with a dotted line along its stops
    const showTrip = (slug) => {
        shown = slug ? markers.filter((marker) => marker.trip?.slug === slug) : markers;
        showingOneTrip = Boolean(slug);

        cluster.clearLayers();
        cluster.addLayers(shown.map((marker) => layers.get(marker.id).layer));

        route?.remove();
        route = null;

        if (slug && shown.length > 1) {
            const stops = [...shown].sort((a, b) => a.time - b.time).map((marker) => [marker.lat, marker.lng]);
            route = L.polyline(stops, { className: 'travel-route', interactive: false }).addTo(map);
        }

        map.closePopup();
        showAll();
        reportVisible();
    };

    map.on('moveend', reportVisible);
    showTrip(trip);

    return {
        showAll,
        showTrip,
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

/**
 * The map under a story: every published part of its trip, numbered like the trip strip.
 * The route is solid up to this story and dotted for the parts that came after it.
 */
export function createTripMap(element, markers) {
    const canHover = window.matchMedia('(hover: hover)').matches;

    const map = L.map(element, {
        minZoom: 1,
        maxZoom: 16,
        zoomSnap: 0.25,
        maxBounds: L.latLngBounds([-85, -540], [85, 540]),
        maxBoundsViscosity: 1,
        // Inside a page: no scroll zoom, and on phones one finger keeps scrolling the page
        scrollWheelZoom: false,
        dragging: !L.Browser.mobile,
    });

    addBaseTiles(map);
    twoFingerHint(map);

    const stops = markers.map((marker) => [marker.lat, marker.lng]);
    const current = markers.findIndex((marker) => marker.current);
    const split = current === -1 ? stops.length - 1 : current;

    if (split > 0) {
        L.polyline(stops.slice(0, split + 1), { className: 'trip-route-travelled', interactive: false }).addTo(map);
    }
    if (split < stops.length - 1) {
        L.polyline(stops.slice(split), { className: 'travel-route', interactive: false }).addTo(map);
    }

    markers.forEach((marker) => {
        const icon = L.divIcon({
            className: `trip-pin${marker.current ? ' is-current' : ''}`,
            html: `<span>${Number(marker.number)}</span>`,
            iconSize: marker.current ? [36, 36] : [28, 28],
            popupAnchor: [0, -16],
        });

        const layer = L.marker([marker.lat, marker.lng], {
            icon,
            title: marker.title,
            riseOnHover: true,
            zIndexOffset: marker.current ? 1000 : 0,
        }).addTo(map);

        if (marker.current) {
            layer.bindTooltip('Dit verhaal', { permanent: true, direction: 'top', offset: [0, -18], className: 'trip-pin-label' });
        } else {
            attachCard(map, layer, marker, canHover);
        }
    });

    if (stops.length === 1) {
        map.setView(stops[0], 6);
    } else {
        map.fitBounds(stops, { padding: [48, 48], maxZoom: 8 });
    }

    return map;
}
