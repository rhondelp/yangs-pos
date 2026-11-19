@php
    use App\Support\Money;
    use Carbon\CarbonImmutable;
    $day = CarbonImmutable::parse($date);
@endphp

<x-layouts.app :title="__('Transactions')">
    <form method="GET" action="{{ route('transactions.index') }}" class="mb-4 grid grid-cols-2 items-end gap-3 sm:grid-cols-3 lg:flex lg:flex-wrap">
        <x-ui.input name="date" type="date" :label="__('Business day')" :value="$date" class="lg:w-44" />
        <x-ui.select name="table_id" :label="__('Table')" :options="$tables" :selected="$filters['table_id']" :placeholder="__('All tables')" class="lg:w-40" />
        @if ($cashiers !== null)
            <x-ui.select name="cashier_id" :label="__('Cashier')" :options="$cashiers" :selected="$filters['cashier_id']" :placeholder="__('Everyone')" class="lg:w-44" />
        @endif
        <x-ui.select name="payment_status" :label="__('Payment')" :selected="$filters['payment_status']" :placeholder="__('Any')" class="lg:w-48"
                     :options="['paid' => __('Paid'), 'partially_refunded' => __('Partly refunded'), 'refunded' => __('Refunded')]" />
        <x-ui.select name="method_id" :label="__('Paid by')" :options="$methods" :selected="$filters['method_id']" :placeholder="__('Any method')" class="lg:w-40" />
        <x-ui.button type="submit" variant="secondary" icon="search">{{ __('Show') }}</x-ui.button>
    </form>

    @if ($sessions->isEmpty())
        <x-ui.empty-state icon="receipt" :title="__('No paid tabs on :day.', ['day' => $day->format('M j, Y')])">
            {{ __('Paid tabs appear here once their bill is closed.') }}
        </x-ui.empty-state>
    @else
        <x-ui.table>
            <x-slot:head>
                <tr>
                    <th scope="col">{{ __('Receipt') }}</th>
                    <th scope="col">{{ __('Table') }}</th>
                    <th scope="col">{{ __('Paid at') }}</th>
                    <th scope="col">{{ __('Cashier') }}</th>
                    <th scope="col" class="text-right">{{ __('Total') }}</th>
                    <th scope="col" class="text-right">{{ __('Refunded') }}</th>
                    <th scope="col"><span class="sr-only">{{ __('Actions') }}</span></th>
                </tr>
            </x-slot:head>
            @foreach ($sessions as $session)
                <tr>
                    <td class="text-base text-fg tabular">{{ $session->receipt_number }}</td>
                    <td class="font-display text-lg font-semibold">{{ $session->table->label }}</td>
                    <td class="text-muted tabular">{{ $session->closed_at->timezone(config('app.timezone'))->format('g:i A') }}</td>
                    <td class="text-muted">{{ $session->closedBy?->name ?? '—' }}</td>
                    <td class="text-right tabular">{{ Money::format($session->net_total) }}</td>
                    <td class="text-right tabular {{ $session->refunds_sum_amount ? 'text-warning' : 'text-muted' }}">
                        {{ $session->refunds_sum_amount ? '-'.Money::format(Money::round((string) $session->refunds_sum_amount)) : '—' }}
                    </td>
                    <td class="text-right">
                        <x-ui.button :href="route('transactions.show', $session)" variant="ghost">{{ __('Open') }}<span class="sr-only"> {{ $session->receipt_number }}</span></x-ui.button>
                    </td>
                </tr>
            @endforeach
        </x-ui.table>
    @endif
</x-layouts.app>
