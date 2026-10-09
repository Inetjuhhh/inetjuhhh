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

window.Alpine = Alpine;

Alpine.start();
