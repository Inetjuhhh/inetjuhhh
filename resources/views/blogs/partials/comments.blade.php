@php
    $count = $comments->count();
    $initial = fn (string $name) => mb_strtoupper(mb_substr(trim($name), 0, 1));
    $turnstileKey = config('services.turnstile.site_key');
@endphp

<section id="reacties" class="mt-16 scroll-mt-28">
    <h2 class="font-display text-3xl text-cream">
        {{ $count === 0 ? 'Reacties' : ($count === 1 ? '1 reactie' : "{$count} reacties") }}
    </h2>

    @if(session('comment_status'))
        <p class="mt-6 rounded-2xl border-l-4 border-brand bg-night-700 p-4 text-cream" role="status">
            {{ session('comment_status') }}
        </p>
    @endif

    {{-- Approved comments, with the owner's replies underneath --}}
    @if($count > 0)
        <ol class="mt-8 space-y-6">
            @foreach($comments as $comment)
                <li id="reactie-{{ $comment->id }}" class="scroll-mt-28">
                    <article class="flex gap-4">
                        <span class="flex h-11 w-11 flex-none items-center justify-center rounded-full bg-night-600 font-display text-lg text-cream" aria-hidden="true">
                            {{ $initial($comment->name) }}
                        </span>
                        <div class="min-w-0 flex-1 rounded-2xl bg-night-700 p-5 ring-1 ring-white/5">
                            <p class="text-sm">
                                <span class="font-semibold text-cream">{{ $comment->name }}</span>
                                <span class="text-cream-muted">· {{ $comment->created_at->translatedFormat('d F Y') }}</span>
                            </p>
                            <p class="mt-2 whitespace-pre-line leading-relaxed text-cream/90">{{ $comment->response }}</p>
                        </div>
                    </article>

                    @foreach($comment->replies as $reply)
                        <article id="reactie-{{ $reply->id }}" class="mt-3 ms-8 flex gap-4 scroll-mt-28 sm:ms-16">
                            <img src="{{ \App\Support\Avatar::url(512) }}" alt="" class="h-11 w-11 flex-none rounded-full ring-2 ring-brand/60">
                            <div class="min-w-0 flex-1 rounded-2xl bg-night-600/60 p-5 ring-1 ring-brand/20">
                                <p class="text-sm">
                                    <span class="font-semibold text-cream">{{ $reply->name }}</span>
                                    <span class="ms-1 pill bg-brand/20 text-brand">Schrijver</span>
                                    <span class="text-cream-muted">· {{ $reply->created_at->translatedFormat('d F Y') }}</span>
                                </p>
                                <p class="mt-2 whitespace-pre-line leading-relaxed text-cream/90">{{ $reply->response }}</p>
                            </div>
                        </article>
                    @endforeach
                </li>
            @endforeach
        </ol>
    @else
        <p class="mt-4 text-cream-muted">Nog geen reacties. Wat vond jij van dit verhaal?</p>
    @endif

    {{-- Form: no account, email address is never shown --}}
    <form method="POST" action="{{ route('comments.store', $blog) }}"
          x-data="{ sending: false }" x-on:submit="sending = true"
          {{-- The robot check (Cloudflare) loads only once someone starts on the form, not for every reader --}}
          @if($turnstileKey) x-on:focusin.once="window.renderTurnstile($refs.turnstile)" @endif
          class="mt-10 rounded-3xl bg-night-700 p-6 ring-1 ring-white/5 sm:p-8">
        @csrf
        <h3 class="font-display text-2xl text-cream">Laat een reactie achter</h3>

        <fieldset @disabled($disabled ?? false) class="mt-6 grid gap-5 sm:grid-cols-2">
            <div>
                <label for="comment-name" class="text-sm font-medium text-cream">Naam</label>
                <input id="comment-name" name="name" type="text" required maxlength="80" autocomplete="name"
                       value="{{ old('name') }}"
                       class="mt-1.5 block w-full rounded-xl border-night-500 bg-night-800 text-cream placeholder-cream-muted focus:border-brand focus:ring-brand">
                @error('name')<p class="mt-1 text-sm text-accent">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="comment-email" class="text-sm font-medium text-cream">E-mailadres</label>
                <input id="comment-email" name="email" type="email" required maxlength="190" autocomplete="email"
                       value="{{ old('email') }}"
                       class="mt-1.5 block w-full rounded-xl border-night-500 bg-night-800 text-cream placeholder-cream-muted focus:border-brand focus:ring-brand">
                <p class="mt-1 text-xs text-cream-muted">Wordt nooit getoond.</p>
                @error('email')<p class="mt-1 text-sm text-accent">{{ $message }}</p>@enderror
            </div>
            <div class="sm:col-span-2">
                <label for="comment-text" class="text-sm font-medium text-cream">Je reactie</label>
                <textarea id="comment-text" name="response" rows="5" required maxlength="{{ config('blog.comments.max_length') }}"
                          class="mt-1.5 block w-full rounded-xl border-night-500 bg-night-800 text-cream placeholder-cream-muted focus:border-brand focus:ring-brand">{{ old('response') }}</textarea>
                @error('response')<p class="mt-1 text-sm text-accent">{{ $message }}</p>@enderror
            </div>

            {{-- Spam traps: a field only bots fill in, and when the form was shown --}}
            <div class="hidden" aria-hidden="true">
                <label for="comment-website">Laat dit veld leeg</label>
                <input id="comment-website" name="{{ \App\Support\SpamGuard::HONEYPOT }}" type="text" tabindex="-1" autocomplete="off">
            </div>
            <input type="hidden" name="{{ \App\Support\SpamGuard::STARTED }}" value="{{ \App\Support\SpamGuard::startedToken() }}">

            <label class="flex items-start gap-3 text-sm text-cream-muted sm:col-span-2">
                <input type="checkbox" name="notify_on_reply" value="1" @checked(old('notify_on_reply'))
                       class="mt-0.5 rounded border-night-500 bg-night-800 text-brand focus:ring-brand">
                Stuur me een mailtje als er op mijn reactie wordt geantwoord
            </label>

            @if($turnstileKey)
                <div class="sm:col-span-2">
                    <div x-ref="turnstile" data-sitekey="{{ $turnstileKey }}"></div>
                </div>
            @endif

            <div class="flex flex-col gap-4 sm:col-span-2 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-xs leading-relaxed text-cream-muted sm:max-w-sm">
                    Je e-mailadres wordt nooit getoond of gedeeld. Een eerste reactie lees ik even voordat hij online komt. <a href="{{ route('privacy') }}" class="underline underline-offset-2 hover:text-cream">Zo ga ik met je gegevens om</a>.
                </p>
                <button type="submit" x-bind:disabled="sending"
                        class="inline-flex items-center justify-center gap-2 rounded-full bg-brand px-6 py-2.5 font-semibold text-night-900 transition hover:bg-cream disabled:opacity-60">
                    <span x-text="sending ? 'Versturen…' : 'Plaats reactie'">Plaats reactie</span>
                </button>
            </div>
        </fieldset>
    </form>

</section>
