@php($canManage = auth()->user()->can('products.manage'))

<x-layouts.app :title="__('Products')">
    <div class="flex flex-wrap items-end gap-4">
        <form method="GET" action="{{ route('products.index') }}" class="flex flex-1 flex-wrap items-end gap-3" role="search">
            <x-ui.input name="q" type="search" :label="__('Search')" :value="$filters['q'] ?? null" :placeholder="__('Product name')" class="min-w-56 flex-1" />
            <x-ui.select name="category_id" :label="__('Category')" :placeholder="__('All categories')" :selected="$filters['category_id'] ?? null"
                         :options="$categories->pluck('name', 'id')->all()" class="min-w-48" />
            <x-ui.select name="status" :label="__('Show')" :selected="$filters['status'] ?? 'active'"
                         :options="['active' => __('On the list'), 'archived' => __('Archived'), 'all' => __('Everything')]" class="min-w-40" />
            <x-ui.button type="submit" variant="secondary" icon="search">{{ __('Filter') }}</x-ui.button>
        </form>
        @if ($canManage)
            <x-ui.button :href="route('products.create')" icon="plus">{{ __('New product') }}</x-ui.button>
        @endif
    </div>

    @if ($products->isEmpty())
        <x-ui.empty-state :title="__('No products found')" icon="cube">
            @if (array_filter($filters))
                {{ __('Try another search or reset the filters.') }}
            @else
                {{ __('Add the drinks and food you sell. Cashiers see them on the POS once they are available.') }}
            @endif
            @if ($canManage)
                <x-slot:actions><x-ui.button :href="route('products.create')" icon="plus">{{ __('New product') }}</x-ui.button></x-slot:actions>
            @endif
        </x-ui.empty-state>
    @else
        <x-ui.table>
            <x-slot:head>
                <tr>
                    <th scope="col">{{ __('Product') }}</th>
                    <th scope="col" class="text-right">{{ __('Price') }}</th>
                    @if ($canManage)
                        <th scope="col" class="text-right">{{ __('Cost and margin') }}</th>
                    @endif
                    <th scope="col">{{ __('On the menu') }}</th>
                    @if ($canManage)
                        <th scope="col"><span class="sr-only">{{ __('Actions') }}</span></th>
                    @endif
                </tr>
            </x-slot:head>
            @foreach ($products as $product)
                @php($status = $product->trashed() ? 'archived' : ($product->is_available ? 'available' : 'hidden'))
                <tr>
                    <td>
                        <div class="flex items-center gap-3">
                            @if ($product->imageUrl())
                                <img src="{{ $product->imageUrl() }}" alt="" class="size-12 shrink-0 rounded-control object-cover" loading="lazy">
                            @else
                                <span class="flex size-12 shrink-0 items-center justify-center rounded-control bg-raised text-muted"><x-ui.icon name="photo" /></span>
                            @endif
                            <span class="min-w-0">
                                <span class="block text-base font-medium text-fg">{{ $product->name }}</span>
                                <span class="block text-sm text-muted">
                                    {{ $product->category->name }}@if ($product->variants_count), {{ trans_choice(':count option|:count options', $product->variants_count) }}@endif
                                </span>
                            </span>
                        </div>
                    </td>
                    <td class="text-right font-display text-lg font-semibold text-fg tabular">{{ \App\Support\Money::format($product->price) }}</td>
                    @if ($canManage)
                        @php($margin = $product->margin())
                        <td class="text-right tabular">
                            <span class="block text-muted">{{ \App\Support\Money::format($product->cost) }}</span>
                            @if ($margin['amount'] !== null)
                                <span class="block text-sm text-fg">{{ \App\Support\Money::format($margin['amount']) }}@if ($margin['percent'] !== null) ({{ $margin['percent'] }}%)@endif</span>
                            @endif
                        </td>
                    @endif
                    <td>
                        @if ($canManage && ! $product->trashed())
                            {{-- The badge is the switch: one tap hides or shows the product on the POS. --}}
                            <form method="POST" action="{{ route('products.availability', $product) }}">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="inline-flex min-h-touch items-center rounded-control px-1 hover:bg-raised"
                                        title="{{ $product->is_available ? __('Tap to hide from the menu') : __('Tap to show on the menu') }}">
                                    <x-ui.status-badge :status="$status" />
                                    <span class="sr-only">{{ $product->is_available ? __('Hide :name from the menu', ['name' => $product->name]) : __('Show :name on the menu', ['name' => $product->name]) }}</span>
                                </button>
                            </form>
                        @else
                            <x-ui.status-badge :status="$status" />
                        @endif
                    </td>
                    @if ($canManage)
                        <td class="text-right">
                            @if ($product->trashed())
                                <form method="POST" action="{{ route('products.restore', $product) }}">
                                    @csrf
                                    <x-ui.button type="submit" variant="ghost">{{ __('Restore') }}<span class="sr-only"> {{ $product->name }}</span></x-ui.button>
                                </form>
                            @else
                                <x-ui.button :href="route('products.edit', $product)" variant="ghost">{{ __('Edit') }}<span class="sr-only"> {{ $product->name }}</span></x-ui.button>
                            @endif
                        </td>
                    @endif
                </tr>
            @endforeach
        </x-ui.table>
    @endif

    {{ $products->links() }}
</x-layouts.app>
