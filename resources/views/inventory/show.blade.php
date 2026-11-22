@php
    use App\Models\InventoryMovement;
    use App\Models\StockReceiptItem;
    use App\Support\Money;
    use App\Support\Quantity;
    $canManage = auth()->user()->can('inventory.manage');
    $types = InventoryMovement::typeLabels();
    $reasons = InventoryMovement::reasonLabels();
    $unitRows = old('units', $item->units->map(fn ($u) => ['id' => $u->id, 'name' => $u->name, 'factor' => Quantity::format($u->factor)])->all());
@endphp

<x-layouts.app :title="$item->name">
    <div class="mb-4 flex flex-wrap items-center gap-3">
        <x-ui.button :href="route('inventory.index')" variant="ghost" icon="chevron-left">{{ __('Inventory') }}</x-ui.button>
        <x-ui.status-badge :status="$item->stockStatus()" />
    </div>

    <div class="grid gap-4 sm:grid-cols-3">
        <x-ui.stat-card :label="__('On hand')" :value="Quantity::format($item->on_hand).' '.$item->base_unit" />
        <x-ui.stat-card :label="__('Average cost per :unit', ['unit' => $item->base_unit])" :value="Money::format(Money::round($item->avg_unit_cost))" />
        <x-ui.stat-card :label="__('Stock value')" :value="bccomp($item->on_hand, '0', 3) > 0 ? Money::format($item->stockValue()) : '—'" />
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        <x-ui.card :title="__('Details')">
            @if ($canManage && ! $item->trashed())
                <form method="POST" action="{{ route('inventory.update', $item) }}" class="grid gap-4 sm:grid-cols-2">
                    @csrf
                    @method('PUT')
                    <x-ui.input name="name" :label="__('Name')" :value="$item->name" required maxlength="120" class="sm:col-span-2" />
                    <x-ui.input name="sku" :label="__('SKU (optional)')" :value="$item->sku" maxlength="60" />
                    <x-ui.input name="base_unit" :label="__('Counted in')" :value="$item->base_unit" required maxlength="20" :hint="__('Fixed once it has stock, units or recipes')" />
                    <x-ui.input name="low_stock_level" inputmode="decimal" :label="__('Low-stock level (:unit)', ['unit' => $item->base_unit])" :value="Quantity::format($item->low_stock_level)" />
                    <x-ui.select name="category_id" :label="__('Category')" :options="$categories" :selected="$item->category_id" :placeholder="__('None')" />
                    <div class="flex flex-wrap items-end gap-3">
                        <x-ui.button type="submit">{{ __('Save details') }}</x-ui.button>
                    </div>
                </form>
            @else
                <dl class="grid gap-3 text-base sm:grid-cols-2">
                    <div><dt class="text-sm text-muted">{{ __('SKU') }}</dt><dd>{{ $item->sku ?? '—' }}</dd></div>
                    <div><dt class="text-sm text-muted">{{ __('Counted in') }}</dt><dd>{{ $item->base_unit }}</dd></div>
                    <div><dt class="text-sm text-muted">{{ __('Category') }}</dt><dd>{{ $item->category ? ($item->category->trashed() ? __(':name (archived)', ['name' => $item->category->name]) : $item->category->name) : '—' }}</dd></div>
                    <div><dt class="text-sm text-muted">{{ __('Low-stock level') }}</dt><dd>{{ Quantity::format($item->low_stock_level) }} {{ $item->base_unit }}</dd></div>
                </dl>
            @endif
        </x-ui.card>

        <x-ui.card :title="__('Units')">
            <p class="mb-4 text-base text-muted">
                {{ __('Bigger packs you buy in. Stock is always counted in :unit.', ['unit' => $item->base_unit]) }}
            </p>
            @if ($item->units->isNotEmpty())
                <ul class="mb-4 space-y-1 text-base">
                    @foreach ($item->units as $unit)
                        <li>1 {{ $unit->name }} = {{ Quantity::format($unit->factor) }} {{ $item->base_unit }}</li>
                    @endforeach
                </ul>
            @endif

            @if ($canManage && ! $item->trashed())
                @error('units')
                    <x-ui.alert variant="error" class="mb-4">{{ $message }}</x-ui.alert>
                @enderror
                <form method="POST" action="{{ route('inventory.units', $item) }}" x-data="{ rows: @js(array_values($unitRows)) }" class="space-y-3">
                    @csrf
                    @method('PUT')
                    <template x-for="(row, i) in rows" :key="i">
                        <div class="flex items-end gap-2">
                            <input type="hidden" x-bind:name="`units[${i}][id]`" x-bind:value="row.id ?? ''">
                            <div class="min-w-0 flex-1">
                                <label x-bind:for="`unit-name-${i}`" class="mb-1 block text-sm font-medium text-muted">{{ __('Unit') }}</label>
                                <input x-bind:id="`unit-name-${i}`" type="text" maxlength="30" x-bind:name="`units[${i}][name]`" x-model="row.name" placeholder="case"
                                       class="block min-h-touch w-full rounded-control border border-edge bg-raised px-3 text-base text-fg focus:border-accent focus:ring-accent">
                            </div>
                            <div class="w-32">
                                <label x-bind:for="`unit-factor-${i}`" class="mb-1 block text-sm font-medium text-muted">{{ __(':unit in it', ['unit' => ucfirst($item->base_unit)]) }}</label>
                                <input x-bind:id="`unit-factor-${i}`" type="text" inputmode="decimal" x-bind:name="`units[${i}][factor]`" x-model="row.factor" placeholder="24"
                                       class="block min-h-touch w-full rounded-control border border-edge bg-raised px-3 text-right text-base text-fg tabular focus:border-accent focus:ring-accent">
                            </div>
                            <button type="button" x-on:click="rows.splice(i, 1)" class="inline-flex size-touch shrink-0 items-center justify-center rounded-control text-muted hover:bg-raised hover:text-danger" x-bind:aria-label="`{{ __('Remove') }} ${row.name || '{{ __('unit') }}'}`">
                                <x-ui.icon name="trash" />
                            </button>
                        </div>
                    </template>
                    @foreach ($errors->getMessages() as $key => $messages)
                        @if (str_starts_with($key, 'units.'))
                            <p class="text-sm font-medium text-danger">{{ $messages[0] }}</p>
                        @endif
                    @endforeach
                    <div class="flex flex-wrap gap-3">
                        <x-ui.button variant="secondary" icon="plus" x-on:click="rows.push({ id: null, name: '', factor: '' })">{{ __('Add unit') }}</x-ui.button>
                        <x-ui.button type="submit">{{ __('Save units') }}</x-ui.button>
                    </div>
                </form>
            @endif
        </x-ui.card>
    </div>

    @if (auth()->user()->can('inventory.adjust') && ! $item->trashed())
        <x-ui.card :title="__('Stock-out')">
            <p class="mb-4 max-w-2xl text-base text-muted">{{ __('Take stock off the shelf that was not sold: broken, expired, missing or used by the bar. It is valued at the average cost.') }}</p>
            <form method="POST" action="{{ route('inventory.stock-outs', $item) }}" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4" x-data="{ sending: false }" x-on:submit="sending = true">
                @csrf
                <x-ui.input name="quantity" id="stock-out-quantity" inputmode="decimal" :label="__('Quantity')" required />
                <x-ui.select name="inventory_unit_id" id="stock-out-unit" :label="__('Unit')"
                             :options="$item->units->mapWithKeys(fn ($u) => [$u->id => $u->name.' ('.Quantity::format($u->factor).')'])->all()" :placeholder="$item->base_unit" />
                <x-ui.select name="reason" id="stock-out-reason" :label="__('Reason')" :options="$reasons" :placeholder="__('Choose…')" required />
                <x-ui.input name="note" id="stock-out-note" :label="__('Note (needed for Other)')" maxlength="300" />
                <div class="sm:col-span-2 lg:col-span-4">
                    <x-ui.button type="submit" variant="danger" x-bind:disabled="sending">{{ __('Record stock-out') }}</x-ui.button>
                </div>
            </form>
        </x-ui.card>
    @endif

    <x-ui.table :caption="__('Stock movements')">
        <x-slot:head>
            <tr>
                <th scope="col">{{ __('When') }}</th>
                <th scope="col">{{ __('What') }}</th>
                <th scope="col" class="text-right">{{ __('Change') }}</th>
                <th scope="col" class="text-right">{{ __('Unit cost') }}</th>
                <th scope="col" class="text-right">{{ __('Balance') }}</th>
                <th scope="col">{{ __('By') }}</th>
            </tr>
        </x-slot:head>
        @forelse ($movements as $movement)
            <tr>
                <td class="whitespace-nowrap text-muted tabular">{{ $movement->created_at->timezone(config('app.timezone'))->format('M j, g:i A') }}</td>
                <td>
                    <span class="text-fg">{{ $types[$movement->type] ?? $movement->type }}</span>
                    @if ($movement->reason)
                        <span class="text-sm text-muted">{{ $reasons[$movement->reason] ?? $movement->reason }}@if ($movement->note): {{ $movement->note }}@endif</span>
                    @endif
                    @if ($movement->is_unusual)
                        <x-ui.status-badge status="unusual" class="ml-1" />
                    @endif
                    @if ($movement->source instanceof StockReceiptItem)
                        <a href="{{ route('stock-receipts.show', $movement->source->stock_receipt_id) }}" class="ml-1 inline-flex min-h-touch items-center text-sm text-accent underline-offset-4 hover:underline">{{ $movement->source->receipt->reference }}</a>
                    @endif
                </td>
                <td class="whitespace-nowrap text-right tabular {{ bccomp($movement->quantity, '0', 3) > 0 ? 'text-success' : 'text-danger' }}">
                    {{ bccomp($movement->quantity, '0', 3) > 0 ? '+' : '' }}{{ Quantity::format($movement->quantity) }}
                </td>
                <td class="whitespace-nowrap text-right tabular text-muted">{{ Money::format(Money::round($movement->unit_cost)) }}</td>
                <td class="whitespace-nowrap text-right tabular text-fg">{{ Quantity::format($movement->balance_after) }} {{ $item->base_unit }}</td>
                <td class="text-muted">{{ $movement->createdBy?->name ?? '—' }}</td>
            </tr>
        @empty
            <tr><td colspan="6" class="py-6 text-center text-muted">{{ __('No stock movements yet. Record a stock-in to add stock.') }}</td></tr>
        @endforelse
    </x-ui.table>
    {{ $movements->links() }}

    @if ($canManage)
        <x-ui.card :title="$item->trashed() ? __('Restore') : __('Archive')">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <p class="max-w-xl text-base text-muted">
                    {{ $item->trashed() ? __('Bring this item back into stock-in and recipes.') : __('Archiving hides the item from stock-in and recipes. Its history stays.') }}
                </p>
                @if ($item->trashed())
                    <form method="POST" action="{{ route('inventory.restore', $item) }}">
                        @csrf
                        <x-ui.button type="submit" variant="secondary">{{ __('Restore item') }}</x-ui.button>
                    </form>
                @else
                    <form method="POST" action="{{ route('inventory.archive', $item) }}">
                        @csrf
                        @method('DELETE')
                        <x-ui.button type="submit" variant="danger" icon="archive">{{ __('Archive item') }}</x-ui.button>
                    </form>
                @endif
            </div>
        </x-ui.card>
    @endif
</x-layouts.app>
