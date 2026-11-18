@props([
    'label',
    'value' => null,   // already formatted, e.g. "₱1,250.00"; null means no data yet
    'hint' => null,
    'empty' => null,   // shown when value is null
])

<div {{ $attributes->merge(['class' => 'rounded-card border border-line bg-surface p-5']) }}>
    <p class="text-sm font-medium text-muted">{{ $label }}</p>
    @if ($value !== null)
        <p class="mt-2 font-display text-4xl font-semibold tabular text-fg">{{ $value }}</p>
        @if ($hint)
            <p class="mt-1 text-sm text-muted">{{ $hint }}</p>
        @endif
    @else
        <p class="mt-2 font-display text-4xl font-semibold text-line" aria-hidden="true">—</p>
        <p class="mt-1 text-sm text-muted">{{ $empty ?? __('No data yet.') }}</p>
    @endif
</div>
