<x-mail::message>
Hoi {{ $name }},

Er staat een nieuw verhaal online{{ $blog->trip ? ' over ' . $blog->trip->name : '' }}!

<a href="{{ $url }}"><img src="{{ $cover }}" alt="{{ $blog->title }}" width="570" style="display: block; width: 100%; max-width: 570px; height: auto; border-radius: 12px; margin: 8px 0 20px;"></a>

# {{ $blog->title }}

@if($excerpt)
{{ $excerpt }}
@endif

<x-mail::button :url="$url">
Lees het verhaal
</x-mail::button>

Veel leesplezier!<br>
{{ $author ?? config('app.name') }}

<x-mail::subcopy>
Je krijgt deze mail omdat je je hebt ingeschreven op {{ config('app.name') }}. Liever geen mails meer? [Uitschrijven]({{ $unsubscribeUrl }})
</x-mail::subcopy>
</x-mail::message>
