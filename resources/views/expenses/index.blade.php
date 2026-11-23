@php
    use App\Support\Money;
@endphp

<x-layouts.app :title="__('Expenses')">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <p class="max-w-2xl text-base text-muted">{{ __('Running costs of the bar. Stock bought for sale is recorded in Stock-in, not here.') }}</p>
        @can('expenses.manage')
            <x-ui.button :href="route('expenses.create')" icon="plus">{{ __('Record expense') }}</x-ui.button>
        @endcan
    </div>

    <form method="GET" action="{{ route('expenses.index') }}" class="mb-4 grid grid-cols-2 items-end gap-3 sm:grid-cols-3 lg:flex lg:flex-wrap">
        <x-ui.input name="from" type="date" :label="__('From')" :value="$filters['from']" class="lg:w-48" />
        <x-ui.input name="to" type="date" :label="__('To')" :value="$filters['to']" class="lg:w-48" />
        <x-ui.select name="category_id" :label="__('Category')" :options="$categories" :selected="$filters['category_id']" :placeholder="__('All categories')" class="col-span-2 sm:col-span-1 lg:w-52" />
        <label for="voided" class="flex min-h-touch items-center gap-3 text-base text-fg">
            <input id="voided" name="voided" type="checkbox" value="1" class="size-6 rounded border-edge bg-raised text-accent focus:ring-accent" @checked($filters['voided'])>
            {{ __('Show voided') }}
        </label>
        <x-ui.button type="submit" variant="secondary" icon="search">{{ __('Show') }}</x-ui.button>
    </form>

    {{-- Two separate figures: stock purchases are inventory, never operating expenses (ARCHITECTURE §3.6). --}}
    <div class="mb-4 grid gap-4 sm:grid-cols-2">
        <x-ui.stat-card :label="__('Expenses')" :value="Money::format($totals['expenses'])" :hint="$filters['category_id'] ? __(':category only, voided excluded', ['category' => $categories[$filters['category_id']] ?? '']) : __('Voided expenses excluded')" />
        <x-ui.stat-card :label="__('Inventory purchases')" :value="Money::format($totals['inventory_purchases'])" :hint="__('Stock-in, not an expense')" class="border-dashed" />
    </div>

    @if ($expenses->isEmpty())
        <x-ui.empty-state icon="banknotes" :title="__('No expenses in this period.')">
            {{ __('Try a wider date range or another category.') }}
        </x-ui.empty-state>
    @else
        <x-ui.table :caption="__('Expenses')">
            <x-slot:head>
                <tr>
                    <th scope="col">{{ __('Date') }}</th>
                    <th scope="col">{{ __('Category') }}</th>
                    <th scope="col">{{ __('Description') }}</th>
                    <th scope="col" class="text-right">{{ __('Amount') }}</th>
                    <th scope="col">{{ __('Recorded by') }}</th>
                    <th scope="col"><span class="sr-only">{{ __('Actions') }}</span></th>
                </tr>
            </x-slot:head>
            @foreach ($expenses as $expense)
                <tr @class(['text-muted' => $expense->isVoided()])>
                    <td class="whitespace-nowrap tabular">{{ $expense->expense_date->format('M j, Y') }}</td>
                    <td>{{ $expense->category->name }}</td>
                    <td>
                        <span @class(['text-base', 'text-fg' => ! $expense->isVoided(), 'line-through' => $expense->isVoided()])>{{ $expense->description }}</span>
                        @if ($expense->attachment_path)
                            <x-ui.icon name="receipt" class="ml-1 inline size-4 text-muted" />
                            <span class="sr-only">{{ __('Has a receipt') }}</span>
                        @endif
                        @if ($expense->isVoided())
                            <x-ui.status-badge status="voided" class="ml-1" />
                        @endif
                    </td>
                    <td @class(['whitespace-nowrap text-right tabular', 'line-through' => $expense->isVoided()])>{{ Money::format($expense->amount) }}</td>
                    <td class="text-muted">{{ $expense->recordedBy?->name ?? '—' }}</td>
                    <td class="text-right">
                        <x-ui.button :href="route('expenses.show', $expense)" variant="ghost">{{ __('Open') }}<span class="sr-only"> {{ $expense->description }}</span></x-ui.button>
                    </td>
                </tr>
            @endforeach
        </x-ui.table>
        {{ $expenses->links() }}
    @endif
</x-layouts.app>
