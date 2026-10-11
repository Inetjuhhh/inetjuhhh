<x-dynamic-component
    :component="$getFieldWrapperView()"
    :field="$field"
>
    <div
        x-data="{
            lat: $wire.$entangle(@js($getSiblingStatePath('latitude'))),
            lng: $wire.$entangle(@js($getSiblingStatePath('longitude'))),
            picker: null,

            async init() {
                const { mountLocationPicker } = await import(@js(\Illuminate\Support\Facades\Vite::asset('resources/js/location-picker.js')));

                this.picker = mountLocationPicker(this.$refs.map, {
                    position: () => this.hasPin ? [parseFloat(this.lat), parseFloat(this.lng)] : null,
                    onPick: (lat, lng) => {
                        this.lat = Math.round(lat * 1e6) / 1e6;
                        this.lng = Math.round(lng * 1e6) / 1e6;
                    },
                });

                this.$watch('lat', () => this.picker.sync());
                this.$watch('lng', () => this.picker.sync());
            },

            get hasPin() {
                return this.lat !== null && this.lat !== '' && this.lng !== null && this.lng !== '';
            },

            clear() {
                this.lat = null;
                this.lng = null;
            },
        }"
        {{ $getExtraAttributeBag() }}
    >
        <div wire:ignore class="relative isolate overflow-hidden rounded-lg ring-1 ring-gray-950/10 dark:ring-white/10">
            <div x-ref="map" class="h-72 w-full bg-gray-100 dark:bg-gray-800"></div>
        </div>

        <div class="mt-2 flex flex-wrap items-center justify-between gap-2 text-sm">
            <p class="text-gray-500 dark:text-gray-400">
                <span x-show="hasPin">
                    📍 <span x-text="`${Number(lat).toFixed(4)}, ${Number(lng).toFixed(4)}`"></span>
                    · sleep de pin of klik op de kaart om te verplaatsen
                </span>
                <span x-show="! hasPin">Klik op de kaart om zelf een pin te zetten. Zonder pin komt de blog in het midden van het land.</span>
            </p>
            <button type="button" x-show="hasPin" x-on:click="clear()"
                    class="text-sm font-medium text-danger-600 hover:underline dark:text-danger-400">
                Pin verwijderen
            </button>
        </div>
    </div>
</x-dynamic-component>
