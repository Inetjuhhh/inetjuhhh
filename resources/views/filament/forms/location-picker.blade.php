<x-dynamic-component
    :component="$getFieldWrapperView()"
    :field="$field"
>
    <div
        x-data="{
            lat: $wire.$entangle(@js($getLatitudePath())),
            lng: $wire.$entangle(@js($getLongitudePath())),
            placedFor: @js($getPlacedForPaths()),
            repeater: @js($getRepeaterPaths()),
            coordinateFields: @js([$getLatitudeField(), $getLongitudeField()]),
            picker: null,

            // In a list of stops: the closest stop that is on the map (the one before, else the one after)
            near() {
                if (! this.repeater) return null;

                const items = Object.entries($wire.get(this.repeater.list) ?? {});
                const here = items.findIndex(([key]) => key === this.repeater.item);
                const [latField, lngField] = this.coordinateFields;
                const placed = ([, item]) => item && item[latField] !== null && item[latField] !== '' && item[latField] !== undefined;
                const before = items.slice(0, Math.max(here, 0)).reverse().find(placed);
                const after = items.slice(here + 1).find(placed);
                const found = before ?? after;

                return found ? [parseFloat(found[1][latField]), parseFloat(found[1][lngField])] : null;
            },

            async init() {
                const { mountLocationPicker } = await import(@js(\Illuminate\Support\Facades\Vite::asset('resources/js/location-picker.js')));

                this.picker = mountLocationPicker(this.$refs.map, {
                    position: () => this.hasPin ? [parseFloat(this.lat), parseFloat(this.lng)] : null,
                    near: () => this.near(),
                    zoom: @js($getZoom()),
                    onPick: (lat, lng) => {
                        this.lat = Math.round(lat * 1e6) / 1e6;
                        this.lng = Math.round(lng * 1e6) / 1e6;
                        // Placed by hand: the pin belongs to the name typed next to it, so saving keeps it
                        if (this.placedFor) {
                            $wire.set(this.placedFor[0], $wire.get(this.placedFor[1]), false);
                        }
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
            <div x-ref="map" class="{{ $getMapHeight() }} w-full bg-gray-100 dark:bg-gray-800"></div>
        </div>

        @if($isCompact())
            {{-- Small, next to a field (the stops of a route): only the coordinates and a cross --}}
            <div class="mt-1 flex items-center justify-between gap-2 text-xs text-gray-500 dark:text-gray-400">
                <span x-show="hasPin">📍 <span x-text="`${Number(lat).toFixed(3)}, ${Number(lng).toFixed(3)}`"></span></span>
                <span x-show="! hasPin">{{ $getEmptyText() }}</span>
                <button type="button" x-show="hasPin" x-on:click="clear()" title="Pin verwijderen"
                        class="font-medium text-danger-600 hover:underline dark:text-danger-400">✕ pin</button>
            </div>
        @else
            <div class="mt-2 flex flex-wrap items-center justify-between gap-2 text-sm">
                <p class="text-gray-500 dark:text-gray-400">
                    <span x-show="hasPin">
                        📍 <span x-text="`${Number(lat).toFixed(4)}, ${Number(lng).toFixed(4)}`"></span>
                        · sleep de pin of klik op de kaart om te verplaatsen
                    </span>
                    <span x-show="! hasPin">{{ $getEmptyText() }}</span>
                </p>
                <button type="button" x-show="hasPin" x-on:click="clear()"
                        class="text-sm font-medium text-danger-600 hover:underline dark:text-danger-400">
                    Pin verwijderen
                </button>
            </div>
        @endif
    </div>
</x-dynamic-component>
