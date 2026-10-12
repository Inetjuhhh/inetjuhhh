import './bootstrap';

import Alpine from 'alpinejs';
import intersect from '@alpinejs/intersect';

Alpine.plugin(intersect);

// Full-screen photo viewer, opened from photo and gallery blocks
Alpine.store('lightbox', {
    isOpen: false,
    images: [],
    index: 0,

    show(images, index = 0) {
        this.images = images;
        this.index = index;
        this.isOpen = true;
        document.documentElement.classList.add('overflow-hidden');
    },
    close() {
        this.isOpen = false;
        document.documentElement.classList.remove('overflow-hidden');
    },
    next() {
        this.index = (this.index + 1) % this.images.length;
    },
    prev() {
        this.index = (this.index - 1 + this.images.length) % this.images.length;
    },
    get current() {
        return this.images[this.index] ?? null;
    },
});

// Interactive travel map. Leaflet is only downloaded on pages that show a map.
Alpine.data('travelMap', ({ markers = [], mode = 'full', trip = null } = {}) => ({
    markers,
    visibleIds: markers.map((marker) => marker.id),
    activeTrip: trip,
    map: null,

    async init() {
        const { createTravelMap } = await import('./travel-map');

        this.map = createTravelMap(this.$refs.map, markers, {
            mode,
            trip,
            onVisibleChange: (ids) => (this.visibleIds = ids),
        });
    },

    // Filter the map on one trip (or null for all stories) and keep it in the address bar
    selectTrip(slug) {
        this.activeTrip = this.activeTrip === slug ? null : slug;
        this.map?.showTrip(this.activeTrip);

        const url = new URL(window.location.href);
        this.activeTrip ? url.searchParams.set('reis', this.activeTrip) : url.searchParams.delete('reis');
        window.history.replaceState({}, '', url);
    },

    // Newest first, or in reading order when one trip is shown
    get visible() {
        const visible = this.markers.filter((marker) => this.visibleIds.includes(marker.id));

        return this.activeTrip ? visible.sort((a, b) => a.time - b.time) : visible;
    },
}));

// The route of a trip under a story; Leaflet only loads once the map scrolls into view
Alpine.data('tripMap', (markers = []) => ({
    async load() {
        const { createTripMap } = await import('./travel-map');

        createTripMap(this.$refs.map, markers);
    },
}));

window.Alpine = Alpine;

Alpine.start();
