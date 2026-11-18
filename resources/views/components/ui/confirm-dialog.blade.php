@props([
    'id',
    'title',
    'action',
    'method' => 'POST',
    'confirm' => __('Confirm'),
    'variant' => 'danger',
])

{{-- Open with document.getElementById('{{ $id }}').showModal(). Cancel closes without submitting. --}}
<x-ui.modal :id="$id" :title="$title">
    <div class="text-base text-muted">{{ $slot }}</div>

    <x-slot:footer>
        <form method="dialog">
            <x-ui.button type="submit" variant="secondary">{{ __('Cancel') }}</x-ui.button>
        </form>
        <form method="POST" action="{{ $action }}">
            @csrf
            @if (strtoupper($method) !== 'POST')
                <input type="hidden" name="_method" value="{{ strtoupper($method) }}">
            @endif
            {{ $fields ?? '' }}
            <x-ui.button type="submit" :variant="$variant">{{ $confirm }}</x-ui.button>
        </form>
    </x-slot:footer>
</x-ui.modal>
