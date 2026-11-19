@php
    use App\Support\Money;
    use App\Support\Quantity;
@endphp

<x-layouts.app :title="__('Inventory')">
    @can('inventory.manage')
        <x-ui.card :title="__('New item')">
            <form method="POST" action="{{ route('inventory.store') }}" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @csrf
                <x-ui.input name="name" :label="__('Name')" required maxlength="120" class="sm:col-span-2" />
                <x-ui.input name="base_unit" :label="__('Counted in')" required maxlength="20" :hint="__('The smallest unit: bottle, ml, pc')" />
                <x-ui.input name="low_stock_level" inputmode="decimal" :label="__('Low-stock level')" :hint="__('Warn at or below this')" />
                <div class="sm:col-span-2 lg:col-span-4">
                    <x-ui.button type="submit" icon="plus">{{ __('Add item') }}</x-ui.button>
                </div>
            </form>
        </x-ui.card>
    @endcan

    <x-ui.table>
        <x-slot:head>
            <tr>
                <th scope="col">{{ __('Item') }}</th>
                <th scope="col" class="text-right">{{ __('On hand') }}</th>
                <th scope="col" class="text-right">{{ __('Avg cost') }}</th>
                <th scope="col" class="text-right">{{ __('Value') }}</th>
                <th scope="col">{{ __('Status') }}</th>
                <th scope="col"><span class="sr-only">{{ __('Actions') }}</span></th>
            </tr>
        </x-slot:head>
        @forelse ($items as $item)
            <tr>
                <td>
                    <span class="block text-base text-fg">{{ $item->name }}</span>
                    @if ($item->units->isNotEmpty())
                        <span class="block text-sm text-muted">{{ $item->units->map(fn ($u) => '1 '.$u->name.' = '.Quantity::format($u->factor).' '.$item->base_unit)->join(', ') }}</span>
                    @endif
                </td>
                <td class="whitespace-nowrap text-right tabular text-fg">{{ Quantity::format($item->on_hand) }} {{ $item->base_unit }}</td>
                <td class="whitespace-nowrap text-right tabular text-muted">{{ Money::format(Money::round($item->avg_unit_cost)) }}</td>
                {{-- Valuation counts only stock actually on hand (ARCHITECTURE §3.6). --}}
                <td class="whitespace-nowrap text-right tabular text-muted">{{ bccomp($item->on_hand, '0', 3) > 0 ? Money::format(Money::round(bcmul($item->on_hand, $item->avg_unit_cost, 10))) : '—' }}</td>
                <td><x-ui.status-badge :status="$item->stockStatus()" /></td>
                <td class="text-right">
                    <x-ui.button :href="route('inventory.show', $item)" variant="ghost">{{ __('Open') }}<span class="sr-only"> {{ $item->name }}</span></x-ui.button>
                </td>
            </tr>
        @empty
            <tr><td colspan="6" class="py-6 text-center text-muted">{{ __('No inventory items yet.') }}</td></tr>
        @endforelse
    </x-ui.table>
</x-layouts.app>
