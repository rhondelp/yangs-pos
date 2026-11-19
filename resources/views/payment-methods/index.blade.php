<x-layouts.app :title="__('Payment methods')">
    <x-ui.card :title="__('New payment method')">
        <form method="POST" action="{{ route('payment-methods.store') }}" class="space-y-5">
            @csrf
            @include('payment-methods._fields')
            <x-ui.button type="submit" icon="plus">{{ __('Add method') }}</x-ui.button>
        </form>
    </x-ui.card>

    <x-ui.table>
        <x-slot:head>
            <tr>
                <th scope="col">{{ __('Name') }}</th>
                <th scope="col">{{ __('Reference') }}</th>
                <th scope="col" class="text-right">{{ __('Position') }}</th>
                <th scope="col">{{ __('Status') }}</th>
                <th scope="col"><span class="sr-only">{{ __('Actions') }}</span></th>
            </tr>
        </x-slot:head>
        @foreach ($methods as $method)
            <tr>
                <td class="text-base text-fg">
                    {{ $method->name }}
                    @if ($method->is_cash)
                        <span class="text-sm text-muted">({{ __('gives change') }})</span>
                    @endif
                </td>
                <td class="text-muted">{{ $method->requires_reference ? __('Required') : '—' }}</td>
                <td class="text-right text-muted tabular">{{ $method->sort_order }}</td>
                <td><x-ui.status-badge :status="$method->is_active ? 'active' : 'inactive'" /></td>
                <td>
                    <div class="flex justify-end">
                        <x-ui.button :href="route('payment-methods.edit', $method)" variant="ghost">{{ __('Edit') }}<span class="sr-only"> {{ $method->name }}</span></x-ui.button>
                    </div>
                </td>
            </tr>
        @endforeach
    </x-ui.table>
</x-layouts.app>
