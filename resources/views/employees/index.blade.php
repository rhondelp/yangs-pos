@php
    use App\Enums\PayType;
    use App\Support\Money;

    $statuses = ['active' => __('Active'), 'on_leave' => __('On leave'), 'resigned' => __('Resigned'), 'terminated' => __('Terminated'), 'archived' => __('Archived')];
    $payText = fn ($rate) => $rate ? Money::format($rate->rate).' '.($rate->pay_type === PayType::Custom ? $rate->custom_basis : __($rate->pay_type->label())) : '—';
    $canPay = auth()->user()->can('employees.manage');
@endphp

<x-layouts.app :title="__('Employees')">
    <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
        <form method="GET" action="{{ route('employees.index') }}" class="flex flex-wrap items-end gap-3">
            <x-ui.select name="status" :label="__('Status')" :options="$statuses" :selected="$status" :placeholder="__('All current staff')" class="w-56" onchange="this.form.submit()" />
            <noscript><x-ui.button type="submit" variant="secondary">{{ __('Show') }}</x-ui.button></noscript>
        </form>
        @if ($canPay)
            <x-ui.button :href="route('employees.create')" icon="plus">{{ __('Add employee') }}</x-ui.button>
        @endif
    </div>

    @if ($employees->isEmpty())
        <x-ui.empty-state icon="user" :title="__('No employees here yet.')">
            {{ $canPay ? __('Add the people who work at the bar to schedule and pay them.') : __('Ask the owner to add employees.') }}
        </x-ui.empty-state>
    @else
        <x-ui.table :caption="__('Employees')">
            <x-slot:head>
                <tr>
                    <th scope="col">{{ __('Name') }}</th>
                    <th scope="col">{{ __('Position') }}</th>
                    <th scope="col">{{ __('Status') }}</th>
                    <th scope="col">{{ __('Phone') }}</th>
                    @if ($canPay)
                        <th scope="col" class="text-right">{{ __('Pay today') }}</th>
                    @endif
                    <th scope="col"><span class="sr-only">{{ __('Actions') }}</span></th>
                </tr>
            </x-slot:head>
            @foreach ($employees as $employee)
                <tr>
                    <th scope="row" class="text-base font-medium text-fg">{{ $employee->full_name }}</th>
                    <td>{{ $employee->position }}</td>
                    <td><x-ui.status-badge :status="$employee->trashed() ? 'archived' : $employee->employment_status" /></td>
                    <td class="whitespace-nowrap text-muted">{{ $employee->phone ?? '—' }}</td>
                    @if ($canPay)
                        <td class="whitespace-nowrap text-right tabular">{{ $payText($pay[$employee->id] ?? null) }}</td>
                    @endif
                    <td class="text-right">
                        <x-ui.button :href="route('employees.show', $employee)" variant="ghost">{{ __('Open') }}<span class="sr-only"> {{ $employee->full_name }}</span></x-ui.button>
                    </td>
                </tr>
            @endforeach
        </x-ui.table>
    @endif
</x-layouts.app>
