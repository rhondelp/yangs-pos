@php
    use App\Enums\SessionStatus;
    use App\Models\InventoryMovement;
    use App\Models\OrderItem;
    use App\Models\StockReceiptItem;
    use App\Support\Money;
    use App\Support\Quantity;
    $adjustments = $filters['type'] === 'adjustment';
    $types = InventoryMovement::typeLabels();
    $reasons = InventoryMovement::reasonLabels();
    $canOpenTabs = auth()->user()->can('orders.view-all');
    $link = 'inline-flex min-h-touch items-center text-accent underline-offset-4 hover:underline';
@endphp

<x-layouts.app :title="$adjustments ? __('Stock adjustments') : __('Stock movements')">
    @include('inventory._tabs', ['current' => $adjustments ? 'adjustments' : 'movements'])

    <form method="GET" action="{{ route('inventory.movements') }}" class="mb-4 grid grid-cols-2 items-end gap-3 sm:grid-cols-3 lg:flex lg:flex-wrap">
        <x-ui.select name="item_id" :label="__('Item')" :options="$items" :selected="$filters['item_id']" :placeholder="__('All items')" class="col-span-2 sm:col-span-1 lg:w-56" />
        @if ($adjustments)
            <input type="hidden" name="type" value="adjustment">
        @else
            <x-ui.select name="type" :label="__('Type')" :options="$types" :selected="$filters['type']" :placeholder="__('All types')" class="lg:w-44" />
        @endif
        <x-ui.input name="from" type="date" :label="__('From (business day)')" :value="$filters['from']" class="lg:w-48" />
        <x-ui.input name="to" type="date" :label="__('To (business day)')" :value="$filters['to']" class="lg:w-48" />
        <x-ui.select name="user_id" :label="__('Recorded by')" :options="$users" :selected="$filters['user_id']" :placeholder="__('Everyone')" class="lg:w-44" />
        <x-ui.button type="submit" variant="secondary" icon="search">{{ __('Show') }}</x-ui.button>
    </form>

    @if ($movements->isEmpty())
        <x-ui.empty-state icon="archive" :title="$adjustments ? __('No stock-outs found.') : __('No stock movements found.')">
            {{ __('Try a wider date range or fewer filters.') }}
        </x-ui.empty-state>
    @else
        <x-ui.table :caption="$adjustments ? __('Stock adjustments') : __('Stock movements')">
            <x-slot:head>
                <tr>
                    <th scope="col">{{ __('When') }}</th>
                    <th scope="col">{{ __('Item') }}</th>
                    <th scope="col">{{ $adjustments ? __('Reason') : __('What') }}</th>
                    <th scope="col" class="text-right">{{ __('Change') }}</th>
                    <th scope="col" class="text-right">{{ __('Value') }}</th>
                    <th scope="col" class="text-right">{{ __('Balance') }}</th>
                    <th scope="col">{{ __('By') }}</th>
                </tr>
            </x-slot:head>
            @foreach ($movements as $movement)
                @php
                    $source = $movement->source;
                    $session = $source instanceof OrderItem ? $source->order?->session : null;
                @endphp
                <tr>
                    <td class="whitespace-nowrap text-muted tabular">{{ $movement->created_at->timezone(config('app.timezone'))->format('M j, g:i A') }}</td>
                    <td><a href="{{ route('inventory.show', $movement->item) }}" class="{{ $link }} text-base">{{ $movement->item->name }}</a></td>
                    <td>
                        <span class="text-fg">{{ $adjustments ? ($reasons[$movement->reason] ?? $movement->reason) : ($types[$movement->type] ?? $movement->type) }}</span>
                        @if (! $adjustments && $movement->reason)
                            <span class="text-sm text-muted">{{ $reasons[$movement->reason] ?? $movement->reason }}</span>
                        @endif
                        @if ($movement->note)
                            <span class="block text-sm text-muted">{{ $movement->note }}</span>
                        @endif
                        @if ($movement->is_unusual)
                            <x-ui.status-badge status="unusual" class="mt-1" />
                        @endif
                        @if ($source instanceof StockReceiptItem)
                            <a href="{{ route('stock-receipts.show', $source->stock_receipt_id) }}" class="{{ $link }} ml-1 text-sm">{{ $source->receipt->reference }}</a>
                        @elseif ($source instanceof OrderItem && $source->order)
                            {{-- Only a closed tab has a page, and only to users who see every tab. --}}
                            @if ($canOpenTabs && $session?->status === SessionStatus::Closed)
                                <a href="{{ route('transactions.show', $session) }}" class="{{ $link }} ml-1 text-sm">{{ $source->order->reference }}</a>
                            @else
                                <span class="ml-1 text-sm text-muted">{{ $source->order->reference }}</span>
                            @endif
                        @endif
                    </td>
                    <td class="whitespace-nowrap text-right tabular {{ bccomp($movement->quantity, '0', 3) > 0 ? 'text-success' : 'text-danger' }}">
                        {{ bccomp($movement->quantity, '0', 3) > 0 ? '+' : '' }}{{ Quantity::format($movement->quantity) }} {{ $movement->item->base_unit }}
                    </td>
                    <td class="whitespace-nowrap text-right tabular text-muted">{{ Money::format($movement->total_cost) }}</td>
                    <td class="whitespace-nowrap text-right tabular text-fg">{{ Quantity::format($movement->balance_after) }}</td>
                    <td class="text-muted">{{ $movement->createdBy?->name ?? '—' }}</td>
                </tr>
            @endforeach
        </x-ui.table>
        {{ $movements->links() }}
    @endif
</x-layouts.app>
