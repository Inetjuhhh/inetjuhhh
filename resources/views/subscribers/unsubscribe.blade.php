<x-app-layout title="Uitschrijven">
    <div class="max-w-xl mx-auto px-4 sm:px-6 lg:px-8 py-24 text-center">
        @if($subscriber)
            <h1 class="font-display text-4xl text-cream">Geen mails meer ontvangen?</h1>
            <p class="mt-4 text-lg text-cream-muted">
                Je krijgt nu een mailtje op {{ $subscriber->email }} als er een nieuwe blog online staat.
                Klik op de knop en je hoort niets meer van me. Je e-mailadres wordt dan meteen gewist.
            </p>
            <form method="POST" action="{{ route('subscribers.destroy', $token) }}" class="mt-10">
                @csrf
                <button type="submit"
                        class="inline-flex items-center gap-2 rounded-full bg-accent px-6 py-3 font-semibold text-night-900 transition hover:bg-cream">
                    Ja, schrijf me uit
                </button>
            </form>
            <a href="{{ route('blogs.index') }}" class="mt-6 inline-block text-sm text-brand hover:underline underline-offset-4">
                Toch niet, ik blijf lezen
            </a>
        @else
            <h1 class="font-display text-4xl text-cream">Je bent al uitgeschreven</h1>
            <p class="mt-4 text-lg text-cream-muted">Dit e-mailadres staat niet (meer) op de lijst. Je krijgt geen mails meer van me.</p>
            <a href="{{ route('blogs.index') }}" class="mt-10 inline-flex items-center gap-2 rounded-full bg-brand px-6 py-3 font-semibold text-night-900 transition hover:bg-cream">
                Naar de verhalen
            </a>
        @endif
    </div>
</x-app-layout>
