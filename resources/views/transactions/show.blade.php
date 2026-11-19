@php
    use App\Support\Money;
    use Illuminate\Support\Str;
    $net = Money::sub($session->net_total, $bill['refunded']);
    $methodOptions = $methods->pluck('name', 'id')->all();
    // Reopen the dialog a failed refund or correction came from (value is checked, never echoed raw).
    $reopen = $errors->any() && preg_match('/^(refund|correct)-\d+$/', (string) old('dialog')) ? old('dialog') : null;
@endphp

<x-layouts.app :title="__('Receipt :number', ['number' => $session->receipt_number])">
    <div class="mb-4 flex flex-wrap items-center gap-3">
        <x-ui.button :href="route('transactions.index', ['date' => $session->business_date->toDateString()])" variant="ghost" icon="chevron-left">{{ __('Transactions') }}</x-ui.button>
        @if ($canReceipt)
            <x-ui.button :href="route('receipts.show', $session)" variant="secondary" icon="printer">{{ __('Receipt') }}</x-ui.button>
        @endif
    </div>

    <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
        <x-ui.card :title="__('Table :label', ['label' => $session->table->label])">
            <p class="mb-4 text-base text-muted">
                {{ __('Opened :at by :name.', ['at' => $session->opened_at->timezone(config('app.timezone'))->format('M j, g:i A'), 'name' => $session->openedBy?->name ?? '—']) }}
                {{ __('Paid :at, closed by :name.', ['at' => $session->closed_at->timezone(config('app.timezone'))->format('g:i A'), 'name' => $session->closedBy?->name ?? '—']) }}
            </p>

            <ul class="divide-y divide-line">
                @foreach ($bill['items'] as $item)
                    <li class="flex justify-between gap-3 py-2 text-base">
                        <span>{{ $item['quantity'] }} × {{ $item['name'] }}</span>
                        <span class="tabular">{{ Money::format($item['line_total']) }}</span>
                    </li>
                @endforeach
            </ul>

            @if ($bill['voids'])
                <h3 class="mb-1 mt-4 text-sm font-medium text-muted">{{ __('Voided') }}</h3>
                <ul class="space-y-1">
                    @foreach ($bill['voids'] as $void)
                        <li class="flex justify-between gap-3 text-base text-muted">
                            <span><span class="line-through">{{ $void['quantity'] }} × {{ $void['product_name'] }}</span> <span class="text-sm">{{ $void['reason'] }}</span></span>
                            <span class="tabular">-{{ Money::format($void['amount']) }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif

            <dl class="mt-4 space-y-1 border-t border-line pt-3 text-base">
                <div class="flex justify-between"><dt class="text-muted">{{ __('Items total') }}</dt><dd class="tabular">{{ Money::format($session->gross_total) }}</dd></div>
                @if (bccomp($session->discount_total, '0', 2) > 0)
                    <div class="flex justify-between"><dt class="text-muted">{{ __('Discounts') }}</dt><dd class="tabular">-{{ Money::format($session->discount_total) }}</dd></div>
                @endif
                <div class="flex justify-between"><dt class="font-semibold">{{ __('Total') }}</dt><dd class="tabular font-semibold">{{ Money::format($session->net_total) }}</dd></div>
                @if (bccomp($bill['refunded'], '0', 2) > 0)
                    <div class="flex justify-between text-warning"><dt>{{ __('Refunded') }}</dt><dd class="tabular">-{{ Money::format($bill['refunded']) }}</dd></div>
                @endif
                <div class="flex items-baseline justify-between pt-1"><dt class="font-semibold">{{ __('Net') }}</dt><dd class="font-display text-2xl font-bold text-accent tabular">{{ Money::format($net) }}</dd></div>
            </dl>
        </x-ui.card>

        <x-ui.card :title="__('Payments')">
            <ul class="space-y-4">
                @foreach ($bill['payments'] as $payment)
                    @php($left = Money::sub($payment['amount'], $payment['refunded']))
                    <li class="rounded-control border border-line p-3">
                        <div class="flex flex-wrap items-baseline justify-between gap-2">
                            <span class="text-base text-fg">
                                {{ $payment['method'] }}
                                @if ($payment['reference_no'])<span class="text-sm text-muted">#{{ $payment['reference_no'] }}</span>@endif
                                @if ($payment['corrected'])<span class="ml-1 rounded-full bg-info/15 px-2 py-0.5 text-sm text-info">{{ __('Corrected') }}</span>@endif
                            </span>
                            <span class="font-display text-xl font-semibold tabular">{{ Money::format($payment['amount']) }}</span>
                        </div>
                        @foreach ($payment['refunds'] as $refund)
                            <p class="mt-1 flex justify-between gap-3 text-base text-warning">
                                <span>{{ __('Refund') }}, {{ $refund['method'] }}@if ($refund['reference_no']) #{{ $refund['reference_no'] }}@endif: <span class="text-muted">{{ $refund['reason'] }}</span></span>
                                <span class="tabular">-{{ Money::format($refund['amount']) }}</span>
                            </p>
                        @endforeach

                        @if (($canRefund && bccomp($left, '0', 2) > 0) || $canCorrect)
                            <div class="mt-3 flex flex-wrap gap-2">
                                @if ($canRefund && bccomp($left, '0', 2) > 0)
                                    <x-ui.button variant="secondary" x-data x-on:click="document.getElementById('refund-{{ $payment['id'] }}').showModal()">{{ __('Refund') }}</x-ui.button>
                                @endif
                                @if ($canCorrect)
                                    <x-ui.button variant="ghost" x-data x-on:click="document.getElementById('correct-{{ $payment['id'] }}').showModal()">{{ __('Correct method') }}</x-ui.button>
                                @endif
                            </div>
                        @endif
                    </li>

                    @if ($canRefund && bccomp($left, '0', 2) > 0)
                        <x-ui.modal :id="'refund-'.$payment['id']" :title="__('Refund :method payment', ['method' => $payment['method']])">
                            <form method="POST" action="{{ route('transactions.refunds', $payment['id']) }}" class="space-y-4" x-data="{ sending: false }" x-on:submit="sending = true">
                                @csrf
                                <input type="hidden" name="idempotency_key" value="{{ Str::uuid() }}">
                                <input type="hidden" name="dialog" value="refund-{{ $payment['id'] }}">
                                @if ($reopen === 'refund-'.$payment['id'])
                                    <x-ui.alert variant="error">{{ $errors->first() }}</x-ui.alert>
                                @endif
                                <x-ui.input :id="'refund-amount-'.$payment['id']" :old="$reopen === 'refund-'.$payment['id']" name="amount" inputmode="decimal" :label="__('Amount (up to :max)', ['max' => Money::format($left)])" :value="$left" required />
                                <x-ui.select :id="'refund-method-'.$payment['id']" :old="$reopen === 'refund-'.$payment['id']" name="method_id" :label="__('Pay back by')" :options="$methodOptions" :selected="$payment['method_id']" />
                                <x-ui.input :id="'refund-reference-'.$payment['id']" :old="$reopen === 'refund-'.$payment['id']" name="reference_no" maxlength="60" :label="__('Reference no. (e-wallet refunds)')" />
                                <x-ui.input :id="'refund-reason-'.$payment['id']" :old="$reopen === 'refund-'.$payment['id']" name="reason" maxlength="200" :label="__('Reason')" required />
                                @if ($approval)
                                    @include('transactions._approver', ['prefix' => 'refund-'.$payment['id']])
                                @endif
                                <div class="flex justify-end gap-2">
                                    <x-ui.button type="submit" variant="danger" x-bind:disabled="sending">{{ __('Refund') }}</x-ui.button>
                                </div>
                            </form>
                        </x-ui.modal>
                    @endif

                    @if ($canCorrect)
                        <x-ui.modal :id="'correct-'.$payment['id']" :title="__('Correct the payment method')">
                            <form method="POST" action="{{ route('transactions.corrections', $payment['id']) }}" class="space-y-4" x-data="{ sending: false }" x-on:submit="sending = true">
                                @csrf
                                <input type="hidden" name="dialog" value="correct-{{ $payment['id'] }}">
                                @if ($reopen === 'correct-'.$payment['id'])
                                    <x-ui.alert variant="error">{{ $errors->first() }}</x-ui.alert>
                                @endif
                                <p class="text-base text-muted">{{ __('The payment stays as recorded; reports and receipts use the corrected method from now on.') }}</p>
                                <x-ui.select :id="'correct-method-'.$payment['id']" :old="$reopen === 'correct-'.$payment['id']" name="method_id" :label="__('Actually paid by')" :options="$methodOptions" :selected="$payment['method_id']" />
                                <x-ui.input :id="'correct-reference-'.$payment['id']" :old="$reopen === 'correct-'.$payment['id']" name="reference_no" maxlength="60" :label="__('Reference no.')" :value="$payment['reference_no']" />
                                <x-ui.input :id="'correct-reason-'.$payment['id']" :old="$reopen === 'correct-'.$payment['id']" name="reason" maxlength="200" :label="__('Reason')" required />
                                @if ($approval)
                                    @include('transactions._approver', ['prefix' => 'correct-'.$payment['id']])
                                @endif
                                <div class="flex justify-end gap-2">
                                    <x-ui.button type="submit" x-bind:disabled="sending">{{ __('Save correction') }}</x-ui.button>
                                </div>
                            </form>
                        </x-ui.modal>
                    @endif
                @endforeach
            </ul>
        </x-ui.card>
    </div>

    @if ($reopen)
        <div x-data x-init="document.getElementById(@js($reopen))?.showModal()" data-reopen="{{ $reopen }}"></div>
    @endif
</x-layouts.app>
