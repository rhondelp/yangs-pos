@props([
    'variant' => 'primary', // primary | secondary | danger | ghost
    'size' => 'md',         // md (48px) | lg (56px, POS actions)
    'href' => null,
    'type' => 'button',     // pass type="submit" in forms
    'icon' => null,
])

@php
    $classes = implode(' ', [
        'inline-flex min-h-touch items-center justify-center gap-2 rounded-control px-5 font-semibold transition-colors duration-150 disabled:pointer-events-none disabled:opacity-50',
        $size === 'lg' ? 'min-h-14 px-6 text-lg' : 'text-base',
        match ($variant) {
            'secondary' => 'border border-edge bg-raised text-fg hover:bg-line',
            'danger' => 'bg-danger text-on-accent hover:bg-danger/85',
            'ghost' => 'text-fg hover:bg-raised',
            default => 'bg-accent text-on-accent hover:bg-accent/85',
        },
    ]);
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if ($icon)<x-ui.icon :name="$icon" class="size-5" />@endif
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if ($icon)<x-ui.icon :name="$icon" class="size-5" />@endif
        {{ $slot }}
    </button>
@endif
