{{-- On a phone the blog form has two tabs: the story, and everything around it (publishing, photo,
     trip, tags, map). On a wide screen both columns show side by side and this bar is hidden.
     The `pane` state lives on the form's grid (BlogResource::form). --}}
<div class="blog-pane-switch">
    <x-filament::tabs contained>
        <x-filament::tabs.item alpine-active="pane === 'story'" x-on:click="pane = 'story'" icon="heroicon-o-document-text">
            Verhaal
        </x-filament::tabs.item>
        <x-filament::tabs.item alpine-active="pane === 'details'" x-on:click="pane = 'details'; $nextTick(() => window.dispatchEvent(new Event('resize')))" icon="heroicon-o-adjustments-horizontal">
            Details
        </x-filament::tabs.item>
    </x-filament::tabs>
</div>
