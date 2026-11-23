<x-layouts.app :title="__('Record expense')">
    <div class="mb-4">
        <x-ui.button :href="route('expenses.index')" variant="ghost" icon="chevron-left">{{ __('Expenses') }}</x-ui.button>
    </div>

    <x-ui.card :title="__('New expense')" class="max-w-3xl">
        <p class="mb-4 text-base text-muted">{{ __('Bought stock to sell? Record it in Stock-in instead, so it counts as inventory.') }}</p>
        <form method="POST" action="{{ route('expenses.store') }}" enctype="multipart/form-data" class="grid gap-4 sm:grid-cols-2"
              x-data="{ sending: false }" x-on:submit="sending = true">
            @csrf
            <x-ui.select name="expense_category_id" :label="__('Category')" :options="$categories" :selected="$prefill['expense_category_id']" :placeholder="__('Choose…')" required />
            <x-ui.input name="expense_date" type="date" :label="__('Date')" :value="$prefill['expense_date']" required />
            <x-ui.input name="description" :label="__('Description')" :value="$prefill['description']" maxlength="200" required class="sm:col-span-2" :hint="__('For example: Meralco bill for November')" />
            <x-ui.input name="amount" inputmode="decimal" :label="__('Amount (₱)')" :value="$prefill['amount']" required />
            <x-ui.input name="attachment" type="file" accept="image/*,application/pdf" :label="__('Receipt (optional)')"
                        :hint="__('Photo or PDF, up to 5 MB.')" class="[&_input]:py-2.5" />
            <div class="sm:col-span-2">
                <x-ui.button type="submit" size="lg" x-bind:disabled="sending">
                    <span x-show="! sending">{{ __('Record expense') }}</span>
                    <span x-cloak x-show="sending">{{ __('Recording…') }}</span>
                </x-ui.button>
            </div>
        </form>
    </x-ui.card>
</x-layouts.app>
