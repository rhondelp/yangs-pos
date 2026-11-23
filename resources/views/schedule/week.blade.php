@php
    use App\Enums\EmploymentStatus;
    use Carbon\CarbonImmutable;

    $canManage = auth()->user()->can('schedules.manage');
    $tz = \App\Services\Staff\ScheduleService::TZ;
    $today = \App\Support\BusinessDate::for(now());
    $span = fn ($s) => $s->starts_at->setTimezone($tz)->format('H:i').'–'.$s->ends_at->setTimezone($tz)->format('H:i');
    $reopen = old('_form') === 'schedule' && $errors->any();
    // The dialog's starting state: after a refused save, what was sent; otherwise empty.
    $initial = [
        'id' => $reopen ? old('_schedule_id') : null,
        'employee_id' => $reopen ? old('employee_id') : null,
        'employee' => $reopen ? old('_employee') : '',
        'duty_date' => $reopen ? old('duty_date') : null,
        'day' => $reopen ? old('_day') : '',
        'shift_id' => $reopen ? old('shift_id') : null,
        'custom' => $reopen && old('start_time') !== null,
        'start_time' => $reopen ? old('start_time') : '',
        'end_time' => $reopen ? old('end_time') : '',
        'note' => $reopen ? old('note') : '',
    ];
@endphp

