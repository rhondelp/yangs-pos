@props(['title' => null, 'padded' => true])

<section {{ $attributes->merge(['class' => 'rounded-card border border-line bg-surface']) }}>
    @if ($title || isset($actions))
        <header class="flex min-h-touch flex-wrap items-center justify-between gap-3 border-b border-line px-5 py-3">
            @if ($title)
                <h2 class="font-display text-lg font-semibold text-fg">{{ $title }}</h2>
            @endif
            @isset($actions)
                <div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>
            @endisset
        </header>
    @endif
    <div @class(['p-5' => $padded])>
        {{ $slot }}
    </div>
</section>
