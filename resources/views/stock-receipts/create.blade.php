@php
    $blank = ['inventory_item_id' => '', 'inventory_unit_id' => '', 'quantity' => '', 'line_cost' => ''];
    $lines = array_values(old('lines', [$blank]));
    $field = 'block min-h-touch w-full rounded-control border bg-raised px-3 text-base text-fg focus:border-accent focus:ring-accent';
@endphp

<x-layouts.app :title="__('New stock-in')">
    <form method="POST" action="{{ route('stock-receipts.store') }}" class="space-y-4"
          x-data="stockIn(@js(['items' => $items, 'lines' => $lines, 'blank' => $blank, 'errors' => $errors->getMessages()]))" x-on:submit="sending = true">
        @csrf

        {{-- One summary at the top, linked to the fields; each field keeps its own message below. --}}
        @if ($errors->any())
            <div role="alert" tabindex="-1" x-init="$el.focus()" aria-labelledby="stock-in-errors" class="rounded-card border border-danger/40 bg-danger/10 p-4 text-fg">
                <h2 id="stock-in-errors" class="font-display text-lg font-semibold">{{ __('Check the stock-in') }}</h2>
                <ul class="mt-2 list-inside list-disc text-base">
                    @foreach ($errors->getMessages() as $key => $messages)
                        <li><a href="#{{ str_replace('.', '-', $key) }}" class="inline-flex min-h-touch items-center underline-offset-4 hover:underline">{{ $messages[0] }}</a></li>
                    @endforeach
                </ul>
            </div>
        @endif

        <x-ui.card :title="__('Delivery')">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <x-ui.select name="supplier_id" id="supplier_id" :label="__('Supplier')" :options="$suppliers" :placeholder="__('No supplier / bought at a store')" class="sm:col-span-2" />
                <x-ui.input name="invoice_no" id="invoice_no" :label="__('Invoice or reference no.')" maxlength="60" />
                <x-ui.input name="received_at" id="received_at" type="datetime-local" :label="__('Received')" :value="now()->format('Y-m-d\TH:i')" required />
            </div>
        </x-ui.card>

        <x-ui.card :title="__('Items received')">
            <ol class="space-y-3">
                <template x-for="(line, i) in lines" :key="line.key">
                    <li class="rounded-control border border-line p-3">
                        <div class="grid grid-cols-2 gap-3 sm:grid-cols-[minmax(0,2fr)_minmax(0,1fr)_minmax(0,1fr)_minmax(0,1fr)_auto] sm:items-start">
                            <div class="col-span-2 sm:col-span-1">
                                <label x-bind:for="`lines-${i}-inventory_item_id`" class="mb-1 block text-sm font-medium text-muted">{{ __('Item') }}</label>
                                <select x-bind:id="`lines-${i}-inventory_item_id`" x-bind:name="`lines[${i}][inventory_item_id]`" x-model="line.inventory_item_id" x-on:change="line.inventory_unit_id = ''" required
                                        class="{{ $field }}" x-bind:class="error(i, 'inventory_item_id') ? 'border-danger' : 'border-edge'">
                                    <option value="">{{ __('Choose…') }}</option>
                                    <template x-for="item in items" :key="item.id">
                                        <option x-bind:value="item.id" x-text="item.name" x-bind:selected="String(item.id) === String(line.inventory_item_id)"></option>
                                    </template>
                                </select>
                                <p class="mt-1 text-sm font-medium text-danger" x-show="error(i, 'inventory_item_id')" x-text="error(i, 'inventory_item_id')"></p>
                            </div>
                            <div>
                                <label x-bind:for="`lines-${i}-inventory_unit_id`" class="mb-1 block text-sm font-medium text-muted">{{ __('Unit') }}</label>
                                <select x-bind:id="`lines-${i}-inventory_unit_id`" x-bind:name="`lines[${i}][inventory_unit_id]`" x-model="line.inventory_unit_id"
                                        class="{{ $field }}" x-bind:class="error(i, 'inventory_unit_id') ? 'border-danger' : 'border-edge'">
                                    <option value="" x-text="itemOf(line)?.base_unit ?? '—'"></option>
                                    <template x-for="unit in itemOf(line)?.units ?? []" :key="unit.id">
                                        <option x-bind:value="unit.id" x-text="`${unit.name} (${number(unit.factor)})`" x-bind:selected="String(unit.id) === String(line.inventory_unit_id)"></option>
                                    </template>
                                </select>
                                <p class="mt-1 text-sm font-medium text-danger" x-show="error(i, 'inventory_unit_id')" x-text="error(i, 'inventory_unit_id')"></p>
                            </div>
                            <div>
                                <label x-bind:for="`lines-${i}-quantity`" class="mb-1 block text-sm font-medium text-muted">{{ __('Quantity') }}</label>
                                <input x-bind:id="`lines-${i}-quantity`" type="text" inputmode="decimal" x-bind:name="`lines[${i}][quantity]`" x-model="line.quantity" required autocomplete="off"
                                       class="{{ $field }} text-right tabular" x-bind:class="error(i, 'quantity') ? 'border-danger' : 'border-edge'">
                                <p class="mt-1 text-sm font-medium text-danger" x-show="error(i, 'quantity')" x-text="error(i, 'quantity')"></p>
                            </div>
                            <div>
                                <label x-bind:for="`lines-${i}-line_cost`" class="mb-1 block text-sm font-medium text-muted">{{ __('Line cost (₱)') }}</label>
                                <input x-bind:id="`lines-${i}-line_cost`" type="text" inputmode="decimal" x-bind:name="`lines[${i}][line_cost]`" x-model="line.line_cost" required autocomplete="off"
                                       class="{{ $field }} text-right tabular" x-bind:class="error(i, 'line_cost') ? 'border-danger' : 'border-edge'">
                                <p class="mt-1 text-sm font-medium text-danger" x-show="error(i, 'line_cost')" x-text="error(i, 'line_cost')"></p>
                            </div>
                            <div class="col-span-2 flex items-end justify-between gap-2 sm:col-span-1 sm:pt-6">
                                <p class="text-sm text-muted sm:hidden" x-text="preview(line)"></p>
                                <button type="button" x-on:click="remove(i)" x-bind:disabled="lines.length === 1"
                                        class="inline-flex size-touch shrink-0 items-center justify-center rounded-control text-muted hover:bg-raised hover:text-danger disabled:opacity-40"
                                        x-bind:aria-label="`{{ __('Remove line') }} ${i + 1}`"><x-ui.icon name="trash" /></button>
                            </div>
                        </div>
                        <p class="mt-2 hidden text-sm text-muted sm:block" x-text="preview(line)"></p>
                    </li>
                </template>
            </ol>
            <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
                <x-ui.button variant="secondary" icon="plus" x-on:click="add()">{{ __('Add line') }}</x-ui.button>
                <p class="text-base text-muted">{{ __('Total') }} <span class="font-display text-2xl font-bold text-fg tabular" x-text="total()"></span></p>
            </div>
        </x-ui.card>

        <x-ui.card>
            <x-ui.textarea name="notes" id="notes" :label="__('Notes (optional)')" rows="2" maxlength="500" />
        </x-ui.card>

        <div class="flex flex-wrap gap-3">
            <x-ui.button type="submit" size="lg" x-bind:disabled="sending">
                <span x-show="! sending">{{ __('Record stock-in') }}</span>
                <span x-cloak x-show="sending">{{ __('Recording…') }}</span>
            </x-ui.button>
            <x-ui.button :href="route('stock-receipts.index')" variant="ghost" size="lg">{{ __('Cancel') }}</x-ui.button>
        </div>
    </form>
</x-layouts.app>
