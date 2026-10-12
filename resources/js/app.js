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

// Search bar: asks the server for suggestions while typing (a little after the last key)
Alpine.data('siteSearch', ({ suggestUrl, searchUrl }) => ({
    query: '',
    results: [],
    places: [],
    total: 0,
    didYouMean: null,
    open: false,
    loading: false,
    active: -1,
    request: null,
    cache: new Map(),

    init() {
        this.query = this.$refs.input.value;
    },

    // Blogs, then countries and trips, then "show all results"; the arrow keys walk through these
    get options() {
        if (this.query.trim().length < 2) {
            return [];
        }

        return [
            ...this.results.map((result) => ({ ...result, kind: 'blog' })),
            ...this.places.map((place) => ({ ...place, kind: 'place' })),
            // Nothing found: no "show all" line (Enter still opens the results page, which fixes typos)
            ...(this.total > 0 ? [{ kind: 'all', url: `${searchUrl}?q=${encodeURIComponent(this.query.trim())}` }] : []),
        ];
    },

    async suggest() {
        const query = this.query.trim();
        this.active = -1;

        if (query.length < 2) {
            this.open = false;
            return;
        }

        if (this.cache.has(query)) {
            this.show(this.cache.get(query));
            return;
        }

        this.request?.abort();
        this.request = new AbortController();
        this.loading = true;
        this.open = true;

        try {
            const response = await fetch(`${suggestUrl}?q=${encodeURIComponent(query)}`, {
                headers: { Accept: 'application/json' },
                signal: this.request.signal,
            });
            if (!response.ok) throw new Error(response.status);

            const data = await response.json();
            this.cache.set(query, data);

            // Only show it when it still belongs to what's in the box
            if (query === this.query.trim()) {
                this.show(data);
            }
        } catch (error) {
            if (error.name !== 'AbortError') {
                this.loading = false;
            }
        }
    },

    show(data) {
        this.results = data.results;
        this.places = data.places;
        this.total = data.total;
        this.didYouMean = data.didYouMean;
        this.loading = false;
        this.open = true;
    },

    move(step) {
        if (!this.open) {
            this.suggest();
            return;
        }

        const count = this.options.length;
        if (count > 0) {
            this.active = (this.active + step + count) % count;
        }
    },

    go(option) {
        window.location.href = option.url;
    },

    // Enter: the highlighted suggestion, otherwise the page with all results
    submit(event) {
        const option = this.open ? this.options[this.active] : null;

        if (option) {
            event.preventDefault();
            this.go(option);
        } else if (this.query.trim() === '') {
            event.preventDefault();
        }
    },

    close(blur = false) {
        this.open = false;
        this.active = -1;
        if (blur) {
            this.$refs.input.blur();
        }
    },

    // The title split into pieces, with the typed words marked (shown with x-text, so always safe)
    parts(text) {
        const words = this.query.trim().split(/\s+/).filter((word) => word.length >= 2)
            .map((word) => word.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'));

        if (words.length === 0) {
            return [{ text, hit: false }];
        }

        const pattern = new RegExp(`(${words.join('|')})`, 'gi');

        return text.split(pattern).filter(Boolean)
            .map((piece) => ({ text: piece, hit: words.some((word) => new RegExp(`^${word}$`, 'i').test(piece)) }));
    },
}));

window.Alpine = Alpine;

Alpine.start();
