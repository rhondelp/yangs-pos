@php
    $variantRows = collect(old('variants', $product->exists
            ? $product->variants->map(fn ($v) => ['id' => $v->id, 'name' => $v->name, 'price' => $v->price, 'cost' => $v->cost])->all()
            : []))
        ->values()
        ->map(fn ($row, $i) => ['key' => $i, 'id' => $row['id'] ?? null, 'name' => $row['name'] ?? '', 'price' => $row['price'] ?? '', 'cost' => $row['cost'] ?? ''])
        ->all();
    $variantErrors = collect($errors->getMessages())->filter(fn ($m, $key) => str_starts_with($key, 'variants'))->flatten()->unique();
    $checkbox = 'size-6 rounded border-edge bg-raised text-accent focus:ring-accent';
@endphp

@csrf
<input type="hidden" name="variants_sent" value="1">

<div class="grid gap-6 xl:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
    <div class="space-y-6">
        <x-ui.card :title="__('Details')">
            <div class="grid gap-5 sm:grid-cols-2">
                <x-ui.input name="name" :label="__('Name')" :value="$product->name" required autofocus class="sm:col-span-2" />
                <x-ui.select name="category_id" :label="__('Category')" :placeholder="__('Choose a category')" required
                             :options="$categories->pluck('name', 'id')->all()" :selected="$product->category_id" />
                <x-ui.select name="type" :label="__('Type')" required
                             :options="collect($types)->mapWithKeys(fn ($t) => [$t->value => $t->label()])->all()" :selected="$product->type?->value" />
                <x-ui.textarea name="description" :label="__('Description (optional)')" :value="$product->description" rows="2" class="sm:col-span-2" />
            </div>
        </x-ui.card>

        <x-ui.card :title="__('Price')">
            <div class="grid gap-5 sm:grid-cols-2">
                <x-ui.input name="price" type="number" step="0.01" min="0" inputmode="decimal" :label="__('Selling price (₱)')" :value="$product->price" required />
                <x-ui.input name="cost" type="number" step="0.01" min="0" inputmode="decimal" :label="__('Cost per unit (₱, optional)')" :value="$product->cost"
                            :hint="__('What one unit costs you. Leave blank if you do not know yet.')" />
            </div>
            @if ($product->exists)
                <p class="mt-4 text-sm text-muted">{{ __('Price changes apply to new orders only. Past sales keep the price they were sold at.') }}</p>
            @endif
        </x-ui.card>

        <x-ui.card :title="__('Options')">
            <p class="mb-4 text-base text-muted">{{ __('Other ways to sell this product, each with its own price, such as a bucket of 6. Leave empty if it is sold one way only.') }}</p>
            @if ($variantErrors->isNotEmpty())
                <x-ui.alert variant="error" class="mb-4">
                    <ul class="list-inside list-disc">@foreach ($variantErrors as $message)<li>{{ $message }}</li>@endforeach</ul>
                </x-ui.alert>
            @endif

            <div x-data="{ rows: @js($variantRows), next: {{ count($variantRows) }} }" class="space-y-3">
                <template x-for="(row, i) in rows" :key="row.key">
                    <div class="grid gap-3 rounded-control border border-line bg-bg/40 p-3 sm:grid-cols-[minmax(0,2fr)_minmax(0,1fr)_minmax(0,1fr)_auto] sm:items-end">
                        <input type="hidden" :name="`variants[${i}][id]`" :value="row.id ?? ''">
                        <div>
                            <label :for="`variant-${row.key}-name`" class="mb-1.5 block text-sm font-medium text-fg">{{ __('Option name') }}</label>
                            <input :id="`variant-${row.key}-name`" :name="`variants[${i}][name]`" x-model="row.name" type="text" required maxlength="80"
                                   class="block min-h-touch w-full rounded-control border border-edge bg-raised px-3 text-base text-fg focus:border-accent focus:ring-accent">
                        </div>
                        <div>
                            <label :for="`variant-${row.key}-price`" class="mb-1.5 block text-sm font-medium text-fg">{{ __('Price (₱)') }}</label>
                            <input :id="`variant-${row.key}-price`" :name="`variants[${i}][price]`" x-model="row.price" type="number" step="0.01" min="0" inputmode="decimal" required
                                   class="block min-h-touch w-full rounded-control border border-edge bg-raised px-3 text-base text-fg focus:border-accent focus:ring-accent">
                        </div>
                        <div>
                            <label :for="`variant-${row.key}-cost`" class="mb-1.5 block text-sm font-medium text-fg">{{ __('Cost (₱)') }}</label>
                            <input :id="`variant-${row.key}-cost`" :name="`variants[${i}][cost]`" x-model="row.cost" type="number" step="0.01" min="0" inputmode="decimal"
                                   class="block min-h-touch w-full rounded-control border border-edge bg-raised px-3 text-base text-fg focus:border-accent focus:ring-accent">
                        </div>
                        <button type="button" x-on:click="rows.splice(i, 1)"
                                class="inline-flex size-touch items-center justify-center rounded-control text-muted hover:bg-raised hover:text-danger"
                                :aria-label="`{{ __('Remove option') }} ${row.name || (i + 1)}`">
                            <x-ui.icon name="trash" />
                        </button>
                    </div>
                </template>
                <x-ui.button variant="secondary" icon="plus" x-on:click="rows.push({ key: next++, id: null, name: '', price: '', cost: '' })">{{ __('Add option') }}</x-ui.button>
            </div>
        </x-ui.card>
    </div>

    <div class="space-y-6">
        <x-ui.card :title="__('Photo')">
            @if ($product->imageUrl())
                <img src="{{ $product->imageUrl() }}" alt="{{ __('Current photo of :name', ['name' => $product->name]) }}" class="mb-4 aspect-square w-full max-w-60 rounded-card object-cover">
                <label for="remove_image" class="mb-4 flex min-h-touch items-center gap-3">
                    <input id="remove_image" name="remove_image" type="checkbox" value="1" class="{{ $checkbox }}">
                    <span class="text-base text-fg">{{ __('Remove this photo') }}</span>
                </label>
            @endif
            <x-ui.input name="image" type="file" accept="image/jpeg,image/png,image/webp" :label="$product->imageUrl() ? __('Replace photo') : __('Upload a photo')"
                        :hint="__('JPG, PNG or WebP, up to 2 MB.')" class="[&_input]:py-2.5" />
        </x-ui.card>

        <x-ui.card :title="__('On the menu')">
            <div class="space-y-2">
                <input type="hidden" name="is_available" value="0">
                <label for="is_available" class="flex min-h-touch items-center gap-3">
                    <input id="is_available" name="is_available" type="checkbox" value="1" class="{{ $checkbox }}" @checked(old('is_available', $product->is_available))>
                    <span class="text-base text-fg">{{ __('Available to sell') }}</span>
                </label>
                <input type="hidden" name="track_inventory" value="0">
                <label for="track_inventory" class="flex min-h-touch items-start gap-3 pt-3">
                    <input id="track_inventory" name="track_inventory" type="checkbox" value="1" class="{{ $checkbox }} mt-0.5" @checked(old('track_inventory', $product->track_inventory))>
                    <span>
                        <span class="block text-base text-fg">{{ __('Track stock for this product') }}</span>
                        <span class="block text-sm text-muted">{{ __('Stock is deducted when sold once inventory is set up.') }}</span>
                    </span>
                </label>
            </div>
        </x-ui.card>

        <div class="flex flex-wrap gap-3">
            <x-ui.button type="submit" size="lg">{{ $product->exists ? __('Save product') : __('Add product') }}</x-ui.button>
            <x-ui.button :href="route('products.index')" variant="ghost" size="lg">{{ __('Cancel') }}</x-ui.button>
        </div>
    </div>
</div>
