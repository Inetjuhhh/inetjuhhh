{{-- Heart for a blog: no account, one per browser (see LikeController) --}}
<div x-data="{
        liked: @js($liked),
        count: @js($blog->likes_count),
        busy: false,
        pop: false,
        async toggle() {
            if (this.busy) return;
            this.busy = true;
            // Show the result right away; the server answer corrects it if needed
            this.liked = ! this.liked;
            this.count += this.liked ? 1 : -1;
            if (this.liked) { this.pop = true; setTimeout(() => this.pop = false, 400); }

            try {
                const response = await fetch(@js(route('blogs.like', $blog)), {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    },
                });
                if (! response.ok) throw new Error(response.status);
                const data = await response.json();
                this.liked = data.liked;
                this.count = data.count;
            } catch {
                this.liked = ! this.liked;
                this.count += this.liked ? 1 : -1;
            } finally {
                this.busy = false;
            }
        },
     }"
     class="flex flex-col items-center gap-3 rounded-3xl bg-night-700 px-6 py-8 text-center ring-1 ring-white/5">
    <p class="font-display text-2xl text-cream">Ik zou het leuk vinden om te weten als je het een leuke blog vond :)</p>
    <button type="button" x-on:click="toggle()" @disabled($disabled ?? false)
            x-bind:aria-pressed="liked.toString()"
            x-bind:aria-label="liked ? 'Hartje weghalen' : 'Geef een hartje'"
            class="group inline-flex items-center gap-3 rounded-full px-6 py-3 font-semibold transition disabled:cursor-not-allowed disabled:opacity-50"
            x-bind:class="liked ? 'bg-accent text-night-900' : 'bg-night-600 text-cream hover:bg-night-500'">
        <i class="fa-heart text-xl transition-transform duration-300"
           x-bind:class="[liked ? 'fas' : 'far text-accent', pop ? 'scale-150' : 'group-hover:scale-110']"></i>
        <span x-text="liked ? 'Bedankt!' : 'Geef een hartje'"></span>
    </button>
    <p class="text-sm text-cream-muted" x-show="count > 0" x-cloak>
        <span x-text="count"></span> <span x-text="count === 1 ? 'lezer vond' : 'lezers vonden'"></span> dit een mooi verhaal
    </p>
</div>
