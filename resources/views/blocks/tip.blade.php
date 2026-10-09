<aside @class([
    'not-prose my-8 flex gap-4 rounded-2xl border-l-4 bg-night-700 p-5 sm:p-6',
    'border-brand' => $type === 'tip',
    'border-accent' => $type === 'warning',
    'border-cream-muted' => $type === 'info',
])>
    <span class="text-2xl leading-none" aria-hidden="true">{{ $emoji }}</span>
    <div>
        <p @class([
            'font-semibold',
            'text-brand' => $type === 'tip',
            'text-accent' => $type === 'warning',
            'text-cream' => $type === 'info',
        ])>{{ $title }}</p>
        <p class="mt-1 leading-relaxed text-cream/90">{!! nl2br(e($text)) !!}</p>
    </div>
</aside>
