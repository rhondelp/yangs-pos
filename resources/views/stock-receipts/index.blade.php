@php use App\Support\Money; @endphp

<x-layouts.app :title="__('Stock-in')">
    @can('inventory.stock-in')
        <div class="mb-4">
            <x-ui.button :href="route('stock-receipts.create')" icon="plus" size="lg">{{ __('New stock-in') }}</x-ui.button>
        </div>
    @endcan

    <x-ui.table>
        <x-slot:head>
            <tr>
                <th scope="col">{{ __('Reference') }}</th>
                <th scope="col">{{ __('Received') }}</th>
                <th scope="col">{{ __('Supplier') }}</th>
                <th scope="col">{{ __('Invoice') }}</th>
                <th scope="col" class="text-right">{{ __('Lines') }}</th>
                <th scope="col" class="text-right">{{ __('Total') }}</th>
                <th scope="col"><span class="sr-only">{{ __('Actions') }}</span></th>
            </tr>
        </x-slot:head>
        @forelse ($receipts as $receipt)
            <tr>
                <td class="text-base text-fg tabular">{{ $receipt->reference }}</td>
                <td class="whitespace-nowrap text-muted tabular">{{ $receipt->received_at->timezone(config('app.timezone'))->format('M j, Y g:i A') }}</td>
                <td class="text-muted">{{ $receipt->supplier?->name ?? '—' }}</td>
                <td class="text-muted">{{ $receipt->invoice_no ?? '—' }}</td>
                <td class="text-right text-muted tabular">{{ $receipt->lines_count }}</td>
                <td class="whitespace-nowrap text-right tabular text-fg">{{ Money::format($receipt->total_cost) }}</td>
                <td class="text-right">
                    <x-ui.button :href="route('stock-receipts.show', $receipt)" variant="ghost">{{ __('Open') }}<span class="sr-only"> {{ $receipt->reference }}</span></x-ui.button>
                </td>
            </tr>
        @empty
            <tr><td colspan="7" class="py-6 text-center text-muted">{{ __('No stock-ins yet.') }}</td></tr>
        @endforelse
    </x-ui.table>
    {{ $receipts->links() }}
</x-layouts.app>
