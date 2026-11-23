{{--
    Curator's own view, plus the trip the photo window starts on (see App\Livewire\CuratorPanel):
    the trip of the blog being edited, or the trip whose cover is being chosen.
--}}
@php
    $page = \Livewire\Livewire::current();
    $settings['tripId'] ??= match (true) {
        $page instanceof \Filament\Resources\Pages\EditRecord && $page->getRecord() instanceof \App\Models\Trip => $page->getRecord()->getKey(),
        is_object($page) && is_array($page->data ?? null) && filled($page->data['trip_id'] ?? null) => (int) $page->data['trip_id'],
        default => null,
    };
@endphp
<livewire:curator-panel
    :settings="$settings"
    @insertMedia="callSchemaComponentMethod({{ \Illuminate\Support\Js::from($key) }}, 'updateState', $event.detail); close()"
/>
