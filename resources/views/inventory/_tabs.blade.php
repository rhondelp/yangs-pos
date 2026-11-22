{{-- $current: levels | movements | adjustments --}}

<nav aria-label="{{ __('Inventory views') }}" class="mb-4 flex flex-wrap gap-2 border-b border-line">
    @foreach ([
        'levels' => [__('Stock levels'), route('inventory.index')],
        'movements' => [__('Movements'), route('inventory.movements')],
        'adjustments' => [__('Adjustments'), route('inventory.movements', ['type' => 'adjustment'])],
    ] as $key => [$label, $href])
        <a href="{{ $href }}" @if ($current === $key) aria-current="page" @endif
           class="-mb-px inline-flex min-h-touch items-center border-b-2 px-4 text-base font-medium {{ $current === $key ? 'border-accent text-fg' : 'border-transparent text-muted hover:text-fg' }}">
            {{ $label }}
        </a>
    @endforeach
</nav>
