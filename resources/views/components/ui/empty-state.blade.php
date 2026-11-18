@props(['title', 'icon' => 'inbox'])

<div {{ $attributes->merge(['class' => 'flex flex-col items-center rounded-card border border-dashed border-line px-6 py-10 text-center']) }}>
    <x-ui.icon :name="$icon" class="size-10 text-muted" />
    <h3 class="mt-3 font-display text-xl font-semibold text-fg">{{ $title }}</h3>
    @if ($slot->isNotEmpty())
        <div class="mt-1 max-w-md text-base text-muted">{{ $slot }}</div>
    @endif
    @isset($actions)
        <div class="mt-5 flex flex-wrap justify-center gap-3">{{ $actions }}</div>
    @endisset
</div>
