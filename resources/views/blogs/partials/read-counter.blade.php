{{--
    Counts a read once the story has been on screen for 15 seconds (see ViewCounter). Only time while
    the page is really in view counts: not in a background tab, nor while a browser loads the page
    in advance. Sends where the reader came from (the page before, or ?bron=nieuwsbrief), nothing else.
--}}
<script>
    (() => {
        const needed = @js(\App\Support\ViewCounter::SECONDS * 1000);
        let seen = 0, since = null, sent = false;

        const visible = () => document.visibilityState === 'visible' && ! document.prerendering;
        const tick = () => {
            if (sent) return;
            const now = performance.now();
            if (since !== null) seen += now - since;
            since = visible() ? now : null;
            if (seen >= needed) send();
        };
        const send = () => {
            sent = true;
            const params = new URLSearchParams(location.search);
            fetch(@js(route('blogs.read', $blog->slug)), {
                method: 'POST',
                keepalive: true,
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                },
                body: JSON.stringify({
                    referrer: document.referrer,
                    bron: params.get('bron') || params.get('utm_source') || '',
                }),
            }).catch(() => {});
        };

        tick();
        document.addEventListener('visibilitychange', tick);
        document.addEventListener('prerenderingchange', tick);
        setInterval(tick, 1000);
    })();
</script>
