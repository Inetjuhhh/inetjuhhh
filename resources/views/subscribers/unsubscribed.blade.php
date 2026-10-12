<x-app-layout title="Uitgeschreven">
    <div class="max-w-xl mx-auto px-4 sm:px-6 lg:px-8 py-24 text-center">
        <p class="text-5xl" aria-hidden="true">👋</p>
        <h1 class="mt-6 font-display text-4xl text-cream">Je bent uitgeschreven</h1>
        <p class="mt-4 text-lg text-cream-muted">
            Je krijgt geen mails meer en je e-mailadres is gewist. Je bent natuurlijk altijd welkom om langs te komen en te lezen.
        </p>
        <a href="{{ route('blogs.index') }}"
           class="mt-10 inline-flex items-center gap-2 rounded-full bg-brand px-6 py-3 font-semibold text-night-900 transition hover:bg-cream">
            Naar de verhalen
        </a>
    </div>
</x-app-layout>
