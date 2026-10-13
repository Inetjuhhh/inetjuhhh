@php
    // $id, $label, $icon, $trips; lit when the trip being viewed is in this menu
    $active = request()->routeIs('blogs.index') && $trips->contains('slug', request()->query('reis'));
@endphp

<li>
    <button id="{{ $id }}Button" data-dropdown-toggle="{{ $id }}" data-dropdown-trigger="hover" type="button"
            class="{{ $active ? $navActive : $navLink }} inline-flex items-center w-full md:w-auto">
        {{ $label }}
        <svg class="w-2.5 h-2.5 ms-2" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 10 6">
            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 1 4 4 4-4"/>
        </svg>
    </button>

    {{-- Newest first; scrolls when the list gets longer than the screen --}}
    <div id="{{ $id }}" class="z-50 hidden w-64 rounded-xl bg-night-700 border border-night-600 shadow-soft overflow-hidden">
        <ul class="py-2 text-sm max-h-[70vh] overflow-y-auto" aria-labelledby="{{ $id }}Button">
            @foreach($trips as $menuTrip)
                <li>
                    <a href="{{ $menuTrip->filterUrl() }}"
                       class="flex items-center gap-2 px-4 py-2 text-cream-muted hover:bg-night-600 hover:text-cream transition">
                        <span class="text-xs" aria-hidden="true">{{ $icon }}</span>{{ $menuTrip->name }}
                    </a>
                </li>
            @endforeach
        </ul>
    </div>
</li>
