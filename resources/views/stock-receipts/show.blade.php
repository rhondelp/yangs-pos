@php
    use App\Support\Money;
    use App\Support\Quantity;
@endphp

<x-layouts.app :title="__('Stock-in :reference', ['reference' => $receipt->reference])">
    <div class="mb-4">
        <x-ui.button :href="route('stock-receipts.index')" variant="ghost" icon="chevron-left">{{ __('Stock-in') }}</x-ui.button>
    </div>

    <x-ui.card>
        <dl class="grid gap-4 text-base sm:grid-cols-2 lg:grid-cols-4">
            <div><dt class="text-sm text-muted">{{ __('Supplier') }}</dt><dd class="text-fg">{{ $receipt->supplier?->name ?? '—' }}</dd></div>
            <div><dt class="text-sm text-muted">{{ __('Invoice or reference') }}</dt><dd class="text-fg">{{ $receipt->invoice_no ?? '—' }}</dd></div>
            <div><dt class="text-sm text-muted">{{ __('Received') }}</dt><dd class="text-fg tabular">{{ $receipt->received_at->timezone(config('app.timezone'))->format('M j, Y g:i A') }}</dd></div>
            <div><dt class="text-sm text-muted">{{ __('Received by') }}</dt><dd class="text-fg">{{ $receipt->receivedBy?->name ?? '—' }}</dd></div>
        </dl>
        @if ($receipt->notes)
            <p class="mt-4 text-base text-muted">{{ $receipt->notes }}</p>
        @endif
    </x-ui.card>

    <x-ui.table>
        <x-slot:head>
            <tr>
                <th scope="col">{{ __('Item') }}</th>
                <th scope="col" class="text-right">{{ __('Received') }}</th>
                <th scope="col" class="text-right">{{ __('In stock units') }}</th>
                <th scope="col" class="text-right">{{ __('Cost each') }}</th>
                <th scope="col" class="text-right">{{ __('Line cost') }}</th>
            </tr>
        </x-slot:head>
        @foreach ($receipt->lines as $line)
            <tr>
                <td class="text-base">
                    @can('inventory.view')
                        <a href="{{ route('inventory.show', $line->inventory_item_id) }}" class="inline-flex min-h-touch items-center text-fg underline-offset-4 hover:underline">{{ $line->item->name }}</a>
                    @else
                        {{ $line->item->name }}
                    @endcan
                </td>
                <td class="whitespace-nowrap text-right tabular text-fg">{{ Quantity::format($line->quantity) }} {{ $line->unit_name }}</td>
                <td class="whitespace-nowrap text-right tabular text-muted">{{ Quantity::format($line->base_quantity) }} {{ $line->item->base_unit }}</td>
                <td class="whitespace-nowrap text-right tabular text-muted">{{ Money::format(Money::round($line->base_unit_cost)) }}</td>
                <td class="whitespace-nowrap text-right tabular text-fg">{{ Money::format($line->line_cost) }}</td>
            </tr>
        @endforeach
        <tr>
            <td colspan="4" class="text-right font-semibold text-fg">{{ __('Total') }}</td>
            <td class="whitespace-nowrap text-right font-display text-xl font-bold tabular text-accent">{{ Money::format($receipt->total_cost) }}</td>
        </tr>
    </x-ui.table>
</x-layouts.app>
