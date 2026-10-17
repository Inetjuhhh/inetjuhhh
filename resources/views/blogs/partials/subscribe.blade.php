@php
    // $variant: 'blog' (card under a story) or 'footer' (small, in the footer)
    $errorBag = $errors->getBag("newsletter_{$variant}");
    $status = session("newsletter_status_{$variant}");
    $footer = $variant === 'footer';
    // Old input only belongs here when this form was the one sent
    $old = fn (string $field) => $errorBag->any() ? old($field) : '';
@endphp

{{-- Sign up for a mail about every new blog. Closed until someone asks for it. --}}
<div id="nieuwsbrief-{{ $variant }}" x-data="{ open: @js($errorBag->any()), sending: false }"
     @if($footer)
         {{-- The countdown banner's "Mail me" opens this form (coming-trip.blade.php) --}}
         x-on:open-newsletter.window="open = true; $nextTick(() => { $el.scrollIntoView({ behavior: 'smooth' }); $refs.name?.focus({ preventScroll: true }) })"
     @endif
     @class([
         'scroll-mt-28',
         'mt-10 rounded-3xl bg-night-700 p-6 ring-1 ring-white/5 sm:p-8' => ! $footer,
     ])>
    <div @class(['flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between' => ! $footer])>
        <div>
            @if($footer)
                <p class="text-xs uppercase tracking-widest text-accent font-semibold">Nieuwe verhalen</p>
                <p class="mt-3 text-sm text-cream-muted">Krijg een mailtje als er een nieuwe blog online staat.</p>
            @else
                <p class="font-display text-2xl text-cream">
                    <i class="far fa-envelope me-1 text-accent"></i> Wil je het volgende verhaal niet missen?
                </p>
                <p class="mt-1 text-cream-muted">Ik stuur je een mailtje zodra er een nieuwe blog online staat. Meer niet.</p>
            @endif
        </div>

        @unless($status)
            <button type="button" x-show="! open" x-on:click="open = true; $nextTick(() => $refs.name.focus())"
                    aria-controls="nieuwsbrief-{{ $variant }}-form"
                    @class([
                        'inline-flex flex-none items-center gap-2 rounded-full font-semibold transition',
                        'mt-3 text-sm text-brand hover:underline underline-offset-4' => $footer,
                        'self-start bg-brand px-5 py-2.5 text-night-900 hover:bg-cream sm:self-auto' => ! $footer,
                    ])>
                Houd me op de hoogte
                @if($footer)<span aria-hidden="true">→</span>@endif
            </button>
        @endunless
    </div>

    @if($status)
        <p class="mt-4 rounded-2xl border-l-4 border-brand bg-night-800 p-4 text-sm text-cream" role="status">{{ $status }}</p>
    @else
        <form id="nieuwsbrief-{{ $variant }}-form" method="POST" action="{{ route('subscribers.store') }}"
              x-show="open" x-transition.opacity x-cloak x-on:submit="sending = true"
              @class(['mt-4 grid gap-3', 'sm:grid-cols-2 mt-6' => ! $footer])>
            @csrf
            <input type="hidden" name="form" value="{{ $variant }}">

            <div>
                <label for="nieuwsbrief-{{ $variant }}-name" class="text-sm font-medium text-cream">Naam</label>
                <input id="nieuwsbrief-{{ $variant }}-name" x-ref="name" name="name" type="text" required maxlength="80"
                       autocomplete="given-name" value="{{ $old('name') }}"
                       class="mt-1.5 block w-full rounded-xl border-night-500 bg-night-800 text-cream placeholder-cream-muted focus:border-brand focus:ring-brand">
                @if($errorBag->has('name'))<p class="mt-1 text-sm text-accent">{{ $errorBag->first('name') }}</p>@endif
            </div>
            <div>
                <label for="nieuwsbrief-{{ $variant }}-email" class="text-sm font-medium text-cream">E-mailadres</label>
                <input id="nieuwsbrief-{{ $variant }}-email" name="email" type="email" required maxlength="190"
                       autocomplete="email" value="{{ $old('email') }}"
                       class="mt-1.5 block w-full rounded-xl border-night-500 bg-night-800 text-cream placeholder-cream-muted focus:border-brand focus:ring-brand">
                @if($errorBag->has('email'))<p class="mt-1 text-sm text-accent">{{ $errorBag->first('email') }}</p>@endif
            </div>

            {{-- Spam traps, the same as the comment form --}}
            <div class="hidden" aria-hidden="true">
                <label for="nieuwsbrief-{{ $variant }}-website">Laat dit veld leeg</label>
                <input id="nieuwsbrief-{{ $variant }}-website" name="{{ \App\Support\SpamGuard::HONEYPOT }}" type="text" tabindex="-1" autocomplete="off">
            </div>
            <input type="hidden" name="{{ \App\Support\SpamGuard::STARTED }}" value="{{ \App\Support\SpamGuard::startedToken() }}">

            <div @class(['flex flex-col gap-3', 'sm:col-span-2 sm:flex-row sm:items-center sm:justify-between' => ! $footer])>
                <p class="text-xs leading-relaxed text-cream-muted">
                    Je krijgt eerst een mail om je inschrijving te bevestigen. Uitschrijven kan in elke mail.
                </p>
                <button type="submit" x-bind:disabled="sending"
                        class="inline-flex flex-none items-center justify-center gap-2 self-start rounded-full bg-brand px-5 py-2.5 font-semibold text-night-900 transition hover:bg-cream disabled:opacity-60">
                    <span x-text="sending ? 'Versturen…' : 'Inschrijven'">Inschrijven</span>
                </button>
            </div>
        </form>
    @endif
</div>
