{{-- One trip on the Reizen page: its photo, "Reis" or "Citytrip", and its name --}}
<a href="{{ $trip['url'] }}" @class(['group relative block h-36 overflow-hidden rounded-2xl bg-night-700 ring-1 ring-white/5', $width ?? 'w-56'])>
    <img src="{{ $trip['cover'] }}" alt="" loading="lazy"
         class="absolute inset-0 h-full w-full object-cover transition duration-700 group-hover:scale-105">
    <div class="absolute inset-0 bg-gradient-to-t from-night-900/90 via-night-900/20 to-transparent"></div>
    <span class="absolute top-2 left-2 pill bg-night-900/70 backdrop-blur text-cream text-xs">
        {{ $trip['city'] ? '🏙️ Citytrip' : '🧭 Reis' }}
    </span>
    <span class="absolute bottom-3 left-3 right-3 font-display text-lg leading-tight text-cream group-hover:text-brand transition">{{ $trip['name'] }}</span>
</a>
