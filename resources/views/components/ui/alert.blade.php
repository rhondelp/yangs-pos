@props(['variant' => 'info', 'title' => null]) {{-- success | error | warning | info --}}

@php
    [$tone, $icon] = match ($variant) {
        'success' => ['border-success/40 bg-success/10 text-success', 'success'],
        'error' => ['border-danger/40 bg-danger/10 text-danger', 'warning'],
        'warning' => ['border-warning/40 bg-warning/10 text-warning', 'warning'],
        default => ['border-info/40 bg-info/10 text-info', 'info'],
    };
@endphp

<div role="{{ $variant === 'error' ? 'alert' : 'status' }}" {{ $attributes->merge(['class' => "flex gap-3 rounded-card border p-4 {$tone}"]) }}>
    <x-ui.icon :name="$icon" class="mt-0.5 size-5" />
    <div class="text-base text-fg">
        @if ($title)
            <p class="font-semibold">{{ $title }}</p>
        @endif
        {{ $slot }}
    </div>
</div>
