<x-mail::message>
# Hoi {{ $name }}!

Leuk dat je op de hoogte wilt blijven van mijn verhalen. Klik op de knop om je inschrijving te bevestigen. Daarna krijg je een mailtje zodra er een nieuwe blog online staat.

<x-mail::button :url="$url">
Ja, houd me op de hoogte
</x-mail::button>

Heb je je niet ingeschreven? Dan kun je deze mail gewoon negeren; je komt dan niet op de lijst.

Liefs,<br>
{{ config('app.name') }}

<x-mail::subcopy>
Deze link werkt 7 dagen. Werkt de knop niet? Kopieer dan deze link in je browser: <span class="break-all">[{{ $url }}]({{ $url }})</span>
</x-mail::subcopy>
</x-mail::message>
