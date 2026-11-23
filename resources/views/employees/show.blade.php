@php
    use App\Enums\PayType;
    use App\Support\Money;

    $payText = fn ($rate) => $rate ? Money::format($rate->rate).' '.($rate->pay_type === PayType::Custom ? $rate->custom_basis : __($rate->pay_type->label())) : '—';
    $payTypes = ['daily' => __('Daily'), 'weekly' => __('Weekly'), 'monthly' => __('Monthly'), 'custom' => __('Custom')];
    $form = old('_form'); // which dialog was sent, so only it shows old input and errors
    $tz = config('app.timezone');
@endphp

<x-layouts.app :title="$employee->full_name">
    <div class="mb-4 flex flex-wrap items-center gap-3">
        <x-ui.button :href="route('employees.index')" variant="ghost" icon="chevron-left">{{ __('Employees') }}</x-ui.button>
        <x-ui.status-badge :status="$employee->trashed() ? 'archived' : $employee->employment_status" />
        @can('schedules.view')
            <x-ui.button :href="route('schedule.week')" variant="ghost" icon="clipboard-list">{{ __('See their week') }}</x-ui.button>
        @endcan
        @if ($canPay)
            <div class="ml-auto flex flex-wrap gap-3">
                @if ($employee->trashed())
                    <form method="POST" action="{{ route('employees.restore', $employee) }}">@csrf
                        <x-ui.button type="submit" variant="secondary">{{ __('Restore') }}</x-ui.button>
                    </form>
                @else
                    <x-ui.button :href="route('employees.edit', $employee)" variant="secondary">{{ __('Edit details') }}</x-ui.button>
                    <x-ui.button variant="ghost" x-data x-on:click="document.getElementById('archive-employee').showModal()">{{ __('Archive') }}</x-ui.button>
                @endif
            </div>
        @endif
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        <x-ui.card :title="__('Details')">
            <dl class="grid gap-3 text-base sm:grid-cols-2">
                <div><dt class="text-sm text-muted">{{ __('Position') }}</dt><dd>{{ $employee->position }}</dd></div>
                <div><dt class="text-sm text-muted">{{ __('Hired') }}</dt><dd>{{ $employee->date_hired->format('M j, Y') }}</dd></div>
                @if ($employee->date_separated)
                    <div><dt class="text-sm text-muted">{{ __('Last day of work') }}</dt><dd>{{ $employee->date_separated->format('M j, Y') }}</dd></div>
                @endif
                <div><dt class="text-sm text-muted">{{ __('Phone') }}</dt><dd>{{ $employee->phone ?? '—' }}</dd></div>
                <div><dt class="text-sm text-muted">{{ __('Email') }}</dt><dd class="break-all">{{ $employee->email ?? '—' }}</dd></div>
                <div class="sm:col-span-2"><dt class="text-sm text-muted">{{ __('Address') }}</dt><dd>{{ $employee->address ?? '—' }}</dd></div>
                <div><dt class="text-sm text-muted">{{ __('Emergency contact') }}</dt><dd>{{ $employee->emergency_contact_name ?? '—' }}</dd></div>
                <div><dt class="text-sm text-muted">{{ __('Emergency phone') }}</dt><dd>{{ $employee->emergency_contact_phone ?? '—' }}</dd></div>
                <div><dt class="text-sm text-muted">{{ __('Login') }}</dt><dd>{{ $employee->user?->name ?? __('None') }}</dd></div>
            </dl>
        </x-ui.card>

        @if ($canPay)
            <x-ui.card :title="__('Pay')">
                <p class="text-sm text-muted">{{ __('Today') }}</p>
                <p class="font-display text-3xl font-semibold tabular text-fg">{{ $payText($current) }}</p>
                @unless ($employee->trashed())
                    <x-ui.button variant="secondary" class="mt-4" x-data x-on:click="document.getElementById('rate-new').showModal()">{{ __('Change pay') }}</x-ui.button>
                @endunless

                <h3 class="mt-6 font-display text-lg font-semibold text-fg">{{ __('Pay history') }}</h3>
                <ul class="mt-2 divide-y divide-line">
                    @foreach ($rates as $rate)
                        @php $replaced = $rate->replacedBy !== null; @endphp
                        <li class="flex min-h-touch flex-wrap items-center justify-between gap-3 py-2">
                            <div>
                                <p @class(['tabular text-base', 'text-fg' => ! $replaced, 'text-muted line-through' => $replaced])>{{ $payText($rate) }}</p>
                                <p class="text-sm text-muted">{{ __('From :date, by :name', ['date' => $rate->effective_from->format('M j, Y'), 'name' => $rate->createdBy?->name ?? '—']) }}</p>
                            </div>
                            @if ($replaced)
                                <x-ui.status-badge status="replaced" />
                            @elseif (! $employee->trashed())
                                <x-ui.button variant="ghost" x-data x-on:click="document.getElementById('rate-{{ $rate->id }}').showModal()">{{ __('Edit') }}<span class="sr-only"> {{ $payText($rate) }}</span></x-ui.button>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </x-ui.card>
        @endif
    </div>

    @if ($canPay)
        <x-ui.card :title="__('Night bonuses')" class="mt-4">
            <x-slot:actions>
                @if (! $employee->trashed() && $employee->employment_status->value === 'active')
                    <x-ui.button icon="plus" x-data x-on:click="document.getElementById('bonus-new').showModal()">{{ __('Give bonus') }}</x-ui.button>
                @endif
            </x-slot:actions>
            @if ($bonuses->isEmpty())
                <p class="text-base text-muted">{{ __('No bonuses yet.') }}</p>
            @else
                <ul class="divide-y divide-line">
                    @foreach ($bonuses as $bonus)
                        <li class="flex min-h-touch flex-wrap items-center justify-between gap-3 py-2">
                            <div>
                                <p @class(['tabular text-base', 'text-fg' => ! $bonus->isVoided(), 'text-muted line-through' => $bonus->isVoided()])>
                                    {{ Money::format($bonus->amount) }} · {{ $bonus->business_date->format('D, M j') }}
                                </p>
                                <p class="text-sm text-muted">{{ $bonus->reason }} — {{ $bonus->givenBy?->name ?? '—' }}</p>
                                @if ($bonus->isVoided())
                                    <p class="text-sm text-muted">{{ __(':what on :date by :name: :reason', ['what' => $bonus->replacedBy ? __('Replaced') : __('Voided'),
                                        'date' => $bonus->voided_at->timezone($tz)->format('M j, g:i A'), 'name' => $bonus->voidedBy?->name ?? '—', 'reason' => $bonus->void_reason]) }}</p>
                                @endif
                            </div>
                            @if ($bonus->isVoided())
                                <x-ui.status-badge :status="$bonus->replacedBy ? 'replaced' : 'voided'" />
                            @else
                                <div class="flex gap-2">
                                    <x-ui.button variant="ghost" x-data x-on:click="document.getElementById('bonus-{{ $bonus->id }}').showModal()">{{ __('Edit') }}</x-ui.button>
                                    <x-ui.button variant="ghost" x-data x-on:click="document.getElementById('bonus-void-{{ $bonus->id }}').showModal()">{{ __('Void') }}</x-ui.button>
                                </div>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-ui.card>

        {{-- Dialogs. Each form carries _form so only the one sent shows its old input and errors, and reopens. --}}
        @unless ($employee->trashed())
            <x-ui.modal id="rate-new" :title="__('Change pay')">
                <form method="POST" action="{{ route('employees.rates.store', $employee) }}" class="grid gap-4" x-data="{ payType: @js($form === 'rate-new' ? old('pay_type') : ($current?->pay_type->value ?? 'daily')) }">
                    @csrf <input type="hidden" name="_form" value="rate-new">
                    <x-ui.select id="rate-new-type" name="pay_type" :label="__('Pay type')" :options="$payTypes" :selected="$current?->pay_type->value" x-model="payType" :old="$form === 'rate-new'" required />
                    <x-ui.input id="rate-new-rate" name="rate" inputmode="decimal" :label="__('New rate (₱)')" :old="$form === 'rate-new'" required />
                    <div x-show="payType === 'custom'" x-cloak><x-ui.input id="rate-new-basis" name="custom_basis" :label="__('Paid for')" maxlength="80" :old="$form === 'rate-new'" /></div>
                    <x-ui.input id="rate-new-from" name="effective_from" type="date" :label="__('Starts on')" :value="$today" :old="$form === 'rate-new'" required
                                :hint="__('Days before this keep the old pay.')" />
                    <div class="flex justify-end"><x-ui.button type="submit">{{ __('Save new pay') }}</x-ui.button></div>
                </form>
            </x-ui.modal>

            @foreach ($rates->whereNull('replacedBy') as $rate)
                @php $id = "rate-{$rate->id}"; @endphp
                <x-ui.modal :id="$id" :title="__('Edit pay')">
                    <form method="POST" action="{{ route('employee-rates.update', $rate) }}" class="grid gap-4" x-data="{ payType: @js($form === $id ? old('pay_type') : $rate->pay_type->value) }">
                        @csrf @method('PUT') <input type="hidden" name="_form" value="{{ $id }}">
                        <p class="text-base text-muted">{{ __('The old version stays in the pay history.') }}</p>
                        <x-ui.select :id="$id.'-type'" name="pay_type" :label="__('Pay type')" :options="$payTypes" :selected="$rate->pay_type->value" x-model="payType" :old="$form === $id" required />
                        <x-ui.input :id="$id.'-rate'" name="rate" inputmode="decimal" :label="__('Rate (₱)')" :value="$rate->rate" :old="$form === $id" required />
                        <div x-show="payType === 'custom'" x-cloak><x-ui.input :id="$id.'-basis'" name="custom_basis" :label="__('Paid for')" :value="$rate->custom_basis" :old="$form === $id" /></div>
                        <x-ui.input :id="$id.'-from'" name="effective_from" type="date" :label="__('Starts on')" :value="$rate->effective_from->toDateString()" :old="$form === $id" required />
                        <x-ui.input :id="$id.'-reason'" name="reason" :label="__('Why change it?')" maxlength="200" :old="$form === $id" required />
                        <div class="flex justify-end"><x-ui.button type="submit">{{ __('Save') }}</x-ui.button></div>
                    </form>
                </x-ui.modal>
            @endforeach

            <x-ui.modal id="bonus-new" :title="__('Give a bonus')">
                <form method="POST" action="{{ route('employees.bonuses.store', $employee) }}" class="grid gap-4">
                    @csrf <input type="hidden" name="_form" value="bonus-new">
                    <x-ui.alert variant="info">{{ __('Paid in cash tonight. Do not also record it as an expense.') }}</x-ui.alert>
                    <x-ui.input id="bonus-new-amount" name="amount" inputmode="decimal" :label="__('Amount (₱)')" :old="$form === 'bonus-new'" required />
                    <x-ui.input id="bonus-new-reason" name="reason" :label="__('What for?')" maxlength="200" :old="$form === 'bonus-new'" required />
                    <x-ui.input id="bonus-new-date" name="business_date" type="date" :label="__('Night')" :value="$today" :max="$today" :old="$form === 'bonus-new'" required />
                    <div class="flex justify-end"><x-ui.button type="submit">{{ __('Record bonus') }}</x-ui.button></div>
                </form>
            </x-ui.modal>
        @endunless

        @foreach ($bonuses->filter(fn ($b) => ! $b->isVoided()) as $bonus)
            @php $id = "bonus-{$bonus->id}"; $voidId = "bonus-void-{$bonus->id}"; @endphp
            <x-ui.modal :id="$id" :title="__('Edit bonus')">
                <form method="POST" action="{{ route('employee-bonuses.update', $bonus) }}" class="grid gap-4">
                    @csrf @method('PUT') <input type="hidden" name="_form" value="{{ $id }}">
                    <p class="text-base text-muted">{{ __('The old one stays in the history, marked replaced.') }}</p>
                    <x-ui.input :id="$id.'-amount'" name="amount" inputmode="decimal" :label="__('Amount (₱)')" :value="$bonus->amount" :old="$form === $id" required />
                    <x-ui.input :id="$id.'-reason'" name="reason" :label="__('What for?')" :value="$bonus->reason" maxlength="200" :old="$form === $id" required />
                    <x-ui.input :id="$id.'-date'" name="business_date" type="date" :label="__('Night')" :value="$bonus->business_date->toDateString()" :max="$today" :old="$form === $id" required />
                    <x-ui.input :id="$id.'-why'" name="edit_reason" :label="__('Why change it?')" maxlength="200" :old="$form === $id" required />
                    <div class="flex justify-end"><x-ui.button type="submit">{{ __('Save') }}</x-ui.button></div>
                </form>
            </x-ui.modal>
            <x-ui.modal :id="$voidId" :title="__('Void this bonus')">
                <form method="POST" action="{{ route('employee-bonuses.void', $bonus) }}" class="grid gap-4">
                    @csrf <input type="hidden" name="_form" value="{{ $voidId }}">
                    <p class="text-base text-muted">{{ __(':amount on :date stays on record, marked voided.', ['amount' => Money::format($bonus->amount), 'date' => $bonus->business_date->format('M j')]) }}</p>
                    <x-ui.input :id="$voidId.'-reason'" name="reason" :label="__('Reason')" maxlength="200" :old="$form === $voidId" required />
                    <div class="flex justify-end"><x-ui.button type="submit" variant="danger">{{ __('Void bonus') }}</x-ui.button></div>
                </form>
            </x-ui.modal>
        @endforeach

        @unless ($employee->trashed())
            <x-ui.modal id="archive-employee" :title="__('Archive :name?', ['name' => $employee->full_name])">
                <form method="POST" action="{{ route('employees.archive', $employee) }}" class="grid gap-4">
                    @csrf @method('DELETE')
                    <p class="text-base text-muted">{{ __('They leave the list, and their shifts after today are cancelled. Pay and bonus history stay. You can restore them later.') }}</p>
                    <div class="flex justify-end"><x-ui.button type="submit" variant="danger">{{ __('Archive') }}</x-ui.button></div>
                </form>
            </x-ui.modal>
        @endunless

        @if ($form && $errors->any())
            <div x-data x-init="document.getElementById(@js($form))?.showModal()"></div>
        @endif
    @endif
</x-layouts.app>
