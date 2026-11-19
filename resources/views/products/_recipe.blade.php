{{-- What one sale takes from stock, per way the product is sold. Deduction on sale comes in a later Phase 3 step. --}}
@php
    use App\Support\Quantity;
    $canEdit = auth()->user()->can('inventory.manage');
    $forms = collect([['id' => '', 'name' => __('Sold as :name', ['name' => $product->name])]])
        ->concat($product->variants->map(fn ($v) => ['id' => (string) $v->id, 'name' => $v->name]));
    $itemOptions = $inventoryItems->mapWithKeys(fn ($i) => [$i->id => "{$i->name} ({$i->base_unit})"])->all();
    $field = 'block min-h-touch w-full rounded-control border border-edge bg-raised px-3 text-base text-fg focus:border-accent focus:ring-accent';
@endphp

<x-ui.card :title="__('Recipe')">
    <p class="mb-4 max-w-2xl text-base text-muted">
        {{ __('What one sale takes from stock. A bucket of 6 uses 6 bottles; a cocktail lists each ingredient. Changes apply to future sales only.') }}
    </p>

    @foreach ($errors->getMessages() as $key => $messages)
        @if (str_starts_with($key, 'components') || $key === 'product_variant_id')
            <x-ui.alert variant="error" class="mb-4">{{ $messages[0] }}</x-ui.alert>
            @break
        @endif
    @endforeach

    <div class="grid gap-4 lg:grid-cols-2">
        @foreach ($forms as $form)
            @php($current = $recipes->get($form['id'], collect()))
            <section class="rounded-control border border-line p-4" aria-labelledby="recipe-{{ $form['id'] ?: 'base' }}">
                <h3 id="recipe-{{ $form['id'] ?: 'base' }}" class="font-display text-lg font-semibold text-fg">{{ $form['name'] }}</h3>
                @if ($current->isEmpty())
                    <p class="mt-1 text-base text-muted">{{ __('No recipe: stock is not deducted, and cost comes from the product.') }}</p>
                @else
                    <ul class="mt-1 text-base text-fg">
                        @foreach ($current as $component)
                            <li>{{ Quantity::format($component->quantity) }} {{ $component->item->base_unit }} {{ $component->item->name }}</li>
                        @endforeach
                    </ul>
                @endif

                @if ($canEdit)
                    <form method="POST" action="{{ route('products.recipe', $product) }}" class="mt-3 space-y-2"
                          x-data="recipeEditor(@js(['rows' => $current->map(fn ($c) => ['inventory_item_id' => (string) $c->inventory_item_id, 'quantity' => Quantity::format($c->quantity)])->values()->all(), 'blank' => ['inventory_item_id' => '', 'quantity' => '']]))">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="product_variant_id" value="{{ $form['id'] }}">
                        <template x-for="(row, i) in rows" :key="row.key">
                            <div class="flex items-end gap-2">
                                <div class="min-w-0 flex-1">
                                    <label x-bind:for="`recipe-{{ $form['id'] ?: 'base' }}-item-${i}`" class="sr-only">{{ __('Inventory item') }}</label>
                                    <select x-bind:id="`recipe-{{ $form['id'] ?: 'base' }}-item-${i}`" x-bind:name="`components[${i}][inventory_item_id]`" x-model="row.inventory_item_id" class="{{ $field }}">
                                        <option value="">{{ __('Choose an item…') }}</option>
                                        @foreach ($itemOptions as $id => $label)
                                            <option value="{{ $id }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="w-28">
                                    <label x-bind:for="`recipe-{{ $form['id'] ?: 'base' }}-qty-${i}`" class="sr-only">{{ __('Quantity per sale') }}</label>
                                    <input x-bind:id="`recipe-{{ $form['id'] ?: 'base' }}-qty-${i}`" type="text" inputmode="decimal" x-bind:name="`components[${i}][quantity]`" x-model="row.quantity" placeholder="1"
                                           class="{{ $field }} text-right tabular">
                                </div>
                                <button type="button" x-on:click="remove(i)" class="inline-flex size-touch shrink-0 items-center justify-center rounded-control text-muted hover:bg-raised hover:text-danger" aria-label="{{ __('Remove ingredient') }}">
                                    <x-ui.icon name="trash" />
                                </button>
                            </div>
                        </template>
                        <div class="flex flex-wrap gap-2 pt-1">
                            <x-ui.button variant="secondary" icon="plus" x-on:click="add()">{{ __('Add ingredient') }}</x-ui.button>
                            <x-ui.button type="submit">{{ __('Save recipe') }}</x-ui.button>
                        </div>
                    </form>
                @endif
            </section>
        @endforeach
    </div>
</x-ui.card>
