<x-app-layout title="Inschrijving bevestigd">
    <div class="max-w-xl mx-auto px-4 sm:px-6 lg:px-8 py-24 text-center">
        <p class="text-5xl" aria-hidden="true">📬</p>
        <h1 class="mt-6 font-display text-4xl text-cream">Je staat op de lijst, {{ $subscriber->name }}!</h1>
        <p class="mt-4 text-lg text-cream-muted">
            Zodra er een nieuwe blog online staat, krijg je een mailtje op {{ $subscriber->email }}.
            Wil je ooit geen mails meer? Onderaan elke mail staat een link om je uit te schrijven.
        </p>
        <a href="{{ route('blogs.index') }}"
           class="mt-10 inline-flex items-center gap-2 rounded-full bg-brand px-6 py-3 font-semibold text-night-900 transition hover:bg-cream">
            Lees de verhalen
        </a>
    </div>
</x-app-layout>
