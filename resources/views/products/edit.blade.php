<x-layouts.app :title="$product->name">
    <form method="POST" action="{{ route('products.update', $product) }}" enctype="multipart/form-data">
        @method('PUT')
        @include('products._form')
    </form>

    @canany(['inventory.view', 'inventory.manage'])
        @include('products._recipe')
    @endcanany

    <x-ui.table :caption="__('Price history')">
        <x-slot:head>
            <tr>
                <th scope="col">{{ __('When') }}</th>
                <th scope="col">{{ __('Item') }}</th>
                <th scope="col">{{ __('Changed') }}</th>
                <th scope="col" class="text-right">{{ __('Before') }}</th>
                <th scope="col" class="text-right">{{ __('After') }}</th>
                <th scope="col">{{ __('By') }}</th>
            </tr>
        </x-slot:head>
        @forelse ($product->priceHistory as $entry)
            <tr>
                <td class="whitespace-nowrap text-muted tabular">{{ $entry->created_at->format('Y-m-d H:i') }}</td>
                <td class="text-fg">{{ $entry->variant?->name ?? __('Main price') }}</td>
                <td class="text-muted">{{ $entry->field === 'price' ? __('Selling price') : __('Cost') }}</td>
                <td class="text-right text-muted tabular">{{ $entry->old_value === null ? __('(new)') : \App\Support\Money::format($entry->old_value) }}</td>
                <td class="text-right text-fg tabular">{{ \App\Support\Money::format($entry->new_value) }}</td>
                <td class="text-muted">{{ $entry->changedBy?->name ?? __('System') }}</td>
            </tr>
        @empty
            <tr><td colspan="6" class="py-6 text-center text-muted">{{ __('No price changes yet.') }}</td></tr>
        @endforelse
    </x-ui.table>

    <x-ui.card :title="__('Archive')">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <p class="max-w-xl text-base text-muted">{{ __('Archiving removes the product from the menu and lists. Its sales history stays. You can restore it later.') }}</p>
            <x-ui.button variant="danger" icon="archive" x-data x-on:click="document.getElementById('archive-product').showModal()">{{ __('Archive product') }}</x-ui.button>
        </div>
    </x-ui.card>

    <x-ui.confirm-dialog id="archive-product" :title="__('Archive :name?', ['name' => $product->name])" :action="route('products.archive', $product)" method="DELETE" :confirm="__('Archive')">
        {{ __('It will disappear from the POS menu right away.') }}
    </x-ui.confirm-dialog>
</x-layouts.app>
