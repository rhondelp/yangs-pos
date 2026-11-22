@php
    use App\Support\Money;
    use App\Support\Quantity;
@endphp

<x-layouts.app :title="__('Inventory')">
    @include('inventory._tabs', ['current' => 'levels'])

    @can('inventory.manage')
        <x-ui.card :title="__('New item')">
            <form method="POST" action="{{ route('inventory.store') }}" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @csrf
                <x-ui.input name="name" :label="__('Name')" required maxlength="120" class="sm:col-span-2" />
                <x-ui.input name="base_unit" :label="__('Counted in')" required maxlength="20" :hint="__('The smallest unit: bottle, ml, pc')" />
                <x-ui.input name="low_stock_level" inputmode="decimal" :label="__('Low-stock level')" :hint="__('Warn at or below this')" />
                <x-ui.select name="category_id" :label="__('Category')" :options="$categories" :placeholder="__('None')" />
                <div class="sm:col-span-2 lg:col-span-4">
                    <x-ui.button type="submit" icon="plus">{{ __('Add item') }}</x-ui.button>
                </div>
            </form>
        </x-ui.card>
    @endcan

    <div class="mb-4 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
        <x-ui.stat-card :label="__('Stock value')" :value="Money::format($summary['total_value'])" class="col-span-2 sm:col-span-1" />
        <x-ui.stat-card :label="__('Items')" :value="(string) $summary['item_count']" />
        <x-ui.stat-card :label="__('Low')" :value="(string) $summary['low']" />
        <x-ui.stat-card :label="__('Out')" :value="(string) $summary['out']" />
        <x-ui.stat-card :label="__('Below zero')" :value="(string) $summary['negative']" />
    </div>

    <form method="GET" action="{{ route('inventory.index') }}" class="mb-4 grid grid-cols-2 items-end gap-3 sm:grid-cols-3 lg:flex lg:flex-wrap">
        <x-ui.input name="search" type="search" :label="__('Search')" :value="$filters['search']" :placeholder="__('Name or SKU')" maxlength="100" class="col-span-2 sm:col-span-1 lg:w-56" />
        <x-ui.select name="category_id" id="filter-category" :old="false" :label="__('Category')" :options="$filterCategories" :selected="$filters['category_id']" :placeholder="__('All categories')" class="lg:w-48" />
        <x-ui.select name="status" :label="__('Status')" :selected="$filters['status']" :placeholder="__('All')" class="lg:w-48"
                     :options="['attention' => __('Needs attention'), 'low' => __('Low'), 'out' => __('Out'), 'negative' => __('Below zero')]" />
        <label for="archived" class="flex min-h-touch items-center gap-3 text-base text-fg">
            <input id="archived" name="archived" type="checkbox" value="1" class="size-6 rounded border-edge bg-raised text-accent focus:ring-accent" @checked($filters['archived'])>
            {{ __('Show archived') }}
        </label>
        <x-ui.button type="submit" variant="secondary" icon="search">{{ __('Show') }}</x-ui.button>
    </form>

    <x-ui.table>
        <x-slot:head>
            <tr>
                <th scope="col">{{ __('Item') }}</th>
                <th scope="col">{{ __('Category') }}</th>
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
                <td class="text-muted">{{ $item->category?->name ?? '—' }}</td>
                <td class="whitespace-nowrap text-right tabular text-fg">{{ Quantity::format($item->on_hand) }} {{ $item->base_unit }}</td>
                <td class="whitespace-nowrap text-right tabular text-muted">{{ Money::format(Money::round($item->avg_unit_cost)) }}</td>
                {{-- Valuation counts only stock actually on hand (ARCHITECTURE §3.6). --}}
                <td class="whitespace-nowrap text-right tabular text-muted">{{ bccomp($item->on_hand, '0', 3) > 0 ? Money::format($item->stockValue()) : '—' }}</td>
                <td><x-ui.status-badge :status="$item->stockStatus()" /></td>
                <td class="text-right">
                    <x-ui.button :href="route('inventory.show', $item)" variant="ghost">{{ __('Open') }}<span class="sr-only"> {{ $item->name }}</span></x-ui.button>
                </td>
            </tr>
        @empty
            <tr><td colspan="7" class="py-6 text-center text-muted">{{ request()->hasAny(['search', 'category_id', 'status']) ? __('No items match these filters.') : __('No inventory items yet.') }}</td></tr>
        @endforelse
    </x-ui.table>
</x-layouts.app>
