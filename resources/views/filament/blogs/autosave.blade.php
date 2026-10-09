<div x-data
     x-init="setInterval(() => { if (document.visibilityState === 'visible') $wire.autosave() }, 30000)"
     class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
    @if($this->canAutosave())
        <x-filament::icon icon="heroicon-o-cloud-arrow-up" class="h-5 w-5" />
        @if($this->autosavedAt)
            Concept automatisch opgeslagen om {{ $this->autosavedAt }}
        @else
            Concepten worden elke 30 seconden automatisch opgeslagen
        @endif
    @elseif(($this->data['status'] ?? null) === 'published')
        <x-filament::icon icon="heroicon-o-information-circle" class="h-5 w-5" />
        Automatisch opslaan staat uit voor gepubliceerde blogs: je wijzigingen gaan pas live als je op opslaan drukt.
    @endif
</div>
