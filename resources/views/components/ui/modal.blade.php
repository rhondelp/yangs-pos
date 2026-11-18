@props(['id', 'title'])

{{--
    Native <dialog>: focus is trapped and the page behind is inert while open; Esc closes it.
    Open:  <x-ui.button x-on:click="document.getElementById('{{ $id }}').showModal()">…</x-ui.button>
    Close: any <button> inside <form method="dialog">, or Esc.
--}}
<dialog id="{{ $id }}" aria-labelledby="{{ $id }}-title"
        {{ $attributes->merge(['class' => 'm-auto w-[min(32rem,calc(100vw-2rem))] rounded-card border border-line bg-surface p-0 text-fg']) }}>
    <div class="flex min-h-touch items-center justify-between gap-3 border-b border-line py-2 pl-5 pr-2">
        <h2 id="{{ $id }}-title" class="font-display text-xl font-semibold">{{ $title }}</h2>
        <form method="dialog">
            <button type="submit" class="inline-flex size-touch items-center justify-center rounded-control text-muted hover:bg-raised hover:text-fg" aria-label="{{ __('Close') }}">
                <x-ui.icon name="close" />
            </button>
        </form>
    </div>
    <div class="p-5">
        {{ $slot }}
    </div>
    @isset($footer)
        <div class="flex flex-wrap justify-end gap-3 border-t border-line px-5 py-4">{{ $footer }}</div>
    @endisset
</dialog>
