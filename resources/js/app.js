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
Alpine.data('travelMap', ({ markers = [], mode = 'full' } = {}) => ({
    markers,
    visibleIds: markers.map((marker) => marker.id),
    map: null,

    async init() {
        const { createTravelMap } = await import('./travel-map');

        this.map = createTravelMap(this.$refs.map, markers, {
            mode,
            onVisibleChange: (ids) => (this.visibleIds = ids),
        });
    },

    get visible() {
        return this.markers.filter((marker) => this.visibleIds.includes(marker.id));
    },
}));

window.Alpine = Alpine;

Alpine.start();
