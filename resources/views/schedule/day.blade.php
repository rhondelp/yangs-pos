@php
    use App\Support\Money;
    use Carbon\CarbonImmutable;

    $tz = \App\Services\Staff\ScheduleService::TZ;
    $day = CarbonImmutable::parse($date);
    $length = function ($s) {
        $minutes = (int) $s->starts_at->diffInMinutes($s->ends_at);

        return intdiv($minutes, 60).' h'.($minutes % 60 ? ' '.($minutes % 60).' min' : '');
    };
    $total = $bonuses->reduce(fn (string $sum, $b) => Money::add($sum, $b->amount), '0.00');
    $form = old('_form');
    $working = $shifts->unique('employee_id');
@endphp

<x-layouts.app :title="__('Schedule')">
    <div class="mb-4 flex flex-wrap items-center gap-2">
        <x-ui.button :href="route('schedule.week', ['week' => $week])" variant="ghost" icon="chevron-left">{{ __('Week') }}</x-ui.button>
        <x-ui.button :href="route('schedule.day', ['date' => $day->subDay()->toDateString()])" variant="secondary" icon="chevron-left"><span class="sr-only">{{ __('Previous day') }}</span></x-ui.button>
        <h2 class="min-w-56 text-center font-display text-xl font-semibold text-fg">{{ $day->format('D, M j, Y') }}</h2>
        <x-ui.button :href="route('schedule.day', ['date' => $day->addDay()->toDateString()])" variant="secondary" icon="chevron-right"><span class="sr-only">{{ __('Next day') }}</span></x-ui.button>
    </div>

    @error('date')
        <x-ui.alert variant="error" class="mb-4">{{ $message }}</x-ui.alert>
    @enderror

    @if ($shifts->isEmpty())
        <x-ui.empty-state icon="clipboard-list" :title="__('No one is scheduled this day.')">{{ __('Assign shifts from the week view.') }}</x-ui.empty-state>
    @else
        <x-ui.table :caption="__('Who works')">
            <x-slot:head>
                <tr>
                    <th scope="col">{{ __('Employee') }}</th>
                    <th scope="col">{{ __('Shift') }}</th>
                    <th scope="col">{{ __('Time') }}</th>
                    <th scope="col">{{ __('Length') }}</th>
                    <th scope="col">{{ __('Note') }}</th>
                </tr>
            </x-slot:head>
            @foreach ($shifts as $shift)
                <tr>
                    <th scope="row" class="text-base font-medium text-fg">{{ $shift->employee->full_name }}</th>
                    <td>{{ $shift->shift?->name ?? __('Custom') }}</td>
                    <td class="whitespace-nowrap tabular">{{ $shift->starts_at->setTimezone($tz)->format('H:i') }}–{{ $shift->ends_at->setTimezone($tz)->format('H:i') }}</td>
                    <td class="whitespace-nowrap tabular text-muted">{{ $length($shift) }}</td>
                    <td class="text-muted">{{ $shift->note ?? '—' }}</td>
                </tr>
            @endforeach
        </x-ui.table>
    @endif

    @if ($canPay)
        <x-ui.card :title="__('Night bonuses')" class="mt-4">
            <p class="mb-3 text-sm text-muted">{{ __('Paid in cash tonight. Do not also record it as an expense.') }}</p>
            @if ($bonuses->isEmpty())
                <p class="text-base text-muted">{{ __('No bonuses this night.') }}</p>
            @else
                <ul class="divide-y divide-line">
                    @foreach ($bonuses as $bonus)
                        <li class="flex min-h-touch items-center justify-between gap-3 py-2">
                            <span><span class="text-base text-fg">{{ $bonus->employee->full_name }}</span> <span class="text-sm text-muted">— {{ $bonus->reason }}</span></span>
                            <span class="tabular text-fg">{{ Money::format($bonus->amount) }}</span>
                        </li>
                    @endforeach
                    <li class="flex min-h-touch items-center justify-between gap-3 py-2 font-semibold">
                        <span>{{ __('Total') }}</span><span class="tabular">{{ Money::format($total) }}</span>
                    </li>
                </ul>
            @endif
            @if ($working->isNotEmpty() && $date <= \App\Support\BusinessDate::for(now()))
                <div class="mt-4 flex flex-wrap gap-2">
                    @foreach ($working as $shift)
                        @if ($shift->employee->employment_status->value === 'active' && ! $shift->employee->trashed())
                            <x-ui.button variant="secondary" icon="plus" x-data x-on:click="document.getElementById('bonus-{{ $shift->employee_id }}').showModal()">
                                {{ __('Give bonus') }}<span class="sr-only">{{ __(' to :name', ['name' => $shift->employee->full_name]) }}</span>
                                <span aria-hidden="true">· {{ $shift->employee->full_name }}</span>
                            </x-ui.button>
                            @php $id = "bonus-{$shift->employee_id}"; @endphp
                            <x-ui.modal :id="$id" :title="__('Bonus for :name', ['name' => $shift->employee->full_name])">
                                <form method="POST" action="{{ route('employees.bonuses.store', $shift->employee_id) }}" class="grid gap-4">
                                    @csrf
                                    <input type="hidden" name="_form" value="{{ $id }}">
                                    <input type="hidden" name="return_to" value="day">
                                    <input type="hidden" name="business_date" value="{{ $date }}">
                                    <x-ui.alert variant="info">{{ __('Paid in cash tonight. Do not also record it as an expense.') }}</x-ui.alert>
                                    <x-ui.input :id="$id.'-amount'" name="amount" inputmode="decimal" :label="__('Amount (₱)')" :old="$form === $id" required />
                                    <x-ui.input :id="$id.'-reason'" name="reason" :label="__('What for?')" maxlength="200" :old="$form === $id" required />
                                    <div class="flex justify-end"><x-ui.button type="submit">{{ __('Record bonus') }}</x-ui.button></div>
                                </form>
                            </x-ui.modal>
                        @endif
                    @endforeach
                </div>
            @endif
        </x-ui.card>
        @if ($form && $errors->any())
            <div x-data x-init="document.getElementById(@js($form))?.showModal()"></div>
        @endif
    @endif
</x-layouts.app>
