@php
    use App\Support\Money;

    $money = fn (string $v) => Money::format($v);
    $less = fn (string $v) => Money::format(Money::sub('0', $v)); // a deduction, shown with its minus sign
    $tone = fn (string $v) => str_starts_with($v, '-') ? 'text-danger' : 'text-fg';
    $by = $filters['by'];
    $views = ['day' => __('Day'), 'week' => __('Week'), 'month' => __('Month'), 'year' => __('Year'), 'product' => __('Product'),
        'category' => __('Category'), 'cashier' => __('Cashier'), 'payment_method' => __('Payment method')];
    $isPeriod = in_array($by, ['day', 'week', 'month', 'year'], true);
    $rows = $breakdown['rows'];
    $foot = $breakdown['footer'];
@endphp

<x-layouts.app :title="__('Profit & loss')">
    <p class="mb-4 max-w-2xl text-base text-muted">{{ __('What the bar sold, what it cost, and what is left. Only paid tabs count as sales; stock bought is not a cost until it is sold.') }}</p>

    <form method="GET" action="{{ route('finance.index') }}" class="mb-4 grid grid-cols-2 items-end gap-3 sm:grid-cols-4 lg:flex lg:flex-wrap">
        <x-ui.input name="from" type="date" :label="__('From')" :value="$filters['from']" class="lg:w-48" />
        <x-ui.input name="to" type="date" :label="__('To')" :value="$filters['to']" class="lg:w-48" />
        <x-ui.select name="by" :label="__('Show by')" :options="$views" :selected="$by" class="lg:w-52" />
        <x-ui.button type="submit" variant="secondary" icon="search">{{ __('Show') }}</x-ui.button>
    </form>

    <div class="mb-6 grid gap-4 lg:grid-cols-3">
        {{-- The statement: each figure has one source (ARCHITECTURE §3.6). --}}
        <section aria-labelledby="statement-title" class="rounded-card border border-line bg-surface p-5 lg:col-span-2">
            <h2 id="statement-title" class="sr-only">{{ __('Statement') }}</h2>
            <dl class="text-base tabular">
                @foreach ([
                    [__('Gross sales'), $money($summary['gross_sales']), null],
                    [__('Discounts'), $less($summary['discounts']), null],
                    [__('Refunds'), $less($summary['refunds']), null],
                    [__('Net sales'), $money($summary['net_sales']), 'subtotal'],
                    [__('Cost of goods sold'), $less($summary['cogs']), null],
                    [__('Gross profit'), $money($summary['gross_profit']), 'subtotal'],
                    [__('Operating expenses'), $less($summary['operating_expenses']), null],
                    [__('Inventory losses'), $less($summary['inventory_losses']), null],
                ] as [$label, $value, $kind])
                    <div @class(['flex items-baseline justify-between gap-4 py-1.5',
                        'mt-1 border-t border-line pt-2 font-display text-xl font-semibold' => $kind === 'subtotal'])>
                        <dt @class(['text-fg' => $kind, 'text-muted' => ! $kind])>{{ $label }}</dt>
                        <dd class="{{ $tone($value) }}">{{ $value }}</dd>
                    </div>
                @endforeach
                <div class="mt-2 flex flex-wrap items-baseline justify-between gap-x-4 border-t-2 border-line pt-3">
                    <dt class="font-display text-2xl font-semibold text-fg">{{ __('Net profit') }}</dt>
                    <dd class="font-display text-5xl font-bold {{ $tone($summary['net_profit']) }}">{{ $money($summary['net_profit']) }}</dd>
                </div>
            </dl>
            <p class="mt-3 text-sm text-muted">{{ __('Inventory losses are wasted voids, poured items cancelled before payment, and stock taken out by hand.') }}</p>
        </section>

        <div class="grid content-start gap-4">
            <x-ui.stat-card :label="__('Inventory purchases')" :value="$money($summary['inventory_purchases'])" :hint="__('Stock-in, not in profit')" class="border-dashed" />
            @if ($summary['items_without_cost'] > 0)
                <x-ui.alert variant="warning">
                    {{ trans_choice(':count item sold without a cost|:count items sold without a cost', $summary['items_without_cost']) }}.
                    {{ __('They count as ₱0.00, so cost and margins look better than they are. Add a cost or recipe to those products.') }}
                </x-ui.alert>
            @endif
        </div>
    </div>

    @if ($rows === [])
        <x-ui.empty-state icon="chart" :title="$isPeriod ? __('No sales or costs in this period.') : __('No sales in this period.')">
            {{ __('Try a wider date range.') }}
        </x-ui.empty-state>
    @else
        <x-ui.table :caption="__('By :view', ['view' => mb_strtolower($views[$by])])">
            <x-slot:head>
                <tr>
                    @if ($isPeriod)
                        <th scope="col">{{ $views[$by] }}</th>
                        @foreach ([__('Net sales'), __('COGS'), __('Gross profit'), __('Expenses'), __('Losses'), __('Net profit')] as $heading)
                            <th scope="col" class="text-right">{{ $heading }}</th>
                        @endforeach
                    @elseif (in_array($by, ['product', 'category'], true))
                        <th scope="col">{{ $views[$by] }}</th>
                        @if ($by === 'product')
                            <th scope="col">{{ __('Category') }}</th>
                        @endif
                        @foreach ([__('Sold'), __('Sales'), __('COGS'), __('Gross profit'), __('Margin')] as $heading)
                            <th scope="col" class="text-right">{{ $heading }}</th>
                        @endforeach
                    @elseif ($by === 'cashier')
                        <th scope="col">{{ __('Closed by') }}</th>
                        <th scope="col" class="text-right">{{ __('Tabs') }}</th>
                        <th scope="col" class="text-right">{{ __('Sales') }}</th>
                    @else
                        <th scope="col">{{ __('Method') }}</th>
                        <th scope="col" class="text-right">{{ __('Payments') }}</th>
                        <th scope="col" class="text-right">{{ __('Refunds') }}</th>
                        <th scope="col" class="text-right">{{ __('Net') }}</th>
                    @endif
                </tr>
            </x-slot:head>

            @foreach ($rows as $row)
                <tr class="tabular">
                    @if ($isPeriod)
                        <th scope="row" class="whitespace-nowrap font-normal text-fg">{{ $row['label'] }}</th>
                        @foreach (['net_sales', 'cogs', 'gross_profit', 'operating_expenses', 'inventory_losses', 'net_profit'] as $key)
                            <td class="whitespace-nowrap text-right {{ $tone($row[$key]) }}">{{ $money($row[$key]) }}</td>
                        @endforeach
                    @elseif (in_array($by, ['product', 'category'], true))
                        <th scope="row" class="font-normal text-fg">
                            {{ $row['name'] }}
                            @if ($row['quantity_without_cost'] > 0)
                                <span class="ml-1 rounded bg-warning/15 px-1.5 py-0.5 text-sm text-warning">{{ trans_choice(':count without a cost|:count without a cost', $row['quantity_without_cost']) }}</span>
                            @endif
                        </th>
                        @if ($by === 'product')
                            <td class="text-muted">{{ $row['category'] }}</td>
                        @endif
                        <td class="text-right">{{ $row['quantity'] }}</td>
                        <td class="whitespace-nowrap text-right">{{ $money($row['sales']) }}</td>
                        <td class="whitespace-nowrap text-right">{{ $money($row['cogs']) }}</td>
                        <td class="whitespace-nowrap text-right {{ $tone($row['gross_profit']) }}">{{ $money($row['gross_profit']) }}</td>
                        <td class="whitespace-nowrap text-right">{{ $row['margin'] === null ? '–' : $row['margin'].'%' }}</td>
                    @elseif ($by === 'cashier')
                        <th scope="row" class="font-normal text-fg">{{ $row['name'] }}</th>
                        <td class="text-right">{{ $row['tabs'] }}</td>
                        <td class="whitespace-nowrap text-right">{{ $money($row['sales']) }}</td>
                    @else
                        <th scope="row" class="font-normal text-fg">{{ $row['name'] }}</th>
                        <td class="whitespace-nowrap text-right">{{ $money($row['payments']) }}</td>
                        <td class="whitespace-nowrap text-right">{{ $less($row['refunds']) }}</td>
                        <td class="whitespace-nowrap text-right {{ $tone($row['net']) }}">{{ $money($row['net']) }}</td>
                    @endif
                </tr>
            @endforeach

            {{-- Footer rows: they take the table to the same net sales as the statement. --}}
            @php
                $span = match (true) { $by === 'product' => 3, $by === 'category' => 2, default => 1 };
                $footRows = match (true) {
                    $isPeriod => [[__('Total'), array_map($money, $foot)]],
                    in_array($by, ['product', 'category'], true) => [
                        [__('Total'), ['sales' => $money($foot['sales']), 'cogs' => $money($foot['cogs']), 'gross_profit' => '', 'margin' => '']],
                        [__('Bill discounts'), ['sales' => $less($foot['bill_discounts']), 'cogs' => '', 'gross_profit' => '', 'margin' => '']],
                        [__('Refunds'), ['sales' => $less($foot['refunds']), 'cogs' => '', 'gross_profit' => '', 'margin' => '']],
                        [__('Net sales'), ['sales' => $money($foot['net_sales']), 'cogs' => $money($foot['cogs']), 'gross_profit' => $money($foot['gross_profit']), 'margin' => '']],
                    ],
                    $by === 'cashier' => [
                        [__('Total'), ['tabs' => '', 'sales' => $money($foot['sales'])]],
                        [__('Refunds'), ['tabs' => '', 'sales' => $less($foot['refunds'])]],
                        [__('Net sales'), ['tabs' => '', 'sales' => $money($foot['net_sales'])]],
                    ],
                    default => [[__('Net sales'), ['payments' => $money($foot['payments']), 'refunds' => $less($foot['refunds']), 'net' => $money($foot['net_sales'])]]],
                };
            @endphp
            @foreach ($footRows as $i => [$label, $cells])
                <tr @class(['tabular bg-raised/40', 'border-t-2' => $i === 0, 'font-semibold' => $loop->last])>
                    <th scope="row" colspan="{{ $span }}" class="text-left text-fg">{{ $label }}</th>
                    @foreach ($cells as $cell)
                        <td class="whitespace-nowrap text-right {{ $cell === '' ? '' : $tone($cell) }}">{{ $cell }}</td>
                    @endforeach
                </tr>
            @endforeach
        </x-ui.table>
    @endif
</x-layouts.app>
