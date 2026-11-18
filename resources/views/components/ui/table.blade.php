@props(['caption' => null])

{{-- Pass <thead> rows in the "head" slot and <tr> rows in the default slot. Cell styling is applied here. --}}
<div {{ $attributes->merge(['class' => 'overflow-x-auto rounded-card border border-line bg-surface']) }}>
    <table class="min-w-full text-left text-sm
                  [&_tbody_tr]:border-t [&_tbody_tr]:border-line [&_tbody_tr:hover]:bg-raised/60
                  [&_td]:px-4 [&_td]:py-2 [&_td]:align-middle
                  [&_th]:px-4 [&_th]:py-3 [&_thead_th]:font-medium [&_thead_th]:text-muted">
        @if ($caption)
            <caption class="px-4 py-3 text-left font-display text-lg font-semibold text-fg">{{ $caption }}</caption>
        @endif
        @isset($head)
            <thead class="bg-raised/50">{{ $head }}</thead>
        @endisset
        <tbody>
            {{ $slot }}
        </tbody>
    </table>
</div>
