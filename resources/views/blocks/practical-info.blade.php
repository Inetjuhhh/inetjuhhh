<section class="not-prose my-10 rounded-2xl bg-night-700 p-6 sm:p-8 shadow-soft ring-1 ring-white/5">
    <h3 class="flex items-center gap-2 font-display text-2xl text-cream">
        <i class="fas fa-clipboard-list text-accent text-lg"></i>{{ $title }}
    </h3>
    <dl class="mt-5 grid gap-x-8 gap-y-4 sm:grid-cols-2">
        @foreach($items as $item)
            <div class="border-t border-night-600 pt-3">
                <dt class="text-xs font-semibold uppercase tracking-widest text-accent">{{ $item['label'] }}</dt>
                <dd class="mt-1 text-cream">{{ $item['value'] }}</dd>
            </div>
        @endforeach
    </dl>
</section>