<x-layouts.app :title="__('Schedule')">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-2">
            <x-ui.button :href="route('schedule.week', ['week' => $prev])" variant="secondary" icon="chevron-left"><span class="sr-only">{{ __('Previous week') }}</span></x-ui.button>
            <h2 class="min-w-48 text-center font-display text-xl font-semibold text-fg">{{ $label }}</h2>
            <x-ui.button :href="route('schedule.week', ['week' => $next])" variant="secondary" icon="chevron-right"><span class="sr-only">{{ __('Next week') }}</span></x-ui.button>
            <x-ui.button :href="route('schedule.week')" variant="ghost">{{ __('This week') }}</x-ui.button>
        </div>
        @if ($canManage)
            <div class="flex flex-wrap gap-3">
                <x-ui.button :href="route('shifts.index')" variant="ghost">{{ __('Shift templates') }}</x-ui.button>
                <x-ui.button variant="secondary" x-data x-on:click="document.getElementById('copy-week').showModal()">{{ __('Copy this week to…') }}</x-ui.button>
            </div>
        @endif
    </div>

    @if (! $reopen && $errors->hasAny(['week', 'reason', 'from_week', 'to_week']))
        <x-ui.alert variant="error" class="mb-4">
            @foreach (['week', 'reason', 'from_week', 'to_week'] as $key)@if ($errors->has($key))<p>{{ $errors->first($key) }}</p>@endif @endforeach
        </x-ui.alert>
    @endif

    @if ($employees->isEmpty())
        <x-ui.empty-state icon="user" :title="__('No one to schedule yet.')">{{ __('Add employees first, then assign their shifts here.') }}</x-ui.empty-state>
    @else
        <div x-data="{ form: @js($initial),
                       openNew(employeeId, employee, date, day) { this.form = { id: null, employee_id: employeeId, employee, duty_date: date, day, shift_id: null, custom: false, start_time: '', end_time: '', note: '' }; this.show() },
                       openEdit(shift) { this.form = { ...shift }; this.show() },
                       show() { document.getElementById('shift-dialog').showModal() } }">
            <div class="relative overflow-x-auto rounded-card border border-line bg-surface">
                <table class="min-w-full border-collapse text-left text-sm">
                    <caption class="sr-only">{{ __('Shifts for :week', ['week' => $label]) }}</caption>
                    <thead class="bg-raised/50">
                        <tr>
                            <th scope="col" class="sticky left-0 z-10 min-w-36 bg-surface px-3 py-3 font-medium text-muted">{{ __('Employee') }}</th>
                            @foreach ($days as $day)
                                @php $d = CarbonImmutable::parse($day); @endphp
                                <th scope="col" @class(['min-w-32 px-2 py-2 font-medium', 'text-accent' => $day === $today, 'text-muted' => $day !== $today])>
                                    <a href="{{ route('schedule.day', ['date' => $day]) }}" class="flex min-h-touch flex-col justify-center rounded-control px-2 hover:bg-raised">
                                        <span>{{ $d->format('D') }}</span><span class="font-display text-lg text-fg tabular">{{ $d->format('j') }}</span>
                                    </a>
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($employees as $employee)
                            @php $active = ! $employee->trashed() && $employee->employment_status === EmploymentStatus::Active; @endphp
                            <tr class="border-t border-line align-top">
                                <th scope="row" class="sticky left-0 z-10 bg-surface px-3 py-2 font-normal">
                                    <span @class(['block text-base font-medium', 'text-fg' => $active, 'text-muted' => ! $active])>{{ $employee->full_name }}</span>
                                    @unless ($active)
                                        <x-ui.status-badge :status="$employee->trashed() ? 'archived' : $employee->employment_status" class="mt-1" />
                                    @endunless
                                </th>
                                @foreach ($days as $day)
                                    @php $dayLabel = CarbonImmutable::parse($day)->format('D M j'); @endphp
                                    <td @class(['px-1.5 py-1.5', 'bg-accent/5' => $day === $today])>
                                        <div class="flex flex-col gap-1.5">
                                            @foreach ($cells[$employee->id][$day] ?? [] as $shift)
                                                @php
                                                    $shiftName = $shift->shift?->name ?? __('Custom');
                                                    $state = ['id' => $shift->id, 'employee_id' => $employee->id, 'employee' => $employee->full_name, 'duty_date' => $day, 'day' => $dayLabel,
                                                        'shift_id' => $shift->shift_id, 'custom' => $shift->shift_id === null,
                                                        'start_time' => $shift->starts_at->setTimezone($tz)->format('H:i'), 'end_time' => $shift->ends_at->setTimezone($tz)->format('H:i'), 'note' => $shift->note ?? ''];
                                                @endphp
                                                @if ($canManage)
                                                    <button type="button" x-on:click="openEdit(@js($state))"
                                                            class="min-h-touch w-full rounded-control border-l-4 border-accent bg-raised px-2 py-1.5 text-left hover:bg-line focus:outline-none focus-visible:ring-2 focus-visible:ring-accent">
                                                        <span class="block font-medium text-fg">{{ $shiftName }}</span>
                                                        <span class="block tabular text-muted">{{ $span($shift) }}</span>
                                                        @if ($shift->note)<span class="block truncate text-xs text-muted">{{ $shift->note }}</span>@endif
                                                        <span class="sr-only">{{ __('Edit shift, :name, :day', ['name' => $employee->full_name, 'day' => $dayLabel]) }}</span>
                                                    </button>
                                                @else
                                                    <div class="rounded-control border-l-4 border-accent bg-raised px-2 py-1.5">
                                                        <span class="block font-medium text-fg">{{ $shiftName }}</span>
                                                        <span class="block tabular text-muted">{{ $span($shift) }}</span>
                                                        @if ($shift->note)<span class="block truncate text-xs text-muted">{{ $shift->note }}</span>@endif
                                                    </div>
                                                @endif
                                            @endforeach
                                            @if ($canManage && $active)
                                                <button type="button" x-on:click="openNew({{ $employee->id }}, @js($employee->full_name), '{{ $day }}', @js($dayLabel))"
                                                        aria-label="{{ __('Assign shift, :name, :day', ['name' => $employee->full_name, 'day' => $dayLabel]) }}"
                                                        class="flex min-h-touch w-full items-center justify-center rounded-control border border-dashed border-line text-muted hover:border-accent hover:text-accent focus:outline-none focus-visible:ring-2 focus-visible:ring-accent">
                                                    <x-ui.icon name="plus" class="size-5" />
                                                </button>
                                            @endif
                                        </div>
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($canManage)
                <x-ui.modal id="shift-dialog" :title="__('Shift')">
                    <div class="grid gap-4">
                        <p class="text-base text-fg"><span x-text="form.employee"></span> · <span x-text="form.day"></span></p>

                        <form method="POST" x-bind:action="form.id ? @js(route('schedule.update', '__ID__')).replace('__ID__', form.id) : @js(route('schedule.store'))" class="grid gap-4">
                            @csrf
                            <input type="hidden" name="_method" value="PUT" x-bind:disabled="! form.id">
                            <input type="hidden" name="_form" value="schedule">
                            <input type="hidden" name="_schedule_id" x-bind:value="form.id ?? ''">
                            <input type="hidden" name="_employee" x-bind:value="form.employee">
                            <input type="hidden" name="_day" x-bind:value="form.day">
                            <input type="hidden" name="employee_id" x-bind:value="form.employee_id" x-bind:disabled="!! form.id">
                            <input type="hidden" name="duty_date" x-bind:value="form.duty_date">
                            <input type="hidden" name="shift_id" x-bind:value="form.custom ? '' : (form.shift_id ?? '')">

                            <fieldset>
                                <legend class="mb-2 text-sm font-medium text-muted">{{ __('Shift') }}</legend>
                                <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                                    @foreach ($templates as $template)
                                        <button type="button" x-on:click="form.shift_id = {{ $template->id }}; form.custom = false"
                                                x-bind:aria-pressed="(! form.custom && form.shift_id == {{ $template->id }}).toString()"
                                                x-bind:class="! form.custom && form.shift_id == {{ $template->id }} ? 'border-accent bg-accent/15 text-fg' : 'border-edge bg-raised text-fg'"
                                                class="flex min-h-14 flex-col items-center justify-center rounded-control border px-2 py-1.5">
                                            <span class="font-semibold">{{ $template->name }}</span>
                                            <span class="text-sm tabular text-muted">{{ $template->start_time }}–{{ $template->end_time }}</span>
                                        </button>
                                    @endforeach
                                    <button type="button" x-on:click="form.custom = true"
                                            x-bind:aria-pressed="form.custom.toString()"
                                            x-bind:class="form.custom ? 'border-accent bg-accent/15 text-fg' : 'border-edge bg-raised text-fg'"
                                            class="flex min-h-14 items-center justify-center rounded-control border px-2 font-semibold">{{ __('Custom') }}</button>
                                </div>
                            </fieldset>

                            <div x-show="form.custom" x-cloak class="grid grid-cols-2 gap-4">
                                <x-ui.input id="shift-start" name="start_time" type="time" :label="__('Starts')" x-model="form.start_time" x-bind:disabled="! form.custom" :old="false" />
                                <x-ui.input id="shift-end" name="end_time" type="time" :label="__('Ends')" x-model="form.end_time" x-bind:disabled="! form.custom" :old="false"
                                            :hint="__('Earlier than the start means the next morning.')" />
                            </div>
                            @if ($reopen)
                                <x-ui.alert variant="error">
                                    @foreach (array_unique($errors->all()) as $message)<p>{{ $message }}</p>@endforeach
                                </x-ui.alert>
                            @endif
                            <x-ui.input id="shift-note" name="note" :label="__('Note (optional)')" maxlength="200" x-model="form.note" :old="false" />
                            <div class="flex justify-end">
                                <x-ui.button type="submit"><span x-text="form.id ? @js(__('Save shift')) : @js(__('Assign shift'))"></span></x-ui.button>
                            </div>
                        </form>

                        <div x-show="form.id" x-cloak class="grid gap-3 border-t border-line pt-4">
                            @foreach ($history as $scheduleId => $entries)
                                <div x-show="form.id == {{ $scheduleId }}">
                                    <h3 class="text-sm font-medium text-muted">{{ __('History') }}</h3>
                                    <ul class="mt-1 space-y-1 text-sm text-fg">
                                        @foreach ($entries as $entry)
                                            <li>{{ __('Changed by :name, :when:', ['name' => $entry->user_name ?? '—', 'when' => $entry->created_at->timezone(config('app.timezone'))->format('M j, g:i A')]) }}
                                                {{ substr($entry->old_values['starts_at'] ?? '', 11) ?: '' }}{{ isset($entry->old_values['ends_at']) ? '–'.substr($entry->old_values['ends_at'], 11) : '' }}
                                                → {{ substr($entry->new_values['starts_at'] ?? '', 11) ?: '' }}{{ isset($entry->new_values['ends_at']) ? '–'.substr($entry->new_values['ends_at'], 11) : '' }}
                                                @isset($entry->new_values['shift']) ({{ $entry->old_values['shift'] ?? '' }} → {{ $entry->new_values['shift'] }}) @endisset
                                                @isset($entry->new_values['note']) · {{ __('note: :note', ['note' => $entry->new_values['note']]) }} @endisset
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endforeach
                            <form method="POST" x-bind:action="@js(route('schedule.cancel', '__ID__')).replace('__ID__', form.id)" class="flex flex-wrap items-end gap-3">
                                @csrf
                                <x-ui.input id="cancel-reason" name="reason" :label="__('Cancel this shift: why?')" maxlength="200" required class="min-w-56 flex-1" :old="false" />
                                <x-ui.button type="submit" variant="danger">{{ __('Cancel shift') }}</x-ui.button>
                            </form>
                        </div>
                    </div>
                </x-ui.modal>
                @if ($reopen)
                    <div x-init="show()"></div>
                @endif
            @endif
        </div>
    @endif

    @if ($canManage)
        <x-ui.modal id="copy-week" :title="__('Copy this week')">
            <form method="POST" action="{{ route('schedule.copy') }}" class="grid gap-4" x-data="{ sending: false }" x-on:submit="sending = true">
                @csrf
                <input type="hidden" name="from_week" value="{{ $week }}">
                <p class="text-base text-muted">{{ __('Every shift of :week goes to the same days and times. Clashes, staff who are not active and past days are skipped and listed.', ['week' => $label]) }}</p>
                <x-ui.select id="copy-to" name="to_week" :label="__('Copy to')" :options="$copyTargets" :selected="$next" required />
                <div class="flex justify-end"><x-ui.button type="submit" x-bind:disabled="sending">{{ __('Copy shifts') }}</x-ui.button></div>
            </form>
        </x-ui.modal>
    @endif
</x-layouts.app>
